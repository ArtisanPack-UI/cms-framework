<?php

/**
 * Menu Resource
 *
 * Transforms a {@see Menu} into a WordPress `/wp/v2/menus`-shaped response
 * array. The `locations` field lists the theme-declared location keys this
 * menu is currently assigned to.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;

/**
 * @since 2.0.0
 */
final class MenuResource
{
    /**
     * @since 2.0.0
     *
     * @return array<string, mixed>
     */
    public static function toArray( Menu $menu ): array
    {
        return [
            'id'             => (int) $menu->id,
            'description'    => $menu->description ?? '',
            'name'           => $menu->name,
            'slug'           => $menu->slug,
            'meta'           => [],
            'locations'      => $menu->locationAssignments
                ->pluck( 'location' )
                ->values()
                ->all(),
            'auto_add_pages' => (bool) $menu->auto_add_pages,
            'theme'          => $menu->theme,
        ];
    }

    /**
     * @since 2.0.0
     *
     * @param  iterable<int, Menu>  $menus
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection( iterable $menus ): array
    {
        $out = [];

        foreach ( $menus as $menu ) {
            $out[] = self::toArray( $menu );
        }

        return $out;
    }
}
