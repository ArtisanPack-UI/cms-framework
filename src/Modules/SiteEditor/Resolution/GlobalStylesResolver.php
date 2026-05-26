<?php

/**
 * Global Styles Resolver
 *
 * Resolves the singleton global-styles tree for the active theme by deep-merging
 * three sources in priority order (lowest → highest):
 *
 *   1. `themes/{active}/theme.json` `settings` + `styles` — theme defaults.
 *   2. The active variation file at `themes/{active}/styles/{variation}.json`,
 *      when the DB row pins one (or the theme default-variation does).
 *   3. The DB row's `settings` + `styles` columns — user customization.
 *
 * The DB row is created lazily on the first user write; reads with no DB row
 * return the file-only resolution.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\File;

/**
 * @since 2.0.0
 */
class GlobalStylesResolver
{
    /**
     * @since 2.0.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * Resolve the singleton global styles for the active theme.
     *
     * Returns null only when there is no active theme. When there is an
     * active theme but no DB row, returns a resolved object backed by
     * `theme.json` defaults plus any default variation declared by the theme.
     *
     * @since 2.0.0
     */
    public function resolve(): ?ResolvedGlobalStyles
    {
        $theme = $this->activeTheme();

        if ( null === $theme ) {
            return null;
        }

        $row             = GlobalStyles::query()->where( 'theme', $theme['slug'] )->first();
        $variationSlug   = null !== $row && null !== $row->variation
            ? $row->variation
            : null;
        $variationData   = null !== $variationSlug ? $this->loadVariation( $theme['slug'], $variationSlug ) : null;

        $defaultSettings = is_array( $theme['settings'] ?? null ) ? $theme['settings'] : [];
        $defaultStyles   = is_array( $theme['styles'] ?? null ) ? $theme['styles'] : [];

        $variationSettings = is_array( $variationData['settings'] ?? null ) ? $variationData['settings'] : [];
        $variationStyles   = is_array( $variationData['styles'] ?? null ) ? $variationData['styles'] : [];

        $userSettings = null !== $row && is_array( $row->settings ) ? $row->settings : [];
        $userStyles   = null !== $row && is_array( $row->styles ) ? $row->styles : [];

        $mergedSettings = $this->deepMerge( $this->deepMerge( $defaultSettings, $variationSettings ), $userSettings );
        $mergedStyles   = $this->deepMerge( $this->deepMerge( $defaultStyles, $variationStyles ), $userStyles );

        return new ResolvedGlobalStyles(
            theme                : (string) $theme['slug'],
            settings             : $mergedSettings,
            styles               : $mergedStyles,
            variation            : $variationSlug,
            hasUserCustomization : null !== $row,
            model                : $row,
        );
    }

    /**
     * List declared variations from the active theme's `styles/` directory.
     *
     * Each entry is the parsed variation JSON (typically containing `title`,
     * `slug`, `description`, `settings`, `styles`). Returns an empty array
     * when there is no active theme or no `styles/` directory.
     *
     * @since 2.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    public function variations(): array
    {
        $theme = $this->activeTheme();

        if ( null === $theme ) {
            return [];
        }

        $directory = $this->themeStylesDirectory( (string) $theme['slug'] );

        if ( ! File::isDirectory( $directory ) ) {
            return [];
        }

        $variations = [];

        foreach ( File::files( $directory ) as $file ) {
            if ( 'json' !== $file->getExtension() ) {
                continue;
            }

            $contents = File::get( $file->getRealPath() );
            $decoded  = json_decode( $contents, true );

            if ( ! is_array( $decoded ) ) {
                continue;
            }

            if ( ! isset( $decoded['slug'] ) || ! is_string( $decoded['slug'] ) ) {
                $decoded['slug'] = $file->getFilenameWithoutExtension();
            }

            if ( ! isset( $decoded['title'] ) || ! is_string( $decoded['title'] ) ) {
                $decoded['title'] = $this->humanizeSlug( $decoded['slug'] );
            }

            $variations[] = $decoded;
        }

        usort( $variations, function ( array $a, array $b ): int {
            return strcmp( (string) ( $a['slug'] ?? '' ), (string) ( $b['slug'] ?? '' ) );
        } );

        return $variations;
    }

    /**
     * Revert user customization for the active theme by deleting its DB row.
     *
     * Returns true when a row was deleted, false when no row existed.
     *
     * @since 2.0.0
     */
    public function revert(): bool
    {
        $theme = $this->activeTheme();

        if ( null === $theme ) {
            return false;
        }

        return GlobalStyles::query()->where( 'theme', $theme['slug'] )->delete() > 0;
    }

    /**
     * Apply a user customization payload, creating the DB row on first call
     * and updating it on subsequent calls. Returns the updated model so the
     * caller can surface it through a REST resource.
     *
     * @since 2.0.0
     *
     * @param  array{settings?: array<string, mixed>|null, styles?: array<string, mixed>|null, variation?: string|null, title?: string|null, author_id?: int|null}  $payload
     */
    public function update( array $payload ): ?GlobalStyles
    {
        $theme = $this->activeTheme();

        if ( null === $theme ) {
            return null;
        }

        // Distinguish "key absent — leave the column alone" from "key present
        // and null — clear the column." `array_filter(…, fn !== null)` would
        // collapse the second case into the first, breaking the WP semantic
        // where PUT `{"variation": null}` clears a stored variation.
        $assignable = [];

        foreach ( ['settings', 'styles', 'variation', 'title', 'author_id'] as $key ) {
            if ( array_key_exists( $key, $payload ) ) {
                $assignable[ $key ] = $payload[ $key ];
            }
        }

        return GlobalStyles::query()->updateOrCreate(
            ['theme' => (string) $theme['slug']],
            $assignable,
        );
    }

    /**
     * Active-theme manifest accessor. Returns null when no theme is active or
     * the active theme has no `slug` key in its manifest.
     *
     * @since 2.0.0
     *
     * @return array<string, mixed>|null
     */
    protected function activeTheme(): ?array
    {
        $theme = $this->themeManager->getActiveTheme();

        return null !== $theme && ! empty( $theme['slug'] ) ? $theme : null;
    }

    /**
     * Load the variation JSON file for the given theme + slug. Returns null
     * when the file does not exist or fails to decode.
     *
     * @since 2.0.0
     *
     * @return array<string, mixed>|null
     */
    protected function loadVariation( string $theme, string $slug ): ?array
    {
        if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $slug ) ) {
            return null;
        }

        $path = $this->themeStylesDirectory( $theme ) . '/' . $slug . '.json';

        if ( ! File::exists( $path ) ) {
            return null;
        }

        $decoded = json_decode( File::get( $path ), true );

        return is_array( $decoded ) ? $decoded : null;
    }

    /**
     * @since 2.0.0
     */
    protected function themeStylesDirectory( string $theme ): string
    {
        $directory = (string) config( 'cms.themes.directory', 'themes' );

        return base_path( $directory ) . '/' . $theme . '/styles';
    }

    /**
     * Recursive associative-array merge. Right-hand keys override left-hand
     * keys; nested associative arrays merge instead of replacing wholesale.
     * Numeric arrays (lists) are replaced — matches WP behavior for palette
     * arrays where a downstream layer should redeclare the full list rather
     * than append to it.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     *
     * @return array<string, mixed>
     */
    protected function deepMerge( array $base, array $overrides ): array
    {
        foreach ( $overrides as $key => $value ) {
            if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && $this->isAssoc( $value ) && $this->isAssoc( $base[ $key ] ) ) {
                $base[ $key ] = $this->deepMerge( $base[ $key ], $value );

                continue;
            }

            $base[ $key ] = $value;
        }

        return $base;
    }

    /**
     * @since 2.0.0
     *
     * @param  array<int|string, mixed>  $value
     */
    protected function isAssoc( array $value ): bool
    {
        if ( [] === $value ) {
            return false;
        }

        return array_keys( $value ) !== range( 0, count( $value) - 1);
    }

    /**
     * @since 2.0.0
     */
    protected function humanizeSlug( string $slug): string
    {
        return ucwords( str_replace( ['-', '_'], ' ', $slug));
    }
}
