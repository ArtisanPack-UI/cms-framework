<?php

/**
 * Global Styles Resource
 *
 * Transforms a {@see ResolvedGlobalStyles} into the WordPress
 * `/wp/v2/global-styles/{id}` response shape. Singleton-per-theme: the `id`
 * field surfaces from the DB row when one exists, and falls through to 0
 * when resolution is file-only (matches WP's behavior for not-yet-customized
 * themes).
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedGlobalStyles;

/**
 * @since 1.2.0
 */
final class GlobalStylesResource
{
    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public static function toArray(ResolvedGlobalStyles $resolved): array
    {
        return [
            'id'                     => $resolved->wpId(),
            'theme'                  => $resolved->theme,
            'title'                  => null !== $resolved->model ? $resolved->model->title : null,
            'settings'               => (object) $resolved->settings,
            'styles'                 => (object) $resolved->styles,
            'variation'              => $resolved->variation,
            'has_user_customization' => $resolved->hasUserCustomization,
            'content_hash'           => $resolved->contentHash(),
            'modified'               => null !== $resolved->model
                ? optional($resolved->model->updated_at)->toIso8601String()
                : null,
        ];
    }
}
