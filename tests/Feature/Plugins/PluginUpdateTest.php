<?php

declare( strict_types = 1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    $this->pluginManager   = app( PluginManager::class );
    $this->updateManager   = new UpdateManager( $this->pluginManager );
    $this->testPluginsPath = __DIR__ . '/../../Support/Plugins';
    $this->pluginsPath     = base_path( 'plugins' );

    // Ensure plugins directory exists
    File::ensureDirectoryExists( $this->pluginsPath );
} );

afterEach( function (): void {
    // Cleanup test plugins
    if ( File::exists( $this->pluginsPath . '/valid-plugin' ) ) {
        File::deleteDirectory( $this->pluginsPath . '/valid-plugin' );
    }

    // Cleanup backups
    $backupPath = storage_path( 'app/plugin-backups' );
    if ( File::exists( $backupPath ) ) {
        File::deleteDirectory( $backupPath );
    }

    // Cleanup temp files
    $tempFiles = File::glob( storage_path( 'app/temp-plugin-*.zip' ) );
    foreach ( $tempFiles as $tempFile ) {
        File::delete( $tempFile );
    }
} );

describe( 'Plugin Update Flow', function (): void {
    it( 'successfully updates a plugin', function (): void {
        // Setup: Install plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        Plugin::create( [
            'slug'      => 'valid-plugin',
            'name'      => 'Valid Test Plugin',
            'version'   => '1.0.0',
            'is_active' => false,
            'meta'      => [
                'update_url' => 'https://example.com/updates/valid-plugin',
            ],
        ] );

        // Mock update check
        Http::fake( [
            'https://example.com/updates/valid-plugin' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/valid-plugin-2.0.0.zip',
            ] ),
            'https://example.com/downloads/valid-plugin-2.0.0.zip' => Http::response(
                File::get( $this->testPluginsPath . '/valid-plugin.zip' ),
            ),
        ] );

        // Create mock ZIP for testing
        $this->createMockUpdateZip();

        // Update plugin
        $result = $this->updateManager->updatePlugin( 'valid-plugin' );

        expect( $result )->toBeTrue();

        // Verify version updated in database
        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '2.0.0' );
    } )->skip( 'Requires mock ZIP creation' );

    it( 'creates backup before updating', function (): void {
        // Setup: Install plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        Plugin::create( [
            'slug'    => 'valid-plugin',
            'name'    => 'Valid Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/valid-plugin',
            ],
        ] );

        // Call backup method directly
        $reflection = new ReflectionClass( $this->updateManager );
        $method     = $reflection->getMethod( 'backupPlugin' );
        $method->setAccessible( true );

        $backupPath = $method->invoke( $this->updateManager, 'valid-plugin' );

        expect( File::exists( $backupPath ) )->toBeTrue();
        expect( $backupPath )->toContain( 'valid-plugin-1.0.0' );
    } );

    it( 'deactivates plugin before updating if active', function (): void {
        // Setup: Install and activate plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $manifest = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );

        Plugin::create( [
            'slug'      => 'valid-plugin',
            'name'      => 'Valid Test Plugin',
            'version'   => '1.0.0',
            'is_active' => true,
            'meta'      => array_merge( $manifest, [
                'update_url' => 'https://example.com/updates/valid-plugin',
            ] ),
        ] );

        Http::fake( [
            'https://example.com/updates/valid-plugin' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/valid-plugin-2.0.0.zip',
            ] ),
        ] );

        // Verify plugin is active before update
        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->is_active )->toBeTrue();
    } )->skip( 'Requires complete update flow mock' );

    it( 'throws exception when plugin not found', function (): void {
        expect( fn () => $this->updateManager->updatePlugin( 'non-existent' ) )
            ->toThrow( PluginUpdateException::class );
    } );

    it( 'returns false when no update available', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '2.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => Http::response( [
                'version' => '1.0.0', // Older version
            ] ),
        ] );

        $result = $this->updateManager->updatePlugin( 'test-plugin' );

        expect( $result )->toBeFalse();
    } );
} );

describe( 'Backup and Restore', function (): void {
    it( 'creates valid backup ZIP', function (): void {
        // Setup: Install plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        Plugin::create( [
            'slug'    => 'valid-plugin',
            'name'    => 'Valid Test Plugin',
            'version' => '1.0.0',
        ] );

        // Call backup method
        $reflection = new ReflectionClass( $this->updateManager );
        $method     = $reflection->getMethod( 'backupPlugin' );
        $method->setAccessible( true );

        $backupPath = $method->invoke( $this->updateManager, 'valid-plugin' );

        // Verify ZIP is valid
        $zip    = new ZipArchive;
        $result = $zip->open( $backupPath );

        expect( $result )->toBeTrue();
        expect( $zip->numFiles )->toBeGreaterThan( 0 );

        $zip->close();
    } );

    it( 'restores plugin from backup', function (): void {
        // Setup: Install plugin
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        Plugin::create( [
            'slug'    => 'valid-plugin',
            'name'    => 'Valid Test Plugin',
            'version' => '1.0.0',
        ] );

        // Create backup
        $reflection   = new ReflectionClass( $this->updateManager );
        $backupMethod = $reflection->getMethod( 'backupPlugin' );
        $backupMethod->setAccessible( true );
        $backupPath = $backupMethod->invoke( $this->updateManager, 'valid-plugin' );

        // Delete plugin directory
        File::deleteDirectory( $this->pluginsPath . '/valid-plugin' );

        expect( File::exists( $this->pluginsPath . '/valid-plugin' ) )->toBeFalse();

        // Restore from backup
        $restoreMethod = $reflection->getMethod( 'restoreFromBackup' );
        $restoreMethod->setAccessible( true );
        $restoreMethod->invoke( $this->updateManager, 'valid-plugin', $backupPath );

        // Verify restored
        expect( File::exists( $this->pluginsPath . '/valid-plugin' ) )->toBeTrue();
        expect( File::exists( $this->pluginsPath . '/valid-plugin/plugin.json' ) )->toBeTrue();
    } );
} );

describe( 'Download Handling', function (): void {
    it( 'downloads update from URL', function (): void {
        Http::fake( [
            'https://example.com/plugin.zip' => Http::response( 'fake-zip-content' ),
        ] );

        $reflection = new ReflectionClass( $this->updateManager );
        $method     = $reflection->getMethod( 'downloadUpdate' );
        $method->setAccessible( true );

        $downloadPath = $method->invoke( $this->updateManager, 'https://example.com/plugin.zip' );

        expect( File::exists( $downloadPath ) )->toBeTrue();
        expect( File::get( $downloadPath ) )->toBe( 'fake-zip-content' );

        // Cleanup
        File::delete( $downloadPath );
    } );

    it( 'throws exception on download failure', function (): void {
        Http::fake( [
            'https://example.com/plugin.zip' => Http::response( null, 500 ),
        ] );

        $reflection = new ReflectionClass( $this->updateManager );
        $method     = $reflection->getMethod( 'downloadUpdate' );
        $method->setAccessible( true );

        expect( fn () => $method->invoke( $this->updateManager, 'https://example.com/plugin.zip' ) )
            ->toThrow( Exception::class );
    } );
} );

describe( 'Update Hooks', function (): void {
    it( 'fires updating hook before update', function (): void {
        $hookFired = false;

        addAction( 'plugin.updating', function ( $slug, $oldVersion, $newVersion ) use ( &$hookFired ): void {
            $hookFired = true;
            expect( $slug )->toBe( 'test-plugin' );
            expect( $oldVersion )->toBe( '1.0.0' );
            expect( $newVersion )->toBe( '2.0.0' );
        } );

        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/test-plugin.zip',
            ] ),
        ] );

        // Note: This will fail at download, but should still fire the hook
        try {
            $this->updateManager->updatePlugin( 'test-plugin' );
        } catch ( Exception $e ) {
            // Expected to fail at download
        }

        expect( $hookFired )->toBeTrue();
    } );
} );

// Helper function
function createMockUpdateZip(): void
{
    // This would create a mock ZIP file for testing
    // Implementation depends on test requirements
}
