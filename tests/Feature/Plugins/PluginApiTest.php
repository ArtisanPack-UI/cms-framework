<?php

declare( strict_types = 1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    // Create admin user with all permissions
    $this->admin = TestUser::factory()->create();

    // Ensure plugins directory exists
    $this->pluginsPath = base_path( 'plugins' );
    File::ensureDirectoryExists( $this->pluginsPath );
} );

afterEach( function (): void {
    // Cleanup test plugins
    if ( File::exists( $this->pluginsPath . '/valid-plugin' ) ) {
        File::deleteDirectory( $this->pluginsPath . '/valid-plugin' );
    }
} );

describe( 'Plugin API - Index', function (): void {
    it( 'returns list of all plugins', function (): void {
        $this->actingAs( $this->admin );

        // Create some test plugins
        Plugin::create( [
            'slug'      => 'test-plugin-1',
            'name'      => 'Test Plugin 1',
            'version'   => '1.0.0',
            'is_active' => true,
        ] );

        Plugin::create( [
            'slug'      => 'test-plugin-2',
            'name'      => 'Test Plugin 2',
            'version'   => '2.0.0',
            'is_active' => false,
        ] );

        $response = $this->getJson( '/api/v1/plugins' );

        $response->assertStatus( 200 )
            ->assertJsonStructure( [
                'plugins' => [
                    '*' => [
                        'slug',
                        'name',
                        'version',
                        'is_active',
                    ],
                ],
            ] );
    } );

    it( 'requires authentication', function (): void {
        $response = $this->getJson( '/api/v1/plugins' );

        $response->assertStatus( 401 );
    } );
} );

describe( 'Plugin API - Show', function (): void {
    it( 'returns specific plugin details', function (): void {
        $this->actingAs( $this->admin );

        // Setup plugin
        File::copyDirectory(
            __DIR__ . '/../../Support/Plugins/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        Plugin::create( [
            'slug'      => 'valid-plugin',
            'name'      => 'Valid Test Plugin',
            'version'   => '1.0.0',
            'is_active' => false,
        ] );

        $response = $this->getJson( '/api/v1/plugins/valid-plugin' );

        $response->assertStatus( 200 )
            ->assertJson( [
                'plugin' => [
                    'slug'    => 'valid-plugin',
                    'name'    => 'Valid Test Plugin',
                    'version' => '1.0.0',
                ],
            ] );
    } );

    it( 'returns 404 for non-existent plugin', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->getJson( '/api/v1/plugins/non-existent' );

        $response->assertStatus( 404 );
    } );
} );

describe( 'Plugin API - Activate', function (): void {
    it( 'can activate a plugin', function (): void {
        $this->actingAs( $this->admin );

        // Setup plugin
        File::copyDirectory(
            __DIR__ . '/../../Support/Plugins/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        Plugin::create( [
            'slug'      => 'valid-plugin',
            'name'      => 'Valid Test Plugin',
            'version'   => '1.0.0',
            'is_active' => false,
            'meta'      => json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true ),
        ] );

        $response = $this->postJson( '/api/v1/plugins/valid-plugin/activate' );

        $response->assertStatus( 200 )
            ->assertJson( [
                'message' => 'Plugin activated successfully',
            ] );

        // Verify plugin is active
        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->is_active )->toBeTrue();
    } );

    it( 'returns error when activating non-existent plugin', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->postJson( '/api/v1/plugins/non-existent/activate' );

        $response->assertStatus( 422 )
            ->assertJsonStructure( ['message'] );
    } );
} );

describe( 'Plugin API - Deactivate', function (): void {
    it( 'can deactivate a plugin', function (): void {
        $this->actingAs( $this->admin );

        // Setup active plugin
        Plugin::create( [
            'slug'      => 'test-plugin',
            'name'      => 'Test Plugin',
            'version'   => '1.0.0',
            'is_active' => true,
        ] );

        $response = $this->postJson( '/api/v1/plugins/test-plugin/deactivate' );

        $response->assertStatus( 200 )
            ->assertJson( [
                'message' => 'Plugin deactivated successfully',
            ] );

        // Verify plugin is inactive
        $plugin = Plugin::where( 'slug', 'test-plugin' )->first();
        expect( $plugin->is_active )->toBeFalse();
    } );
} );

describe( 'Plugin API - Delete', function (): void {
    it( 'can delete a plugin', function (): void {
        $this->actingAs( $this->admin );

        // Setup plugin
        File::copyDirectory(
            __DIR__ . '/../../Support/Plugins/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        Plugin::create( [
            'slug'      => 'valid-plugin',
            'name'      => 'Valid Test Plugin',
            'version'   => '1.0.0',
            'is_active' => false,
        ] );

        $response = $this->deleteJson( '/api/v1/plugins/valid-plugin' );

        $response->assertStatus( 200 )
            ->assertJson( [
                'message' => 'Plugin deleted successfully',
            ] );

        // Verify plugin is deleted
        expect( Plugin::where( 'slug', 'valid-plugin' )->exists() )->toBeFalse();
    } );
} );

describe( 'Plugin API - Check Updates', function (): void {
    it( 'returns update information', function (): void {
        $this->actingAs( $this->admin );

        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
        ] );

        $response = $this->getJson( '/api/v1/plugins/updates' );

        $response->assertStatus( 200 )
            ->assertJsonStructure( [
                'updates',
            ] );
    } );
} );

describe( 'Plugin API - Permission Checks', function (): void {
    it( 'requires authentication for all endpoints', function (): void {
        $endpoints = [
            ['GET', '/api/v1/plugins'],
            ['GET', '/api/v1/plugins/test-plugin'],
            ['POST', '/api/v1/plugins/test-plugin/activate'],
            ['POST', '/api/v1/plugins/test-plugin/deactivate'],
            ['DELETE', '/api/v1/plugins/test-plugin'],
            ['GET', '/api/v1/plugins/updates'],
        ];

        foreach ( $endpoints as [$method, $uri] ) {
            $response = $this->json( $method, $uri );
            $response->assertStatus( 401 );
        }
    } );
});
