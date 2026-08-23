<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts\ComposerPackageInstallerInterface;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\Composer\FakeComposerPackageInstaller;
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
    $tempFiles = array_merge(
        File::glob( storage_path( 'app/temp-plugin-*.zip' ) ),
        File::glob( storage_path( 'app/test-update-*.zip' ) ),
    );
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

        addAction( 'ap.cmsFramework.plugin.updating', function ( $slug, $oldVersion, $newVersion ) use ( &$hookFired ): void {
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

describe( 'Manifest re-validation on update (#283)', function (): void {
    // Build an update ZIP whose top-level directory is the plugin slug and
    // whose plugin.json carries $manifestOverrides merged over a clean base.
    // Returns [zipPath, sha256] so the caller can advertise the digest the
    // download is verified against.
    $buildUpdateZip = function ( array $manifestOverrides ): array {
        $manifest = array_merge( [
            'slug'        => 'valid-plugin',
            'name'        => 'Valid Test Plugin',
            'version'     => '2.0.0',
            'description' => 'Updated plugin',
            'author'      => 'Test Author',
        ], $manifestOverrides );

        $zipPath = storage_path( 'app/test-update-' . uniqid() . '.zip' );

        $zip = new ZipArchive;
        $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE );
        $zip->addFromString( 'valid-plugin/plugin.json', json_encode( $manifest ) );
        $zip->addFromString( 'valid-plugin/src/Stub.php', "<?php\n" );
        $zip->close();

        return [$zipPath, hash( 'sha256', File::get( $zipPath ) )];
    };

    // Install a clean valid-plugin at 1.0.0 wired to a legacy update feed.
    $installPlugin = function (): void {
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
                'slug'       => 'valid-plugin',
                'name'       => 'Valid Test Plugin',
                'version'    => '1.0.0',
                'update_url' => 'https://example.com/updates/valid-plugin',
            ],
        ] );
    };

    it( 'rejects an update whose manifest declares a migrations_path traversal', function () use ( $buildUpdateZip, $installPlugin ): void {
        $installPlugin->call( $this );
        [$zipPath, $sha256] = $buildUpdateZip( [
            'migrations_path' => '../../database/migrations',
        ] );

        Http::fake( [
            'https://example.com/updates/valid-plugin' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/valid-plugin-2.0.0.zip',
                'sha256'       => $sha256,
            ] ),
            'https://example.com/downloads/valid-plugin-2.0.0.zip' => Http::response( File::get( $zipPath ) ),
        ] );

        expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
            ->toThrow( PluginUpdateException::class );

        // Row rolled back: version, meta, and the traversal value never seated.
        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' );
        expect( $plugin->meta )->not->toHaveKey( 'migrations_path' );

        // Files restored from backup: the on-disk manifest is the 1.0.0 original.
        $onDisk = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );
        expect( $onDisk['version'] )->toBe( '1.0.0' );
        expect( $onDisk )->not->toHaveKey( 'migrations_path' );

        File::delete( $zipPath );
    } );

    it( 'rejects an update whose manifest declares an unprefixed permission', function () use ( $buildUpdateZip, $installPlugin ): void {
        $installPlugin->call( $this );
        [$zipPath, $sha256] = $buildUpdateZip( [
            'permissions' => ['manage_users'],
        ] );

        Http::fake( [
            'https://example.com/updates/valid-plugin' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/valid-plugin-2.0.0.zip',
                'sha256'       => $sha256,
            ] ),
            'https://example.com/downloads/valid-plugin-2.0.0.zip' => Http::response( File::get( $zipPath ) ),
        ] );

        expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
            ->toThrow( PluginUpdateException::class );

        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' );
        expect( $plugin->meta )->not->toHaveKey( 'permissions' );

        File::delete( $zipPath );
    } );

    it( 'applies an update whose manifest passes validation', function () use ( $buildUpdateZip, $installPlugin ): void {
        $installPlugin->call( $this );
        [$zipPath, $sha256] = $buildUpdateZip( [
            'migrations_path' => 'database/migrations',
            'permissions'     => ['valid-plugin.manage'],
        ] );

        Http::fake( [
            'https://example.com/updates/valid-plugin' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/valid-plugin-2.0.0.zip',
                'sha256'       => $sha256,
            ] ),
            'https://example.com/downloads/valid-plugin-2.0.0.zip' => Http::response( File::get( $zipPath ) ),
        ] );

        $result = $this->updateManager->updatePlugin( 'valid-plugin' );

        expect( $result )->toBeTrue();

        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '2.0.0' );
        expect( $plugin->meta['migrations_path'] )->toBe( 'database/migrations' );

        File::delete( $zipPath );
    } );

    it( 'rejects an update whose manifest slug differs from the plugin being updated (#315)', function () use ( $buildUpdateZip, $installPlugin ): void {
        $installPlugin->call( $this );
        // The archive's directory is still `valid-plugin` (so extraction lines
        // up), but its manifest declares a different `slug` — with a permission
        // namespace to match — the divergence this fix rejects.
        [$zipPath, $sha256] = $buildUpdateZip( [
            'slug'        => 'other-plugin',
            'permissions' => ['other-plugin.manage'],
        ] );

        Http::fake( [
            'https://example.com/updates/valid-plugin' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/valid-plugin-2.0.0.zip',
                'sha256'       => $sha256,
            ] ),
            'https://example.com/downloads/valid-plugin-2.0.0.zip' => Http::response( File::get( $zipPath ) ),
        ] );

        expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
            ->toThrow( PluginUpdateException::class );

        // Row rolled back: version and the foreign slug/permissions never seated.
        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' );
        expect( $plugin->meta['slug'] )->toBe( 'valid-plugin' );
        expect( $plugin->meta )->not->toHaveKey( 'permissions' );

        // Files restored from backup: the on-disk manifest is the 1.0.0 original.
        $onDisk = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );
        expect( $onDisk['version'] )->toBe( '1.0.0' );

        File::delete( $zipPath );
    } );
} );

describe( 'Reactivation failure after update (#45)', function (): void {
    it( 'rolls back and reports the dependency reason, not a download failure, when the new manifest adds an unsatisfiable dependency', function (): void {
        // Install an ACTIVE valid-plugin at 1.0.0 wired to a legacy update feed,
        // so the update flow reaches the step-7 reactivation.
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
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

        // The new manifest requires a plugin that is not installed, so the
        // reactivation at step 7 throws DependencyNotSatisfiedException.
        $manifest = [
            'slug'        => 'valid-plugin',
            'name'        => 'Valid Test Plugin',
            'version'     => '2.0.0',
            'description' => 'Updated plugin',
            'author'      => 'Test Author',
            'requires'    => [ 'plugins' => [ 'ghost-plugin' => '^1.0' ] ],
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

        expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
            ->toThrow( PluginUpdateException::class, 'dependencies are not satisfied' );

        // Row rolled back: version, active state restored, and the new requires
        // never seated.
        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' )
            ->and( $plugin->is_active )->toBeTrue()
            ->and( $plugin->meta )->not->toHaveKey( 'requires' );

        // Files restored from backup: the on-disk manifest is the 1.0.0 original.
        $onDisk = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );
        expect( $onDisk['version'] )->toBe( '1.0.0' );

        File::delete( $zipPath );
    } );

    it( 'rolls back and reports the composer reason when the new manifest adds an unsatisfiable composer package (#323)', function (): void {
        // Auto-install is opt-in (off by default); this test exercises the
        // installer-failure rollback path, so it turns auto-install on.
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );

        // A fake installer that reports nothing installed and fails any install,
        // so the reactivation at step 7 cannot satisfy the new composer block.
        $installer              = new FakeComposerPackageInstaller;
        $installer->failInstall = true;
        $installer->failReason  = 'Could not reach Packagist.';
        app()->instance( ComposerPackageInstallerInterface::class, $installer );

        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
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

        $manifest = [
            'slug'        => 'valid-plugin',
            'name'        => 'Valid Test Plugin',
            'version'     => '2.0.0',
            'description' => 'Updated plugin',
            'author'      => 'Test Author',
            'composer'    => [ 'acme/engine' => '^1.0' ],
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

        expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
            ->toThrow( PluginUpdateException::class, 'Could not reach Packagist.' );

        // Row rolled back and the new composer block never seated.
        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' )
            ->and( $plugin->is_active )->toBeTrue()
            ->and( $plugin->meta )->not->toHaveKey( 'composer' );

        // Files restored from backup.
        $onDisk = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );
        expect( $onDisk['version'] )->toBe( '1.0.0' );

        File::delete( $zipPath );
    } );
} );

// Helper function
function createMockUpdateZip(): void
{
    // This would create a mock ZIP file for testing
    // Implementation depends on test requirements
}
