<?php

/**
 * Enqueued Assets renderer.
 *
 * Normalizes the `frontendStyles()` / `editorStyles()` / `frontendScripts()`
 * arrays returned by a {@see \ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme}
 * into escaped `<link>` / `<script>` tags for the corresponding Blade
 * directives.
 *
 * @since      2.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Support;

use Illuminate\Support\Facades\URL;

/**
 * Renders theme-declared style / script enqueues.
 *
 * Kept static so the Blade directives can call the renderer without
 * wiring an instance through the container each compile.
 *
 * @since 2.5.0
 */
class EnqueuedAssets
{
    /**
     * Render a list of theme-declared styles as `<link>` tags.
     *
     * Entries are normalized into a `{handle, src, media, ver, deps}`
     * shape, deduplicated by handle (first entry wins), and each
     * attribute is HTML-attribute-escaped. External URLs (`https://`,
     * `//cdn.example`) pass through unchanged; relative entries resolve
     * against the theme's `assets/` route.
     *
     * @since 2.5.0
     *
     * @param  string  $slug  Active theme slug.
     * @param  array<int|string, array<string, mixed>|string>  $styles
     *
     * @return string Concatenated `<link>` tags, or empty string if none.
     */
    public static function renderStyles( string $slug, array $styles ): string
    {
        $tags = [];

        foreach ( self::normalize( $slug, $styles ) as $handle => $entry ) {
            $attrs = [
                'rel'  => 'stylesheet',
                'id'   => $handle . '-css',
                'href' => self::withVersion( $entry['src'], $entry['ver'] ?? null ),
            ];

            if ( ! empty( $entry['media'] ) ) {
                $attrs['media'] = (string) $entry['media'];
            }

            $tags[] = '<link' . self::renderAttributes( $attrs ) . ' />';
        }

        return implode( "\n", $tags );
    }

    /**
     * Render a list of theme-declared scripts as `<script>` tags.
     *
     * Same normalization as {@see renderStyles()}. Supports `defer`,
     * `async`, `type`, and `module` flags per entry.
     *
     * @since 2.5.0
     *
     * @param  string  $slug  Active theme slug.
     * @param  array<int|string, array<string, mixed>|string>  $scripts
     *
     * @return string Concatenated `<script>` tags, or empty string if none.
     */
    public static function renderScripts( string $slug, array $scripts ): string
    {
        $tags = [];

        foreach ( self::normalize( $slug, $scripts ) as $handle => $entry ) {
            $attrs = [
                'id'  => $handle . '-js',
                'src' => self::withVersion( $entry['src'], $entry['ver'] ?? null ),
            ];

            $type = $entry['type'] ?? null;
            if ( ! empty( $entry['module'] ) ) {
                $type = 'module';
            }
            if ( null !== $type && '' !== $type ) {
                $attrs['type'] = (string) $type;
            }

            if ( ! empty( $entry['defer'] ) ) {
                $attrs['defer'] = true;
            }

            if ( ! empty( $entry['async'] ) ) {
                $attrs['async'] = true;
            }

            $tags[] = '<script' . self::renderAttributes( $attrs ) . '></script>';
        }

        return implode( "\n", $tags );
    }

    /**
     * Resolve a URL for a theme-relative asset.
     *
     * Points at the `themes.assets` named route registered by the
     * ThemesServiceProvider. Slug and path traversal are validated at
     * the route handler.
     *
     * @since 2.5.0
     *
     * @param  string  $slug  Theme slug.
     * @param  string  $path  Path relative to the theme's `assets/` directory.
     *
     * @return string Absolute asset URL.
     */
    public static function assetUrl( string $slug, string $path ): string
    {
        $path = ltrim( $path, '/' );

        return URL::route( 'themes.assets', [
            'slug' => $slug,
            'path' => $path,
        ] );
    }

    /**
     * Normalize enqueue entries into a `{handle => {src, media, ver, deps, ...}}` map.
     *
     * Rules:
     *  - String entry: treated as `src`; handle is derived from the
     *    array key (if string) or the file basename (if numeric).
     *  - Array entry: `src` is required; other keys are copied through.
     *  - Handles collide → first wins, subsequent entries are dropped.
     *  - Missing / non-string `src` values drop the entry silently so a
     *    malformed theme return doesn't emit a broken tag.
     *
     * Entries with an absolute URL (`https://…`, `//cdn.example`, or a
     * leading `/`) keep the URL as-is. Relative entries are resolved
     * against the theme's asset route.
     *
     * @since 2.5.0
     *
     * @param  string  $slug  Active theme slug.
     * @param  array<int|string, array<string, mixed>|string>  $entries
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function normalize( string $slug, array $entries ): array
    {
        $normalized = [];

        foreach ( $entries as $key => $entry ) {
            if ( is_string( $entry ) ) {
                $src   = $entry;
                $extra = [];
            } elseif ( is_array( $entry ) && isset( $entry['src'] ) && is_string( $entry['src'] ) ) {
                $src   = $entry['src'];
                $extra = $entry;
                unset( $extra['src'] );
            } else {
                continue;
            }

            $src = trim( $src );
            if ( '' === $src ) {
                continue;
            }

            $handle = is_string( $key )
                ? $key
                : pathinfo( $src, PATHINFO_FILENAME );

            if ( '' === $handle || isset( $normalized[ $handle ] ) ) {
                continue;
            }

            $normalized[ $handle ] = array_merge( $extra, [
                'src' => self::resolveSrc( $slug, $src ),
            ] );
        }

        return $normalized;
    }

    /**
     * Resolve a source string to a URL. Absolute URLs and root-relative
     * paths pass through; everything else is routed through the theme
     * asset route.
     *
     * @since 2.5.0
     *
     * @param  string  $slug  Active theme slug.
     * @param  string  $src  Raw src value from a theme entry.
     *
     * @return string Resolved URL.
     */
    protected static function resolveSrc( string $slug, string $src ): string
    {
        if ( str_starts_with( $src, 'http://' )
            || str_starts_with( $src, 'https://' )
            || str_starts_with( $src, '//' )
            || str_starts_with( $src, '/' ) ) {
            return $src;
        }

        return self::assetUrl( $slug, $src );
    }

    /**
     * Append a `?ver=` query string when a version is provided. Skipped
     * when the URL already carries a query string.
     *
     * @since 2.5.0
     */
    protected static function withVersion( string $url, mixed $version ): string
    {
        if ( null === $version || '' === $version ) {
            return $url;
        }

        if ( str_contains( $url, '?' ) ) {
            return $url;
        }

        return $url . '?ver=' . rawurlencode( (string) $version );
    }

    /**
     * Render an attribute map to an HTML attribute string. Boolean-true
     * values emit the bare attribute; string values are HTML-attribute
     * encoded.
     *
     * @since 2.5.0
     *
     * @param  array<string, bool|string>  $attributes
     */
    protected static function renderAttributes( array $attributes ): string
    {
        $rendered = '';

        foreach ( $attributes as $name => $value ) {
            if ( true === $value ) {
                $rendered .= ' ' . $name;

                continue;
            }

            if ( false === $value || null === $value) {
                continue;
            }

            $rendered .= ' ' . $name . '="' . htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $rendered;
    }
}
