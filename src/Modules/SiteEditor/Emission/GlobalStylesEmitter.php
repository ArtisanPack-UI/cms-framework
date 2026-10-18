<?php

/**
 * Global Styles CSS Emitter
 *
 * Translates the resolved global-styles tree into CSS — `@font-face` rules
 * for `settings.typography.fontFamilies[].fontFace`, custom properties
 * for `settings.color.palette`, `settings.typography.fontSizes`,
 * `settings.typography.fontFamilies`, `settings.spacing.spacingSizes`,
 * `settings.color.gradients`, `settings.shadow.presets`, and
 * `settings.custom`, plus inline rules for
 * `styles.color` (including `color.gradient`)/`styles.typography`/`styles.spacing` and per-element styles
 * (`elements.link`, `elements.heading`, `elements.h1`–`h6`,
 * `elements.button`, `elements.caption`, `elements.cite`, and the link
 * and button `:link` / `:any-link` / `:visited` / `:hover` / `:focus` /
 * `:focus-visible` / `:active` states).
 *
 * Block style variations (`styles.blocks.*.variations` and `styles/*.json`
 * partials that declare `blockTypes`) are not emitted here; visual-editor
 * appends them after this output. Hosts without it can append their own
 * CSS through the `ap.cmsFramework.globalStyles.css` filter.
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
use Illuminate\Support\Facades\Route;

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
     *
     * v4: emit per-block-element overrides under
     * `styles.blocks.{name}.elements.{element}` as compound selectors
     * with comma-joined element groups distributed across the block
     * selector (`.wp-block-quote a`, `.wp-block-artisanpack-card h1,
     * .wp-block-artisanpack-card h2, …`) so the child selector stays
     * scoped to the block (#208).
     *
     * v5: scope the `.has-{slug}-color` rule for the `text`, `link` and
     * `border` slugs to real picks of that slug so it no longer overrides
     * a block's own color through the matching block-support marker.
     *
     * v6: restrict preset slugs to `[a-z0-9-]` (#380); emit shadow
     * presets, `@font-face` rules, `.has-{slug}-font-family` bindings,
     * the `h1`–`h6` / `caption` / `cite` elements and element pseudo
     * states (#382).
     *
     * v7: drop `settings.custom` values and `styles.*` declaration values
     * that could break out of the stylesheet, and skip `styles.blocks`
     * keys outside WordPress's block-name charset (#385).
     *
     * v8: track CSS string state when validating values, so interleaved
     * quotes are dropped and parens inside a string are allowed (#387).
     *
     * v9: wrap element, block and block-element rules in `:root :where(…)`
     * so they share WordPress's flat (0,1,0) specificity (#390).
     *
     * v10: reject values with unbalanced or crossed `( )` / `[ ]` outside
     * strings; emit `outline.*`; scope `elements.link` to
     * `a:where(:not(.wp-element-button))`; limit element pseudo states to
     * WordPress's link and button lists.
     *
     * v11: emit `color.gradient` as `background`, before `background-color`
     * (#398).
     *
     * v12: alias preset classes with WordPress's kebab case when it differs
     * (`.has-h2-font-size, .has-h-2-font-size`) (#401).
     *
     * v13: target both `.wp-block-{name}` and `.wp-block-artisanpack-{name}`
     * for `artisanpack/*` blocks, so forks of core blocks match (#405).
     */
    private const SCHEMA_VERSION = 'v13';

    /**
     * Palette slugs whose `.has-{slug}-color` rule shares its name with a
     * block-support marker class. See {@see self::colorUtilityRules()}.
     * Listed in emission order: `text` comes last so its fallback rule
     * wins the cases markup alone can't disambiguate.
     *
     * @since 2.14.1
     */
    private const MARKER_COLOR_SLUGS = ['link', 'border', 'text'];

    /**
     * Attribute selectors matching an inline `color:` declaration in the
     * forms the renderers (`color: #fff; …`) and the block editor
     * (`color:#fff;…`) serialize it, without matching `background-color`
     * or `border-color`. See {@see self::colorUtilitySelector()}.
     *
     * @since 2.14.1
     */
    private const INLINE_COLOR_ATTRIBUTE_SELECTORS = '[style^="color:"], [style*=" color:"], [style*=";color:"]';

    /**
     * Flat theme.json → CSS property map for scalar-leaf declarations.
     * Nested shapes that aren't a single scalar (spacing.padding as an
     * array of sides, border.top.width, border.radius.topLeft, …) are
     * handled by dedicated walkers in {@see stylesRootDeclarations()}.
     *
     * `color.gradient` comes before `color.background`, in WordPress's
     * `PROPERTIES_METADATA` order: the `background` shorthand resets
     * `background-color`, so a node with both keeps its color (#398).
     *
     * @var array<string, string>
     */
    private const SCALAR_DECLARATION_MAP = [
        'color.gradient'            => 'background',
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
        'outline.color'             => 'outline-color',
        'outline.offset'            => 'outline-offset',
        'outline.style'             => 'outline-style',
        'outline.width'             => 'outline-width',
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
     * `styles.elements.{name}` → CSS selector map. Shared between the
     * root-level per-element emission ({@see elementStyleBlocks()}) and
     * the per-block-element emission ({@see blockElementStyleBlocks()})
     * so both agree on which elements are supported and which selectors
     * they map to. Comma-joined values are distributed across the parent
     * block selector at the block-element call site — never concatenated
     * — so the child selector doesn't leak out of block scope (#208).
     *
     * `heading` comes before `h1`–`h6` so a per-level rule overrides the
     * shared heading rule by source order, as in WordPress. `link` skips
     * `.wp-element-button` links, as WordPress's `a:where(:not(…))`
     * selector does, so link styles never restyle buttons. Mirrors
     * `WP_Theme_JSON::ELEMENTS` and visual-editor's
     * `ElementsSupport::ELEMENT_SELECTORS`.
     *
     * @since 2.0.0
     * @since 2.15.0 `link` excludes `.wp-element-button`.
     *
     * @var array<string, string>
     */
    private const ELEMENT_SELECTORS = [
        'link'    => 'a:where(:not(.wp-element-button))',
        'heading' => 'h1, h2, h3, h4, h5, h6',
        'h1'      => 'h1',
        'h2'      => 'h2',
        'h3'      => 'h3',
        'h4'      => 'h4',
        'h5'      => 'h5',
        'h6'      => 'h6',
        'button'  => '.wp-element-button, .wp-block-button__link',
        'caption' => '.wp-element-caption',
        'cite'    => 'cite',
    ];

    /**
     * Pseudo-state sub-nodes recognized under each element node, in the
     * order WordPress emits them so `:focus` overrides `:hover` when an
     * author sets both. Only `link` and `button` take states, as in
     * `WP_Theme_JSON::VALID_ELEMENT_PSEUDO_SELECTORS`. Mirrors
     * visual-editor's `ElementsSupport::PSEUDO_STATES`.
     *
     * @since 2.15.0
     *
     * @var array<string, array<int, string>>
     */
    private const ELEMENT_PSEUDO_STATES = [
        'link'   => [':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active'],
        'button' => [':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active'],
    ];

    /**
     * `fontFace[]` key → `@font-face` descriptor map. `fontFamily` and
     * `src` are handled separately by {@see self::fontFaceRule()}.
     *
     * @since 2.15.0
     *
     * @var array<string, string>
     */
    private const FONT_FACE_DESCRIPTORS = [
        'fontStyle'             => 'font-style',
        'fontWeight'            => 'font-weight',
        'fontDisplay'           => 'font-display',
        'fontStretch'           => 'font-stretch',
        'unicodeRange'          => 'unicode-range',
        'fontFeatureSettings'   => 'font-feature-settings',
        'fontVariationSettings' => 'font-variation-settings',
        'ascentOverride'        => 'ascent-override',
        'descentOverride'       => 'descent-override',
        'lineGapOverride'       => 'line-gap-override',
        'sizeAdjust'            => 'size-adjust',
    ];

    /**
     * Descriptor defaults WordPress's `WP_Font_Face` applies when a
     * `fontFace` entry leaves them out.
     *
     * @since 2.15.0
     *
     * @var array<string, string>
     */
    private const FONT_FACE_DEFAULTS = [
        'fontStyle'   => 'normal',
        'fontWeight'  => '400',
        'fontDisplay' => 'fallback',
    ];

    /**
     * Font file extension → `format()` hint for `@font-face` sources.
     *
     * @since 2.15.0
     *
     * @var array<string, string>
     */
    private const FONT_FORMATS = [
        'woff2' => 'woff2',
        'woff'  => 'woff',
        'ttf'   => 'truetype',
        'otf'   => 'opentype',
        'eot'   => 'embedded-opentype',
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
     * The cached CSS passes through the `ap.cmsFramework.globalStyles.css`
     * filter on every call, with the {@see ResolvedGlobalStyles} it was
     * built from, so a host can append rules (block style variations,
     * for one) without its output being frozen into the cache. A
     * callback that returns a non-string is ignored.
     *
     * @since 2.0.0
     * @since 2.15.0 Applies the `ap.cmsFramework.globalStyles.css` filter.
     */
    public function emit(): string
    {
        $resolved = $this->resolver->resolve();

        if ( null === $resolved ) {
            return '';
        }

        $cacheKey = $this->cacheKey( $resolved );

        $css = Cache::get( $cacheKey );

        if ( ! is_string( $css ) ) {
            $css = $this->buildCss( $resolved );

            Cache::put( $cacheKey, $css, self::CACHE_TTL );
        }

        $filtered = applyFilters( 'ap.cmsFramework.globalStyles.css', $css, $resolved );

        return is_string( $filtered ) ? $filtered : $css;
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
            $this->shadowCustomProperties( $resolved->settings ),
            $this->settingsCustomProperties( $resolved->settings ),
            $this->stylesRootDeclarations( $resolved->styles ),
        );

        $blocks = $this->fontFaceRules( $resolved->settings, $resolved->theme );

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
     * The `.has-{slug}-color` rules for a palette, in emission order.
     *
     * Ordinary slugs get the plain WordPress rule. The `text`, `link` and
     * `border` slugs produce a class that is also a block-support marker
     * (`has-text-color`, `has-link-color`, `has-border-color`), which
     * blocks carry for any custom text, link or border color, so a plain
     * rule would force the slug's color onto blocks that never picked it.
     * Each of those slugs gets two rules instead:
     *
     *  - `.has-{slug}-color-preset` — the exact pick, which visual-editor's
     *    Blade renderer adds when a block picks the slug as its text color.
     *  - a zero-specificity fallback ({@see self::colorUtilitySelector()})
     *    for markup the renderer doesn't build (the editor canvas, saved
     *    Gutenberg HTML), where the class set is the only signal. The
     *    fallbacks come last, `text` after `link` / `border`, so a block
     *    that picked `text` and also has a link or border color keeps its
     *    text color.
     *
     * Mirrors visual-editor's `PresetRegistry::colorUtilityRules()`.
     *
     * @since 2.14.1
     *
     * @param  array<int, string>  $slugs  Kebab-cased palette slugs, in palette order.
     *
     * @return array<int, string>
     */
    protected function colorUtilityRules( array $slugs ): array
    {
        $rules = [];

        foreach ( array_unique( $slugs ) as $slug ) {
            if ( ! in_array( $slug, self::MARKER_COLOR_SLUGS, true ) ) {
                $rules[] = $this->presetClassSelector( $slug, 'color' ) . ' { color: var(--wp--preset--color--' . $slug . ') !important; }';
            }
        }

        foreach ( self::MARKER_COLOR_SLUGS as $slug ) {
            if ( in_array( $slug, $slugs, true ) ) {
                $rules[] = '.has-' . $slug . '-color-preset { color: var(--wp--preset--color--' . $slug . ') !important; }';
            }
        }

        foreach ( self::MARKER_COLOR_SLUGS as $slug ) {
            if ( in_array( $slug, $slugs, true ) ) {
                $rules[] = $this->colorUtilitySelector( $slug ) . ' { color: var(--wp--preset--color--' . $slug . ') !important; }';
            }
        }

        return $rules;
    }

    /**
     * Selector for a palette slug's `.has-{slug}-color` rule.
     *
     * Ordinary slugs keep the plain class. For the marker slugs this is
     * the fallback rule's selector (see {@see self::colorUtilityRules()}):
     * `link` / `border` also require `has-text-color` (every text pick
     * adds it), elements with an inline `color:` (a custom color) are
     * skipped, and `:where()` keeps it at zero specificity so another
     * palette slug's class or the exact `.has-{slug}-color-preset` rule
     * wins. Mirrors visual-editor's `PresetRegistry::colorUtilitySelector()`.
     *
     * @since 2.14.1
     */
    protected function colorUtilitySelector( string $slug ): string
    {
        $selector = '.has-' . $slug . '-color';

        if ( ! in_array( $slug, self::MARKER_COLOR_SLUGS, true ) ) {
            return $selector;
        }

        if ( 'text' !== $slug ) {
            $selector .= '.has-text-color';
        }

        return ':where(' . $selector . ':not(' . self::INLINE_COLOR_ATTRIBUTE_SELECTORS . '))';
    }

    /**
     * Emit `.has-{slug}-color`, `-background-color`, `-border-color`,
     * `-font-size`, `-font-family`, and `-gradient-background` rules that
     * bind each preset slug to its matching `--wp--preset--*` custom
     * property.
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

        $colorSlugs = $this->presetSlugs( $palette, 'color' );

        foreach ( $colorSlugs as $slug ) {
            $var = '--wp--preset--color--' . $slug;

            $rules[] = $this->presetClassSelector( $slug, 'background-color' ) . ' { background-color: var(' . $var . ') !important; }';
            $rules[] = $this->presetClassSelector( $slug, 'border-color' ) . ' { border-color: var(' . $var . ') !important; }';
        }

        array_push( $rules, ...$this->colorUtilityRules( $colorSlugs ) );

        $fontSizes = is_array( $settings['typography']['fontSizes'] ?? null )
            ? $settings['typography']['fontSizes']
            : [];

        foreach ( $this->presetSlugs( $fontSizes, 'size' ) as $slug ) {
            $rules[] = $this->presetClassSelector( $slug, 'font-size' ) . ' { font-size: var(--wp--preset--font-size--' . $slug . ') !important; }';
        }

        $fontFamilies = is_array( $settings['typography']['fontFamilies'] ?? null )
            ? $settings['typography']['fontFamilies']
            : [];

        foreach ( $this->presetSlugs( $fontFamilies, 'fontFamily' ) as $slug ) {
            $rules[] = $this->presetClassSelector( $slug, 'font-family' ) . ' { font-family: var(--wp--preset--font-family--' . $slug . ') !important; }';
        }

        $gradients = is_array( $settings['color']['gradients'] ?? null )
            ? $settings['color']['gradients']
            : [];

        foreach ( $this->presetSlugs( $gradients, 'gradient' ) as $slug ) {
            $rules[] = $this->presetClassSelector( $slug, 'gradient-background' ) . ' { background: var(--wp--preset--gradient--' . $slug . ') !important; }';
        }

        return $rules;
    }

    /**
     * The `.has-{slug}-{suffix}` preset class selector, plus the WordPress
     * kebab-case form when it differs (#401).
     *
     * The editor builds preset class names with WordPress's `kebabCase`,
     * which splits letters from digits (`h2` → `has-h-2-font-size`), as
     * `_wp_to_kebab_case()` does for WordPress's own global styles. The
     * renderers print the slug as-is (`has-h2-font-size`). Emitting both
     * keeps editor-saved and renderer-built markup resolving to the preset.
     * Custom property names keep the slug as-is.
     *
     * @since 2.15.3
     *
     * @param  string  $slug    A normalized `[a-z0-9-]` preset slug.
     * @param  string  $suffix  The class suffix, e.g. `font-size`.
     */
    protected function presetClassSelector( string $slug, string $suffix ): string
    {
        $selector = '.has-' . $slug . '-' . $suffix;
        $kebab    = $this->wordpressKebabSlug( $slug );

        return $kebab === $slug ? $selector : $selector . ', .has-' . $kebab . '-' . $suffix;
    }

    /**
     * A normalized preset slug in WordPress's kebab case: hyphens between
     * letters and digits (`h2` → `h-2`, `gray900` → `gray-900`). Slugs are
     * already lowercase `[a-z0-9-]`, so letter–digit boundaries are the only
     * splits left to make.
     *
     * @since 2.15.3
     */
    protected function wordpressKebabSlug( string $slug ): string
    {
        return (string) preg_replace( ['/([a-z])(?=[0-9])/', '/([0-9])(?=[a-z])/'], '$1-', $slug );
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
            $slug = $this->presetEntrySlug( $item, $valueKey );

            if ( null !== $slug ) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * The normalized slug of one preset entry, or null when the entry is
     * malformed: not an array, no string slug, a `$valueKey` value that
     * isn't scalar or fails {@see self::isSafeCssValue()}, or a slug with
     * no letters or digits left after {@see self::presetSlug()}. The
     * declaration and the `.has-*` binding both go through here, so a
     * skipped entry never leaves a class pointing at an undeclared var.
     *
     * @since 2.15.0
     */
    protected function presetEntrySlug( mixed $item, string $valueKey ): ?string
    {
        if ( ! is_array( $item ) ) {
            return null;
        }

        $slug  = $item['slug'] ?? null;
        $value = $item[ $valueKey ] ?? null;

        if ( ! is_string( $slug ) || ! is_scalar( $value ) || ! $this->isSafeCssValue( (string) $value ) ) {
            return null;
        }

        $slug = $this->presetSlug( $slug );

        return '' === trim( $slug, '-' ) ? null : $slug;
    }

    /**
     * Normalize a preset slug (or `var:preset|…` segment) for use in a
     * class name or custom-property name: the {@see self::kebab()}
     * camelCase split and lowercase, then every character outside
     * `[a-z0-9-]` becomes `-`, so a theme.json or saved global-styles
     * slug can't close the selector or rule it's written into (#380).
     * Slugs already in `[a-zA-Z0-9_-]` normalize exactly as before.
     * Mirrors visual-editor's `PresetRegistry::presetSlug()`.
     *
     * @since 2.15.0
     */
    protected function presetSlug( string $value ): string
    {
        return (string) preg_replace( '/[^a-z0-9\-]/', '-', $this->kebab( trim( $value ) ) );
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
     * Translate `settings.shadow.presets[].(slug, shadow)` into
     * `--wp--preset--shadow--{slug}: {shadow};` declarations so a picked
     * shadow preset (`var:preset|shadow|{slug}`) resolves (#382).
     *
     * @since 2.15.0
     *
     * @param  array<string, mixed>  $settings
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function shadowCustomProperties( array $settings ): array
    {
        $presets = $settings['shadow']['presets'] ?? [];

        if ( ! is_array( $presets ) ) {
            return [];
        }

        return $this->presetCustomProperties( $presets, 'shadow', 'shadow' );
    }

    /**
     * Emit an `@font-face` rule for every entry of
     * `settings.typography.fontFamilies[].fontFace`, so fonts a theme
     * bundles the WordPress way load without hand-written CSS (#382).
     *
     * @since 2.15.0
     *
     * @param  array<string, mixed>  $settings
     * @param  string                $theme     Active theme slug, for `file:./` sources.
     *
     * @return array<int, string>
     */
    protected function fontFaceRules( array $settings, string $theme ): array
    {
        $families = $settings['typography']['fontFamilies'] ?? null;

        if ( ! is_array( $families ) ) {
            return [];
        }

        $rules = [];

        foreach ( $families as $family ) {
            if ( ! is_array( $family ) || ! is_array( $family['fontFace'] ?? null ) ) {
                continue;
            }

            foreach ( $family['fontFace'] as $face ) {
                $rule = is_array( $face ) ? $this->fontFaceRule( $face, $theme ) : null;

                if ( null !== $rule ) {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * Build one `@font-face` rule, or null when the face has no usable
     * family name or source. Missing `fontStyle` / `fontWeight` /
     * `fontDisplay` fall back to `WP_Font_Face`'s defaults, and any
     * descriptor whose value could break out of the rule is dropped.
     *
     * @since 2.15.0
     *
     * @param  array<string, mixed>  $face
     */
    protected function fontFaceRule( array $face, string $theme ): ?string
    {
        $family = is_string( $face['fontFamily'] ?? null )
            ? trim( trim( $face['fontFamily'] ), '"\'' )
            : '';

        if ( '' === $family || ! $this->isSafeCssValue( $family ) || str_contains( $family, '"' ) ) {
            return null;
        }

        $sources = [];

        foreach ( (array) ( $face['src'] ?? [] ) as $src ) {
            $source = is_string( $src ) ? $this->fontFaceSource( $src, $theme ) : null;

            if ( null !== $source ) {
                $sources[] = $source;
            }
        }

        if ( [] === $sources ) {
            return null;
        }

        $declarations = [
            ['font-family', '"' . $family . '"'],
        ];

        foreach ( self::FONT_FACE_DESCRIPTORS as $key => $descriptor ) {
            $value = $face[ $key ] ?? self::FONT_FACE_DEFAULTS[ $key ] ?? null;

            if ( ! is_scalar( $value ) || is_bool( $value ) ) {
                continue;
            }

            $value = trim( (string) $value );

            if ( '' === $value || ! $this->isSafeCssValue( $value ) ) {
                continue;
            }

            $declarations[] = [$descriptor, $value];
        }

        $declarations[] = ['src', implode( ', ', $sources )];

        return '@font-face {' . "\n" . $this->formatDeclarations( $declarations ) . "\n" . '}';
    }

    /**
     * Turn one `fontFace[].src` entry into a `url("…") format("…")`
     * source, or null when it can't be served safely.
     *
     * `file:./assets/…` sources resolve through the `themes.assets`
     * route, which serves the theme's `assets/` directory; other
     * `file:` paths, empty asset paths, `..` segments and `%` (so an
     * encoded `%2e%2e` segment can't climb out either) are skipped.
     * Absolute `http(s)` URLs and root-relative paths pass through. The
     * theme-asset URL keeps only the path (including any subdirectory
     * base path) so the cached CSS doesn't pin the host it was first
     * built on.
     *
     * @since 2.15.0
     */
    protected function fontFaceSource( string $src, string $theme ): ?string
    {
        $src = trim( $src );

        if ( str_starts_with( $src, 'file:' ) ) {
            $path = (string) preg_replace( '#^(?:\./)+#', '', ltrim( substr( $src, 5 ) ) );

            $assetPath = ltrim( substr( $path, 7 ), '/' );

            if ( ! str_starts_with( $path, 'assets/' ) || '' === $assetPath || str_contains( $path, '%' ) || in_array( '..', explode( '/', $path ), true ) || ! Route::has( 'themes.assets' ) ) {
                return null;
            }

            // Keep only the path of the absolute URL: a relative `route()`
            // strips the app's root URL, which drops the base path of an
            // app served from a subdirectory.
            $url = (string) parse_url( route( 'themes.assets', ['slug' => $theme, 'path' => $assetPath] ), PHP_URL_PATH );
        } elseif ( 1 === preg_match( '#^(?:https?://|/(?!/))#i', $src ) ) {
            $url = $src;
        } else {
            return null;
        }

        if ( 1 === preg_match( '/["\'\\\\\s<>(){};]/', $url ) ) {
            return null;
        }

        $source = 'url("' . $url . '")';

        $extension = strtolower( pathinfo( (string) parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

        if ( isset( self::FONT_FORMATS[ $extension ] ) ) {
            $source .= ' format("' . self::FONT_FORMATS[ $extension ] . '")';
        }

        return $source;
    }

    /**
     * Whether a theme-supplied value can be written into the stylesheet
     * as-is: nothing that closes the declaration, rule or `<style>` tag
     * (`; { } < >`), opens or closes a comment, smuggles a CSS escape,
     * breaks the line, leaves a string open, or leaves a parenthesis or
     * bracket outside a string unbalanced or crossed. An open `(` or `[`
     * starts a CSS block that swallows every rule after it, so `x [`
     * would wipe out the rest of the stylesheet.
     * Mirrors visual-editor's `ThemeJsonTokensCompiler::isPrintablePresetValue()`.
     *
     * @since 2.15.0
     */
    protected function isSafeCssValue( string $value ): bool
    {
        if ( 1 === preg_match( '#[;{}<>\\\\\x00-\x1F\x7F]|/\*|\*/#', $value ) ) {
            return false;
        }

        // Track string state the way a CSS tokenizer does: only the quote
        // that opened a string closes it, and quotes, parens or brackets
        // inside a string are literal (#387). Backslashes are rejected
        // above, so escapes can't occur. Open blocks are a stack of the
        // closers they expect, so `([)]` is rejected as crossed.
        $quote   = null;
        $closers = [];
        $length  = strlen( $value );

        for ( $i = 0; $i < $length; $i++ ) {
            $char = $value[ $i ];

            if ( null !== $quote ) {
                if ( $quote === $char ) {
                    $quote = null;
                }
            } elseif ( '"' === $char || "'" === $char ) {
                $quote = $char;
            } elseif ( '(' === $char && $this->opensUnquotedUrl( $value, $i ) ) {
                // An unquoted `url(` is a single url token that ends at the
                // first `)`. A quote or `(` inside it makes a bad-url token,
                // which a tokenizer reads differently from this scan.
                $end = strpos( $value, ')', $i );

                if ( false === $end || false !== strpbrk( substr( $value, $i + 1, $end - $i - 1 ), "\"'(" ) ) {
                    return false;
                }

                $i = $end;
            } elseif ( '(' === $char ) {
                $closers[] = ')';
            } elseif ( '[' === $char ) {
                $closers[] = ']';
            } elseif ( ( ')' === $char || ']' === $char ) && array_pop( $closers ) !== $char ) {
                return false;
            }
        }

        return null === $quote && [] === $closers;
    }

    /**
     * Whether the `(` at `$offset` opens an unquoted url token: it follows
     * the whole identifier `url` (any case), and its first non-whitespace
     * character isn't a quote.
     *
     * @since 2.15.0
     */
    protected function opensUnquotedUrl( string $value, int $offset ): bool
    {
        if ( $offset < 3 ) {
            return false;
        }

        return 1 === preg_match( '/(?<![\w\x80-\xFF-])url\(\s*+(?![\'"])/Ai', $value, $match, 0, $offset - 3 );
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
     * Values that fail {@see self::isSafeCssValue()} are skipped (#385).
     *
     * @since 2.0.0
     * @since 2.15.0 Skips unsafe values.
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, array{0: string, 1: string}>  $declarations
     */
    protected function flattenCustom( array $node, string $prefix, array &$declarations ): void
    {
        foreach ( $node as $key => $value ) {
            $segment = '--' . $this->presetSlug( (string) $key );

            if ( is_array( $value ) && $this->isAssoc( $value ) ) {
                $this->flattenCustom( $value, $prefix . $segment, $declarations );

                continue;
            }

            if ( is_scalar( $value ) && $this->isSafeCssValue( (string) $value ) ) {
                $declarations[] = [$prefix . $segment, (string) $value];
            }
        }
    }

    /**
     * `styles.color.background`, `styles.color.text`, `styles.typography.fontSize`,
     * etc. — top-level styles get applied to `:root` so they cascade. Per-block /
     * per-element styles get their own selectors below. Every value goes
     * through {@see self::declarationValue()}, so one that could break out
     * of the rule is skipped (#385).
     *
     * @since 2.0.0
     * @since 2.15.0 Skips unsafe values.
     * @since 2.15.2 Emits `color.gradient` as `background` (#398).
     *
     * @param  array<string, mixed>  $styles
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function stylesRootDeclarations( array $styles ): array
    {
        $declarations = [];

        foreach ( self::SCALAR_DECLARATION_MAP as $path => $property ) {
            $value = $this->declarationValue( $this->pluck( $styles, $path ) );

            if ( null === $value ) {
                continue;
            }

            $declarations[] = [$property, $value];
        }

        // border.radius.{corner} — WP theme.json v3 allows per-corner
        // radii alongside (or instead of) the shorthand handled above.
        $radius = $styles['border']['radius'] ?? null;

        if ( is_array( $radius ) ) {
            foreach ( self::BORDER_RADIUS_CORNERS as $corner => $property ) {
                $value = $this->declarationValue( $radius[ $corner ] ?? null );

                if ( null === $value ) {
                    continue;
                }

                $declarations[] = [$property, $value];
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
                    $value = $this->declarationValue( $sideDefinition[ $facet ] ?? null );

                    if ( null === $value ) {
                        continue;
                    }

                    $declarations[] = [$prefix . '-' . $facet, $value];
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
                $value = $this->declarationValue( $definition[ $side ] ?? null );

                if ( null === $value ) {
                    continue;
                }

                $declarations[] = [$spacingKey . '-' . $suffix, $value];
            }
        }

        return $declarations;
    }

    /**
     * The CSS value for one `styles.*` leaf: the
     * {@see self::translatePresetValue()} form of a scalar, or null when
     * the leaf isn't scalar or the translated value fails
     * {@see self::isSafeCssValue()} (#385). Checking after translation
     * means `var:preset|…` references pass as the `var(...)` they become.
     *
     * @since 2.15.0
     */
    protected function declarationValue( mixed $value ): ?string
    {
        if ( ! is_scalar( $value ) ) {
            return null;
        }

        $value = $this->translatePresetValue( (string) $value );

        return $this->isSafeCssValue( $value ) ? $value : null;
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
     * form both work. Both segments go through {@see self::presetSlug()}
     * so `var:preset|color|brandPrimary` references the
     * `--wp--preset--color--brand-primary` property the palette declares.
     *
     * @since 2.5.0
     * @since 2.15.0 Normalizes the category and slug segments.
     */
    protected function translatePresetValue( string $value ): string
    {
        // Trailing `(?!\|)` guard so `var:preset|color|primary|garbage`
        // doesn't half-translate into `var(--wp--preset--color--primary)|garbage`
        // — malformed refs pass through unchanged instead of emitting a
        // subtly-broken value.
        return (string) preg_replace_callback(
            '/var:preset\|([A-Za-z0-9_-]+)\|([A-Za-z0-9_-]+)(?![A-Za-z0-9_|-])/',
            fn ( array $matches ): string => 'var(--wp--preset--' . $this->presetSlug( $matches[1] ) . '--' . $this->presetSlug( $matches[2] ) . ')',
            $value,
        );
    }

    /**
     * Per-element rule blocks for every `styles.elements.{name}` in
     * {@see ELEMENT_SELECTORS}. Each renders as its own selector, wrapped
     * in `:root :where(…)` (#390).
     *
     * @since 2.0.0
     * @since 2.15.0 Adds `h1`–`h6`, `caption`, `cite` and pseudo states;
     *               wraps rules in `:root :where(…)`.
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

        $blocks = [];

        foreach ( self::ELEMENT_SELECTORS as $element => $selector ) {
            if ( ! isset( $elements[ $element ] ) || ! is_array( $elements[ $element ] ) ) {
                continue;
            }

            array_push( $blocks, ...$this->elementNodeRules( $element, $selector, $elements[ $element ] ) );
        }

        return $blocks;
    }

    /**
     * Rules for one element node: the base rule, then one rule per
     * pseudo-state sub-node the element supports, in
     * {@see ELEMENT_PSEUDO_STATES} order. Elements other than `link` and
     * `button` take no states. The pseudo is appended to every part of a
     * comma-joined selector
     * (`.wp-element-button:hover, .wp-block-button__link:hover`).
     * Nodes with no declarations emit nothing.
     *
     * @since 2.15.0
     *
     * @param  string                $element   The `styles.elements` key.
     * @param  array<string, mixed>  $node
     *
     * @return array<int, string>
     */
    protected function elementNodeRules( string $element, string $selector, array $node ): array
    {
        $rules = [];

        $declarations = $this->stylesRootDeclarations( $node );

        if ( [] !== $declarations ) {
            $rules[] = $this->flatSpecificitySelector( $selector ) . ' {' . "\n" . $this->formatDeclarations( $declarations ) . "\n" . '}';
        }

        foreach ( self::ELEMENT_PSEUDO_STATES[ $element ] ?? [] as $pseudo ) {
            if ( ! is_array( $node[ $pseudo ] ?? null ) ) {
                continue;
            }

            $pseudoDeclarations = $this->stylesRootDeclarations( $node[ $pseudo ] );

            if ( [] === $pseudoDeclarations ) {
                continue;
            }

            $pseudoSelector = implode( ', ', array_map(
                static fn ( string $part ): string => trim( $part ) . $pseudo,
                explode( ',', $selector ),
            ) );

            $rules[] = $this->flatSpecificitySelector( $pseudoSelector ) . ' {' . "\n" . $this->formatDeclarations( $pseudoDeclarations ) . "\n" . '}';
        }

        return $rules;
    }

    /**
     * Wrap a selector list in `:root :where(…)`, as WordPress's global
     * styles do for element, block and block-element rules: every one
     * lands at a flat (0,1,0) specificity, so source order decides
     * between them (a block's link rule overrides the root `a:hover`
     * one, and the block style variation rules visual-editor appends
     * after this output, such as
     * `:root :where(.wp-block-group.is-style-card h1)`, override the
     * block's own), while `.has-*` preset classes (`!important`) and
     * inline styles still win (#390).
     * Mirrors visual-editor's `ThemeJsonTokensCompiler::whereRule()`.
     *
     * @since 2.15.0
     */
    protected function flatSpecificitySelector( string $selector ): string
    {
        return ':root :where(' . $selector . ')';
    }

    /**
     * Per-block rule blocks: `styles.blocks.{namespace/name}`. Each
     * entry renders into the selector list from {@see blockSelector()}
     * (`core/quote` → `.wp-block-quote`, `artisanpack/card`
     * → `.wp-block-card, .wp-block-artisanpack-card`) (#201, #405),
     * wrapped in `:root :where(…)` (#390).
     *
     * @since 2.5.0
     * @since 2.15.0 Wraps rules in `:root :where(…)`.
     * @since 2.15.3 `artisanpack/*` blocks target both class forms.
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

            if ( [] !== $declarations ) {
                $rules[] = $this->flatSpecificitySelector( $selector ) . ' {' . "\n" . $this->formatDeclarations( $declarations ) . "\n" . '}';
            }

            foreach ( $this->blockElementStyleBlocks( $selector, $blockStyles ) as $rule ) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Per-block-element rule blocks:
     * `styles.blocks.{name}.elements.{element}` composes the parent
     * block selector with each supported element selector from
     * {@see ELEMENT_SELECTORS}. Comma-joined element selectors are
     * distributed across the block selector so the child selector
     * doesn't leak out of block scope:
     *
     *   `.wp-block-quote h1, .wp-block-quote h2, …`
     *
     * — not the naive concatenation `.wp-block-quote h1, h2, …`,
     * which would let `h2` through `h6` apply globally (#208). Each
     * rule is wrapped in `:root :where(…)` (#390).
     *
     * @since 2.5.0
     * @since 2.15.0 Wraps rules in `:root :where(…)`.
     *
     * @param  array<string, mixed>  $blockStyles
     *
     * @return array<int, string>
     */
    protected function blockElementStyleBlocks( string $blockSelector, array $blockStyles ): array
    {
        $elements = $blockStyles['elements'] ?? null;

        if ( ! is_array( $elements ) ) {
            return [];
        }

        $rules = [];

        foreach ( self::ELEMENT_SELECTORS as $element => $elementSelector ) {
            if ( ! isset( $elements[ $element ] ) || ! is_array( $elements[ $element ] ) ) {
                continue;
            }

            $composed = $this->composeBlockElementSelector( $blockSelector, $elementSelector );

            array_push( $rules, ...$this->elementNodeRules( $element, $composed, $elements[ $element ] ) );
        }

        return $rules;
    }

    /**
     * Distribute the parent block selector across each comma-separated
     * child selector token so the emitted rule stays scoped to the block
     * even when the element selector is a group (`h1, h2, …`) (#208).
     * A block selector list (`.wp-block-x, .wp-block-artisanpack-x`) is
     * distributed too, so every element token is scoped to every block
     * class (`.wp-block-x h2, .wp-block-artisanpack-x h2`) (#405).
     *
     * @since 2.5.0
     * @since 2.15.3 Distributes a comma-separated block selector list.
     */
    protected function composeBlockElementSelector( string $blockSelector, string $elementSelector ): string
    {
        $parts          = array_map( 'trim', explode( ',', $elementSelector ) );
        $blockSelectors = array_filter(
            array_map( 'trim', explode( ',', $blockSelector ) ),
            static fn ( string $selector ): bool => '' !== $selector,
        );

        $composed = [];

        foreach ( $parts as $part ) {
            if ( '' === $part ) {
                continue;
            }

            foreach ( $blockSelectors as $selector ) {
                $composed[] = $selector . ' ' . $part;
            }
        }

        return implode( ', ', $composed );
    }

    /**
     * Map a `{namespace}/{name}` block identifier to the CSS selector
     * list for the classes it renders on the front end. `core/x` renders
     * `.wp-block-x`. A first-party `artisanpack/x` block can be a fork of
     * `core/x` (rendering `.wp-block-x`) or its own block (rendering
     * `.wp-block-artisanpack-x`), so both are targeted (#405). Any other
     * namespace renders `.wp-block-{namespace}-{name}`. Mirrors
     * visual-editor's `BlockStyleCssCompiler::blockSelectors()`.
     *
     * Returns `null` for malformed identifiers so the caller can skip:
     * both parts must use WordPress's block-name charset (`[a-z0-9-]`),
     * so a key can't inject its own selector or rule (#385).
     *
     * @since 2.5.0
     * @since 2.15.0 Restricts both parts to `[a-z0-9-]`.
     * @since 2.15.3 `artisanpack/x` returns `.wp-block-x, .wp-block-artisanpack-x`.
     */
    protected function blockSelector( string $blockName ): ?string
    {
        // A valid WP block name is exactly `{namespace}/{name}` — one
        // slash. Extra slashes (e.g. `ns/foo/bar`) would explode into a
        // `.wp-block-ns-foo/bar` selector that silently breaks the whole
        // stylesheet block, so reject them here.
        if ( 1 !== substr_count( $blockName, '/' ) ) {
            return null;
        }

        [$namespace, $name] = explode( '/', $blockName, 2 );

        $namespace = trim( $namespace );
        $name      = trim( $name );

        if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $namespace ) || 1 !== preg_match( '/^[a-z0-9-]+$/', $name ) ) {
            return null;
        }

        if ( 'core' === $namespace ) {
            return '.wp-block-' . $name;
        }

        if ( 'artisanpack' === $namespace ) {
            return '.wp-block-' . $name . ', .wp-block-artisanpack-' . $name;
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
            $slug = $this->presetEntrySlug( $item, $valueKey );

            if ( null === $slug ) {
                continue;
            }

            $declarations[] = ['--wp--preset--' . $prefix . '--' . $slug, (string) $item[ $valueKey ]];
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

        return array_keys( $value ) !== range( 0, count( $value ) - 1 );
    }
}
