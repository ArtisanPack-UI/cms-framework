<?php

/**
 * Synced Pattern Resource
 *
 * Transforms a {@see ResolvedPattern} (or its underlying {@see BlockPattern})
 * into a WordPress `/wp/v2/blocks` shaped response — i.e. the `wp_block` CPT
 * shape used by Gutenberg for synced patterns.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedPattern;

/**
 * @since 1.2.0
 */
final class SyncedPatternResource
{
    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public static function toArray(ResolvedPattern $pattern): array
    {
        return [
            'id'                     => $pattern->wpId(),
            'slug'                   => $pattern->userFacingSlug,
            'title'                  => [
                'raw'      => $pattern->title,
                'rendered' => $pattern->title,
            ],
            'content'                => [
                'raw'           => $pattern->rawContent,
                'blocks'        => $pattern->blocks,
                'block_version' => 1,
            ],
            'description'            => $pattern->description ?? '',
            'status'                 => 'publish',
            'type'                   => 'wp_block',
            'wp_pattern_sync_status' => '',
            'categories'             => $pattern->categories,
            'block_types'            => $pattern->blockTypes,
            'author'                 => null !== $pattern->model ? (int) ($pattern->model->author_id ?? 0) : 0,
            'modified'               => null !== $pattern->model
                ? optional($pattern->model->updated_at)->toIso8601String()
                : null,
        ];
    }

    /**
     * @since 1.2.0
     *
     * @param  array<int|string, ResolvedPattern>  $patterns
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $patterns): array
    {
        return array_values(array_map(
            static fn (ResolvedPattern $p) => self::toArray($p),
            $patterns,
        ));
    }
}
