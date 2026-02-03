<?php

test( 'example feature test', function (): void {
    expect( true )->toBe( true );
} );

test( 'service provider is loaded', function (): void {
    $providers = app()->getLoadedProviders();

    expect( $providers )->toHaveKey( 'ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider' );
} );

test( 'application has basic configuration', function (): void {
    expect( config( 'app.key' ) )->not()->toBeNull();
    expect( config( 'database.default' ) )->toBe( 'testing');
});
