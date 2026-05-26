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
        $resolved = makeResolved( styles: ['color' => ['background' => '#aaaaaa']]);

        $this->resolver->shouldReceive( 'resolve')->andReturn( $resolved);

        $this->emitter->emit();

        // Cache key carries the schema version so a deploy with a
        // bumped emitter automatically busts every cached entry
        // (Keystone #53). Mirror that here.
        $cacheKey = 'cms.global-styles.css.v2.test-theme.' . $resolved->contentHash();
        expect( Cache::has( $cacheKey))->toBeTrue();

        $this->emitter->invalidate();

        expect( Cache::has( $cacheKey))->toBeFalse();
    });
});
