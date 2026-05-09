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

declare(strict_types=1);

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
     * Public so {@see \ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\MenuItemRequest}
     * can pin its `kind` allow-list to the same set of keys without
     * duplicating the vocabulary.
     *
     * @since 1.2.0
     *
     * @var array<string, string>
     */
    public const KIND_TO_TYPE = [
        'post-type' => 'post_type',
        'taxonomy'  => 'taxonomy',
        'custom'    => 'custom',
    ];

    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public static function toArray(MenuItem $item): array
    {
        $kind = $item->kind ?? 'custom';
        $type = self::KIND_TO_TYPE[$kind] ?? 'custom';

        return [
            'id'          => (int) $item->id,
            'title'       => [
                'raw'      => $item->label,
                'rendered' => $item->label,
            ],
            'url'         => $item->url ?? '',
            'attr_title'  => $item->description ?? '',
            'classes'     => self::splitTokens($item->classes),
            'description' => $item->description ?? '',
            'menu_order'  => (int) $item->position,
            'menus'       => (int) $item->menu_id,
            'parent'      => null !== $item->parent_id ? (int) $item->parent_id : 0,
            'target'      => $item->target ?? '_self',
            'type'        => $type,
            'type_label'  => self::typeLabel($type),
            'object'      => $item->object_type ?? '',
            'object_id'   => null !== $item->object_id ? (int) $item->object_id : 0,
            'xfn'         => self::splitTokens($item->rel),
            'link_type'   => $item->type,
            'meta'        => [],
        ];
    }

    /**
     * @since 1.2.0
     *
     * @param  iterable<int, MenuItem>  $items
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $items): array
    {
        $out = [];

        foreach ($items as $item) {
            $out[] = self::toArray($item);
        }

        return $out;
    }

    /**
     * Split a space-separated token string (CSS class list, XFN rels) into
     * a list, collapsing runs of whitespace and dropping empty tokens.
     * Returns `[]` for null or whitespace-only input.
     *
     * @since 1.2.0
     *
     * @return array<int, string>
     */
    protected static function splitTokens(?string $value): array
    {
        if (null === $value) {
            return [];
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            return [];
        }

        $parts = preg_split('/\s+/', $trimmed);

        return false === $parts ? [] : array_values(array_filter($parts, static fn (string $p): bool => '' !== $p));
    }

    /**
     * @since 1.2.0
     */
    protected static function typeLabel(string $type): string
    {
        return match ($type) {
            'post_type' => 'Post Type',
            'taxonomy'  => 'Taxonomy',
            default     => 'Custom Link',
        };
    }
}
