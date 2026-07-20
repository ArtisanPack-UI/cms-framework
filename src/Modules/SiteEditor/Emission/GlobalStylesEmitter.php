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
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedGlobalStyles;
use Illuminate\Support\Facades\Cache;

/**
 * @since 2.0.0
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
     * Emitter-output schema version. Bumped whenever the structure of
     * the CSS this emitter produces changes (new rule families, removed
     * rules, reformatted selectors). The version is part of the cache
     * key so a deploy with a bumped emitter automatically invalidates
     * every cached entry — no manual `php artisan cache:clear` needed
     * to pick up the new output. Bump on any meaningful emission diff.
     *
     * v2: emit `.has-{slug}-color` / `-background-color` / `-border-color`
     * / `-font-size` / `-gradient-background` preset class bindings
     * (Keystone #53). Without these the picker swatch lights up but
     * neither the canvas nor the front-end visually applies the color.
     *
     * v3: widen the styles walker to cover border / spacing / extended
     * typography / shadow (#200), add per-block emission for
     * `styles.blocks.{namespace/name}` (#201), and translate WP-canonical
     * `var:preset|category|slug` values into real `var(...)` references
     * (#202).
     */
    private const SCHEMA_VERSION = 'v3';

    /**
     * Flat theme.json → CSS property map for scalar-leaf declarations.
     * Nested shapes that aren't a single scalar (spacing.padding as an
     * array of sides, border.top.width, border.radius.topLeft, …) are
     * handled by dedicated walkers in {@see stylesRootDeclarations()}.
     *
     * @var array<string, string>
     */
    private const SCALAR_DECLARATION_MAP = [
        'color.background'          => 'background-color',
        'color.text'                => 'color',
        'typography.fontSize'       => 'font-size',
        'typography.fontFamily'     => 'font-family',
        'typography.lineHeight'     => 'line-height',
        'typography.fontWeight'     => 'font-weight',
        'typography.fontStyle'      => 'font-style',
        'typography.letterSpacing'  => 'letter-spacing',
        'typography.textTransform'  => 'text-transform',
        'typography.textDecoration' => 'text-decoration',
        'border.radius'             => 'border-radius',
        'border.color'              => 'border-color',
        'border.style'              => 'border-style',
        'border.width'              => 'border-width',
        'spacing.padding'           => 'padding',
        'spacing.margin'            => 'margin',
        'shadow'                    => 'box-shadow',
    ];

    /**
     * Border corner → CSS property map for `border.radius.{corner}` walk.
     *
     * @var array<string, string>
     */
    private const BORDER_RADIUS_CORNERS = [
        'topLeft'     => 'border-top-left-radius',
        'topRight'    => 'border-top-right-radius',
        'bottomLeft'  => 'border-bottom-left-radius',
        'bottomRight' => 'border-bottom-right-radius',
    ];

    /**
     * Border side → CSS property prefix map for `border.{side}.{color|style|width}` walk.
     *
     * @var array<string, string>
     */
    private const BORDER_SIDES = [
        'top'    => 'border-top',
        'right'  => 'border-right',
        'bottom' => 'border-bottom',
        'left'   => 'border-left',
    ];

    /**
     * Spacing side → CSS property suffix map for `spacing.{padding|margin}.{side}` walk.
     *
     * @var array<string, string>
     */
    private const SPACING_SIDES = [
        'top'    => 'top',
        'right'  => 'right',
        'bottom' => 'bottom',
        'left'   => 'left',
    ];

    /**
     * @since 2.0.0
     */
    public function __construct(
        private GlobalStylesResolver $resolver,
    ) {
    }

    /**
     * Return the emitted CSS for the active theme's resolved global styles.
     * Returns an empty string when there is no active theme.
     *
     * @since 2.0.0
     */
    public function emit(): string
    {
        $resolved = $this->resolver->resolve();

        if ( null === $resolved ) {
            return '';
        }

        $cacheKey = $this->cacheKey( $resolved );

        $cached = Cache::get( $cacheKey );

        if ( is_string( $cached ) ) {
            return $cached;
        }

        $css = $this->buildCss( $resolved );

        Cache::put( $cacheKey, $css, self::CACHE_TTL );

        return $css;
    }

    /**
     * Force cache invalidation for the active theme. Called from the
     * GlobalStyles model observer on save/delete and from the theme-switch
     * event subscriber.
     *
     * Safe to call when no active theme — drops to a no-op.
     *
     * @since 2.0.0
     */
    public function invalidate(): void
    {
        $resolved = $this->resolver->resolve();

        if ( null === $resolved ) {
            return;
        }

        Cache::forget( $this->cacheKey( $resolved ) );
    }

    /**
     * Compose the cache key. Includes the theme slug so two themes with
     * coincidentally-equal content hashes still occupy distinct entries.
     *
     * @since 2.0.0
     */
    protected function cacheKey( ResolvedGlobalStyles $resolved ): string
    {
        return 'cms.global-styles.css.' . self::SCHEMA_VERSION . '.' . $resolved->theme . '.' . $resolved->contentHash();
    }

    /**
     * Build the full emitted CSS document for a resolved styles tree.
     *
     * @since 2.0.0
     */
    protected function buildCss( ResolvedGlobalStyles $resolved ): string
    {
        $rootDeclarations = array_merge(
            $this->paletteCustomProperties( $resolved->settings ),
            $this->fontSizeCustomProperties( $resolved->settings ),
            $this->fontFamilyCustomProperties( $resolved->settings ),
            $this->spacingCustomProperties( $resolved->settings ),
            $this->gradientCustomProperties( $resolved->settings ),
            $this->settingsCustomProperties( $resolved->settings ),
            $this->stylesRootDeclarations( $resolved->styles ),
        );

        $blocks = [];

        if ( [] !== $rootDeclarations ) {
            $blocks[] = ':root {' . "\n" . $this->formatDeclarations( $rootDeclarations ) . "\n" . '}';
        }

        $elementRules = $this->elementStyleBlocks( $resolved->styles );

        foreach ( $elementRules as $rule ) {
            $blocks[] = $rule;
        }

        foreach ( $this->blockStyleBlocks( $resolved->styles ) as $rule ) {
            $blocks[] = $rule;
        }

        // Preset class bindings — Gutenberg adds `has-{slug}-color`,
        // `has-{slug}-background-color`, `has-{slug}-font-size` etc.
        // when the author picks a preset from the palette / font-size
        // picker. Without a CSS rule binding those classes to the
        // matching `--wp--preset--*` custom property the choice never
        // visually applies — the picker swatch lights up but neither
        // the canvas nor the front-end shows the color change
        // (Keystone #53).
        foreach ( $this->presetClassBindings( $resolved->settings ) as $rule ) {
            $blocks[] = $rule;
        }

        return implode( "\n\n", $blocks );
    }

    /**
     * Emit `.has-{slug}-color`, `-background-color`, `-border-color`,
     * `-font-size`, and `-gradient-background` rules that bind each
     * preset slug to its matching `--wp--preset--*` custom property.
     *
     * `!important` mirrors WordPress core's emission so a preset
     * selection always overrides cascading default styles, the way
     * the upstream block editor and front-end already behave.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, string>
     */
    protected function presetClassBindings( array $settings ): array
    {
        $rules = [];

        $palette = is_array( $settings['color']['palette'] ?? null )
            ? $settings['color']['palette']
            : [];

        foreach ( $this->presetSlugs( $palette, 'color' ) as $slug ) {
            $var = '--wp--preset--color--' . $slug;

            $rules[] = '.has-' . $slug . '-color { color: var(' . $var . ') !important; }';
            $rules[] = '.has-' . $slug . '-background-color { background-color: var(' . $var . ') !important; }';
            $rules[] = '.has-' . $slug . '-border-color { border-color: var(' . $var . ') !important; }';
        }

        $fontSizes = is_array( $settings['typography']['fontSizes'] ?? null )
            ? $settings['typography']['fontSizes']
            : [];

        foreach ( $this->presetSlugs( $fontSizes, 'size' ) as $slug ) {
            $rules[] = '.has-' . $slug . '-font-size { font-size: var(--wp--preset--font-size--' . $slug . ') !important; }';
        }

        $gradients = is_array( $settings['color']['gradients'] ?? null )
            ? $settings['color']['gradients']
            : [];

        foreach ( $this->presetSlugs( $gradients, 'gradient' ) as $slug ) {
            $rules[] = '.has-' . $slug . '-gradient-background { background: var(--wp--preset--gradient--' . $slug . ') !important; }';
        }

        return $rules;
    }

    /**
     * Extract the kebab-cased slug list from a presets array, skipping
     * malformed entries the same way {@see presetCustomProperties()} does
     * — a slug alone isn't enough; the matching `$valueKey`
     * (`color` / `size` / `gradient` / `fontFamily`) has to be a usable
     * scalar too. Without the value guard a malformed entry would still
     * emit a `.has-{slug}-*` rule pointing at an undeclared CSS var.
     *
     * @since 2.0.0
     *
     * @param  array<int, mixed>  $items
     * @param  string             $valueKey  The required-non-empty value key per preset family.
     *
     * @return array<int, string>
     */
    protected function presetSlugs( array $items, string $valueKey ): array
    {
        $slugs = [];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $slug  = $item['slug'] ?? null;
            $value = $item[ $valueKey ] ?? null;

            if ( ! is_string( $slug ) || '' === $slug || ! is_scalar( $value ) ) {
                continue;
            }

            $slugs[] = $this->kebab( $slug );
        }

        return $slugs;
    }

    /**
     * Translate `settings.color.palette[].(slug, color)` into
     * `--wp--preset--color--{slug}: {color};` declarations.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function paletteCustomProperties( array $settings ): array
    {
        $palette = $settings['color']['palette'] ?? [];

        if ( ! is_array( $palette ) ) {
            return [];
        }

        return $this->presetCustomProperties( $palette, 'color', 'color' );
    }

    /**
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function fontSizeCustomProperties( array $settings ): array
    {
        $sizes = $settings['typography']['fontSizes'] ?? [];

        if ( ! is_array( $sizes ) ) {
            return [];
        }

        return $this->presetCustomProperties( $sizes, 'font-size', 'size' );
    }

    /**
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function fontFamilyCustomProperties( array $settings ): array
    {
        $families = $settings['typography']['fontFamilies'] ?? [];

        if ( ! is_array( $families ) ) {
            return [];
        }

        return $this->presetCustomProperties( $families, 'font-family', 'fontFamily' );
    }

    /**
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function spacingCustomProperties( array $settings ): array
    {
        $sizes = $settings['spacing']['spacingSizes'] ?? [];

        if ( ! is_array( $sizes ) ) {
            return [];
        }

        return $this->presetCustomProperties( $sizes, 'spacing', 'size' );
    }

    /**
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function gradientCustomProperties( array $settings ): array
    {
        $gradients = $settings['color']['gradients'] ?? [];

        if ( ! is_array( $gradients ) ) {
            return [];
        }

        return $this->presetCustomProperties( $gradients, 'gradient', 'gradient' );
    }

    /**
     * `settings.custom` flattens into kebab-cased custom properties prefixed
     * with `--wp--custom--`. Nested objects deepen the prefix, matching WP's
     * `wp_get_global_settings()` translation.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function settingsCustomProperties( array $settings ): array
    {
        $custom = $settings['custom'] ?? [];

        if ( ! is_array( $custom ) ) {
            return [];
        }

        $declarations = [];

        $this->flattenCustom( $custom, '--wp--custom', $declarations );

        return $declarations;
    }

    /**
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, array{0: string, 1: string}>  $declarations
     */
    protected function flattenCustom( array $node, string $prefix, array &$declarations ): void
    {
        foreach ( $node as $key => $value ) {
            $segment = '--' . $this->kebab( (string) $key );

            if ( is_array( $value ) && $this->isAssoc( $value ) ) {
                $this->flattenCustom( $value, $prefix . $segment, $declarations );

                continue;
            }

            if ( is_scalar( $value ) ) {
                $declarations[] = [$prefix . $segment, (string) $value];
            }
        }
    }

    /**
     * `styles.color.background`, `styles.color.text`, `styles.typography.fontSize`,
     * etc. — top-level styles get applied to `:root` so they cascade. Per-block /
     * per-element styles get their own selectors below.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $styles
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function stylesRootDeclarations( array $styles ): array
    {
        $declarations = [];

        foreach ( self::SCALAR_DECLARATION_MAP as $path => $property ) {
            $value = $this->pluck( $styles, $path );

            if ( ! is_scalar( $value ) ) {
                continue;
            }

            $declarations[] = [$property, $this->translatePresetValue( (string) $value )];
        }

        // border.radius.{corner} — WP theme.json v3 allows per-corner
        // radii alongside (or instead of) the shorthand handled above.
        $radius = $styles['border']['radius'] ?? null;

        if ( is_array( $radius ) ) {
            foreach ( self::BORDER_RADIUS_CORNERS as $corner => $property ) {
                $value = $radius[ $corner ] ?? null;

                if ( ! is_scalar( $value ) ) {
                    continue;
                }

                $declarations[] = [$property, $this->translatePresetValue( (string) $value )];
            }
        }

        // border.{side}.{color|style|width} — per-side overrides.
        $border = $styles['border'] ?? null;

        if ( is_array( $border ) ) {
            foreach ( self::BORDER_SIDES as $side => $prefix ) {
                $sideDefinition = $border[ $side ] ?? null;

                if ( ! is_array( $sideDefinition ) ) {
                    continue;
                }

                foreach ( ['color', 'style', 'width'] as $facet ) {
                    $value = $sideDefinition[ $facet ] ?? null;

                    if ( ! is_scalar( $value ) ) {
                        continue;
                    }

                    $declarations[] = [$prefix . '-' . $facet, $this->translatePresetValue( (string) $value )];
                }
            }
        }

        // spacing.{padding|margin}.{top|right|bottom|left} — long-form
        // per-side spacing. Only kicks in when the value is an array;
        // the scalar shorthand is handled by the flat map above.
        foreach ( ['padding', 'margin'] as $spacingKey ) {
            $definition = $styles['spacing'][ $spacingKey ] ?? null;

            if ( ! is_array( $definition ) ) {
                continue;
            }

            foreach ( self::SPACING_SIDES as $side => $suffix ) {
                $value = $definition[ $side ] ?? null;

                if ( ! is_scalar( $value ) ) {
                    continue;
                }

                $declarations[] = [$spacingKey . '-' . $suffix, $this->translatePresetValue( (string) $value )];
            }
        }

        return $declarations;
    }

    /**
     * Read a dot-notated path out of a nested styles tree, returning
     * `null` when any segment is missing or the leaf isn't scalar-shaped.
     *
     * @since 2.5.0
     *
     * @param  array<string, mixed>  $styles
     */
    protected function pluck( array $styles, string $path ): mixed
    {
        $node = $styles;

        foreach ( explode( '.', $path ) as $segment ) {
            if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
                return null;
            }

            $node = $node[ $segment ];
        }

        return $node;
    }

    /**
     * Translate the WP-canonical `var:preset|{category}|{slug}` shorthand
     * into a real CSS `var(--wp--preset--{category}--{slug})` reference
     * (#202). Values that don't match the pattern pass through unchanged,
     * so themes that write either the shorthand or the raw `var(...)`
     * form both work.
     *
     * @since 2.5.0
     */
    protected function translatePresetValue( string $value ): string
    {
        return (string) preg_replace_callback(
            '/var:preset\|([A-Za-z0-9_-]+)\|([A-Za-z0-9_-]+)/',
            fn ( array $matches ): string => 'var(--wp--preset--' . $matches[1] . '--' . $matches[2] . ')',
            $value,
        );
    }

    /**
     * Per-element rule blocks: `styles.elements.link`, `styles.elements.heading`,
     * `styles.elements.button`. Each renders as its own selector.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $styles
     *
     * @return array<int, string>
     */
    protected function elementStyleBlocks( array $styles ): array
    {
        $elements = $styles['elements'] ?? [];

        if ( ! is_array( $elements ) ) {
            return [];
        }

        $selectors = [
            'link'    => 'a',
            'heading' => 'h1, h2, h3, h4, h5, h6',
            'button'  => '.wp-element-button, .wp-block-button__link',
        ];

        $blocks = [];

        foreach ( $selectors as $element => $selector ) {
            if ( ! isset( $elements[ $element ] ) || ! is_array( $elements[ $element ] ) ) {
                continue;
            }

            $declarations = $this->stylesRootDeclarations( $elements[ $element ] );

            if ( [] === $declarations ) {
                continue;
            }

            $blocks[] = $selector . ' {' . "\n" . $this->formatDeclarations( $declarations ) . "\n" . '}';
        }

        return $blocks;
    }

    /**
     * Per-block rule blocks: `styles.blocks.{namespace/name}`. Each
     * entry renders into a `.wp-block-{namespace-}{name}` selector,
     * dropping `core/` to match Gutenberg's own class naming
     * (`core/quote` → `.wp-block-quote`, `artisanpack/card`
     * → `.wp-block-artisanpack-card`) (#201).
     *
     * @since 2.5.0
     *
     * @param  array<string, mixed>  $styles
     *
     * @return array<int, string>
     */
    protected function blockStyleBlocks( array $styles ): array
    {
        $blocks = $styles['blocks'] ?? [];

        if ( ! is_array( $blocks ) ) {
            return [];
        }

        $rules = [];

        foreach ( $blocks as $blockName => $blockStyles ) {
            if ( ! is_string( $blockName ) || '' === $blockName || ! is_array( $blockStyles ) ) {
                continue;
            }

            $selector = $this->blockSelector( $blockName );

            if ( null === $selector ) {
                continue;
            }

            $declarations = $this->stylesRootDeclarations( $blockStyles );

            if ( [] === $declarations ) {
                continue;
            }

            $rules[] = $selector . ' {' . "\n" . $this->formatDeclarations( $declarations ) . "\n" . '}';
        }

        return $rules;
    }

    /**
     * Map a `{namespace}/{name}` block identifier to its CSS selector.
     * Returns `null` for malformed identifiers so the caller can skip.
     *
     * @since 2.5.0
     */
    protected function blockSelector( string $blockName ): ?string
    {
        if ( ! str_contains( $blockName, '/' ) ) {
            return null;
        }

        [$namespace, $name] = explode( '/', $blockName, 2 );

        $namespace = trim( $namespace );
        $name      = trim( $name );

        if ( '' === $namespace || '' === $name ) {
            return null;
        }

        if ( 'core' === $namespace ) {
            return '.wp-block-' . $name;
        }

        return '.wp-block-' . $namespace . '-' . $name;
    }

    /**
     * Walk a preset list (`palette`, `fontSizes`, …) and emit its
     * `--wp--preset--{prefix}--{slug}: {value};` form.
     *
     * @since 2.0.0
     *
     * @param  array<int, mixed>  $items
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function presetCustomProperties( array $items, string $prefix, string $valueKey ): array
    {
        $declarations = [];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $slug  = $item['slug'] ?? null;
            $value = $item[ $valueKey ] ?? null;

            if ( ! is_string( $slug ) || '' === $slug || ! is_scalar( $value ) ) {
                continue;
            }

            $declarations[] = ['--wp--preset--' . $prefix . '--' . $this->kebab( $slug ), (string) $value];
        }

        return $declarations;
    }

    /**
     * @since 2.0.0
     *
     * @param  array<int, array{0: string, 1: string}>  $declarations
     */
    protected function formatDeclarations( array $declarations ): string
    {
        $lines = [];

        foreach ( $declarations as [$property, $value] ) {
            $lines[] = "\t" . $property . ': ' . $value . ';';
        }

        return implode( "\n", $lines );
    }

    /**
     * @since 2.0.0
     */
    protected function kebab( string $value ): string
    {
        $value = preg_replace( '/([a-z\d])([A-Z])/', '$1-$2', $value ) ?? $value;
        $value = strtolower( $value );

        return str_replace( '_', '-', $value );
    }

    /**
     * @since 2.0.0
     *
     * @param  array<int|string, mixed>  $value
     */
    protected function isAssoc( array $value ): bool
    {
        if ( [] === $value ) {
            return false;
        }

        return array_keys( $value ) !== range( 0, count( $value) - 1);
    }
}
