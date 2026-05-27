<?php

/**
 * Pattern File Parser
 *
 * Parses theme-shipped block-pattern PHP files. The file format mirrors WP's
 * `register_block_pattern_from_file()` convention: a leading PHP doc comment
 * carries metadata headers (`Title:`, `Slug:`, `Categories:`, `Description:`,
 * `Block Types:`), and everything after the `?>` (or after the closing `*\/`
 * when there is no PHP tag) is treated as the pattern content.
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
     * Parse a pattern file's contents into a structured payload.
     *
     * @since 2.0.0
     *
     * @return array{title: string, slug: string|null, description: string|null, categories: array<int, string>, block_types: array<int, string>, content: string}
     */
    public static function parse( string $contents ): array
    {
        $headers = self::extractHeaders( $contents );
        $content = self::extractContent( $contents );

        return [
            'title'       => trim( $headers['Title'] ?? '' ),
            'slug'        => self::nullable( $headers['Slug'] ?? null ),
            'description' => self::nullable( $headers['Description'] ?? null ),
            'categories'  => self::splitList( $headers['Categories'] ?? '' ),
            'block_types' => self::splitList( $headers['Block Types'] ?? '' ),
            'content'     => trim( $content ),
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
     * @since 2.0.0
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
                $headers[ trim( $headerMatch[1] ) ] = trim( $headerMatch[2] );
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
