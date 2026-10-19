<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission\GlobalStylesEmitter;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedGlobalStyles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

beforeEach( function (): void {
    Cache::flush();

    $this->resolver = mock( GlobalStylesResolver::class );
    $this->emitter  = new GlobalStylesEmitter( $this->resolver );
} );

function makeResolved( array $settings = [], array $styles = [] ): ResolvedGlobalStyles
{
    return new ResolvedGlobalStyles(
        theme                : 'test-theme',
        settings             : $settings,
        styles               : $styles,
        variation            : null,
        hasUserCustomization : false,
        model                : null,
    );
}

describe( 'GlobalStylesEmitter::emit()', function (): void {
    it( 'returns an empty string when no theme is active', function (): void {
        $this->resolver->shouldReceive( 'resolve' )->andReturn( null );

        expect( $this->emitter->emit() )->toBe( '' );
    } );

    it( 'emits palette presets as `--wp--preset--color--{slug}` custom properties', function (): void {
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette' => [
                        ['slug' => 'primary', 'color' => '#000000'],
                        ['slug' => 'accent',  'color' => '#ff0000'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--preset--color--primary: #000000;' )
            ->and( $css )->toContain( '--wp--preset--color--accent: #ff0000;' );
    } );

    it( 'emits font sizes and font families', function (): void {
        $resolved = makeResolved(
            settings: [
                'typography' => [
                    'fontSizes' => [
                        ['slug' => 'small',  'size' => '12px'],
                        ['slug' => 'medium', 'size' => '16px'],
                    ],
                    'fontFamilies' => [
                        ['slug' => 'body', 'fontFamily' => 'Inter, sans-serif'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--preset--font-size--small: 12px;' )
            ->and( $css )->toContain( '--wp--preset--font-size--medium: 16px;' )
            ->and( $css )->toContain( '--wp--preset--font-family--body: Inter, sans-serif;' );
    } );

    it( 'emits spacing sizes', function (): void {
        $resolved = makeResolved(
            settings: [
                'spacing' => [
                    'spacingSizes' => [
                        ['slug' => '20', 'size' => '0.44rem'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--preset--spacing--20: 0.44rem;' );
    } );

    it( 'emits styles.color and styles.typography on :root', function (): void {
        $resolved = makeResolved(
            styles: [
                'color'      => ['background' => '#ffffff', 'text' => '#222222'],
                'typography' => ['fontSize' => '16px', 'fontFamily' => 'Inter'],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ':root {' )
            ->and( $css )->toContain( 'background-color: #ffffff;' )
            ->and( $css )->toContain( 'color: #222222;' )
            ->and( $css )->toContain( 'font-size: 16px;' )
            ->and( $css )->toContain( 'font-family: Inter;' );
    } );

    it( 'emits per-element rules for link / heading / button', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'link'    => ['color' => ['text' => '#0000ff']],
                    'heading' => ['color' => ['text' => '#111111']],
                    'button'  => ['color' => ['background' => '#ff0000', 'text' => '#ffffff']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ':root :where(a:where(:not(.wp-element-button))) {' )
            ->and( $css )->toContain( 'color: #0000ff;' )
            ->and( $css )->toContain( ':root :where(h1, h2, h3, h4, h5, h6) {' )
            ->and( $css )->toContain( ':root :where(.wp-element-button, .wp-block-button__link) {' )
            ->and( $css )->toContain( 'background-color: #ff0000;' );
    } );

    it( 'flattens settings.custom into nested kebab-cased custom properties', function (): void {
        $resolved = makeResolved(
            settings: [
                'custom' => [
                    'spacing'  => ['gutter' => '24px'],
                    'fontSize' => ['tiny' => '10px'],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--custom--spacing--gutter: 24px;' )
            ->and( $css )->toContain( '--wp--custom--font-size--tiny: 10px;' );
    } );

    it( 'emits `.has-{slug}-color` / `-background-color` / `-border-color` rules for each palette preset (Keystone #53)', function (): void {
        // Without these class bindings the editor picker sets
        // `textColor: "accent"` (which renders as `has-accent-color`)
        // but neither the canvas nor the front-end applies the
        // color — the var is declared on `:root` but nothing binds
        // the class to it.
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette' => [
                        ['slug' => 'primary', 'color' => '#0f172a'],
                        ['slug' => 'accent-soft', 'color' => '#dbeafe'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( '.has-primary-color { color: var(--wp--preset--color--primary) !important; }' )
            ->and( $css )->toContain( '.has-primary-background-color { background-color: var(--wp--preset--color--primary) !important; }' )
            ->and( $css )->toContain( '.has-primary-border-color { border-color: var(--wp--preset--color--primary) !important; }' )
            ->and( $css )->toContain( '.has-accent-soft-color { color: var(--wp--preset--color--accent-soft) !important; }' );
    } );

    it( 'scopes color rules that collide with block-support markers to real picks of that slug', function (): void {
        // `has-text-color` is both the `text` slug's class and the marker
        // every block with a custom text color carries. A plain rule for
        // it overrides `.has-base-color` when emitted later, beats a custom
        // inline color through `!important`, and (for `link` / `border`)
        // recolors the text of blocks that only set a link or border color.
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette' => [
                        ['slug' => 'base', 'color' => '#111827'],
                        ['slug' => 'text', 'color' => '#ffffff'],
                        ['slug' => 'link', 'color' => '#00e5ff'],
                        ['slug' => 'border', 'color' => '#374151'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( '.has-base-color { color: var(--wp--preset--color--base) !important; }' )
            ->toContain( ':where(.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"])) { color: var(--wp--preset--color--text) !important; }' )
            ->toContain( ':where(.has-link-color.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"])) { color: var(--wp--preset--color--link) !important; }' )
            ->toContain( ':where(.has-border-color.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"])) { color: var(--wp--preset--color--border) !important; }' )
            ->toContain( '.has-text-background-color { background-color: var(--wp--preset--color--text) !important; }' )
            ->not->toMatch( '/(^|\s)\.has-(text|link|border)-color \{/' );
    } );

    it( 'emits exact marker-slug pick rules and puts the text fallback after the link and border fallbacks', function (): void {
        // A block that picked `text` and also has a border or link color
        // carries `has-text-color has-border-color` / `has-link-color`, so
        // both fallbacks match at equal (zero) specificity and the later
        // one wins. The Blade renderer adds `has-text-color-preset` for an
        // exact match; for other markup the `text` fallback comes last.
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette' => [
                        ['slug' => 'text', 'color' => '#ffffff'],
                        ['slug' => 'border', 'color' => '#374151'],
                        ['slug' => 'link', 'color' => '#00e5ff'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        $textPick       = strpos( $css, '.has-text-color-preset {' );
        $linkFallback   = strpos( $css, ':where(.has-link-color.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"]))' );
        $borderFallback = strpos( $css, ':where(.has-border-color.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"]))' );
        $textFallback   = strpos( $css, ':where(.has-text-color:not([style^="color:"], [style*=" color:"], [style*=";color:"]))' );

        expect( $css )
            ->toContain( '.has-text-color-preset { color: var(--wp--preset--color--text) !important; }' )
            ->toContain( '.has-border-color-preset { color: var(--wp--preset--color--border) !important; }' )
            ->toContain( '.has-link-color-preset { color: var(--wp--preset--color--link) !important; }' )
            ->and( $textPick )->toBeLessThan( $linkFallback )
            ->and( $linkFallback )->toBeLessThan( $borderFallback )
            ->and( $borderFallback )->toBeLessThan( $textFallback );
    } );

    it( 'emits `.has-{slug}-font-size` rules for each font-size preset (Keystone #53)', function (): void {
        $resolved = makeResolved(
            settings: [
                'typography' => [
                    'fontSizes' => [
                        ['slug' => 'small',  'size' => '12px'],
                        ['slug' => 'medium', 'size' => '16px'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( '.has-small-font-size { font-size: var(--wp--preset--font-size--small) !important; }' )
            ->and( $css )->toContain( '.has-medium-font-size { font-size: var(--wp--preset--font-size--medium) !important; }' );
    } );

    it( 'emits `.has-{slug}-gradient-background` rules for each gradient preset (Keystone #53)', function (): void {
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'gradients' => [
                        ['slug' => 'sunset', 'gradient' => 'linear-gradient(...)'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( '.has-sunset-gradient-background { background: var(--wp--preset--gradient--sunset) !important; }' );
    } );

    it( 'skips preset class bindings for malformed entries missing the value key (CodeRabbit follow-up on #53)', function (): void {
        // A slug alone isn't enough — without a usable `color` / `size`
        // / `gradient` we'd emit a `.has-{slug}-*` rule pointing at an
        // undeclared CSS var. Match `presetCustomProperties()`'s
        // existing malformed-entry filter so both code paths stay in
        // lockstep.
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette' => [
                        ['slug' => 'good', 'color' => '#abcdef'],
                        ['slug' => 'no-color'],
                        ['slug' => 'null-color', 'color' => null],
                        ['slug' => 'array-color', 'color' => ['#fff']],
                    ],
                    'gradients' => [
                        ['slug' => 'good-gradient', 'gradient' => 'linear-gradient(...)'],
                        ['slug' => 'no-gradient'],
                    ],
                ],
                'typography' => [
                    'fontSizes' => [
                        ['slug' => 'good-size', 'size' => '14px'],
                        ['slug' => 'no-size'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        // Valid entries still emit.
        expect( $css )
            ->toContain( '.has-good-color { color: var(--wp--preset--color--good) !important; }' )
            ->and( $css )->toContain( '.has-good-gradient-gradient-background' )
            ->and( $css )->toContain( '.has-good-size-font-size' );

        // Malformed entries skipped — no rules referencing undefined vars.
        expect( $css )
            ->not->toContain( '.has-no-color' )
            ->and( $css )->not->toContain( '.has-null-color' )
            ->and( $css )->not->toContain( '.has-array-color' )
            ->and( $css )->not->toContain( '.has-no-gradient' )
            ->and( $css )->not->toContain( '.has-no-size' );
    } );

    it( 'skips preset class bindings when the palette / font-size / gradient list is empty (Keystone #53)', function (): void {
        $resolved = makeResolved();

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        // No empty-slug rules slipped through.
        expect( $css )->not->toMatch( '/\.has--color\b/' );
        expect( $css )->not->toMatch( '/\.has--font-size\b/' );
        expect( $css )->not->toMatch( '/\.has--gradient-background\b/' );
    } );

    it( 'emits extended border / spacing / typography / shadow declarations on :root (#200)', function (): void {
        $resolved = makeResolved(
            styles: [
                'border' => [
                    'radius' => '5px',
                    'color'  => '#123456',
                    'style'  => 'solid',
                    'width'  => '2px',
                ],
                'spacing' => [
                    'padding' => '1rem',
                    'margin'  => '2rem',
                ],
                'typography' => [
                    'fontWeight'     => '600',
                    'letterSpacing'  => '0.02em',
                    'textTransform'  => 'uppercase',
                    'fontStyle'      => 'italic',
                    'textDecoration' => 'underline',
                ],
                'shadow' => '0 2px 4px rgba(0,0,0,0.1)',
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( 'border-radius: 5px;' )
            ->and( $css )->toContain( 'border-color: #123456;' )
            ->and( $css )->toContain( 'border-style: solid;' )
            ->and( $css )->toContain( 'border-width: 2px;' )
            ->and( $css )->toContain( 'padding: 1rem;' )
            ->and( $css )->toContain( 'margin: 2rem;' )
            ->and( $css )->toContain( 'font-weight: 600;' )
            ->and( $css )->toContain( 'letter-spacing: 0.02em;' )
            ->and( $css )->toContain( 'text-transform: uppercase;' )
            ->and( $css )->toContain( 'font-style: italic;' )
            ->and( $css )->toContain( 'text-decoration: underline;' )
            ->and( $css )->toContain( 'box-shadow: 0 2px 4px rgba(0,0,0,0.1);' );
    } );

    it( 'emits per-side spacing when padding / margin are objects (#200)', function (): void {
        $resolved = makeResolved(
            styles: [
                'spacing' => [
                    'padding' => [
                        'top'    => '1rem',
                        'right'  => '2rem',
                        'bottom' => '3rem',
                        'left'   => '4rem',
                    ],
                    'margin' => [
                        'top'    => '0.5rem',
                        'bottom' => '1.5rem',
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( 'padding-top: 1rem;' )
            ->and( $css )->toContain( 'padding-right: 2rem;' )
            ->and( $css )->toContain( 'padding-bottom: 3rem;' )
            ->and( $css )->toContain( 'padding-left: 4rem;' )
            ->and( $css )->toContain( 'margin-top: 0.5rem;' )
            ->and( $css )->toContain( 'margin-bottom: 1.5rem;' );
    } );

    it( 'emits per-corner border radii and per-side border facets (#200)', function (): void {
        $resolved = makeResolved(
            styles: [
                'border' => [
                    'radius' => [
                        'topLeft'     => '5px',
                        'topRight'    => '10px',
                        'bottomLeft'  => '15px',
                        'bottomRight' => '20px',
                    ],
                    'left' => [
                        'color' => '#accent',
                        'style' => 'solid',
                        'width' => '4px',
                    ],
                    'top' => [
                        'width' => '1px',
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( 'border-top-left-radius: 5px;' )
            ->and( $css )->toContain( 'border-top-right-radius: 10px;' )
            ->and( $css )->toContain( 'border-bottom-left-radius: 15px;' )
            ->and( $css )->toContain( 'border-bottom-right-radius: 20px;' )
            ->and( $css )->toContain( 'border-left-color: #accent;' )
            ->and( $css )->toContain( 'border-left-style: solid;' )
            ->and( $css )->toContain( 'border-left-width: 4px;' )
            ->and( $css )->toContain( 'border-top-width: 1px;' );
    } );

    it( 'applies extended coverage to per-element rules too (#200)', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'button' => [
                        'border' => [
                            'radius' => '5px',
                            'color'  => '#f00',
                            'style'  => 'solid',
                            'width'  => '2px',
                        ],
                        'typography' => [
                            'fontWeight'    => '600',
                            'letterSpacing' => '0.02em',
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( ':root :where(.wp-element-button, .wp-block-button__link) {' )
            ->and( $css )->toContain( 'border-radius: 5px;' )
            ->and( $css )->toContain( 'border-color: #f00;' )
            ->and( $css )->toContain( 'border-style: solid;' )
            ->and( $css )->toContain( 'border-width: 2px;' )
            ->and( $css )->toContain( 'font-weight: 600;' )
            ->and( $css )->toContain( 'letter-spacing: 0.02em;' );
    } );

    it( 'emits per-block rules under styles.blocks.{namespace/name} (#201)', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'artisanpack/quote' => [
                        'border' => [
                            'color' => '#accent',
                            'style' => 'solid',
                            'width' => '0 0 0 4px',
                        ],
                        'spacing' => [
                            'padding' => ['left' => '1.5rem'],
                        ],
                        'typography' => ['fontStyle' => 'italic'],
                    ],
                    'artisanpack/card' => [
                        'color'  => ['background' => '#surface2'],
                        'border' => [
                            'color'  => '#surface2',
                            'style'  => 'solid',
                            'width'  => '3px',
                            'radius' => '12px',
                        ],
                    ],
                    'core/quote' => [
                        'typography' => ['fontStyle' => 'italic'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( ':root :where(.wp-block-quote, .wp-block-artisanpack-quote) {' )
            ->and( $css )->toContain( 'border-color: #accent;' )
            ->and( $css )->toContain( 'padding-left: 1.5rem;' )
            ->and( $css )->toContain( 'font-style: italic;' )
            ->and( $css )->toContain( ':root :where(.wp-block-card, .wp-block-artisanpack-card) {' )
            ->and( $css )->toContain( 'border-radius: 12px;' )
            // core/* namespace drops to bare `.wp-block-{name}` selector.
            ->and( $css )->toContain( ':root :where(.wp-block-quote) {' )
            ->and( $css )->not->toContain( '.wp-block-core-quote' );
    } );

    it( 'skips malformed styles.blocks entries (#201)', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'no-namespace'          => ['color' => ['text' => '#000']],
                    '/missing-namespace'    => ['color' => ['text' => '#000']],
                    'missing-name/'         => ['color' => ['text' => '#000']],
                    'too/many/slashes'      => ['color' => ['text' => '#000']],
                    'artisanpack/empty'     => [],
                    'artisanpack/scalar'    => 'not-an-array',
                    'artisanpack/legit'     => ['color' => ['text' => '#111']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( ':root :where(.wp-block-legit, .wp-block-artisanpack-legit) {' )
            ->and( $css )->not->toContain( '.wp-block-no-namespace' )
            ->and( $css )->not->toContain( '.wp-block-missing-name' )
            ->and( $css )->not->toContain( '.wp-block--missing-namespace' )
            ->and( $css )->not->toContain( '.wp-block-artisanpack-empty' )
            ->and( $css )->not->toContain( '.wp-block-artisanpack-scalar' )
            // Extra slashes in the block name would explode into a
            // `.wp-block-too-many/slashes` selector that breaks the whole
            // stylesheet block. Reject anything that isn't exactly one
            // `namespace/name` slash.
            ->and( $css )->not->toContain( '.wp-block-too-many' )
            ->and( $css )->not->toContain( 'many/slashes' );
    } );

    it( 'leaves malformed `var:preset|a|b|garbage` values unchanged rather than half-translating (#202 hardening)', function (): void {
        // Without the trailing anchor the regex greedily consumed
        // `var:preset|color|primary` out of `var:preset|color|primary|garbage`
        // and emitted `var(--wp--preset--color--primary)|garbage` — a
        // subtly-broken value that still looks translated.
        $resolved = makeResolved(
            styles: [
                'color' => [
                    'background' => 'var:preset|color|primary|garbage',
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( 'background-color: var:preset|color|primary|garbage;' )
            ->and( $css )->not->toContain( 'var(--wp--preset--color--primary)|garbage' );
    } );

    it( 'translates `var:preset|category|slug` values into real `var(...)` refs (#202)', function (): void {
        $resolved = makeResolved(
            styles: [
                'color' => [
                    'background' => 'var:preset|color|base',
                    'text'       => 'var:preset|color|text',
                ],
                'typography' => [
                    'fontFamily' => 'var:preset|font-family|sans',
                    'fontSize'   => 'var:preset|font-size|medium',
                ],
                'elements' => [
                    'button' => [
                        'color' => ['background' => 'var:preset|color|primary'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( 'background-color: var(--wp--preset--color--base);' )
            ->and( $css )->toContain( 'color: var(--wp--preset--color--text);' )
            ->and( $css )->toContain( 'font-family: var(--wp--preset--font-family--sans);' )
            ->and( $css )->toContain( 'font-size: var(--wp--preset--font-size--medium);' )
            ->and( $css )->toContain( 'background-color: var(--wp--preset--color--primary);' )
            // No untranslated pipe form leaks through.
            ->and( $css )->not->toContain( 'var:preset|' );
    } );

    it( 'passes raw CSS `var(...)` values through unchanged (#202 idempotency)', function (): void {
        $resolved = makeResolved(
            styles: [
                'color' => ['background' => 'var(--wp--preset--color--base)'],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( 'background-color: var(--wp--preset--color--base);' );
    } );

    it( 'emits per-block-element overrides under styles.blocks.{name}.elements.link (#208)', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'core/quote' => [
                        'elements' => [
                            'link' => [
                                'color' => ['text' => '#accent'],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( ':root :where(.wp-block-quote a:where(:not(.wp-element-button))) {' )
            ->and( $css )->toContain( 'color: #accent;' );
    } );

    it( 'distributes comma-joined element selectors across the block selector for headings (#208)', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'artisanpack/card' => [
                        'elements' => [
                            'heading' => [
                                'typography' => [
                                    'fontWeight'    => '700',
                                    'letterSpacing' => '-0.01em',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        // Each child selector must be prefixed by the block selector so
        // `h2` through `h6` don't leak out of block scope.
        expect( $css )
            ->toContain( ':root :where(.wp-block-card h1, .wp-block-artisanpack-card h1, .wp-block-card h2, .wp-block-artisanpack-card h2, .wp-block-card h3, .wp-block-artisanpack-card h3, .wp-block-card h4, .wp-block-artisanpack-card h4, .wp-block-card h5, .wp-block-artisanpack-card h5, .wp-block-card h6, .wp-block-artisanpack-card h6) {' )
            ->and( $css )->toContain( 'font-weight: 700;' )
            ->and( $css )->toContain( 'letter-spacing: -0.01em;' )
            // No naive concatenation that would leak `h2…h6` out of scope.
            ->and( $css )->not->toContain( '.wp-block-artisanpack-card h1, h2' );
    } );

    it( 'distributes the button element selector too (#208)', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'artisanpack/card' => [
                        'elements' => [
                            'button' => [
                                'color' => ['background' => '#primary'],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( ':root :where(.wp-block-card .wp-element-button, .wp-block-artisanpack-card .wp-element-button, .wp-block-card .wp-block-button__link, .wp-block-artisanpack-card .wp-block-button__link) {' )
            ->and( $css )->toContain( 'background-color: #primary;' );
    } );

    it( 'translates preset-var values inside per-block-element overrides (#208)', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'core/quote' => [
                        'elements' => [
                            'link' => [
                                'color' => ['text' => 'var:preset|color|accent'],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( ':root :where(.wp-block-quote a:where(:not(.wp-element-button))) {' )
            ->and( $css )->toContain( 'color: var(--wp--preset--color--accent);' )
            ->and( $css )->not->toContain( 'var:preset|' );
    } );

    it( 'skips malformed or empty per-block elements maps cleanly (#208)', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'artisanpack/a' => [
                        'elements' => 'not-an-array',
                        'color'    => ['text' => '#111'],
                    ],
                    'artisanpack/b' => [
                        'elements' => [
                            'link'    => 'not-an-array',
                            'heading' => [],
                            'unknown' => ['color' => ['text' => '#f00']],
                        ],
                    ],
                    'artisanpack/c' => [
                        'elements' => [
                            'link' => ['color' => ['text' => '#legit']],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            // Base block rule still emits alongside the invalid elements map.
            ->toContain( ':root :where(.wp-block-a, .wp-block-artisanpack-a) {' )
            // No block-element rule for the scalar `elements` map on block a.
            ->and( $css )->not->toContain( '.wp-block-artisanpack-a a' )
            ->and( $css )->not->toContain( '.wp-block-artisanpack-a h1' )
            // Malformed / unknown element entries on block b drop cleanly.
            ->and( $css )->not->toContain( '.wp-block-artisanpack-b a' )
            ->and( $css )->not->toContain( '.wp-block-artisanpack-b h1' )
            // Legit entry on block c still emits.
            ->and( $css )->toContain( ':root :where(.wp-block-c a:where(:not(.wp-element-button)), .wp-block-artisanpack-c a:where(:not(.wp-element-button))) {' )
            ->and( $css )->toContain( 'color: #legit;' );
    } );

    it( 'emits element rules for a block that declares only `elements.*` with no base-level styles (#208)', function (): void {
        // Regression guard — a block whose only styles live under
        // `elements.*` (no base color/typography/border/…) must still
        // emit its per-element rules. The base rule is skipped when
        // there are no base declarations, but the element loop keeps
        // running.
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'core/quote' => [
                        'elements' => [
                            'link' => [
                                'color' => ['text' => '#accent'],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( ':root :where(.wp-block-quote a:where(:not(.wp-element-button))) {' )
            ->and( $css )->toContain( 'color: #accent;' )
            // No stray empty `.wp-block-quote { }` base rule when the
            // block has no base-level declarations.
            ->and( $css )->not->toContain( ":root :where(.wp-block-quote) {\n}" );
    } );

    it( 'emits both the base block rule and its per-element overrides together (#208)', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'core/quote' => [
                        'typography' => ['fontStyle' => 'italic'],
                        'elements'   => [
                            'link' => [
                                'color' => ['text' => '#accent'],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )
            ->toContain( ':root :where(.wp-block-quote) {' )
            ->and( $css )->toContain( 'font-style: italic;' )
            ->and( $css )->toContain( ':root :where(.wp-block-quote a:where(:not(.wp-element-button))) {' )
            ->and( $css )->toContain( 'color: #accent;' );
    } );
} );

describe( 'GlobalStylesEmitter cache', function (): void {
    it( 'caches output and reuses it on the next call', function (): void {
        $resolved = makeResolved(
            styles: ['color' => ['background' => '#abcdef']],
        );

        $this->resolver->shouldReceive( 'resolve' )->twice()->andReturn( $resolved );

        $first  = $this->emitter->emit();
        $second = $this->emitter->emit();

        expect( $first )->toBe( $second )
            ->and( $first )->toContain( 'background-color: #abcdef;' );
    } );

    it( 'invalidates the cache when invalidate() is called', function (): void {
        $resolved = makeResolved( styles: ['color' => ['background' => '#aaaaaa']] );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $this->emitter->emit();

        // Cache key carries the schema version so a deploy with a
        // bumped emitter automatically busts every cached entry
        // (Keystone #53). Mirror that here.
        $cacheKey = 'cms.global-styles.css.v13.test-theme.' . $resolved->contentHash();
        expect( Cache::has( $cacheKey ) )->toBeTrue();

        $this->emitter->invalidate();

        expect( Cache::has( $cacheKey ) )->toBeFalse();
    } );
} );

describe( 'GlobalStylesEmitter preset slug hardening (#380)', function (): void {
    it( 'neutralizes CSS metacharacters in preset slugs', function ( string $slug, string $expected ): void {
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette'   => [['slug' => $slug, 'color' => '#000000']],
                    'gradients' => [['slug' => $slug, 'gradient' => 'linear-gradient(#000, #fff)']],
                ],
                'typography' => [
                    'fontSizes'    => [['slug' => $slug, 'size' => '12px']],
                    'fontFamilies' => [['slug' => $slug, 'fontFamily' => 'Inter']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--preset--color--' . $expected . ': #000000;' )
            ->and( $css )->toContain( '.has-' . $expected . '-color {' )
            ->and( $css )->toContain( '.has-' . $expected . '-background-color {' )
            ->and( $css )->toContain( '.has-' . $expected . '-font-size {' )
            ->and( $css )->toContain( '.has-' . $expected . '-font-family {' )
            ->and( $css )->toContain( '.has-' . $expected . '-gradient-background {' )
            ->and( $css )->not->toContain( '</style>' )
            ->and( $css )->not->toContain( 'display:none' )
            ->and( $css )->not->toContain( '"' );
    } )->with( [
        'braces and semicolon' => ['x{}body{display:none}', 'x--body-display-none-'],
        'style tag'            => ['a</style><script>', 'a--style--script-'],
        'quotes'               => ['a"b\'c', 'a-b-c'],
        'whitespace'           => ['big gap', 'big-gap'],
        'surrounding space'    => ['  primary ', 'primary'],
    ] );

    it( 'keeps camelCase and snake_case slugs normalizing as before', function (): void {
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette' => [
                        ['slug' => 'brandPrimary', 'color' => '#111111'],
                        ['slug' => 'big_gap', 'color' => '#222222'],
                        ['slug' => 'h2Large', 'color' => '#333333'],
                        ['slug' => 'accent-2', 'color' => '#444444'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--preset--color--brand-primary: #111111;' )
            ->and( $css )->toContain( '--wp--preset--color--big-gap: #222222;' )
            ->and( $css )->toContain( '--wp--preset--color--h2-large: #333333;' )
            ->and( $css )->toContain( '--wp--preset--color--accent-2: #444444;' );
    } );

    it( 'skips preset entries whose slug has no letters or digits', function (): void {
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette' => [
                        ['slug' => '{}', 'color' => '#111111'],
                        ['slug' => '   ', 'color' => '#222222'],
                        ['slug' => '', 'color' => '#333333'],
                        ['slug' => 'ok', 'color' => '#444444'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( '#111111' )
            ->and( $css )->not->toContain( '#222222' )
            ->and( $css )->not->toContain( '#333333' )
            ->and( $css )->not->toContain( '.has--' )
            ->and( $css )->toContain( '--wp--preset--color--ok: #444444;' );
    } );

    it( 'skips preset entries whose value could break out of the rule', function ( string $value ): void {
        $resolved = makeResolved(
            settings: [
                'color'  => ['palette' => [['slug' => 'bad', 'color' => $value], ['slug' => 'ok', 'color' => '#444444']]],
                'shadow' => ['presets' => [['slug' => 'bad', 'shadow' => $value]]],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( '--wp--preset--color--bad' )
            ->and( $css )->not->toContain( '.has-bad-' )
            ->and( $css )->not->toContain( '--wp--preset--shadow--bad' )
            ->and( $css )->not->toContain( 'display:none' )
            ->and( $css )->toContain( '--wp--preset--color--ok: #444444;' );
    } )->with( [
        'rule break'     => ['red;}body{display:none'],
        'style tag'      => ['red</style><script>'],
        'comment'        => ['red /* display:none'],
        'open paren'     => ['rgb(0, 0, 0 display:none'],
        'unclosed quote' => ['"red display:none'],
    ] );

    it( 'keeps quoted font-family stacks and gradients', function (): void {
        $resolved = makeResolved(
            settings: [
                'color'      => ['gradients' => [['slug' => 'sunset', 'gradient' => 'linear-gradient(135deg, rgb(255, 0, 0) 0%, #fff 100%)']]],
                'typography' => ['fontFamilies' => [['slug' => 'serif', 'fontFamily' => '"Source Serif 4", \'Times New Roman\', serif']]],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--preset--gradient--sunset: linear-gradient(135deg, rgb(255, 0, 0) 0%, #fff 100%);' )
            ->and( $css )->toContain( '--wp--preset--font-family--serif: "Source Serif 4", \'Times New Roman\', serif;' );
    } );

    it( 'normalizes settings.custom keys', function (): void {
        $resolved = makeResolved(
            settings: ['custom' => ['line{Height}' => ['body;x' => '1.5']]],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        expect( $this->emitter->emit() )->toContain( '--wp--custom--line-height---body-x: 1.5;' );
    } );

    it( 'normalizes var:preset references to match the declared property', function (): void {
        $resolved = makeResolved(
            settings: ['color' => ['palette' => [['slug' => 'brandPrimary', 'color' => '#111111']]]],
            styles: ['color' => ['text' => 'var:preset|color|brandPrimary']],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        expect( $this->emitter->emit() )->toContain( 'color: var(--wp--preset--color--brand-primary);' );
    } );
} );

describe( 'GlobalStylesEmitter theme.json parity (#382)', function (): void {
    it( 'emits shadow presets as custom properties', function (): void {
        $resolved = makeResolved(
            settings: [
                'shadow' => [
                    'presets' => [
                        ['slug' => 'natural', 'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)'],
                        ['slug' => 'broken'],
                    ],
                ],
            ],
            styles: ['shadow' => 'var:preset|shadow|natural'],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--preset--shadow--natural: 6px 6px 9px rgba(0, 0, 0, 0.2);' )
            ->and( $css )->toContain( 'box-shadow: var(--wp--preset--shadow--natural);' )
            ->and( $css )->not->toContain( '--wp--preset--shadow--broken' );
    } );

    it( 'emits .has-{slug}-font-family bindings', function (): void {
        $resolved = makeResolved(
            settings: [
                'typography' => [
                    'fontFamilies' => [
                        ['slug' => 'body', 'fontFamily' => 'Inter, sans-serif'],
                        ['slug' => 'monoFace', 'fontFamily' => 'monospace'],
                        ['slug' => 'missing-value'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '.has-body-font-family { font-family: var(--wp--preset--font-family--body) !important; }' )
            ->and( $css )->toContain( '.has-mono-face-font-family { font-family: var(--wp--preset--font-family--mono-face) !important; }' )
            ->and( $css )->not->toContain( '.has-missing-value-font-family' );
    } );

    it( 'emits @font-face rules from fontFamilies[].fontFace', function (): void {
        $resolved = makeResolved(
            settings: [
                'typography' => [
                    'fontFamilies' => [
                        [
                            'slug'       => 'inter',
                            'fontFamily' => 'Inter, sans-serif',
                            'fontFace'   => [
                                [
                                    'fontFamily'   => 'Inter',
                                    'fontWeight'   => '300 900',
                                    'fontStyle'    => 'normal',
                                    'fontStretch'  => 'normal',
                                    'unicodeRange' => 'U+0000-00FF',
                                    'src'          => ['file:./assets/fonts/inter/Inter.woff2'],
                                ],
                                [
                                    'fontFamily' => '"Inter"',
                                    'fontStyle'  => 'italic',
                                    'src'        => 'https://cdn.example.com/fonts/Inter-Italic.ttf',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toStartWith( '@font-face {' )
            ->and( $css )->toContain( "@font-face {\n\tfont-family: \"Inter\";\n\tfont-style: normal;\n\tfont-weight: 300 900;\n\tfont-display: fallback;\n\tfont-stretch: normal;\n\tunicode-range: U+0000-00FF;\n\tsrc: url(\"/themes/test-theme/assets/fonts/inter/Inter.woff2\") format(\"woff2\");\n}" )
            ->and( $css )->toContain( "@font-face {\n\tfont-family: \"Inter\";\n\tfont-style: italic;\n\tfont-weight: 400;\n\tfont-display: fallback;\n\tsrc: url(\"https://cdn.example.com/fonts/Inter-Italic.ttf\") format(\"truetype\");\n}" );
    } );

    it( 'skips @font-face sources and faces that cannot be served safely', function (): void {
        $resolved = makeResolved(
            settings: [
                'typography' => [
                    'fontFamilies' => [
                        [
                            'slug'       => 'body',
                            'fontFamily' => 'Body',
                            'fontFace'   => [
                                ['fontFamily' => 'Outside', 'src' => ['file:./fonts/outside.woff2']],
                                ['fontFamily' => 'Traversal', 'src' => ['file:./assets/../theme.json']],
                                ['fontFamily' => 'Protocol', 'src' => ['//evil.example.com/x.woff2', 'javascript:alert(1)']],
                                ['fontFamily' => 'Injected', 'src' => ['https://example.com/x.woff2");}body{x:y']],
                                ['fontFamily' => 'Bad;Name', 'src' => ['https://example.com/ok.woff2']],
                                ['fontFamily' => 'NoSource'],
                                [
                                    'fontFamily' => 'Mixed',
                                    'fontWeight' => '400;}body{color:red',
                                    'src'        => ['file:./fonts/outside.woff2', 'file:./assets/fonts/mixed.woff'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( substr_count( $css, '@font-face' ) )->toBe( 1 )
            ->and( $css )->toContain( "font-family: \"Mixed\";\n\tfont-style: normal;\n\tfont-display: fallback;\n\tsrc: url(\"/themes/test-theme/assets/fonts/mixed.woff\") format(\"woff\");" )
            ->and( $css )->not->toContain( 'color:red' )
            ->and( $css )->not->toContain( 'evil.example.com' )
            ->and( $css )->not->toContain( 'Bad;Name' );
    } );

    it( 'skips file: sources with percent-encoded segments', function ( string $src ): void {
        $resolved = makeResolved(
            settings: [
                'typography' => [
                    'fontFamilies' => [
                        ['slug' => 'body', 'fontFamily' => 'Body', 'fontFace' => [['fontFamily' => 'Body', 'src' => [$src]]]],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        expect( $this->emitter->emit() )->not->toContain( '@font-face' );
    } )->with( [
        'encoded dot segments' => ['file:./assets/%2e%2e/%2e%2e/logout?a.woff2'],
        'encoded slash'        => ['file:./assets/fonts%2F..%2Fx.woff2'],
    ] );

    it( 'skips file: sources with an empty asset path instead of failing the build', function ( string $src ): void {
        $resolved = makeResolved(
            settings: [
                'typography' => [
                    'fontFamilies' => [
                        ['slug' => 'body', 'fontFamily' => 'Body', 'fontFace' => [['fontFamily' => 'Body', 'src' => [$src]]]],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        expect( $this->emitter->emit() )->not->toContain( '@font-face' );
    } )->with( [
        'bare prefix'   => ['file:./assets/'],
        'only slashes'  => ['file:./assets///'],
    ] );

    it( 'keeps the app base path on theme-asset font URLs', function (): void {
        URL::forceRootUrl( 'https://example.com/cms' );

        $resolved = makeResolved(
            settings: [
                'typography' => [
                    'fontFamilies' => [
                        ['slug' => 'body', 'fontFamily' => 'Body', 'fontFace' => [['fontFamily' => 'Body', 'src' => ['file:./assets/fonts/body.woff2']]]],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        expect( $this->emitter->emit() )->toContain( 'src: url("/cms/themes/test-theme/assets/fonts/body.woff2") format("woff2");' );

        URL::forceRootUrl( null );
    } );

    it( 'emits h1–h6, caption and cite element rules after the shared heading rule', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'h1'      => ['typography' => ['fontSize' => '3rem']],
                    'heading' => ['color' => ['text' => '#111111']],
                    'h6'      => ['typography' => ['fontSize' => '1rem']],
                    'caption' => ['typography' => ['fontSize' => '0.75rem']],
                    'cite'    => ['typography' => ['fontStyle' => 'normal']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(h1) {\n\tfont-size: 3rem;\n}" )
            ->and( $css )->toContain( ":root :where(h6) {\n\tfont-size: 1rem;\n}" )
            ->and( $css )->toContain( "\n:root :where(.wp-element-caption) {\n\tfont-size: 0.75rem;\n}" )
            ->and( $css )->toContain( "\n:root :where(cite) {\n\tfont-style: normal;\n}" )
            ->and( strpos( $css, ':root :where(h1, h2, h3, h4, h5, h6) {' ) )->toBeLessThan( strpos( $css, ":root :where(h1) {\n\tfont-size: 3rem;" ) );
    } );

    it( 'emits element pseudo states in WordPress order', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'link' => [
                        'color'          => ['text' => '#0000ff'],
                        ':active'        => ['color' => ['text' => '#000004']],
                        ':focus'         => ['color' => ['text' => '#000002']],
                        ':hover'         => ['color' => ['text' => '#000001']],
                        ':focus-visible' => ['color' => ['text' => '#000003']],
                        ':visited'       => ['color' => ['text' => '#999999']],
                        ':any-link'      => ['color' => ['text' => '#888888']],
                        ':link'          => ['color' => ['text' => '#777777']],
                        ':focus-within'  => ['color' => ['text' => '#666666']],
                    ],
                    'button' => [
                        ':hover' => ['color' => ['background' => 'var:preset|color|accent']],
                        ':focus' => [],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        $link         = strpos( $css, ":root :where(a:where(:not(.wp-element-button)):link) {\n\tcolor: #777777;\n}" );
        $anyLink      = strpos( $css, ":root :where(a:where(:not(.wp-element-button)):any-link) {\n\tcolor: #888888;\n}" );
        $visited      = strpos( $css, ":root :where(a:where(:not(.wp-element-button)):visited) {\n\tcolor: #999999;\n}" );
        $hover        = strpos( $css, ":root :where(a:where(:not(.wp-element-button)):hover) {\n\tcolor: #000001;\n}" );
        $focus        = strpos( $css, ":root :where(a:where(:not(.wp-element-button)):focus) {\n\tcolor: #000002;\n}" );
        $focusVisible = strpos( $css, ":root :where(a:where(:not(.wp-element-button)):focus-visible) {\n\tcolor: #000003;\n}" );
        $active       = strpos( $css, ":root :where(a:where(:not(.wp-element-button)):active) {\n\tcolor: #000004;\n}" );

        expect( $link )->toBeInt()
            ->and( $anyLink )->toBeGreaterThan( $link )
            ->and( $visited )->toBeGreaterThan( $anyLink )
            ->and( $hover )->toBeGreaterThan( $visited )
            ->and( $focus )->toBeGreaterThan( $hover )
            ->and( $focusVisible )->toBeGreaterThan( $focus )
            ->and( $active )->toBeGreaterThan( $focusVisible )
            ->and( strpos( $css, ":root :where(a:where(:not(.wp-element-button))) {\n\tcolor: #0000ff;\n}" ) )->toBeLessThan( $link )
            ->and( $css )->not->toContain( '#666666' )
            ->and( $css )->toContain( ":root :where(.wp-element-button:hover, .wp-block-button__link:hover) {\n\tbackground-color: var(--wp--preset--color--accent);\n}" )
            ->and( $css )->not->toContain( '.wp-element-button:focus' );
    } );

    it( 'emits pseudo states for per-block elements scoped to the block', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'core/quote' => [
                        'elements' => [
                            'link'    => [':hover' => ['color' => ['text' => '#ff0000']]],
                            'h2'      => ['color' => ['text' => '#00ff00']],
                            'heading' => [':focus' => ['color' => ['text' => '#0000ff']]],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(.wp-block-quote a:where(:not(.wp-element-button)):hover) {\n\tcolor: #ff0000;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-block-quote h2) {\n\tcolor: #00ff00;\n}" )
            ->and( $css )->not->toContain( ':focus' )
            ->and( $css )->not->toContain( '#0000ff' )
            ->and( $css )->not->toContain( ":root :where(.wp-block-quote a:where(:not(.wp-element-button))) {\n" );
    } );
} );

describe( 'GlobalStylesEmitter value and block key hardening (#385)', function (): void {
    dataset( 'break-out values', [
        'rule break'      => ['red;}body{display:none'],
        'style tag'       => ['red</style><script>'],
        'open brace'      => ['red{display:none'],
        'comment open'    => ['red /* display:none'],
        'comment close'   => ['red */ display:none'],
        'backslash'       => ['red\\3b display:none'],
        'unclosed quote'  => ['"red display:none'],
        'single quote'    => ["'red display:none"],
        'open paren'      => ['rgb(0, 0, 0 display:none'],
        'close paren'     => ['red) display:none'],
        'newline'         => ["red\ndisplay:none"],
        'open bracket'    => ['red [ display:none'],
        'close bracket'   => ['red ] display:none'],
        'crossed blocks'  => ['([)] display:none'],
    ] );

    it( 'drops unsafe settings.custom values and keeps safe siblings', function ( string $value ): void {
        $resolved = makeResolved(
            settings: ['custom' => ['bad' => $value, 'nested' => ['bad' => $value, 'ok' => '2rem'], 'ok' => '1.5']],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( '--wp--custom--bad' )
            ->and( $css )->not->toContain( '--wp--custom--nested--bad' )
            ->and( $css )->not->toContain( 'display:none' )
            ->and( $css )->toContain( '--wp--custom--nested--ok: 2rem;' )
            ->and( $css )->toContain( '--wp--custom--ok: 1.5;' );
    } )->with( 'break-out values' );

    it( 'drops unsafe root styles values in every walker', function ( string $value ): void {
        $resolved = makeResolved(
            styles: [
                'color'   => ['text' => $value, 'background' => '#abcdef'],
                'border'  => [
                    'radius' => ['topLeft' => $value, 'topRight' => '4px'],
                    'top'    => ['color' => $value, 'width' => '1px'],
                ],
                'spacing' => [
                    'padding' => ['top' => $value, 'left' => '8px'],
                    'margin'  => $value,
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( 'display:none' )
            ->and( $css )->not->toContain( "\tcolor:" )
            ->and( $css )->not->toContain( 'border-top-left-radius' )
            ->and( $css )->not->toContain( 'border-top-color' )
            ->and( $css )->not->toContain( 'padding-top' )
            ->and( $css )->not->toContain( "\tmargin:" )
            ->and( $css )->toContain( 'background-color: #abcdef;' )
            ->and( $css )->toContain( 'border-top-right-radius: 4px;' )
            ->and( $css )->toContain( 'border-top-width: 1px;' )
            ->and( $css )->toContain( 'padding-left: 8px;' );
    } )->with( 'break-out values' );

    it( 'drops unsafe values under elements and their pseudo states', function ( string $value ): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'link' => [
                        'color'  => ['text' => $value],
                        ':hover' => ['color' => ['text' => $value]],
                    ],
                    'button' => [
                        'color'  => ['text' => $value, 'background' => '#111111'],
                        ':focus' => ['color' => ['text' => $value, 'background' => '#222222']],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( 'display:none' )
            ->and( $css )->not->toContain( ':root :where(a:where(:not(.wp-element-button))) {' )
            ->and( $css )->not->toContain( ':root :where(a:where(:not(.wp-element-button)):hover) {' )
            ->and( $css )->toContain( ":root :where(.wp-element-button, .wp-block-button__link) {\n\tbackground-color: #111111;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-element-button:focus, .wp-block-button__link:focus) {\n\tbackground-color: #222222;\n}" );
    } )->with( 'break-out values' );

    it( 'drops unsafe values under blocks and block elements', function ( string $value ): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'core/quote' => [
                        'color'    => ['text' => $value, 'background' => '#333333'],
                        'elements' => [
                            'link' => [
                                'color'  => ['text' => $value],
                                ':hover' => ['color' => ['text' => $value]],
                            ],
                        ],
                    ],
                    'core/pullquote' => [
                        'typography' => ['fontFamily' => $value],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( 'display:none' )
            ->and( $css )->toContain( ":root :where(.wp-block-quote) {\n\tbackground-color: #333333;\n}" )
            ->and( $css )->not->toContain( '.wp-block-quote a' )
            ->and( $css )->not->toContain( '.wp-block-pullquote' );
    } )->with( 'break-out values' );

    it( 'keeps valid declaration values', function (): void {
        $resolved = makeResolved(
            settings: [
                'color'  => ['palette' => [['slug' => 'primary', 'color' => '#000000']]],
                'custom' => [
                    'fontStack' => '"Source Serif 4", \'Times New Roman\', serif',
                    'gutter'    => 'calc(1rem + 2vw)',
                    'accent'    => 'var(--wp--preset--color--primary)',
                ],
            ],
            styles: [
                'color'      => ['text' => 'var:preset|color|primary', 'background' => 'var(--wp--preset--color--primary)'],
                'typography' => ['fontFamily' => '"Source Serif 4", \'Times New Roman\', serif'],
                'spacing'    => ['padding' => ['top' => 'calc(var(--wp--custom--gutter) * 2)']],
                'shadow'     => 'var:preset|shadow|natural',
                'blocks'     => [
                    'core/quote' => ['border' => ['radius' => 'min(4px, 1vw)']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--custom--font-stack: "Source Serif 4", \'Times New Roman\', serif;' )
            ->and( $css )->toContain( '--wp--custom--gutter: calc(1rem + 2vw);' )
            ->and( $css )->toContain( '--wp--custom--accent: var(--wp--preset--color--primary);' )
            ->and( $css )->toContain( "\tcolor: var(--wp--preset--color--primary);" )
            ->and( $css )->toContain( 'background-color: var(--wp--preset--color--primary);' )
            ->and( $css )->toContain( 'font-family: "Source Serif 4", \'Times New Roman\', serif;' )
            ->and( $css )->toContain( 'padding-top: calc(var(--wp--custom--gutter) * 2);' )
            ->and( $css )->toContain( 'box-shadow: var(--wp--preset--shadow--natural);' )
            ->and( $css )->toContain( ":root :where(.wp-block-quote) {\n\tborder-radius: min(4px, 1vw);\n}" );
    } );

    it( 'skips block keys outside the block-name charset', function ( string $blockName ): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    $blockName           => ['color' => ['text' => '#111111']],
                    'artisanpack/card-2' => ['color' => ['text' => '#222222']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( '#111111' )
            ->and( $css )->not->toContain( 'display:none' )
            ->and( $css )->toContain( ":root :where(.wp-block-card-2, .wp-block-artisanpack-card-2) {\n\tcolor: #222222;\n}" );
    } )->with( [
        'rule injection in name'      => ['core/x{}body{display:none}'],
        'rule injection in namespace' => ['x{}body{display:none}/quote'],
        'html tag'                    => ['core/x<script>'],
        'selector combinator'         => ['core/quote > p'],
        'comma'                       => ['core/quote,body'],
        'uppercase'                   => ['Core/Quote'],
        'underscore'                  => ['my_plugin/card'],
        'dot'                         => ['core/quote.evil'],
        'artisanpack rule injection'  => ['artisanpack/x{}body{display:none}'],
        'artisanpack comma'           => ['artisanpack/quote,body'],
    ] );
} );

describe( 'GlobalStylesEmitter CSS string state (#387)', function (): void {
    dataset( 'interleaved quotes', [
        'double then single' => ['"a\' "b\''],
        'single then double' => ['\'a" \'b"'],
        'nested open string' => ['"a" \'b"c\' "d'],
    ] );

    it( 'drops interleaved-quote preset values and emits the next preset', function ( string $value ): void {
        $resolved = makeResolved(
            settings: [
                'color' => [
                    'palette' => [
                        ['slug' => 'bad', 'color' => $value],
                        ['slug' => 'good', 'color' => '#123456'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( '--wp--preset--color--bad' )
            ->and( $css )->toContain( '--wp--preset--color--good: #123456;' );
    } )->with( 'interleaved quotes' );

    it( 'drops interleaved-quote settings.custom values and emits the next one', function ( string $value ): void {
        $resolved = makeResolved(
            settings: ['custom' => ['x' => $value, 'y' => '1rem']],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( '--wp--custom--x' )
            ->and( $css )->toContain( '--wp--custom--y: 1rem;' );
    } )->with( 'interleaved quotes' );

    it( 'drops interleaved-quote styles values and emits the next declaration', function ( string $value ): void {
        $resolved = makeResolved(
            styles: [
                'typography' => ['fontFamily' => $value, 'fontSize' => '18px'],
                'blocks'     => [
                    'core/quote' => ['typography' => ['fontFamily' => $value, 'fontSize' => '20px']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( 'font-family:' )
            ->and( $css )->toContain( 'font-size: 18px;' )
            ->and( $css )->toContain( ":root :where(.wp-block-quote) {\n\tfont-size: 20px;\n}" );
    } )->with( 'interleaved quotes' );

    it( 'accepts parens and the other quote inside a string', function ( string $value ): void {
        $resolved = makeResolved(
            settings: ['custom' => ['label' => $value]],
            styles: ['typography' => ['fontFamily' => $value]],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--custom--label: ' . $value . ';' )
            ->and( $css )->toContain( 'font-family: ' . $value . ';' );
    } )->with( [
        'close paren in double quotes' => ['"Smile :)"'],
        'open paren in single quotes'  => ['\'(\''],
        'single quote in double'       => ['"Don\'t", serif'],
        'double quote in single'       => ['\'Say "hi"\', serif'],
        'paren string inside function' => ['attr(data-x, "(")'],
        'quoted url with parens'       => ['url("a(1).png")'],
        'spaced quoted url'            => ['url( \'a(1).png\' )'],
        'unquoted url'                 => ['url(/img/a.png)'],
        'uppercase unquoted url'       => ['URL(/img/a.png)'],
        'url text inside a string'     => ['"url(a"'],
        'ident ending in url'          => ['myurl(a, "b)", c)'],
        'url inside a function'        => ['image-set(url(/a.png) 1x, url(/b.png) 2x)'],
    ] );

    it( 'drops unquoted url() values a tokenizer reads as a bad url', function ( string $value ): void {
        $resolved = makeResolved(
            settings: ['custom' => ['x' => $value, 'y' => '1rem']],
            styles: ['typography' => ['fontFamily' => $value, 'fontSize' => '18px']],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( '--wp--custom--x' )
            ->and( $css )->not->toContain( 'font-family:' )
            ->and( $css )->toContain( '--wp--custom--y: 1rem;' )
            ->and( $css )->toContain( 'font-size: 18px;' );
    } )->with( [
        'quotes spanning the url close' => ['url(a"b)"c)'],
        'single quote in url'           => ['url(a\'b)'],
        'paren in url'                  => ['url(a(b))'],
        'unclosed url'                  => ['url(a.png'],
        'unclosed spaced url'           => ['url( '],
    ] );

    it( 'still rejects unbalanced parens outside strings', function ( string $value ): void {
        $resolved = makeResolved(
            settings: ['custom' => ['x' => $value, 'y' => '1rem']],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->not->toContain( '--wp--custom--x' )
            ->and( $css )->toContain( '--wp--custom--y: 1rem;' );
    } )->with( [
        'paren after closed string' => ['"a" ('],
        'close before open'         => [')"("'],
        'unclosed calc'             => ['calc(1rem + "x"'],
        'crossed paren and bracket' => ['([)]'],
        'bracket closing a paren'   => ['var(--x]'],
        'unclosed bracket'          => ['[full-start 1fr'],
        'bracket after string'      => ['"[" ]'],
    ] );

    it( 'keeps balanced brackets and brackets inside strings', function ( string $value ): void {
        $resolved = makeResolved(
            settings: ['custom' => ['x' => $value]],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        expect( $this->emitter->emit() )->toContain( '--wp--custom--x: ' . $value . ';' );
    } )->with( [
        'grid line names'     => ['[full-start] minmax(0, 1fr) [full-end]'],
        'bracket in a string' => ['"a[b"'],
        'nested functions'    => ['var(--x, rgb(0 0 0))'],
        'parens in brackets'  => ['[a(b)]'],
    ] );
} );

describe( 'GlobalStylesEmitter block rule specificity (#390)', function (): void {
    it( 'wraps block, block-element and pseudo-state rules in :root :where()', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'core/group' => [
                        'color'    => ['background' => '#ffffff'],
                        'elements' => [
                            'heading' => ['color' => ['text' => '#111111']],
                            'button'  => [
                                'color'  => ['background' => '#333333'],
                                ':hover' => ['color' => ['background' => '#222222']],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(.wp-block-group) {\n\tbackground-color: #ffffff;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-block-group h1, .wp-block-group h2, .wp-block-group h3, .wp-block-group h4, .wp-block-group h5, .wp-block-group h6) {\n\tcolor: #111111;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-block-group .wp-element-button:hover, .wp-block-group .wp-block-button__link:hover) {\n\tbackground-color: #222222;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-block-group .wp-element-button, .wp-block-group .wp-block-button__link) {\n\tbackground-color: #333333;\n}" )
            ->and( $css )->not->toMatch( '/^\.wp-block-group/m' );
    } );

    it( 'wraps root element rules and emits block rules after their pseudo states', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'link'   => ['color' => ['text' => '#0000ff'], ':hover' => ['color' => ['text' => '#ff0000']]],
                    'button' => [':hover' => ['color' => ['background' => '#444444']]],
                ],
                'blocks' => [
                    'core/group' => [
                        'elements' => [
                            'link'   => ['color' => ['text' => '#00ff00']],
                            'button' => [':hover' => ['color' => ['background' => '#555555']]],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        $rootLinkHover   = strpos( $css, ":root :where(a:where(:not(.wp-element-button)):hover) {\n\tcolor: #ff0000;\n}" );
        $rootButtonHover = strpos( $css, ":root :where(.wp-element-button:hover, .wp-block-button__link:hover) {\n\tbackground-color: #444444;\n}" );

        expect( $css )->toContain( ":root :where(a:where(:not(.wp-element-button))) {\n\tcolor: #0000ff;\n}" )
            ->and( $rootLinkHover )->toBeInt()
            ->and( $rootButtonHover )->toBeInt()
            ->and( strpos( $css, ":root :where(.wp-block-group a:where(:not(.wp-element-button))) {\n\tcolor: #00ff00;\n}" ) )->toBeGreaterThan( $rootLinkHover )
            ->and( strpos( $css, ":root :where(.wp-block-group .wp-element-button:hover, .wp-block-group .wp-block-button__link:hover) {\n\tbackground-color: #555555;\n}" ) )->toBeGreaterThan( $rootButtonHover )
            ->and( $css )->not->toMatch( '/^(a|h[1-6]|cite|\.wp-element-[a-z]+)[ ,:{]/m' );
    } );

    it( 'keeps preset class bindings after block rules and !important', function (): void {
        $resolved = makeResolved(
            settings: [
                'color'      => ['palette' => [['slug' => 'primary', 'color' => '#000000']]],
                'typography' => ['fontSizes' => [['slug' => 'large', 'size' => '2rem']]],
            ],
            styles: [
                'blocks' => [
                    'core/group' => [
                        'color'      => ['text' => '#111111', 'background' => '#ffffff'],
                        'typography' => ['fontSize' => '1rem'],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        $blockRule = strpos( $css, ':root :where(.wp-block-group) {' );

        expect( $blockRule )->not->toBeFalse()
            ->and( strpos( $css, '.has-primary-color { color: var(--wp--preset--color--primary) !important; }' ) )->toBeGreaterThan( $blockRule )
            ->and( strpos( $css, '.has-primary-background-color { background-color: var(--wp--preset--color--primary) !important; }' ) )->toBeGreaterThan( $blockRule )
            ->and( strpos( $css, '.has-large-font-size { font-size: var(--wp--preset--font-size--large) !important; }' ) )->toBeGreaterThan( $blockRule );
    } );
} );

describe( 'GlobalStylesEmitter WordPress element parity', function (): void {
    it( 'emits outline declarations, including on focus states', function (): void {
        $resolved = makeResolved(
            styles: [
                'outline'  => ['style' => 'solid'],
                'elements' => [
                    'button' => [
                        ':focus' => [
                            'outline' => [
                                'color'  => 'var:preset|color|accent',
                                'offset' => '2px',
                                'style'  => 'dotted',
                                'width'  => '3px',
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( "\toutline-style: solid;" )
            ->and( $css )->toContain( ":root :where(.wp-element-button:focus, .wp-block-button__link:focus) {\n\toutline-color: var(--wp--preset--color--accent);\n\toutline-offset: 2px;\n\toutline-style: dotted;\n\toutline-width: 3px;\n}" );
    } );

    it( 'drops unsafe outline values', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'link' => [':focus-visible' => ['outline' => ['color' => 'red;}body{display:none', 'width' => '2px']]],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(a:where(:not(.wp-element-button)):focus-visible) {\n\toutline-width: 2px;\n}" )
            ->and( $css )->not->toContain( 'display:none' );
    } );

    it( 'emits color.gradient as background on elements, their states and blocks (#398)', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'button' => [
                        'color'  => ['gradient' => 'var:preset|gradient|brand', 'text' => '#ffffff'],
                        ':hover' => ['color' => ['gradient' => 'linear-gradient(115deg, #2962ff 0%, #e040fb 100%)']],
                    ],
                ],
                'blocks'   => [
                    'core/group' => ['color' => ['gradient' => 'var:preset|gradient|neon']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(.wp-element-button, .wp-block-button__link) {\n\tbackground: var(--wp--preset--gradient--brand);\n\tcolor: #ffffff;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-element-button:hover, .wp-block-button__link:hover) {\n\tbackground: linear-gradient(115deg, #2962ff 0%, #e040fb 100%);\n}" )
            ->and( $css )->toContain( 'background: var(--wp--preset--gradient--neon);' );
    } );

    it( 'emits a gradient before background-color so a node with both keeps its color (#398)', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'button' => ['color' => ['background' => '#111827', 'gradient' => 'var:preset|gradient|brand']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(.wp-element-button, .wp-block-button__link) {\n\tbackground: var(--wp--preset--gradient--brand);\n\tbackground-color: #111827;\n}" );
    } );

    it( 'drops unsafe gradient values (#398)', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'button' => ['color' => ['gradient' => 'red;}body{display:none', 'text' => '#ffffff']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(.wp-element-button, .wp-block-button__link) {\n\tcolor: #ffffff;\n}" )
            ->and( $css )->not->toContain( 'display:none' );
    } );

    it( 'scopes link styles away from button links', function (): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    'link'   => ['color' => ['text' => '#0000ff']],
                    'button' => ['color' => ['text' => '#ffffff']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(a:where(:not(.wp-element-button))) {\n\tcolor: #0000ff;\n}" )
            ->and( $css )->not->toContain( ':root :where(a) {' );
    } );

    it( 'ignores pseudo states on elements other than link and button', function ( string $element ): void {
        $resolved = makeResolved(
            styles: [
                'elements' => [
                    $element => [
                        'color'  => ['text' => '#111111'],
                        ':hover' => ['color' => ['text' => '#222222']],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '#111111' )
            ->and( $css )->not->toContain( '#222222' )
            ->and( $css )->not->toContain( ':hover' );
    } )->with( ['heading', 'h1', 'caption', 'cite'] );
} );

describe( 'GlobalStylesEmitter WordPress kebab-case preset classes (#401)', function (): void {
    it( 'aliases preset classes whose slug has a letter-digit boundary', function (): void {
        $resolved = makeResolved(
            settings: [
                'color'      => [
                    'palette'   => [['slug' => 'gray900', 'color' => '#111827']],
                    'gradients' => [['slug' => 'neon2', 'gradient' => 'linear-gradient(red, blue)']],
                ],
                'typography' => [
                    'fontSizes'    => [['slug' => 'h2', 'size' => '44px']],
                    'fontFamilies' => [['slug' => 'mono2', 'fontFamily' => 'monospace']],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '.has-h2-font-size, .has-h-2-font-size { font-size: var(--wp--preset--font-size--h2) !important; }' )
            ->and( $css )->toContain( '.has-gray900-color, .has-gray-900-color { color: var(--wp--preset--color--gray900) !important; }' )
            ->and( $css )->toContain( '.has-gray900-background-color, .has-gray-900-background-color { background-color: var(--wp--preset--color--gray900) !important; }' )
            ->and( $css )->toContain( '.has-gray900-border-color, .has-gray-900-border-color { border-color: var(--wp--preset--color--gray900) !important; }' )
            ->and( $css )->toContain( '.has-mono2-font-family, .has-mono-2-font-family { font-family: var(--wp--preset--font-family--mono2) !important; }' )
            ->and( $css )->toContain( '.has-neon2-gradient-background, .has-neon-2-gradient-background { background: var(--wp--preset--gradient--neon2) !important; }' )
            ->and( $css )->toContain( '--wp--preset--font-size--h2: 44px;' )
            ->and( $css )->not->toContain( '--wp--preset--font-size--h-2' );
    } );

    it( 'emits a single selector when the slug is already WordPress kebab case', function (): void {
        $resolved = makeResolved(
            settings: [
                'color'      => ['palette' => [['slug' => 'neutral-0', 'color' => '#ffffff']]],
                'typography' => ['fontSizes' => [['slug' => 'button-lg', 'size' => '17px']]],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '.has-button-lg-font-size { font-size: var(--wp--preset--font-size--button-lg) !important; }' )
            ->and( $css )->toContain( '.has-neutral-0-background-color { background-color: var(--wp--preset--color--neutral-0) !important; }' );
    } );
} );

describe( 'GlobalStylesEmitter artisanpack block selectors (#405)', function (): void {
    it( 'targets both the core class and the artisanpack class for a fork', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'artisanpack/post-content' => [
                        'spacing'  => ['padding' => ['top' => '1rem']],
                        'elements' => [
                            'h2'   => ['typography' => ['fontSize' => '2.25rem']],
                            'link' => [':hover' => ['color' => ['text' => '#ff0000']]],
                        ],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(.wp-block-post-content, .wp-block-artisanpack-post-content) {\n\tpadding-top: 1rem;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-block-post-content h2, .wp-block-artisanpack-post-content h2) {\n\tfont-size: 2.25rem;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-block-post-content a:where(:not(.wp-element-button)):hover, .wp-block-artisanpack-post-content a:where(:not(.wp-element-button)):hover) {\n\tcolor: #ff0000;\n}" );
    } );

    it( 'keeps matching an own block through its artisanpack class', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'artisanpack/card' => [
                        'color'    => ['background' => '#eeeeee'],
                        'elements' => ['h3' => ['color' => ['text' => '#111111']]],
                    ],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(.wp-block-card, .wp-block-artisanpack-card) {\n\tbackground-color: #eeeeee;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-block-card h3, .wp-block-artisanpack-card h3) {\n\tcolor: #111111;\n}" );
    } );

    it( 'leaves core and third-party namespaces on a single class', function (): void {
        $resolved = makeResolved(
            styles: [
                'blocks' => [
                    'core/quote' => ['elements' => ['cite' => ['color' => ['text' => '#111111']]]],
                    'acme/card'  => ['elements' => ['cite' => ['color' => ['text' => '#222222']]]],
                ],
            ],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $css = $this->emitter->emit();

        expect( $css )->toContain( ":root :where(.wp-block-quote cite) {\n\tcolor: #111111;\n}" )
            ->and( $css )->toContain( ":root :where(.wp-block-acme-card cite) {\n\tcolor: #222222;\n}" )
            ->and( $css )->not->toContain( '.wp-block-card' )
            ->and( $css )->not->toContain( '.wp-block-artisanpack-quote' );
    } );
} );

describe( 'GlobalStylesEmitter CSS filter', function (): void {
    afterEach( function (): void {
        removeAllFilters( 'ap.cmsFramework.globalStyles.css' );
    } );

    it( 'passes the emitted CSS and resolved styles through ap.cmsFramework.globalStyles.css', function (): void {
        $resolved = makeResolved(
            settings: ['color' => ['palette' => [['slug' => 'primary', 'color' => '#000000']]]],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $received = null;

        addFilter( 'ap.cmsFramework.globalStyles.css', function ( string $css, ResolvedGlobalStyles $styles ) use ( &$received ): string {
            $received = $styles;

            return $css . "\n\n.is-style-card { padding: 1rem; }";
        } );

        $css = $this->emitter->emit();

        expect( $css )->toContain( '--wp--preset--color--primary: #000000;' )
            ->and( $css )->toEndWith( '.is-style-card { padding: 1rem; }' )
            ->and( $received )->toBe( $resolved );
    } );

    it( 'ignores a filter callback that returns a non-string', function (): void {
        $resolved = makeResolved(
            settings: ['color' => ['palette' => [['slug' => 'primary', 'color' => '#000000']]]],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        addFilter( 'ap.cmsFramework.globalStyles.css', fn (): ?string => null );

        expect( $this->emitter->emit() )->toContain( '--wp--preset--color--primary: #000000;' );
    } );

    it( 'applies the filter to cached CSS without caching the filtered result', function (): void {
        $resolved = makeResolved(
            settings: ['color' => ['palette' => [['slug' => 'primary', 'color' => '#000000']]]],
        );

        $this->resolver->shouldReceive( 'resolve' )->andReturn( $resolved );

        $unfiltered = $this->emitter->emit();

        addFilter( 'ap.cmsFramework.globalStyles.css', fn ( string $css ): string => $css . "\n/* host */" );

        expect( $this->emitter->emit() )->toBe( $unfiltered . "\n/* host */" )
            ->and( Cache::get( 'cms.global-styles.css.v13.test-theme.' . $resolved->contentHash() ) )->toBe( $unfiltered );
    } );
} );
