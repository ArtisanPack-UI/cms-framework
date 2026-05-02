<?php

/**
 * Block Pattern Resource
 *
 * Transforms a {@see ResolvedPattern} into a WordPress
 * `/wp/v2/block-patterns/patterns` shaped response — i.e. the read-only
 * inserter-catalogue shape Gutenberg uses for unsynced patterns. Both
 * theme-shipped and user-authored unsynced patterns surface here.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedPattern;

/**
 * @since 1.2.0
 */
final class BlockPatternResource
{
    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public static function toArray( ResolvedPattern $pattern ): array
    {
        return [
            // WP's `name` field on block-pattern responses carries the
            // namespaced slug — `theme/header` for theme patterns, or the
            // `user/{slug}` storage form for user patterns. Mirrors WP's
            // expectation that `name` is globally unique across the inserter.
            'name'        => $pattern->slug,
            'slug'        => $pattern->userFacingSlug,
            'title'       => $pattern->title,
            'description' => $pattern->description ?? '',
            'content'     => $pattern->rawContent,
            'categories'  => $pattern->categories,
            'block_types' => $pattern->blockTypes,
            'source'      => $pattern->source,
            'synced'      => $pattern->synced,
            'theme'       => $pattern->theme,
            'wp_id'       => BlockPattern::SOURCE_USER === $pattern->source ? $pattern->wpId() : null,
            'modified'    => null !== $pattern->model
                ? optional( $pattern->model->updated_at )->toIso8601String()
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
    public static function collection( array $patterns ): array
    {
        return array_values( array_map(
            static fn ( ResolvedPattern $p ) => self::toArray( $p ),
            $patterns,
        ) );
    }
}
