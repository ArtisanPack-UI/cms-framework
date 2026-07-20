<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Support\EnqueuedAssets;

describe( 'EnqueuedAssets::renderStyles', function (): void {
    it( 'returns an empty string for an empty list', function (): void {
        expect( EnqueuedAssets::renderStyles( 'my-theme', [] ) )->toBe( '' );
    } );

    it( 'renders a bare string as a link tag with the derived handle', function (): void {
        $rendered = EnqueuedAssets::renderStyles( 'my-theme', ['main.css'] );

        expect( $rendered )->toContain( '<link' )
            ->toContain( 'rel="stylesheet"' )
            ->toContain( 'id="main-css"' )
            ->toContain( '/themes/my-theme/assets/main.css' );
    } );

    it( 'uses an associative-array key as the enqueue handle', function (): void {
        $rendered = EnqueuedAssets::renderStyles( 'my-theme', [
            'brand' => 'brand.css',
        ] );

        expect( $rendered )->toContain( 'id="brand-css"' );
    } );

    it( 'emits the media attribute when provided', function (): void {
        $rendered = EnqueuedAssets::renderStyles( 'my-theme', [
            ['src' => 'print.css', 'media' => 'print'],
        ] );

        expect( $rendered )->toContain( 'media="print"' );
    } );

    it( 'appends a version query string only when the URL has none', function (): void {
        $rendered = EnqueuedAssets::renderStyles( 'my-theme', [
            ['src' => 'main.css', 'ver' => '1.2.3'],
            ['src' => 'https://cdn.example/x.css?v=1', 'ver' => '9.9.9'],
        ] );

        expect( $rendered )->toContain( '/themes/my-theme/assets/main.css?ver=1.2.3' )
            ->toContain( 'https://cdn.example/x.css?v=1' )
            ->not->toContain( 'https://cdn.example/x.css?v=1?' )
            ->not->toContain( 'https://cdn.example/x.css?v=1&ver=9.9.9' );
    } );

    it( 'passes absolute and root-relative URLs through unchanged', function (): void {
        $rendered = EnqueuedAssets::renderStyles( 'my-theme', [
            'cdn' => 'https://cdn.example/lib.css',
            'app' => '/vendor/lib.css',
        ] );

        expect( $rendered )->toContain( 'href="https://cdn.example/lib.css"' )
            ->toContain( 'href="/vendor/lib.css"' );
    } );

    it( 'silently drops entries with a missing or empty src', function (): void {
        $rendered = EnqueuedAssets::renderStyles( 'my-theme', [
            'bad-array'  => ['media' => 'print'],
            'empty-str'  => '',
            'null-entry' => null,
            'good'       => 'main.css',
        ] );

        expect( $rendered )->toContain( 'id="good-css"' )
            ->not->toContain( 'id="bad-array-css"' )
            ->not->toContain( 'id="empty-str-css"' )
            ->not->toContain( 'id="null-entry-css"' );
    } );

    it( 'drops duplicate handles — first entry wins', function (): void {
        $rendered = EnqueuedAssets::renderStyles( 'my-theme', [
            'brand' => 'first.css',
            ['src'  => 'second.css'],
            'brand' => 'third.css',
        ] );

        expect( $rendered )->toContain( '/themes/my-theme/assets/second.css' );
        expect( substr_count( $rendered, 'id="brand-css"' ) )->toBe( 1 );
    } );

    it( 'HTML-escapes attribute values to prevent injection', function (): void {
        $rendered = EnqueuedAssets::renderStyles( 'my-theme', [
            'x' => ['src' => 'main.css', 'media' => 'print" onload="alert(1)'],
        ] );

        expect( $rendered )->not->toContain( '" onload="alert(1)' );
        expect( $rendered )->toContain( '&quot;' );
    } );
} );

describe( 'EnqueuedAssets::renderScripts', function (): void {
    it( 'renders a bare string as a script tag with the derived handle', function (): void {
        $rendered = EnqueuedAssets::renderScripts( 'my-theme', ['main.js'] );

        expect( $rendered )->toContain( '<script' )
            ->toContain( 'id="main-js"' )
            ->toContain( '/themes/my-theme/assets/main.js' )
            ->toContain( '></script>' );
    } );

    it( 'adds defer and async as bare boolean attributes', function (): void {
        $rendered = EnqueuedAssets::renderScripts( 'my-theme', [
            'a' => ['src' => 'a.js', 'defer' => true],
            'b' => ['src' => 'b.js', 'async' => true],
        ] );

        expect( $rendered )->toMatch( '/<script[^>]* defer[ >]/' )
            ->toMatch( '/<script[^>]* async[ >]/' );
    } );

    it( 'forces type="module" when module flag is set', function (): void {
        $rendered = EnqueuedAssets::renderScripts( 'my-theme', [
            'm' => ['src' => 'app.js', 'module' => true, 'type' => 'text/javascript'],
        ] );

        expect( $rendered )->toContain( 'type="module"' )
            ->not->toContain( 'type="text/javascript"' );
    } );
} );

describe( 'EnqueuedAssets::assetUrl', function (): void {
    it( 'points at the themes.assets route with the given slug + path', function (): void {
        $url = EnqueuedAssets::assetUrl( 'my-theme', 'main.css' );

        expect( $url)->toContain( '/themes/my-theme/assets/main.css');
    });

    it( 'strips a leading slash from the path', function (): void {
        $url = EnqueuedAssets::assetUrl( 'my-theme', '/main.css');

        expect( $url)->toContain( '/themes/my-theme/assets/main.css');
    });
});
