<?php

/**
 * Pattern File Parser
 *
 * Parses theme-shipped block-pattern PHP files. The file format mirrors WP's
 * `register_block_pattern_from_file()` convention: a leading PHP doc comment
 * carries metadata headers (`Title:`, `Slug:`, `Categories:`, `Description:`,
 * `Block Types:`, `Viewport Width:`, `Keywords:`, `Inserter:`,
 * `Template Types:`, `Post Types:`), and everything after the `?>` (or
 * after the closing `*\/` when there is no PHP tag) is treated as the
 * pattern content.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

/**
 * @since 2.0.0
 */
final class PatternFileParser
{
    /**
     * Narrowest `Viewport Width:` a pattern may declare, in pixels.
     *
     * @since 2.13.0
     */
    public const MIN_VIEWPORT_WIDTH = 320;

    /**
     * Widest `Viewport Width:` a pattern may declare, in pixels.
     *
     * @since 2.13.0
     */
    public const MAX_VIEWPORT_WIDTH = 2560;

    /**
     * Parse a pattern file's contents into a structured payload.
     *
     * @since 2.0.0
     * @since 2.15.0 Adds `keywords`, `inserter`, `template_types` and `post_types`.
     *
     * @return array{title: string, slug: string|null, description: string|null, categories: array<int, string>, block_types: array<int, string>, viewport_width: int|null, keywords: array<int, string>, inserter: bool, template_types: array<int, string>, post_types: array<int, string>|null, content: string}
     */
    public static function parse( string $contents ): array
    {
        $headers = self::extractHeaders( $contents );
        $content = self::extractContent( $contents );

        return [
            'title'          => trim( $headers['Title'] ?? '' ),
            'slug'           => self::nullable( $headers['Slug'] ?? null ),
            'description'    => self::nullable( $headers['Description'] ?? null ),
            'categories'     => self::splitList( $headers['Categories'] ?? '' ),
            'block_types'    => self::splitList( $headers['Block Types'] ?? '' ),
            'viewport_width' => self::viewportWidth( $headers['Viewport Width'] ?? null ),
            'keywords'       => self::splitList( $headers['Keywords'] ?? '' ),
            'inserter'       => self::inserter( $headers['Inserter'] ?? null ),
            'template_types' => self::splitList( $headers['Template Types'] ?? '' ),
            'post_types'     => self::postTypes( $headers['Post Types'] ?? null ),
            'content'        => trim( $content ),
        ];
    }

    /**
     * Extract `Header: value` pairs from the *leading* doc-comment.
     *
     * The `\A` anchor + leading-noise allowance restricts the match to a
     * docblock at the very top of the file (after an optional UTF-8 BOM,
     * whitespace, and a single `<?php` tag — the only things that can
     * legitimately precede the metadata block in a WP-style pattern file).
     * Without anchoring, an earlier non-header `/* ... *\/` comment in the
     * file body would be parsed as the header and shadow the real one.
     *
     * Header names match case-insensitively, as WordPress's
     * `get_file_data()` does: each is stored in Title Case (`post types:`
     * becomes `Post Types`).
     *
     * @since 2.0.0
     * @since 2.15.0 Matches header names case-insensitively.
     *
     * @return array<string, string>
     */
    protected static function extractHeaders( string $contents ): array
    {
        // `\b\s*` (instead of `\s+`) lets compact files like
        // `<?php/** ... */` match — common when authors hand-format the
        // header — while `\b` keeps an accidental `<?phpsomething` from
        // being treated as a PHP open tag.
        if ( ! preg_match( '/\A(?:\xEF\xBB\xBF)?\s*(?:<\?php\b\s*)?\/\*\*?(.*?)\*\//s', $contents, $match ) ) {
            return [];
        }

        $headers = [];

        foreach ( preg_split( '/\R/', $match[1] ) as $line ) {
            // Strip leading whitespace and asterisks from each doc-comment line.
            $stripped = preg_replace( '/^\s*\*\s?/', '', $line );

            if ( null === $stripped ) {
                continue;
            }

            if ( preg_match( '/^([A-Za-z][A-Za-z0-9 \-]+):\s*(.*)$/', $stripped, $headerMatch ) ) {
                $headers[ ucwords( strtolower( trim( $headerMatch[1] ) ) ) ] = trim( $headerMatch[2] );
            }
        }

        return $headers;
    }

    /**
     * Extract the pattern body (everything after the doc-comment + optional
     * PHP closer). Falls back to the whole file when no doc-comment is present.
     *
     * @since 2.0.0
     */
    protected static function extractContent( string $contents ): string
    {
        /*
         * Strip a leading PHP-tag block surrounding a doc-comment, then return
         * the body that follows. WP block-pattern PHP files commonly close the
         * PHP tag right after the doc-comment so the body is plain HTML.
         *
         * Both branches are anchored to the start of the file (after an
         * optional UTF-8 BOM and whitespace) so a docblock buried in the body
         * is never mistaken for the leading header — its contents would
         * otherwise be silently stripped along with the docblock itself.
         */
        $leading = '\A(?:\xEF\xBB\xBF)?\s*';

        if ( preg_match( '/' . $leading . '<\?php\b\s*\/\*\*?.*?\*\/\s*\?>\s*(.*)$/s', $contents, $match ) ) {
            return $match[1];
        }

        /*
         * Doc-comment without surrounding PHP tags — return everything after
         * the closing of the comment.
         */
        if ( preg_match( '/' . $leading . '\/\*\*?.*?\*\/\s*(.*)$/s', $contents, $match ) ) {
            return $match[1];
        }

        return $contents;
    }

    /**
     * Split a comma-separated header value into a trimmed string list.
     *
     * @since 2.0.0
     *
     * @return array<int, string>
     */
    protected static function splitList( string $value ): array
    {
        $value = trim( $value );

        if ( '' === $value ) {
            return [];
        }

        return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
    }

    /**
     * Parse the `Viewport Width:` header into a pixel width.
     *
     * Mirrors WordPress's `viewportWidth` pattern property: the width the
     * pattern is laid out at before a preview scales it down. Returns null
     * when the header is missing or isn't a positive whole number (an
     * optional `px` suffix is accepted), so consumers fall back to their
     * default width. Valid values are clamped to
     * {@see self::MIN_VIEWPORT_WIDTH}–{@see self::MAX_VIEWPORT_WIDTH}.
     *
     * @since 2.13.0
     */
    protected static function viewportWidth( ?string $value ): ?int
    {
        $value = trim( (string) $value );

        if ( ! preg_match( '/^(\d+)\s*(?:px)?$/i', $value, $match ) ) {
            return null;
        }

        $width = (int) $match[1];

        if ( $width <= 0 ) {
            return null;
        }

        return max( self::MIN_VIEWPORT_WIDTH, min( self::MAX_VIEWPORT_WIDTH, $width ) );
    }

    /**
     * Parse the `Inserter:` header into WordPress's `inserter` flag.
     *
     * Mirrors WordPress's theme pattern registration: a header PHP
     * treats as empty (missing, blank or `0`) leaves the pattern
     * visible, `yes` / `true` keep it visible, and any other value
     * (`no`, `false`, …) hides it from the inserter.
     *
     * @since 2.15.0
     */
    protected static function inserter( ?string $value ): bool
    {
        $value = strtolower( trim( (string) $value ) );

        return '' === $value || '0' === $value || in_array( $value, ['yes', 'true'], true );
    }

    /**
     * Parse the `Post Types:` header into a lower-cased post-type list,
     * or null when the header is missing or empty so the pattern stays
     * unscoped (offered for every post type).
     *
     * @since 2.15.0
     *
     * @return array<int, string>|null
     */
    protected static function postTypes( ?string $value ): ?array
    {
        $postTypes = self::splitList( strtolower( (string) $value ) );

        return [] === $postTypes ? null : array_values( array_unique( $postTypes ) );
    }

    /**
     * @since 2.0.0
     */
    protected static function nullable( ?string $value ): ?string
    {
        if ( null === $value ) {
            return null;
        }

        $trimmed = trim( $value );

        return '' === $trimmed ? null : $trimmed;
    }
}
