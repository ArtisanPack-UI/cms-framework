<?php

/**
 * Menu Item Resource
 *
 * Transforms a {@see MenuItem} into a WordPress `/wp/v2/menu-items`-shaped
 * response array. The `type` field uses the WP-side vocabulary (`custom` /
 * `post_type` / `taxonomy`) derived from the model's `kind`; the cms-side
 * link-style flag (`link` / `submenu` / `page-list`) lives on `link_type`
 * for visual-editor's adapter to consume.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;

/**
 * @since 1.2.0
 */
final class MenuItemResource
{
    /**
     * Map model `kind` ('post-type' | 'taxonomy' | 'custom' | null) to
     * the WP `type` vocabulary.
     *
     * @since 1.2.0
     */
    protected const KIND_TO_TYPE = [
        'post-type' => 'post_type',
        'taxonomy'  => 'taxonomy',
        'custom'    => 'custom',
    ];

    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public static function toArray( MenuItem $item ): array
    {
        $kind = $item->kind ?? 'custom';
        $type = self::KIND_TO_TYPE[ $kind ] ?? 'custom';

        return [
            'id'         => (int) $item->id,
            'title'      => [
                'raw'      => $item->label,
                'rendered' => $item->label,
            ],
            'url'        => $item->url ?? '',
            'attr_title' => $item->description ?? '',
            'classes'    => null !== $item->classes && '' !== $item->classes
                ? explode( ' ', $item->classes )
                : [],
            'description' => $item->description ?? '',
            'menu_order'  => (int) $item->position,
            'menus'       => (int) $item->menu_id,
            'parent'      => null !== $item->parent_id ? (int) $item->parent_id : 0,
            'target'      => $item->target ?? '_self',
            'type'        => $type,
            'type_label'  => self::typeLabel( $type ),
            'object'      => $item->object_type ?? '',
            'object_id'   => null !== $item->object_id ? (int) $item->object_id : 0,
            'xfn'         => null !== $item->rel && '' !== $item->rel
                ? explode( ' ', $item->rel )
                : [],
            'link_type'  => $item->type,
            'meta'       => [],
        ];
    }

    /**
     * @since 1.2.0
     *
     * @param  iterable<int, MenuItem>  $items
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection( iterable $items ): array
    {
        $out = [];

        foreach ( $items as $item ) {
            $out[] = self::toArray( $item );
        }

        return $out;
    }

    /**
     * @since 1.2.0
     */
    protected static function typeLabel( string $type ): string
    {
        return match ( $type ) {
            'post_type' => 'Post Type',
            'taxonomy'  => 'Taxonomy',
            default     => 'Custom Link',
        };
    }
}
