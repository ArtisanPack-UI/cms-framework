<?php

/**
 * Site Editor Abilities
 *
 * Resolves the Gate ability each group of Site Editor API endpoints
 * requires, following WordPress's REST permissions:
 *
 *  - `read` — every Site Editor endpoint, including reads. Defaults to
 *    `manage-content`, the analogue of the `edit_posts` capability
 *    WordPress requires to read templates, template parts, navigation
 *    menus, global styles and patterns. A user with the `structure`
 *    ability also passes.
 *  - `structure` — writes to global styles, templates, template parts,
 *    menus, menu items and menu location assignments. They change every
 *    page, so they default to `manage-themes`, WordPress's
 *    `edit_theme_options` analogue.
 *  - `content` — writes to block patterns and synced blocks, content an
 *    editor builds with. Defaults to `manage-content`.
 *
 * All three abilities are seeded by `PermissionsTableSeeder` and deny
 * anyone without them.
 *
 * @since      2.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * @since 2.15.0
 */
final class SiteEditorAbilities
{
    /**
     * Group every Site Editor endpoint requires, reads included.
     *
     * @since 2.15.0
     */
    public const READ = 'read';

    /**
     * Group for global styles, templates, template parts, menus, menu
     * items and menu location assignments.
     *
     * @since 2.15.0
     */
    public const STRUCTURE = 'structure';

    /**
     * Group for block patterns and synced blocks.
     *
     * @since 2.15.0
     */
    public const CONTENT = 'content';

    /**
     * Default ability per group.
     *
     * @since 2.15.0
     *
     * @var array<string, string>
     */
    private const DEFAULTS = [
        self::READ      => 'manage-content',
        self::STRUCTURE => 'manage-themes',
        self::CONTENT   => 'manage-content',
    ];

    /**
     * The Gate ability the given group requires.
     *
     * Filterable through `ap.cmsFramework.abilities.siteEditor.read`,
     * `ap.cmsFramework.abilities.siteEditor.structure` and
     * `ap.cmsFramework.abilities.siteEditor.content`, so a host can map
     * any group onto its own ability. An unknown group resolves as
     * `structure`, the strictest default.
     *
     * @since 2.15.0
     *
     * @param  string  $group  {@see self::READ}, {@see self::STRUCTURE} or {@see self::CONTENT}.
     *
     * @return string The ability name.
     */
    public static function for( string $group ): string
    {
        if ( ! isset( self::DEFAULTS[ $group ] ) ) {
            $group = self::STRUCTURE;
        }

        return (string) applyFilters( 'ap.cmsFramework.abilities.siteEditor.' . $group, self::DEFAULTS[ $group ] );
    }

    /**
     * Whether the user may access the given group. The `read` group also
     * admits a user with the `structure` ability, so a role that may
     * change the site's structure can always read it.
     *
     * @since 2.15.0
     *
     * @param  Authorizable|null  $user   The user, or null for a guest.
     * @param  string             $group  {@see self::READ}, {@see self::STRUCTURE} or {@see self::CONTENT}.
     */
    public static function allows( ?Authorizable $user, string $group ): bool
    {
        if ( null === $user ) {
            return false;
        }

        if ( $user->can( self::for( $group ) ) ) {
            return true;
        }

        return self::READ === $group && $user->can( self::for( self::STRUCTURE ) );
    }
}
