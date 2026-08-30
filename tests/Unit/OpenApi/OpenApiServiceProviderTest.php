<?php

declare( strict_types=1 );

/**
 * Unit Tests for the OpenAPI Service Provider.
 *
 * Verifies that the OpenAPI documentation is properly registered
 * and configured through the service provider.
 *
 * @since 1.1.0
 */

use ArtisanPackUI\CMSFramework\Modules\OpenApi\Providers\OpenApiServiceProvider;

test( 'service provider is registered', function (): void {
    $providers = array_keys( app()->getLoadedProviders() );

    expect( $providers )->toContain( OpenApiServiceProvider::class );
} );

test( 'export command is registered', function (): void {
    $this->artisan( 'cms:openapi:export', ['--help' => true] )
        ->assertSuccessful();
} );

test( 'openapi config has default values', function (): void {
    $config = config( 'artisanpack.cms-framework.openapi' );

    expect( $config )
        ->toBeArray()
        ->toHaveKey( 'enabled' )
        ->toHaveKey( 'info' )
        ->toHaveKey( 'ui_path' )
        ->toHaveKey( 'document_path' );

    expect( $config['enabled'] )->toBeTrue();
    expect( $config['info']['title'] )->toBe( 'ArtisanPack CMS Framework API' );
    expect( $config['info']['version'] )->toBe( '2.10.1' );
    expect( $config['ui_path'] )->toBe( '/docs/api/cms' );
    expect( $config['document_path'] )->toBe( '/docs/api/cms.json' );
} );

test( 'openapi config values can be overridden', function (): void {
    config( [
        'artisanpack.cms-framework.openapi.info.title'   => 'Custom API Title',
        'artisanpack.cms-framework.openapi.info.version' => '3.0.0',
    ] );

    expect( config( 'artisanpack.cms-framework.openapi.info.title' ) )->toBe( 'Custom API Title' );
    expect( config( 'artisanpack.cms-framework.openapi.info.version' ) )->toBe( '3.0.0' );
} );
