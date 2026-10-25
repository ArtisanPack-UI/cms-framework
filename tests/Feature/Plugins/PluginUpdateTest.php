<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts\ComposerPackageInstallerInterface;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\Composer\FakeComposerPackageInstaller;
use ArtisanPackUI\CMSFramework\Tests\Support\Plugins\PluginTestHelper;
use Illuminate\Support\Facades\Artisan;
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
    foreach ( ['valid-plugin', 'test-plugin'] as $slug ) {
        if ( File::exists( $this->pluginsPath . '/' . $slug ) ) {
            File::deleteDirectory( $this->pluginsPath . '/' . $slug );
        }
    }

    // Cleanup backups. Guard the config value: an empty one would resolve to
    // the storage root and delete all of it.
    $backupDir  = (string) config( 'cms.plugins.backupPath' );
    $backupPath = storage_path( $backupDir );
    if ( '' !== trim( $backupDir, '/' ) && File::exists( $backupPath ) ) {
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
    // Install `test-plugin` 1.0.0 from its fixture archive, wired to a legacy
    // update feed that offers the 2.0.0 fixture. $sha256 overrides the digest
    // the feed advertises.
    $installTestPlugin = function ( bool $isActive, ?string $sha256 = null ): void {
        PluginTestHelper::extractPluginZip( PluginTestHelper::fixturePath( 'test-plugin-1.0.0' ), $this->pluginsPath );

        $manifest = json_decode( File::get( $this->pluginsPath . '/test-plugin/plugin.json' ), true );

        Plugin::create( [
            'slug'      => 'test-plugin',
            'name'      => 'Test Plugin',
            'version'   => '1.0.0',
            'is_active' => $isActive,
            'meta'      => array_merge( $manifest, [
                'update_url' => PluginTestHelper::updateFeedUrl( 'test-plugin' ),
            ] ),
        ] );

        PluginTestHelper::fakeUpdateFeed( 'test-plugin', '2.0.0', PluginTestHelper::fixturePath( 'test-plugin-2.0.0' ), $sha256 );
    };

    it( 'successfully updates a plugin', function () use ( $installTestPlugin ): void {
        $installTestPlugin->call( $this, false );

        $result = $this->updateManager->updatePlugin( 'test-plugin' );

        expect( $result )->toBeTrue();

        // Version and manifest seated in the database.
        $plugin = Plugin::where( 'slug', 'test-plugin' )->first();
        expect( $plugin->version )->toBe( '2.0.0' )
            ->and( $plugin->meta['version'] )->toBe( '2.0.0' )
            ->and( $plugin->is_active )->toBeFalse();

        // The 2.0.0 files replaced the 1.0.0 ones on disk.
        $pluginPath = $this->pluginsPath . '/test-plugin';
        $onDisk     = json_decode( File::get( $pluginPath . '/plugin.json' ), true );
        expect( $onDisk['version'] )->toBe( '2.0.0' )
            ->and( File::exists( $pluginPath . '/src/Feature.php' ) )->toBeTrue()
            ->and( File::exists( $pluginPath . '/src/Legacy.php' ) )->toBeFalse();

        // A 1.0.0 backup was taken before the files were replaced.
        expect( File::glob( storage_path( config( 'cms.plugins.backupPath' ) . '/test-plugin-1.0.0-*.zip' ) ) )->not->toBeEmpty();
    } );

    it( 'does not re-run an update once the plugin is on the advertised version', function () use ( $installTestPlugin ): void {
        $installTestPlugin->call( $this, false );

        expect( $this->updateManager->updatePlugin( 'test-plugin' ) )->toBeTrue()
            ->and( $this->updateManager->updatePlugin( 'test-plugin' ) )->toBeFalse();
    } );

    it( 'rolls back when the downloaded archive does not match the advertised sha256', function () use ( $installTestPlugin ): void {
        $installTestPlugin->call( $this, false, hash( 'sha256', 'not-the-archive' ) );

        expect( fn () => $this->updateManager->updatePlugin( 'test-plugin' ) )
            ->toThrow( PluginUpdateException::class );

        $plugin = Plugin::where( 'slug', 'test-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' );

        $pluginPath = $this->pluginsPath . '/test-plugin';
        expect( File::exists( $pluginPath . '/src/Legacy.php' ) )->toBeTrue()
            ->and( File::exists( $pluginPath . '/src/Feature.php' ) )->toBeFalse();
    } );

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

    it( 'deactivates plugin before updating if active', function () use ( $installTestPlugin ): void {
        $installTestPlugin->call( $this, true );

        // Record the lifecycle hooks in the order they fire, along with whether
        // the row was active at the time, to prove the update ran while the
        // plugin was deactivated and reactivated it afterwards.
        $events    = [];
        $listeners = [];
        foreach ( ['updating', 'deactivated', 'activated', 'updated'] as $hook ) {
            $listeners[ $hook ] = function () use ( $hook, &$events ): void {
                $events[] = [
                    $hook,
                    (bool) Plugin::where( 'slug', 'test-plugin' )->value( 'is_active' ),
                ];
            };
            addAction( 'ap.cmsFramework.plugin.' . $hook, $listeners[ $hook ] );
        }

        try {
            expect( Plugin::where( 'slug', 'test-plugin' )->first()->is_active )->toBeTrue();

            expect( $this->updateManager->updatePlugin( 'test-plugin' ) )->toBeTrue();

            expect( $events )->toBe( [
                ['updating', true],
                ['deactivated', false],
                ['activated', true],
                ['updated', true],
            ] );

            $plugin = Plugin::where( 'slug', 'test-plugin' )->first();
            expect( $plugin->version )->toBe( '2.0.0' )
                ->and( $plugin->is_active )->toBeTrue();
        } finally {
            foreach ( $listeners as $hook => $listener ) {
                removeAction( 'ap.cmsFramework.plugin.' . $hook, $listener );
            }
        }
    } );

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

    it( 'rejects an update whose manifest declares a migrations_path traversal', function () use ( $installPlugin ): void {
        $installPlugin->call( $this );
        $zipPath = PluginTestHelper::buildUpdateZip( [
            'migrations_path' => '../../database/migrations',
        ] );

        PluginTestHelper::fakeUpdateFeed( 'valid-plugin', '2.0.0', $zipPath );

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

    it( 'rejects an update whose manifest declares an unprefixed permission', function () use ( $installPlugin ): void {
        $installPlugin->call( $this );
        $zipPath = PluginTestHelper::buildUpdateZip( [
            'permissions' => ['manage_users'],
        ] );

        PluginTestHelper::fakeUpdateFeed( 'valid-plugin', '2.0.0', $zipPath );

        expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
            ->toThrow( PluginUpdateException::class );

        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' );
        expect( $plugin->meta )->not->toHaveKey( 'permissions' );

        File::delete( $zipPath );
    } );

    it( 'applies an update whose manifest passes validation', function () use ( $installPlugin ): void {
        $installPlugin->call( $this );
        $zipPath = PluginTestHelper::buildUpdateZip( [
            'migrations_path' => 'database/migrations',
            'permissions'     => ['valid-plugin.manage'],
        ] );

        PluginTestHelper::fakeUpdateFeed( 'valid-plugin', '2.0.0', $zipPath );

        $result = $this->updateManager->updatePlugin( 'valid-plugin' );

        expect( $result )->toBeTrue();

        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '2.0.0' );
        expect( $plugin->meta['migrations_path'] )->toBe( 'database/migrations' );

        File::delete( $zipPath );
    } );

    it( 'rejects an update whose manifest slug differs from the plugin being updated (#315)', function () use ( $installPlugin ): void {
        $installPlugin->call( $this );
        // The archive's directory is still `valid-plugin` (so extraction lines
        // up), but its manifest declares a different `slug` — with a permission
        // namespace to match — the divergence this fix rejects.
        $zipPath = PluginTestHelper::buildUpdateZip( [
            'slug'        => 'other-plugin',
            'permissions' => ['other-plugin.manage'],
        ] );

        PluginTestHelper::fakeUpdateFeed( 'valid-plugin', '2.0.0', $zipPath );

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

describe( 'Framework cache clearing on update (#359)', function (): void {
    // Install an inactive valid-plugin at 1.0.0 and fake a 2.0.0 update whose
    // manifest carries $manifestOverrides. Inactive so the reactivate step is
    // skipped and any clear observed comes from the update itself.
    $prepareUpdate = function ( array $manifestOverrides = [] ): string {
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

        $zipPath = PluginTestHelper::buildUpdateZip( $manifestOverrides );
        PluginTestHelper::fakeUpdateFeed( 'valid-plugin', '2.0.0', $zipPath );

        return $zipPath;
    };

    // Stand a recording double in for the Artisan facade. Testbench's console
    // kernel is `final`, so `Artisan::shouldReceive()` cannot mock it.
    $swapArtisan = function (): object {
        $artisan = new class {
            public array $calls = [];

            public function call( $command, array $parameters = [], $outputBuffer = null ): int
            {
                $this->calls[] = $command;

                return 0;
            }
        };

        Artisan::swap( $artisan );

        return $artisan;
    };

    it( 'clears the route, config and view caches after an update when the flag is on', function () use ( $prepareUpdate, $swapArtisan ): void {
        config( ['cms.plugins.autoClearFrameworkCaches' => true] );
        $zipPath = $prepareUpdate->call( $this );
        $artisan = $swapArtisan();

        expect( $this->updateManager->updatePlugin( 'valid-plugin' ) )->toBeTrue();
        expect( $artisan->calls )->toBe( ['route:clear', 'config:clear', 'view:clear'] );

        File::delete( $zipPath );
    } );

    it( 'leaves the framework caches alone after an update when the flag is off', function () use ( $prepareUpdate, $swapArtisan ): void {
        config( ['cms.plugins.autoClearFrameworkCaches' => false] );
        $zipPath = $prepareUpdate->call( $this );
        $artisan = $swapArtisan();

        expect( $this->updateManager->updatePlugin( 'valid-plugin' ) )->toBeTrue();
        expect( $artisan->calls )->toBe( [] );

        File::delete( $zipPath );
    } );

    it( 'does not clear the framework caches when the update is rolled back', function () use ( $prepareUpdate, $swapArtisan ): void {
        config( ['cms.plugins.autoClearFrameworkCaches' => true] );
        $zipPath = $prepareUpdate->call( $this, [
            'migrations_path' => '../../database/migrations',
        ] );
        $artisan = $swapArtisan();

        expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
            ->toThrow( PluginUpdateException::class );
        expect( $artisan->calls )->toBe( [] );

        File::delete( $zipPath );
    } );

    it( 'does not clear the framework caches when an updated-hook listener throws and the update rolls back', function () use ( $prepareUpdate, $swapArtisan ): void {
        config( ['cms.plugins.autoClearFrameworkCaches' => true] );
        $zipPath = $prepareUpdate->call( $this );
        $artisan = $swapArtisan();

        $listener = function (): void {
            throw new RuntimeException( 'Listener failed.' );
        };
        addAction( 'ap.cmsFramework.plugin.updated', $listener );

        try {
            expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
                ->toThrow( PluginUpdateException::class );
            expect( $artisan->calls )->toBe( [] );
            expect( Plugin::where( 'slug', 'valid-plugin' )->first()->version )->toBe( '1.0.0' );
        } finally {
            removeAction( 'ap.cmsFramework.plugin.updated', $listener );
            File::delete( $zipPath );
        }
    } );
    it( 'clears the caches once per deactivate/activate, not again, when updating an active plugin', function () use ( $prepareUpdate, $swapArtisan ): void {
        config( ['cms.plugins.autoClearFrameworkCaches' => true] );
        $zipPath = $prepareUpdate->call( $this );
        Plugin::where( 'slug', 'valid-plugin' )->update( [ 'is_active' => true ] );
        $artisan = $swapArtisan();

        try {
            expect( $this->updateManager->updatePlugin( 'valid-plugin' ) )->toBeTrue();
            expect( $artisan->calls )->toBe( [
                'route:clear', 'config:clear', 'view:clear',
                'route:clear', 'config:clear', 'view:clear',
            ] );
        } finally {
            File::delete( $zipPath );
        }
    } );

    it( 'rolls the update back when an updated-hook listener throws an Error', function () use ( $prepareUpdate, $swapArtisan ): void {
        config( ['cms.plugins.autoClearFrameworkCaches' => true] );
        $zipPath = $prepareUpdate->call( $this );
        $artisan = $swapArtisan();

        $listener = function (): void {
            throw new TypeError( 'Listener type error.' );
        };
        addAction( 'ap.cmsFramework.plugin.updated', $listener );

        try {
            expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
                ->toThrow( PluginUpdateException::class, 'Listener type error.' );
            expect( $artisan->calls )->toBe( [] );
            expect( Plugin::where( 'slug', 'valid-plugin' )->first()->version )->toBe( '1.0.0' );
            expect( File::exists( $this->pluginsPath . '/valid-plugin/src/Stub.php' ) )->toBeFalse();
        } finally {
            removeAction( 'ap.cmsFramework.plugin.updated', $listener );
            File::delete( $zipPath );
        }
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
        $manifestOverrides = [
            'requires' => [ 'plugins' => [ 'ghost-plugin' => '^1.0' ] ],
        ];

        $zipPath = PluginTestHelper::buildUpdateZip( $manifestOverrides );

        PluginTestHelper::fakeUpdateFeed( 'valid-plugin', '2.0.0', $zipPath );

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
        // This test exercises the installer-failure rollback path, so it pins
        // auto-install on rather than relying on the default.
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );

        // A fake installer that reports nothing installed and fails any install,
        // so the update cannot satisfy the new composer block (#430).
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

        $manifestOverrides = [
            'composer' => [ 'acme/engine' => '^1.0' ],
        ];

        $zipPath = PluginTestHelper::buildUpdateZip( $manifestOverrides );

        PluginTestHelper::fakeUpdateFeed( 'valid-plugin', '2.0.0', $zipPath );

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

describe( 'Composer packages on update (#430)', function (): void {
    beforeEach( function (): void {
        $this->installer = new FakeComposerPackageInstaller;
        app()->instance( ComposerPackageInstallerInterface::class, $this->installer );

        // An inactive valid-plugin at 1.0.0 with no composer block, wired to a
        // legacy feed that offers 2.0.0 declaring `acme/engine`.
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

        $this->updateZipPath = PluginTestHelper::buildUpdateZip( [
            'composer' => [ 'acme/engine' => '^1.0' ],
        ] );
        PluginTestHelper::fakeUpdateFeed( 'valid-plugin', '2.0.0', $this->updateZipPath );
    } );

    afterEach( function (): void {
        File::delete( $this->updateZipPath );
    } );

    it( 'installs the new version\'s packages when updating an inactive plugin', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'acme/engine' => '1.3.0' ];

        expect( $this->updateManager->updatePlugin( 'valid-plugin' ) )->toBeTrue()
            ->and( $this->installer->installCalls )->toBe( [
                [ 'slug' => 'valid-plugin', 'constraints' => [ 'acme/engine' => '^1.0' ] ],
            ] );

        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '2.0.0' )
            ->and( $plugin->is_active )->toBeFalse()
            ->and( $plugin->meta['composer'] )->toBe( [ 'acme/engine' => '^1.0' ] );
    } );

    it( 'rolls an inactive plugin\'s update back when its packages cannot be installed', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->failInstall = true;
        $this->installer->failReason  = 'Could not reach Packagist.';

        expect( fn () => $this->updateManager->updatePlugin( 'valid-plugin' ) )
            ->toThrow( PluginUpdateException::class, 'Could not reach Packagist.' );

        $plugin = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( $plugin->version )->toBe( '1.0.0' )
            ->and( $plugin->is_active )->toBeFalse()
            ->and( $plugin->meta )->not->toHaveKey( 'composer' );

        $onDisk = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );
        expect( $onDisk['version'] )->toBe( '1.0.0' );
    } );

    it( 'leaves the composer block alone when updating an inactive plugin with auto-install off', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', false );

        expect( $this->updateManager->updatePlugin( 'valid-plugin' ) )->toBeTrue()
            ->and( $this->installer->installCalls )->toBe( [] )
            ->and( Plugin::where( 'slug', 'valid-plugin' )->first()->version )->toBe( '2.0.0' );
    } );
} );
