<?php

/**
 * Global Styles CSS Emitter
 *
 * Translates the resolved global-styles tree into CSS — custom properties
 * for `settings.color.palette`, `settings.typography.fontSizes`,
 * `settings.typography.fontFamilies`, `settings.spacing.spacingSizes`,
 * `settings.color.gradients`, and `settings.custom`, plus inline rules for
 * `styles.color`/`styles.typography`/`styles.spacing` and per-element styles
 * (`elements.link`, `elements.heading`, `elements.button`).
 *
 * Output is cached on a content-hash key derived from the resolved styles
 * tree (see {@see ResolvedGlobalStyles::contentHash()}). The cache busts on:
 *
 *   - Any DB write to `global_styles` (model observer dispatches `invalidate`).
 *   - Theme switch (the Themes module `themes.activeTheme` setting changes
 *     and the cached entry is keyed by content hash, so the next resolve
 *     produces a fresh hash).
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedGlobalStyles;
use Illuminate\Support\Facades\Cache;

/**
 * @since 1.2.0
 */
class GlobalStylesEmitter
{
    /**
     * Cache TTL — long-lived because invalidation is event-driven, not
     * time-driven. One day is enough to avoid keeping stale entries forever
     * when an event slips through (theme uninstall, manual DB edit) without
     * forcing routine re-emission.
     */
    private const CACHE_TTL = 86400;

    /**
     * @since 1.2.0
     */
    public function __construct(
        private GlobalStylesResolver $resolver,
    ) {}

    /**
     * Return the emitted CSS for the active theme's resolved global styles.
     * Returns an empty string when there is no active theme.
     *
     * @since 1.2.0
     */
    public function emit(): string
    {
        $resolved = $this->resolver->resolve();

        if (null === $resolved) {
            return '';
        }

        $cacheKey = $this->cacheKey($resolved);

        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return $cached;
        }

        $css = $this->buildCss($resolved);

        Cache::put($cacheKey, $css, self::CACHE_TTL);

        return $css;
    }

    /**
     * Force cache invalidation for the active theme. Called from the
     * GlobalStyles model observer on save/delete and from the theme-switch
     * event subscriber.
     *
     * Safe to call when no active theme — drops to a no-op.
     *
     * @since 1.2.0
     */
    public function invalidate(): void
    {
        $resolved = $this->resolver->resolve();

        if (null === $resolved) {
            return;
        }

        Cache::forget($this->cacheKey($resolved));
    }

    /**
     * Compose the cache key. Includes the theme slug so two themes with
     * coincidentally-equal content hashes still occupy distinct entries.
     *
     * @since 1.2.0
     */
    protected function cacheKey(ResolvedGlobalStyles $resolved): string
    {
        return 'cms.global-styles.css.'.$resolved->theme.'.'.$resolved->contentHash();
    }

    /**
     * Build the full emitted CSS document for a resolved styles tree.
     *
     * @since 1.2.0
     */
    protected function buildCss(ResolvedGlobalStyles $resolved): string
    {
        $rootDeclarations = array_merge(
            $this->paletteCustomProperties($resolved->settings),
            $this->fontSizeCustomProperties($resolved->settings),
            $this->fontFamilyCustomProperties($resolved->settings),
            $this->spacingCustomProperties($resolved->settings),
            $this->gradientCustomProperties($resolved->settings),
            $this->settingsCustomProperties($resolved->settings),
            $this->stylesRootDeclarations($resolved->styles),
        );

        $blocks = [];

        if ([] !== $rootDeclarations) {
            $blocks[] = ':root {'."\n".$this->formatDeclarations($rootDeclarations)."\n".'}';
        }

        $elementRules = $this->elementStyleBlocks($resolved->styles);

        foreach ($elementRules as $rule) {
            $blocks[] = $rule;
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Translate `settings.color.palette[].(slug, color)` into
     * `--wp--preset--color--{slug}: {color};` declarations.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function paletteCustomProperties(array $settings): array
    {
        $palette = $settings['color']['palette'] ?? [];

        if (! is_array($palette)) {
            return [];
        }

        return $this->presetCustomProperties($palette, 'color', 'color');
    }

    /**
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function fontSizeCustomProperties(array $settings): array
    {
        $sizes = $settings['typography']['fontSizes'] ?? [];

        if (! is_array($sizes)) {
            return [];
        }

        return $this->presetCustomProperties($sizes, 'font-size', 'size');
    }

    /**
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function fontFamilyCustomProperties(array $settings): array
    {
        $families = $settings['typography']['fontFamilies'] ?? [];

        if (! is_array($families)) {
            return [];
        }

        return $this->presetCustomProperties($families, 'font-family', 'fontFamily');
    }

    /**
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function spacingCustomProperties(array $settings): array
    {
        $sizes = $settings['spacing']['spacingSizes'] ?? [];

        if (! is_array($sizes)) {
            return [];
        }

        return $this->presetCustomProperties($sizes, 'spacing', 'size');
    }

    /**
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function gradientCustomProperties(array $settings): array
    {
        $gradients = $settings['color']['gradients'] ?? [];

        if (! is_array($gradients)) {
            return [];
        }

        return $this->presetCustomProperties($gradients, 'gradient', 'gradient');
    }

    /**
     * `settings.custom` flattens into kebab-cased custom properties prefixed
     * with `--wp--custom--`. Nested objects deepen the prefix, matching WP's
     * `wp_get_global_settings()` translation.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function settingsCustomProperties(array $settings): array
    {
        $custom = $settings['custom'] ?? [];

        if (! is_array($custom)) {
            return [];
        }

        $declarations = [];

        $this->flattenCustom($custom, '--wp--custom', $declarations);

        return $declarations;
    }

    /**
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, array{0: string, 1: string}>  $declarations
     */
    protected function flattenCustom(array $node, string $prefix, array &$declarations): void
    {
        foreach ($node as $key => $value) {
            $segment = '--'.$this->kebab((string) $key);

            if (is_array($value) && $this->isAssoc($value)) {
                $this->flattenCustom($value, $prefix.$segment, $declarations);

                continue;
            }

            if (is_scalar($value)) {
                $declarations[] = [$prefix.$segment, (string) $value];
            }
        }
    }

    /**
     * `styles.color.background`, `styles.color.text`, `styles.typography.fontSize`,
     * etc. — top-level styles get applied to `:root` so they cascade. Per-block /
     * per-element styles get their own selectors below.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $styles
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function stylesRootDeclarations(array $styles): array
    {
        $declarations = [];

        if (isset($styles['color']['background']) && is_string($styles['color']['background'])) {
            $declarations[] = ['background-color', $styles['color']['background']];
        }

        if (isset($styles['color']['text']) && is_string($styles['color']['text'])) {
            $declarations[] = ['color', $styles['color']['text']];
        }

        if (isset($styles['typography']['fontSize']) && is_scalar($styles['typography']['fontSize'])) {
            $declarations[] = ['font-size', (string) $styles['typography']['fontSize']];
        }

        if (isset($styles['typography']['fontFamily']) && is_string($styles['typography']['fontFamily'])) {
            $declarations[] = ['font-family', $styles['typography']['fontFamily']];
        }

        if (isset($styles['typography']['lineHeight']) && is_scalar($styles['typography']['lineHeight'])) {
            $declarations[] = ['line-height', (string) $styles['typography']['lineHeight']];
        }

        return $declarations;
    }

    /**
     * Per-element rule blocks: `styles.elements.link`, `styles.elements.heading`,
     * `styles.elements.button`. Each renders as its own selector.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $styles
     *
     * @return array<int, string>
     */
    protected function elementStyleBlocks(array $styles): array
    {
        $elements = $styles['elements'] ?? [];

        if (! is_array($elements)) {
            return [];
        }

        $selectors = [
            'link'    => 'a',
            'heading' => 'h1, h2, h3, h4, h5, h6',
            'button'  => '.wp-element-button, .wp-block-button__link',
        ];

        $blocks = [];

        foreach ($selectors as $element => $selector) {
            if (! isset($elements[$element]) || ! is_array($elements[$element])) {
                continue;
            }

            $declarations = $this->stylesRootDeclarations($elements[$element]);

            if ([] === $declarations) {
                continue;
            }

            $blocks[] = $selector.' {'."\n".$this->formatDeclarations($declarations)."\n".'}';
        }

        return $blocks;
    }

    /**
     * Walk a preset list (`palette`, `fontSizes`, …) and emit its
     * `--wp--preset--{prefix}--{slug}: {value};` form.
     *
     * @since 1.2.0
     *
     * @param  array<int, mixed>  $items
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function presetCustomProperties(array $items, string $prefix, string $valueKey): array
    {
        $declarations = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $slug  = $item['slug'] ?? null;
            $value = $item[$valueKey] ?? null;

            if (! is_string($slug) || '' === $slug || ! is_scalar($value)) {
                continue;
            }

            $declarations[] = ['--wp--preset--'.$prefix.'--'.$this->kebab($slug), (string) $value];
        }

        return $declarations;
    }

    /**
     * @since 1.2.0
     *
     * @param  array<int, array{0: string, 1: string}>  $declarations
     */
    protected function formatDeclarations(array $declarations): string
    {
        $lines = [];

        foreach ($declarations as [$property, $value]) {
            $lines[] = "\t".$property.': '.$value.';';
        }

        return implode("\n", $lines);
    }

    /**
     * @since 1.2.0
     */
    protected function kebab(string $value): string
    {
        $value = preg_replace('/([a-z\d])([A-Z])/', '$1-$2', $value) ?? $value;
        $value = strtolower($value);

        return str_replace('_', '-', $value);
    }

    /**
     * @since 1.2.0
     *
     * @param  array<int|string, mixed>  $value
     */
    protected function isAssoc(array $value): bool
    {
        if ([] === $value) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
