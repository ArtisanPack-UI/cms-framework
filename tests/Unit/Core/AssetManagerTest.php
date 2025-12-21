<?php

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\AssetManager;

beforeEach( function (): void {
    // Ensure a fresh Hooks state per test by binding new manager instances
    $this->app->instance( ArtisanPackUI\Hooks\Filter::class, new ArtisanPackUI\Hooks\Filter( $this->app ) );
    $this->app->instance( ArtisanPackUI\Hooks\Action::class, new ArtisanPackUI\Hooks\Action( $this->app ) );
} );

/**
 * ADMIN CONTEXT
 */
test( 'admin: enqueuing a single asset registers it with correct properties', function (): void {
    $assets = app( AssetManager::class );

    $assets->adminEnqueueAsset( 'main-js', '/assets/js/main.js', true );

    $result = $assets->adminAssets();

    expect( $result )->toBeArray();
    expect( $result )->toHaveKey( 'main-js' );
    expect( $result['main-js'] )
        ->toMatchArray( [
            'path'     => '/assets/js/main.js',
            'inFooter' => true,
        ] );
} );

test( 'admin: enqueuing multiple assets aggregates them', function (): void {
    $assets = app( AssetManager::class );

    $assets->adminEnqueueAsset( 'main-js', '/assets/js/main.js' );
    $assets->adminEnqueueAsset( 'styles', '/assets/css/app.css' );

    $result = $assets->adminAssets();

    expect( $result )->toBeArray();
    expect( $result )->toHaveKeys( ['main-js', 'styles'] );
    expect( $result['main-js']['path'] )->toBe( '/assets/js/main.js' );
    expect( $result['main-js']['inFooter'] )->toBeFalse();
    expect( $result['styles']['path'] )->toBe( '/assets/css/app.css' );
} );

test( 'admin: enqueuing same handle twice results in last registration winning', function (): void {
    $assets = app( AssetManager::class );

    $assets->adminEnqueueAsset( 'main-js', '/assets/js/old.js' );
    $assets->adminEnqueueAsset( 'main-js', '/assets/js/new.js', true );

    $result = $assets->adminAssets();

    expect( $result['main-js'] )
        ->toMatchArray( [
            'path'     => '/assets/js/new.js',
            'inFooter' => true,
        ] );
} );

/**
 * PUBLIC CONTEXT
 */
test( 'public: enqueuing assets registers and lists them', function (): void {
    $assets = app( AssetManager::class );

    $assets->publicEnqueueAsset( 'site-js', '/js/site.js', true );
    $assets->publicEnqueueAsset( 'site-css', '/css/site.css', false );

    $result = $assets->publicAssets();

    expect( $result )->toBeArray();
    expect( $result )->toHaveKeys( ['site-js', 'site-css'] );
    expect( $result['site-js']['path'] )->toBe( '/js/site.js' );
    expect( $result['site-js']['inFooter'] )->toBeTrue();
    expect( $result['site-css']['path'] )->toBe( '/css/site.css' );
} );

/**
 * AUTH CONTEXT
 */
test( 'auth: enqueuing assets registers and lists them', function (): void {
    $assets = app( AssetManager::class );

    $assets->authEnqueueAsset( 'auth-js', '/auth/app.js', true );

    $result = $assets->authAssets();

    expect( $result )->toBeArray();
    expect( $result)->toHaveKey( 'auth-js');
    expect( $result['auth-js'])
        ->toMatchArray( [
            'path'     => '/auth/app.js',
            'inFooter' => true,
        ]);
});
