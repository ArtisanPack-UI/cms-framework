<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission\GlobalStylesEmitter;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedGlobalStyles;
use Illuminate\Support\Facades\Cache;

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

        expect( $css )->toContain( 'a {' )
            ->and( $css )->toContain( 'color: #0000ff;' )
            ->and( $css )->toContain( 'h1, h2, h3, h4, h5, h6 {' )
            ->and( $css )->toContain( '.wp-element-button, .wp-block-button__link {' )
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
            ->toContain( '.wp-element-button, .wp-block-button__link {' )
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
            ->toContain( '.wp-block-artisanpack-quote {' )
            ->and( $css )->toContain( 'border-color: #accent;' )
            ->and( $css )->toContain( 'padding-left: 1.5rem;' )
            ->and( $css )->toContain( 'font-style: italic;' )
            ->and( $css )->toContain( '.wp-block-artisanpack-card {' )
            ->and( $css )->toContain( 'border-radius: 12px;' )
            // core/* namespace drops to bare `.wp-block-{name}` selector.
            ->and( $css )->toContain( '.wp-block-quote {' )
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
            ->toContain( '.wp-block-artisanpack-legit {' )
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
        $cacheKey = 'cms.global-styles.css.v3.test-theme.' . $resolved->contentHash();
        expect( Cache::has( $cacheKey ) )->toBeTrue();

        $this->emitter->invalidate();

        expect( Cache::has( $cacheKey ) )->toBeFalse();
    } );
} );
