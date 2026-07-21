<?php

/**
 * Block Markup Parser
 *
 * Server-side port of the subset of WordPress's `parse_blocks()` we need to
 * turn a raw block-markup string into the `[{blockName, attrs, innerBlocks,
 * innerHTML, innerContent}, ...]` tree consumers expect. Emits the WP
 * `parse_blocks()` shape verbatim — NOT the Gutenberg editor's BlockInstance
 * shape (`{name, attributes, innerBlocks}`) that user patterns store in
 * {@see \ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern}'s
 * `block_content` column. The two shapes coexist in the pattern REST envelope's
 * `content.blocks` field today; consumers that iterate blocks must sniff both
 * key sets (see visual-editor's `PatternThumbnail.readBlockName` for the
 * pattern).
 *
 * First consumer (#204): {@see PatternResolver::buildThemePattern()} — theme
 * patterns are read from `.php` files on every request via
 * {@see PatternFileParser}. Without a parsed block tree the pattern browser
 * shows the "Empty pattern" placeholder for every shipped pattern. The parser
 * is deliberately generic — the sibling `TemplateResolver` and
 * `TemplatePartResolver` theme-file branches ship the same `blocks: []` gap
 * and can drop this in as-is.
 *
 * ## Correctness scope
 *
 * The parser is a best-effort optimization for the common case, NOT a
 * full-fidelity WP block parser. For editor correctness, the visual-editor
 * client hydrates blocks via Gutenberg's own `parse(rawContent)` (see
 * `hydrate-blocks.ts`), which is authoritative — this parser's output is
 * only consumed by lightweight surfaces like the pattern thumbnail summary.
 *
 * Known limitations (any real-world case runs the client-side `parse()`
 * fallback for correctness):
 * - Unpaired `{` or `}` bytes inside a JSON attribute string value break
 *   the regex's brace-balancing and cause the block to be interpreted as
 *   freeform text. WP's real `WP_Block_Parser` uses a hand-coded tokenizer
 *   specifically to sidestep this.
 * - Mis-nested closers (e.g. `<!-- /wp:group -->` inside a `wp:paragraph`
 *   body) get absorbed by the wrong recursion level. The outer container
 *   looks closed even when its own closer was consumed elsewhere.
 * - Block trees deeper than {@see self::MAX_DEPTH} are truncated at that
 *   depth so we don't blow the PHP call stack under adversarial input.
 * - PCRE runtime errors (backtrack / recursion / JIT stack limits) are
 *   logged via `Log::warning` and treated as end-of-input so parsing
 *   degrades to a partial tree rather than raising.
 *
 * @since      2.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

use Illuminate\Support\Facades\Log;

/**
 * @since 2.5.0
 */
final class BlockMarkupParser
{
    /**
     * Maximum nesting depth `parseChildren()` will recurse into before
     * truncating a subtree. Chosen well below PHP's default C-stack and
     * Xdebug's `max_nesting_level` (256) so a persisted-but-adversarial
     * `block_content` payload can't crash the fpm worker on every render.
     * Real theme patterns typically nest 3–6 levels; 64 is comfortable.
     *
     * @since 2.5.0
     */
    private const MAX_DEPTH = 64;

    /**
     * Match a block delimiter comment: `<!-- wp:namespace/name {json} -->`,
     * `<!-- wp:namespace/name {json} /-->` (self-closing / void), or
     * `<!-- /wp:namespace/name -->` (closer). The attribute JSON, if any,
     * is captured verbatim and decoded per-match — the recursive `(?-1)`
     * inside `attrs` balances nested `{}` inside the JSON blob. Note this
     * balancing breaks when a JSON string value contains a lone `{` or `}`;
     * see the class docblock's "Known limitations" section.
     */
    private const DELIMITER_REGEX = '/<!--\s+(?P<closer>\/)?wp:(?P<name>[a-z][a-z0-9_-]*\/[a-z][a-z0-9_-]*|[a-z][a-z0-9_-]*)\s*(?P<attrs>\{(?:(?>[^{}]+)|(?-1))*\})?\s*(?P<void>\/)?-->/';

    /**
     * UTF-8 byte-order mark. Some editors (older TextMate/Notepad) prepend
     * one; stripping it here keeps a BOM'd theme-file from producing a
     * spurious `blockName: null` freeform block for its own leading bytes.
     *
     * @since 2.5.0
     */
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Parse a raw block-markup string into the WP `parse_blocks()` shape.
     *
     * Freeform HTML between blocks becomes a `blockName: null` sibling with
     * an empty attrs bag — matching what WP emits so downstream
     * shape-agnostic consumers can treat the whole document as a uniform
     * block list. An empty or whitespace-only input returns an empty array.
     *
     * @since 2.5.0
     *
     * @return array<int, array{blockName: string|null, attrs: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>, innerHTML: string, innerContent: array<int, string|null>}>
     */
    public static function parse( string $markup ): array
    {
        if ( str_starts_with( $markup, self::UTF8_BOM ) ) {
            $markup = substr( $markup, strlen( self::UTF8_BOM ) );
        }

        if ( '' === trim( $markup ) ) {
            return [];
        }

        [$blocks] = self::parseChildren( $markup, 0, null, 0 );

        return $blocks;
    }

    /**
     * Walk `$markup` starting at `$offset`, collecting sibling blocks until
     * either the end of input or a closer for `$parentName` is reached.
     *
     * Recursion feeds each open delimiter's body back through this method so
     * innerBlocks are collected in the same pass — no separate tree walk.
     * The returned offset points at the character immediately after the
     * parent's closer (or at strlen for top-level calls) so the caller can
     * resume from exactly where recursion left off. The returned
     * `innerContent` interleaves freeform text with `null` markers (one per
     * innerBlock, in document order) so a parent can carry the WP shape
     * verbatim; top-level callers ignore it.
     *
     * A container that would recurse deeper than {@see self::MAX_DEPTH} is
     * emitted with an empty `innerBlocks` / `innerContent`; its body is
     * absorbed as-is into the parent's `innerHTML` via freeform text. The
     * client's Gutenberg `parse()` fallback remains authoritative for the
     * truncated subtree.
     *
     * @since 2.5.0
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: array<int, string|null>}
     *                                                                                        Tuple of `[blocks, nextOffset, innerContent]`.
     */
    private static function parseChildren( string $markup, int $offset, ?string $parentName, int $depth ): array
    {
        $blocks       = [];
        $innerContent = [];
        $cursor       = $offset;
        $length       = strlen( $markup );

        while ( $cursor < $length ) {
            $matched = preg_match(
                self::DELIMITER_REGEX,
                $markup,
                $match,
                PREG_OFFSET_CAPTURE,
                $cursor,
            );

            if ( false === $matched ) {
                // PCRE runtime error — recursion / backtrack / JIT stack
                // limit. Log the error code so silent parse truncation
                // becomes observable, then treat the tail as freeform and
                // fall out of the loop. Client-side Gutenberg `parse()`
                // remains authoritative for correctness.
                Log::warning(
                    'BlockMarkupParser: preg_match failed; parse truncated at cursor.',
                    [
                        'pcre_error' => preg_last_error(),
                        'cursor'     => $cursor,
                        'length'     => $length,
                    ],
                );

                self::absorbFreeform( substr( $markup, $cursor ), $parentName, $blocks, $innerContent );
                $cursor = $length;

                break;
            }

            if ( 1 !== $matched ) {
                // No more delimiters — the rest of the buffer is freeform.
                self::absorbFreeform( substr( $markup, $cursor ), $parentName, $blocks, $innerContent );
                $cursor = $length;

                break;
            }

            $delimiterOffset = (int) $match[0][1];
            $delimiterText   = (string) $match[0][0];
            $isCloser        = '' !== ( (string) ( $match['closer'][0] ?? '' ) );
            $isVoid          = '' !== ( (string) ( $match['void'][0] ?? '' ) );
            $name            = self::qualifyBlockName( (string) $match['name'][0] );
            $attrsRaw        = (string) ( $match['attrs'][0] ?? '' );
            $preText         = substr( $markup, $cursor, $delimiterOffset - $cursor );

            if ( '' !== $preText ) {
                self::absorbFreeform( $preText, $parentName, $blocks, $innerContent );
            }

            if ( $isCloser ) {
                if ( null !== $parentName && $name === $parentName ) {
                    return [
                        $blocks,
                        $delimiterOffset + strlen( $delimiterText ),
                        $innerContent,
                    ];
                }

                // Stray closer with no matching opener — treat the
                // delimiter itself as freeform so we don't lose bytes.
                self::absorbFreeform( $delimiterText, $parentName, $blocks, $innerContent );
                $cursor = $delimiterOffset + strlen( $delimiterText );

                continue;
            }

            $attrs          = self::decodeAttrs( $attrsRaw );
            $afterDelimiter = $delimiterOffset + strlen( $delimiterText );

            // Void blocks are just openers with no body — model them as an
            // empty-children/empty-innerContent recursion so the assemble +
            // parent-slot bookkeeping below runs the same tail for both
            // shapes. `innerHtmlFrom([])` naturally returns ''.
            if ( $isVoid ) {
                $children   = [];
                $childInner = [];
                $nextCursor = $afterDelimiter;
            } elseif ( $depth + 1 >= self::MAX_DEPTH ) {
                // Depth cap — emit the opener as an empty container and
                // stop recursing. The truncated body remains in `$markup`
                // and will be re-absorbed as freeform by the caller once
                // this call returns. Prevents PHP-stack blow-ups on
                // adversarial input.
                $children   = [];
                $childInner = [];
                $nextCursor = $afterDelimiter;
            } else {
                [$children, $nextCursor, $childInner] = self::parseChildren(
                    $markup,
                    $afterDelimiter,
                    $name,
                    $depth + 1,
                );
            }

            $blocks[] = self::block(
                $name,
                $attrs,
                $children,
                self::innerHtmlFrom( $childInner ),
                $childInner,
            );

            if ( null !== $parentName ) {
                // A child block (void or container) contributes to the
                // parent's innerContent as a `null` slot — the block itself
                // is tracked via innerBlocks so its rendered HTML is not
                // concatenated in. This keeps the parent's `innerContent`
                // slots lined up 1:1 with `innerBlocks`.
                $innerContent[] = null;
            }

            $cursor = $nextCursor;
        }

        return [$blocks, $cursor, $innerContent];
    }

    /**
     * Route a freeform text segment into either the sibling block list
     * (top-level walk) or the parent's innerContent slot list (nested walk).
     *
     * Top-level whitespace-only segments are dropped — they represent
     * indentation between sibling top-level blocks and don't warrant a
     * freeform entry. Nested walks keep every byte so a parent's
     * `innerHTML` can round-trip verbatim.
     *
     * @since 2.5.0
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, string|null>  $innerContent
     */
    private static function absorbFreeform( string $text, ?string $parentName, array &$blocks, array &$innerContent ): void
    {
        if ( null === $parentName ) {
            if ( '' !== trim( $text ) ) {
                $blocks[] = self::freeformBlock( $text );
            }

            return;
        }

        $innerContent[] = $text;
    }

    /**
     * Concatenate the string entries of an `innerContent` list to produce
     * a block's `innerHTML` — matches WP's convention that `innerHTML` is
     * the sum of the freeform slots, with `null` (child-block) markers
     * dropped.
     *
     * @since 2.5.0
     *
     * @param  array<int, string|null>  $innerContent
     */
    private static function innerHtmlFrom( array $innerContent ): string
    {
        $html = '';

        foreach ( $innerContent as $slot ) {
            if ( is_string( $slot ) ) {
                $html .= $slot;
            }
        }

        return $html;
    }

    /**
     * Namespace-qualify a bare block name (e.g. `paragraph` → `core/paragraph`).
     *
     * WP's own parser stores block names verbatim, but its lookup layer
     * treats an un-namespaced name as core. Doing the expansion here keeps
     * downstream consumers (visual-editor tree walk, thumbnail
     * `readBlockName`) from having to sniff the delimiter twice.
     *
     * @since 2.5.0
     */
    private static function qualifyBlockName( string $name ): string
    {
        return str_contains( $name, '/' ) ? $name : 'core/' . $name;
    }

    /**
     * Decode a delimiter's attribute JSON, returning an empty bag on any
     * failure — matches WP's forgiving behavior where a broken attrs blob
     * degrades the block rather than dropping it entirely. Logs the JSON
     * error so silent attr-loss becomes observable during theme dev.
     *
     * @since 2.5.0
     *
     * @return array<string, mixed>
     */
    private static function decodeAttrs( string $attrsRaw ): array
    {
        if ( '' === $attrsRaw ) {
            return [];
        }

        $decoded = json_decode( $attrsRaw, true );

        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        Log::warning(
            'BlockMarkupParser: failed to decode block attribute JSON; degrading to empty attrs.',
            [
                'json_error' => json_last_error_msg(),
                'attrs_raw'  => $attrsRaw,
            ],
        );

        return [];
    }

    /**
     * Assemble a block array in the WP `parse_blocks()` shape.
     *
     * @since 2.5.0
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<int, array<string, mixed>>  $innerBlocks
     * @param  array<int, string|null>  $innerContent
     *
     * @return array{blockName: string|null, attrs: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>, innerHTML: string, innerContent: array<int, string|null>}
     */
    private static function block( string $name, array $attrs, array $innerBlocks, string $innerHtml, array $innerContent ): array
    {
        return [
            'blockName'    => $name,
            'attrs'        => $attrs,
            'innerBlocks'  => $innerBlocks,
            'innerHTML'    => $innerHtml,
            'innerContent' => $innerContent,
        ];
    }

    /**
     * Freeform HTML sitting between siblings becomes a `blockName: null`
     * entry — matches what WP's parser emits so downstream shape-agnostic
     * consumers keep working. Note: `null` here is the WP-spec value, not
     * a sentinel — visual-editor's `PatternThumbnail` translates it to
     * `core/freeform` for display.
     *
     * @since 2.5.0
     *
     * @return array{blockName: null, attrs: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>, innerHTML: string, innerContent: array<int, string|null>}
     */
    private static function freeformBlock( string $html ): array
    {
        return [
            'blockName'    => null,
            'attrs'        => [],
            'innerBlocks'  => [],
            'innerHTML'    => $html,
            'innerContent' => [$html],
        ];
    }
}
