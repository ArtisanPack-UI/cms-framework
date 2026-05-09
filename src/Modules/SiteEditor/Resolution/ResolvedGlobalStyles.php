<?php

/**
 * Resolved Global Styles DTO
 *
 * Carries the result of {@see GlobalStylesResolver::resolve()}: the merged
 * `settings` + `styles` for the active theme, the active variation slug (if
 * any), and metadata that lets the visual-editor adapter and the CSS emitter
 * keep their cache keys aligned.
 *
 * Distinct from {@see ResolvedEntity} — global styles are a singleton per
 * theme with no slug keyspace, so the shape is intentionally narrower.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;

/**
 * @since 1.2.0
 */
final class ResolvedGlobalStyles
{
    /**
     * @since 1.2.0
     *
     * @param  string  $theme  Active theme slug.
     * @param  array<string, mixed>  $settings  Resolved settings tree (theme.json `settings` + variation + DB overrides merged).
     * @param  array<string, mixed>  $styles  Resolved styles tree (theme.json `styles` + variation + DB overrides merged).
     * @param  string|null  $variation  Active variation slug, or null when the theme defaults are in effect.
     * @param  bool  $hasUserCustomization  True when a DB row exists for the active theme.
     * @param  GlobalStyles|null  $model  The DB row when one exists; null when resolution is file-only.
     */
    public function __construct(
        public readonly string $theme,
        public readonly array $settings,
        public readonly array $styles,
        public readonly ?string $variation,
        public readonly bool $hasUserCustomization,
        public readonly ?GlobalStyles $model,
    ) {}

    /**
     * The integer ID of the backing DB row, or 0 when none exists.
     *
     * Maps to WP's `id` field on global-styles responses.
     *
     * @since 1.2.0
     */
    public function wpId(): int
    {
        return null !== $this->model ? (int) $this->model->id : 0;
    }

    /**
     * Stable content hash used as the CSS-emission cache key. Captures
     * everything that influences the emitted CSS — theme, variation, and
     * the resolved settings/styles trees — so any change busts the cache.
     *
     * @since 1.2.0
     */
    public function contentHash(): string
    {
        return md5((string) json_encode([
            'theme'     => $this->theme,
            'variation' => $this->variation,
            'settings'  => $this->settings,
            'styles'    => $this->styles,
        ]));
    }

    /**
     * Convert to the array shape consumed by visual-editor's
     * `\ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedGlobalStyles::fromArray()`
     * for the `ap.visual-editor.global-styles` filter (singleton).
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function toFilterEntry(): array
    {
        return [
            'theme'                  => $this->theme,
            'settings'               => $this->settings,
            'styles'                 => $this->styles,
            'variation'              => $this->variation,
            'has_user_customization' => $this->hasUserCustomization,
            'wp_id'                  => $this->wpId(),
            'content_hash'           => $this->contentHash(),
            'modified_at'            => null !== $this->model
                ? optional($this->model->updated_at)->toIso8601String()
                : null,
        ];
    }
}
