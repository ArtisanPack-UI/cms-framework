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
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

/**
 * @since 1.2.0
 */
final class PatternFileParser
{
    /**
     * Parse a pattern file's contents into a structured payload.
     *
     * @since 1.2.0
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
     * Extract `Header: value` pairs from the leading doc-comment.
     *
     * @since 1.2.0
     *
     * @return array<string, string>
     */
    protected static function extractHeaders( string $contents ): array
    {
        if ( ! preg_match( '/\/\*\*?(.*?)\*\//s', $contents, $match ) ) {
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
     * @since 1.2.0
     */
    protected static function extractContent( string $contents ): string
    {
        /*
         * Strip a leading PHP-tag block surrounding a doc-comment, then return
         * the body that follows. WP block-pattern PHP files commonly close the
         * PHP tag right after the doc-comment so the body is plain HTML.
         */
        if ( preg_match( '/<\?php\s+\/\*\*?.*?\*\/\s*\?>(.*)$/s', $contents, $match ) ) {
            return $match[1];
        }

        /*
         * Doc-comment without surrounding PHP tags — return everything after
         * the closing of the comment.
         */
        if ( preg_match( '/\/\*\*?.*?\*\/(.*)$/s', $contents, $match ) ) {
            return $match[1];
        }

        return $contents;
    }

    /**
     * Split a comma-separated header value into a trimmed string list.
     *
     * @since 1.2.0
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
     * @since 1.2.0
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
