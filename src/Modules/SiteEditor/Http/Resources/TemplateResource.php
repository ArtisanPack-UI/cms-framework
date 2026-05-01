<?php

/**
 * Template Resource
 *
 * Transforms a {@see ResolvedEntity} into a WordPress `/wp/v2/templates`
 * shaped response array.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity;

/**
 * @since 1.2.0
 */
final class TemplateResource
{
    /**
     * Convert a ResolvedEntity into the WP-shape array used by both
     * `/wp/v2/templates` and (with `area` added) `/wp/v2/template-parts`.
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public static function toArray( ResolvedEntity $entity ): array
    {
        $wpId = $entity->wpId();
        $id   = $wpId > 0 && ! $entity->hasThemeFile
            ? (string) $wpId
            : $entity->theme . '//' . $entity->slug;

        $payload = [
            'id'             => $id,
            'slug'           => $entity->slug,
            'theme'          => $entity->theme,
            'type'           => 'wp_template',
            'source'         => $entity->source,
            'origin'         => null,
            'content'        => [
                'raw'           => $entity->content,
                'block_version' => 1,
            ],
            'title'          => [
                'raw'      => $entity->title ?? '',
                'rendered' => $entity->title ?? '',
            ],
            'description'    => $entity->description ?? '',
            'status'         => $entity->status,
            'wp_id'          => $wpId,
            'has_theme_file' => $entity->hasThemeFile,
            'is_custom'      => $entity->isCustom,
            'author'         => null !== $entity->model ? (int) ( $entity->model->author_id ?? 0 ) : 0,
            'modified'       => null !== $entity->model
                ? optional( $entity->model->updated_at )->toIso8601String()
                : null,
        ];

        if ( null !== $entity->area ) {
            $payload['type'] = 'wp_template_part';
            $payload['area'] = $entity->area;
        }

        return $payload;
    }

    /**
     * @since 1.2.0
     *
     * @param  array<int, ResolvedEntity>  $entities
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection( array $entities ): array
    {
        return array_map( static fn ( ResolvedEntity $e ) => self::toArray( $e ), $entities );
    }
}
