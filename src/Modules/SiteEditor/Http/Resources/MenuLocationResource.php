<?php

/**
 * Menu Location Resource
 *
 * Shapes a single resolved location for `/api/v1/menu-locations`. Mirrors
 * the response shape of WP's `/wp/v2/menu-locations`: a `name` (the key),
 * a `description` (the human label), and the assigned `menu` id (or 0
 * when nothing is assigned).
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources;

/**
 * @since 1.2.0
 */
final class MenuLocationResource
{
    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public static function toArray( string $location, string $label, ?int $menuId ): array
    {
        return [
            'name'        => $location,
            'description' => $label,
            'menu'        => null !== $menuId ? $menuId : 0,
        ];
    }

    /**
     * @since 1.2.0
     *
     * @param  array<string, string>  $locations  Keyed location → label.
     * @param  array<string, int>     $assignments  Keyed location → menu id.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection( array $locations, array $assignments ): array
    {
        $out = [];

        foreach ( $locations as $location => $label ) {
            $out[] = self::toArray( $location, $label, $assignments[ $location ] ?? null );
        }

        return $out;
    }
}
