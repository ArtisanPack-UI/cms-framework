<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    // Privileged user: mutating plugin routes gate on `manage-plugins`.
    $this->admin = grantPermissions( TestUser::factory()->create(), 'manage-plugins' );

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

    it( 'returns a 422 errors bag keyed by slug when activating a non-existent plugin', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->postJson( '/api/v1/plugins/non-existent/activate' );

        $response->assertStatus( 422 )
            ->assertJsonValidationErrors( [
                'slug' => "Plugin with slug 'non-existent' not found.",
            ] );
    } );
} );

describe( 'Plugin API - Install', function (): void {
    it( 'returns a 422 errors bag keyed by plugin_zip when no file is uploaded', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->postJson( '/api/v1/plugins/install', [] );

        $response->assertStatus( 422 )
            ->assertJsonValidationErrors( 'plugin_zip' );
    } );

    it( 'returns a 422 errors bag keyed by plugin_zip when the upload is not a ZIP', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->postJson( '/api/v1/plugins/install', [
            'plugin_zip' => UploadedFile::fake()->create( 'plugin.txt', 4, 'text/plain' ),
        ] );

        $response->assertStatus( 422 )
            ->assertJsonValidationErrors( 'plugin_zip' );
    } );

    it( 'reports a manager-level rejection in the same errors bag keyed by plugin_zip', function (): void {
        $this->actingAs( $this->admin );

        // Passes the form request's rules (a real ZIP, under the size ceiling)
        // but is rejected by PluginManager::validateZip() for carrying no
        // manifest — the path that previously returned a message-only body.
        $zipPath = storage_path( 'app/manifestless-plugin.zip' );
        File::ensureDirectoryExists( dirname( $zipPath ) );

        $zip = new ZipArchive;
        $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE );
        $zip->addFromString( 'readme.txt', 'no manifest here' );
        $zip->close();

        $response = $this->postJson( '/api/v1/plugins/install', [
            'plugin_zip' => new UploadedFile( $zipPath, 'plugin.zip', 'application/zip', null, true ),
        ] );

        File::delete( $zipPath );

        $response->assertStatus( 422 )
            ->assertJsonValidationErrors( 'plugin_zip' );
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

    it( 'returns a 422 errors bag keyed by slug when deactivating a non-existent plugin', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->postJson( '/api/v1/plugins/non-existent/deactivate' );

        $response->assertStatus( 422 )
            ->assertJsonValidationErrors( [
                'slug' => "Plugin with slug 'non-existent' not found.",
            ] );
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

    it( 'returns a 422 errors bag keyed by slug when deleting a non-existent plugin', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->deleteJson( '/api/v1/plugins/non-existent' );

        $response->assertStatus( 422 )
            ->assertJsonValidationErrors( [
                'slug' => "Plugin with slug 'non-existent' not found.",
            ] );
    } );
} );

describe( 'Plugin API - Update', function (): void {
    it( 'returns a 422 errors bag keyed by slug when updating a non-existent plugin', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->postJson( '/api/v1/plugins/non-existent/update' );

        $response->assertStatus( 422 )
            ->assertJsonValidationErrors( 'slug' );
    } );

    it( 'reports a no-op rather than success when no update is available', function (): void {
        $this->actingAs( $this->admin );

        // No `update` source in meta, so checkPluginUpdate() finds nothing and
        // updatePlugin() returns false without touching the install.
        Plugin::create( [
            'slug'    => 'current-plugin',
            'name'    => 'Current Plugin',
            'version' => '1.0.0',
            'meta'    => ['slug' => 'current-plugin'],
        ] );

        $response = $this->postJson( '/api/v1/plugins/current-plugin/update' );

        $response->assertOk()
            ->assertJsonPath( 'updated', false )
            ->assertJsonPath( 'message', 'Plugin is already up to date.' );
    } );

    it( 'returns a structured 409, not a generic 422, when the new version requires a newer host', function (): void {
        $this->actingAs( $this->admin );

        File::copyDirectory(
            __DIR__ . '/../../Support/Plugins/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        Plugin::create( [
            'slug'      => 'valid-plugin',
            'name'      => 'Valid Test Plugin',
            'version'   => '1.0.0',
            'is_active' => true,
            'meta'      => [
                'slug'       => 'valid-plugin',
                'name'       => 'Valid Test Plugin',
                'version'    => '1.0.0',
                'update_url' => 'https://example.com/updates/valid-plugin',
            ],
        ] );

        // The new version declares a host requirement the test host cannot meet,
        // so step-7 reactivation throws IncompatiblePluginException, which
        // UpdateManager rethrows as-is for the controller to render structurally.
        $manifest = [
            'slug'             => 'valid-plugin',
            'name'             => 'Valid Test Plugin',
            'version'          => '2.0.0',
            'description'      => 'Updated plugin',
            'author'           => 'Test Author',
            'min_host_version' => '999.0.0',
        ];

        $zipPath = storage_path( 'app/test-update-' . uniqid() . '.zip' );
        $zip     = new ZipArchive;
        $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE );
        $zip->addFromString( 'valid-plugin/plugin.json', json_encode( $manifest ) );
        $zip->addFromString( 'valid-plugin/src/Stub.php', "<?php\n" );
        $zip->close();
        $sha256 = hash( 'sha256', File::get( $zipPath ) );

        Http::fake( [
            'https://example.com/updates/valid-plugin' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/valid-plugin-2.0.0.zip',
                'sha256'       => $sha256,
            ] ),
            'https://example.com/downloads/valid-plugin-2.0.0.zip' => Http::response( File::get( $zipPath ) ),
        ] );

        $response = $this->postJson( '/api/v1/plugins/valid-plugin/update' );

        $response->assertStatus( 409 )
            ->assertJsonPath( 'code', 'plugin_incompatible' )
            ->assertJsonPath( 'plugin', 'valid-plugin' )
            ->assertJsonPath( 'required_version', '999.0.0' );

        // The failed update rolled back: still active at the old version.
        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' )
            ->and( $plugin->is_active )->toBeTrue();

        File::delete( $zipPath );
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
            // Read-only dependency endpoints (#45): auth-gated even though
            // they are not `manage-plugins`-gated.
            ['GET', '/api/v1/plugins/test-plugin/dependencies'],
            ['GET', '/api/v1/plugins/test-plugin/dependents'],
            ['POST', '/api/v1/plugins/check-dependencies'],
        ];

        foreach ( $endpoints as [$method, $uri] ) {
            $response = $this->json( $method, $uri );
            $response->assertStatus( 401 );
        }
    } );

    it( 'forbids a non-privileged authenticated user from mutating plugins', function (): void {
        // Authenticated, but without the `manage-plugins` ability.
        $this->actingAs( TestUser::factory()->create() );

        $mutating = [
            ['POST', '/api/v1/plugins/install'],
            ['POST', '/api/v1/plugins/valid-plugin/activate'],
            ['POST', '/api/v1/plugins/valid-plugin/deactivate'],
            ['POST', '/api/v1/plugins/valid-plugin/update'],
            ['DELETE', '/api/v1/plugins/valid-plugin'],
            // Update discovery is gated too: it makes one outbound request per
            // installed plugin, an administrative action.
            ['GET', '/api/v1/plugins/updates'],
        ];

        foreach ( $mutating as [$method, $uri] ) {
            $this->json( $method, $uri )->assertForbidden();
        }
    } );
} );
