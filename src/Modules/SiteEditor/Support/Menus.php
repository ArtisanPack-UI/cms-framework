<?php

/**
 * Menus Support Service
 *
 * Locations API for the SiteEditor menus subsystem. Apps register their
 * default location set in `config('cms.menus.locations')`; themes can
 * override or add entries via `theme.json` `menus.locations`. Per plan 14
 * §5.4, the theme wins on key collision (themes are closer to user-visible
 * behavior); a warning is logged so app authors aren't confused.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;

/**
 * @since 2.0.0
 */
class Menus
{
    /**
     * Returns the resolved location map for the active theme. Order:
     * app `config('cms.menus.locations')` defaults first, then the active
     * theme's `theme.json` `menus.locations` (theme keys override app keys
     * on collision; logs a warning per overridden key).
     *
     * Returns the app defaults alone when there is no active theme.
     *
     * @since 2.0.0
     *
     * @return array<string, string>
     */
    public static function locations(): array
    {
        $appLocations = self::normalizeStringMap( config( 'cms.menus.locations', [] ) );

        $theme = self::activeThemeManifest();

        if ( null === $theme ) {
            return $appLocations;
        }

        $themeLocations = self::normalizeStringMap( $theme['menus']['locations'] ?? [] );

        if ( empty( $themeLocations ) ) {
            return $appLocations;
        }

        foreach ( array_keys( $themeLocations ) as $key ) {
            if ( array_key_exists( $key, $appLocations ) && $appLocations[ $key ] !== $themeLocations[ $key ] ) {
                logger()->warning(
                    'Theme `theme.json` menus.locations key overrides app config(\'cms.menus.locations\').',
                    [
                        'location'   => $key,
                        'app_label'  => $appLocations[ $key ],
                        'theme_slug' => $theme['slug'] ?? null,
                    ],
                );
            }
        }

        return array_merge( $appLocations, $themeLocations );
    }

    /**
     * Assign a menu to a location for the active theme. Upserts the
     * `MenuLocationAssignment` row — calling this for a location that is
     * already assigned reassigns it.
     *
     * @since 2.0.0
     *
     * @return MenuLocationAssignment|null Null when there is no active theme.
     */
    public static function assign( string $location, int $menuId ): ?MenuLocationAssignment
    {
        $theme = self::activeThemeSlug();

        if ( null === $theme ) {
            return null;
        }

        return MenuLocationAssignment::query()->updateOrCreate(
            ['theme' => $theme, 'location' => $location],
            ['menu_id' => $menuId],
        );
    }

    /**
     * Unassign a location for the active theme. Returns true when an
     * assignment row was deleted, false when none existed.
     *
     * @since 2.0.0
     */
    public static function unassign( string $location ): bool
    {
        $theme = self::activeThemeSlug();

        if ( null === $theme ) {
            return false;
        }

        return MenuLocationAssignment::query()
            ->where( 'theme', $theme )
            ->where( 'location', $location )
            ->delete() > 0;
    }

    /**
     * Returns the menu currently assigned to the given location for the
     * active theme, or null when nothing is assigned (or no active theme).
     *
     * @since 2.0.0
     */
    public static function assigned( string $location ): ?Menu
    {
        $theme = self::activeThemeSlug();

        if ( null === $theme ) {
            return null;
        }

        $assignment = MenuLocationAssignment::query()
            ->where( 'theme', $theme )
            ->where( 'location', $location )
            ->first();

        return null !== $assignment ? $assignment->menu : null;
    }

    /**
     * @since 2.0.0
     */
    protected static function activeThemeSlug(): ?string
    {
        $theme = self::activeThemeManifest();

        return null !== $theme && ! empty( $theme['slug'] ) ? (string) $theme['slug'] : null;
    }

    /**
     * @since 2.0.0
     *
     * @return array<string, mixed>|null
     */
    protected static function activeThemeManifest(): ?array
    {
        return app( ThemeManager::class )->getActiveTheme();
    }

    /**
     * Coerce arbitrary input into a `array<string, string>` map, dropping
     * entries with non-string keys or non-stringable values. Defends the
     * locations contract from malformed config or theme.json input without
     * raising during a request.
     *
     * @since 2.0.0
     *
     * @return array<string, string>
     */
    protected static function normalizeStringMap( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $input as $key => $value ) {
            if ( ! is_string( $key ) || '' === $key ) {
                continue;
            }

            if ( is_string( $value ) ) {
                $normalized[ $key ] = $value;
            } elseif ( is_scalar( $value ) ) {
                $normalized[ $key ] = (string) $value;
            }
        }

        return $normalized;
    }
}
