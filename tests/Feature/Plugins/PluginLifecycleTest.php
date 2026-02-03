<?php

declare( strict_types = 1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->manager         = app( PluginManager::class );
    $this->testPluginsPath = __DIR__ . '/../../Support/Plugins';
    $this->pluginsPath     = base_path( 'plugins' );

    // Ensure plugins directory exists
    File::ensureDirectoryExists( $this->pluginsPath );
} );

afterEach( function (): void {
    // Cleanup any test plugins
    if ( File::exists( $this->pluginsPath . '/valid-plugin' ) ) {
        File::deleteDirectory( $this->pluginsPath . '/valid-plugin' );
    }
    if ( File::exists( $this->pluginsPath . '/plugin-with-migrations' ) ) {
        File::deleteDirectory( $this->pluginsPath . '/plugin-with-migrations' );
    }
} );

describe( 'Plugin Installation', function (): void {
    it( 'can create a plugin directory manually for testing', function (): void {
        // Copy plugin to plugins directory
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        // Read the manifest
        $manifestPath = $this->pluginsPath . '/valid-plugin/plugin.json';
        $manifest     = json_decode( File::get( $manifestPath ), true );

        // Install plugin (register in database)
        $plugin = Plugin::create( [
            'slug'         => $manifest['slug'],
            'name'         => $manifest['name'],
            'version'      => $manifest['version'],
            'is_active'    => false,
            'meta'         => $manifest,
            'installed_at' => now(),
        ] );

        expect( $plugin )->toBeInstanceOf( Plugin::class )
            ->and( $plugin->slug )->toBe( 'valid-plugin' )
            ->and( Plugin::where( 'slug', 'valid-plugin' )->exists() )->toBeTrue();
    } );
} );

describe( 'Plugin Activation', function (): void {
    it( 'can activate a plugin', function (): void {
        // Setup: Install plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $manifest = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );

        Plugin::create( [
            'slug'             => $manifest['slug'],
            'name'             => $manifest['name'],
            'version'          => $manifest['version'],
            'is_active'        => false,
            'service_provider' => $manifest['service_provider'] ?? null,
            'meta'             => $manifest,
        ] );

        // Activate
        $result = $this->manager->activate( 'valid-plugin' );

        expect( $result )->toBeTrue();

        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->is_active )->toBeTrue();
    } );

    it( 'throws exception when activating non-existent plugin', function (): void {
        expect( fn () => $this->manager->activate( 'non-existent' ) )
            ->toThrow( PluginNotFoundException::class );
    } );

    it( 'activates plugin with migrations', function (): void {
        // Setup: Install plugin with migrations
        File::copyDirectory(
            $this->testPluginsPath . '/plugin-with-migrations',
            $this->pluginsPath . '/plugin-with-migrations',
        );

        $manifest = json_decode( File::get( $this->pluginsPath . '/plugin-with-migrations/plugin.json' ), true );

        Plugin::create( [
            'slug'      => $manifest['slug'],
            'name'      => $manifest['name'],
            'version'   => $manifest['version'],
            'is_active' => false,
            'meta'      => $manifest,
        ] );

        // Activate (should run migrations)
        $result = $this->manager->activate( 'plugin-with-migrations' );

        expect( $result )->toBeTrue();

        // Verify migration ran
        expect( Schema::hasTable( 'plugin_test_table' ) )->toBeTrue();

        // Cleanup: rollback migration
        Schema::dropIfExists( 'plugin_test_table' );
    } );
} );

describe( 'Plugin Deactivation', function (): void {
    it( 'can deactivate an active plugin', function (): void {
        // Setup: Install and activate plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $manifest = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );

        Plugin::create( [
            'slug'      => $manifest['slug'],
            'name'      => $manifest['name'],
            'version'   => $manifest['version'],
            'is_active' => true,
            'meta'      => $manifest,
        ] );

        // Deactivate
        $result = $this->manager->deactivate( 'valid-plugin' );

        expect( $result )->toBeTrue();

        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->is_active )->toBeFalse();
    } );

    it( 'throws exception when deactivating non-existent plugin', function (): void {
        expect( fn () => $this->manager->deactivate( 'non-existent' ) )
            ->toThrow( PluginNotFoundException::class );
    } );
} );

describe( 'Plugin Deletion', function (): void {
    it( 'can delete an inactive plugin', function (): void {
        // Setup: Install plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $manifest = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );

        Plugin::create( [
            'slug'      => $manifest['slug'],
            'name'      => $manifest['name'],
            'version'   => $manifest['version'],
            'is_active' => false,
            'meta'      => $manifest,
        ] );

        // Delete
        $result = $this->manager->delete( 'valid-plugin' );

        expect( $result )->toBeTrue();

        // Verify deleted from database
        expect( Plugin::where( 'slug', 'valid-plugin' )->exists() )->toBeFalse();

        // Verify deleted from filesystem
        expect( File::exists( $this->pluginsPath . '/valid-plugin' ) )->toBeFalse();
    } );

    it( 'deactivates plugin before deletion if active', function (): void {
        // Setup: Install active plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $manifest = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );

        Plugin::create( [
            'slug'      => $manifest['slug'],
            'name'      => $manifest['name'],
            'version'   => $manifest['version'],
            'is_active' => true,
            'meta'      => $manifest,
        ] );

        // Delete (should deactivate first)
        $result = $this->manager->delete( 'valid-plugin' );

        expect( $result )->toBeTrue();
        expect( Plugin::where( 'slug', 'valid-plugin' )->exists() )->toBeFalse();
    } );

    it( 'can delete plugin without removing files', function (): void {
        // Setup: Install plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $manifest = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );

        Plugin::create( [
            'slug'      => $manifest['slug'],
            'name'      => $manifest['name'],
            'version'   => $manifest['version'],
            'is_active' => false,
            'meta'      => $manifest,
        ] );

        // Delete without removing files
        $result = $this->manager->delete( 'valid-plugin', false );

        expect( $result )->toBeTrue();

        // Verify deleted from database
        expect( Plugin::where( 'slug', 'valid-plugin' )->exists() )->toBeFalse();

        // Verify files still exist
        expect( File::exists( $this->pluginsPath . '/valid-plugin' ) )->toBeTrue();
    } );

    it( 'throws exception when deleting non-existent plugin', function (): void {
        expect( fn () => $this->manager->delete( 'non-existent' ) )
            ->toThrow( PluginNotFoundException::class );
    } );
} );

describe( 'Active Plugins Loading', function (): void {
    it( 'loads all active plugins on boot', function (): void {
        // Setup: Create active and inactive plugins
        Plugin::create( [
            'slug'      => 'active-plugin-1',
            'name'      => 'Active Plugin 1',
            'version'   => '1.0.0',
            'is_active' => true,
        ] );

        Plugin::create( [
            'slug'      => 'active-plugin-2',
            'name'      => 'Active Plugin 2',
            'version'   => '1.0.0',
            'is_active' => true,
        ] );

        Plugin::create( [
            'slug'      => 'inactive-plugin',
            'name'      => 'Inactive Plugin',
            'version'   => '1.0.0',
            'is_active' => false,
        ] );

        // This method is called during boot, we're just testing it doesn't error
        expect( fn () => $this->manager->loadActivePlugins() )
            ->not->toThrow( Exception::class );
    } );
});
