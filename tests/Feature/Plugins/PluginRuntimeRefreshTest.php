<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\IncompatiblePluginException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Coverage for #453: plugin installs, activations, deactivations and deletes
 * make their change live on a production host, the way updates do since #450.
 */
beforeEach( function (): void {
    $this->manager         = app( PluginManager::class );
    $this->resetter        = app( WebOpcacheResetter::class );
    $this->testPluginsPath = __DIR__ . '/../../Support/Plugins';
    $this->pluginsPath     = base_path( 'plugins' );
    $this->routeCache      = app()->getCachedRoutesPath();
    $this->hadRoutes       = is_file( $this->routeCache );

    File::ensureDirectoryExists( $this->pluginsPath );
    config()->set( 'cms.plugins.cacheEnabled', false );

    $this->installValidPlugin = function ( array $attributes = [] ): Plugin {
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $this->pluginsPath . '/valid-plugin' );

        return Plugin::create( array_merge( [
            'slug'      => 'valid-plugin',
            'name'      => 'Valid Test Plugin',
            'version'   => '1.0.0',
            'is_active' => false,
            'meta'      => json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true ),
        ], $attributes ) );
    };

    // Stand a recording double in for the Artisan facade. Testbench's console
    // kernel is `final`, so `Artisan::shouldReceive()` cannot mock it.
    $this->swapArtisan = function (): object {
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
} );

afterEach( function (): void {
    foreach ( ['valid-plugin', 'object-author-plugin'] as $slug ) {
        if ( File::exists( $this->pluginsPath . '/' . $slug ) ) {
            File::deleteDirectory( $this->pluginsPath . '/' . $slug );
        }
    }

    if ( ! $this->hadRoutes && is_file( $this->routeCache ) ) {
        File::delete( $this->routeCache );
    }
} );

describe( 'activating a plugin', function (): void {
    it( 'resets OPcache, signals the queue workers and reports how', function (): void {
        ( $this->installValidPlugin )();
        $artisan = ( $this->swapArtisan )();

        expect( $this->manager->activate( 'valid-plugin' ) )->toBeTrue()
            ->and( $this->resetter->resets )->toBe( 1 )
            ->and( $artisan->calls )->toBe( ['queue:restart'] )
            ->and( $this->manager->opcacheResetMethod() )->toBe( 'fastcgi' )
            ->and( $this->manager->phpReloadRequired() )->toBeFalse();
    } );

    it( 'rebuilds a route cache autoClearFrameworkCaches cleared, so the plugin\'s routes exist', function (): void {
        config()->set( 'cms.plugins.autoClearFrameworkCaches', true );
        File::ensureDirectoryExists( dirname( $this->routeCache ) );
        File::put( $this->routeCache, '<?php' );
        Process::fake();
        ( $this->installValidPlugin )();

        $this->manager->activate( 'valid-plugin' );

        expect( is_file( $this->routeCache ) )->toBeFalse();
        Process::assertRan( fn ( PendingProcess $process ): bool => is_array( $process->command )
            && ['artisan', 'route:cache'] === array_slice( $process->command, 1 ) );
    } );

    it( 'does not compile a route cache the site never had', function (): void {
        File::delete( $this->routeCache );
        Process::fake();
        ( $this->installValidPlugin )();

        $this->manager->activate( 'valid-plugin' );

        Process::assertNotRan( fn ( PendingProcess $process ): bool => is_array( $process->command )
            && in_array( 'route:cache', $process->command, true ) );
    } );

    it( 'flags a PHP-FPM reload when no OPcache reset reaches the web server', function (): void {
        $this->resetter->method = null;
        ( $this->installValidPlugin )();

        $this->manager->activate( 'valid-plugin' );

        expect( $this->manager->opcacheResetMethod() )->toBeNull()
            ->and( $this->manager->phpReloadRequired() )->toBeTrue();
    } );

    it( 'neither resets OPcache nor restarts workers when the activation is refused', function (): void {
        $plugin = ( $this->installValidPlugin )();
        $plugin->update( ['meta' => array_merge( $plugin->meta, ['min_host_version' => '999.0.0'] )] );
        $artisan = ( $this->swapArtisan )();

        expect( fn () => $this->manager->activate( 'valid-plugin' ) )
            ->toThrow( IncompatiblePluginException::class );

        expect( $this->resetter->resets )->toBe( 0 )
            ->and( $artisan->calls )->toBe( [] );
    } );

    it( 'does not report an earlier change\'s outcome for a refused activation', function (): void {
        $this->resetter->method = null;
        ( $this->installValidPlugin )();
        $this->manager->activate( 'valid-plugin' );

        expect( $this->manager->phpReloadRequired() )->toBeTrue();

        expect( fn () => $this->manager->activate( 'not-installed' ) )->toThrow( Exception::class );
        expect( $this->manager->phpReloadRequired() )->toBeFalse();
    } );
} );

describe( 'deactivating and deleting a plugin', function (): void {
    it( 'refreshes after a deactivation', function (): void {
        ( $this->installValidPlugin )( ['is_active' => true] );
        $artisan = ( $this->swapArtisan )();

        $this->manager->deactivate( 'valid-plugin' );

        expect( $this->resetter->resets )->toBe( 1 )
            ->and( $artisan->calls )->toBe( ['queue:restart'] );
    } );

    it( 'refreshes once for a delete, not again for its deactivate', function (): void {
        config()->set( 'cms.plugins.autoClearFrameworkCaches', true );
        ( $this->installValidPlugin )( ['is_active' => true] );
        $artisan = ( $this->swapArtisan )();

        $this->manager->delete( 'valid-plugin' );

        expect( $this->resetter->resets )->toBe( 1 )
            ->and( array_count_values( $artisan->calls )['queue:restart'] )->toBe( 1 );
    } );
} );

describe( 'installing a plugin', function (): void {
    it( 'refreshes after installing from disk', function (): void {
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $this->pluginsPath . '/valid-plugin' );
        $artisan = ( $this->swapArtisan )();

        $this->manager->installFromDisk( 'valid-plugin' );

        expect( $this->resetter->resets )->toBe( 1 )
            ->and( $artisan->calls )->toBe( ['queue:restart'] )
            ->and( $this->manager->opcacheResetMethod() )->toBe( 'fastcgi' );
    } );

    it( 'refreshes once for a sync that installs several plugins', function (): void {
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $this->pluginsPath . '/valid-plugin' );
        File::copyDirectory( $this->testPluginsPath . '/object-author-plugin', $this->pluginsPath . '/object-author-plugin' );
        $artisan = ( $this->swapArtisan )();

        $results = $this->manager->syncFromDisk();

        expect( array_column( $results, 'status', 'slug' ) )->toMatchArray( [
            'valid-plugin'         => 'installed',
            'object-author-plugin' => 'installed',
        ] )
            ->and( $this->resetter->resets )->toBe( 1 )
            ->and( array_count_values( $artisan->calls )['queue:restart'] )->toBe( 1 );
    } );

    it( 'still refreshes, then rethrows, when a failure escapes the sync', function (): void {
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $this->pluginsPath . '/valid-plugin' );
        File::copyDirectory( $this->testPluginsPath . '/object-author-plugin', $this->pluginsPath . '/object-author-plugin' );
        $artisan = ( $this->swapArtisan )();

        // Not one of the exceptions the sync turns into a `failed` row.
        $listener = function ( string $slug ): void {
            if ( 'valid-plugin' === $slug ) {
                throw new RuntimeException( 'Listener exploded.' );
            }
        };
        addAction( 'ap.cmsFramework.plugin.installing', $listener );

        try {
            expect( fn () => $this->manager->syncFromDisk() )
                ->toThrow( RuntimeException::class, 'Listener exploded.' );

            expect( $this->resetter->resets )->toBe( 1 )
                ->and( $artisan->calls )->toBe( ['queue:restart'] )
                ->and( $this->manager->opcacheResetMethod() )->toBe( 'fastcgi' );
        } finally {
            removeAction( 'ap.cmsFramework.plugin.installing', $listener );
        }
    } );

    it( 'does not refresh for a sync that changed nothing', function (): void {
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $this->pluginsPath . '/valid-plugin' );
        $this->manager->syncFromDisk();
        $this->resetter->resets = 0;
        $artisan                = ( $this->swapArtisan )();

        $results = $this->manager->syncFromDisk();

        expect( array_unique( array_column( $results, 'status' ) ) )->toBe( ['unchanged'] )
            ->and( $this->resetter->resets )->toBe( 0 )
            ->and( $artisan->calls )->toBe( [] );
    } );
} );
