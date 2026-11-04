<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeValidationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Coverage for #453: theme installs and activations make their change live on
 * a production host, the way theme updates do since #450.
 */
beforeEach( function (): void {
    $this->manager    = app( ThemeManager::class );
    $this->resetter   = app( WebOpcacheResetter::class );
    $this->themesPath = base_path( 'themes' );
    $this->tmpPath    = storage_path( 'app/themes-refresh-tmp' );
    $this->testSlugs  = [];
    $this->routeCache = app()->getCachedRoutesPath();
    $this->hadRoutes  = is_file( $this->routeCache );

    File::ensureDirectoryExists( $this->themesPath );
    File::ensureDirectoryExists( $this->tmpPath );

    config()->set( 'cms.themes.cacheEnabled', false );
    Cache::forget( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ) );

    $this->buildThemeZip = function ( string $slug, array $manifest ): string {
        $this->testSlugs[] = $slug;

        $zipPath = $this->tmpPath . '/' . $slug . '.zip';

        $zip = new ZipArchive;
        $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE );
        $zip->addEmptyDir( $slug );
        $zip->addFromString( $slug . '/theme.json', json_encode( $manifest ) );
        $zip->addFromString( $slug . '/index.blade.php', 'index' );
        $zip->close();

        return $zipPath;
    };
} );

afterEach( function (): void {
    foreach ( $this->testSlugs as $slug ) {
        if ( File::exists( $this->themesPath . '/' . $slug ) ) {
            File::deleteDirectory( $this->themesPath . '/' . $slug );
        }
    }

    if ( File::isDirectory( $this->tmpPath ) ) {
        File::deleteDirectory( $this->tmpPath );
    }

    if ( ! $this->hadRoutes && is_file( $this->routeCache ) ) {
        File::delete( $this->routeCache );
    }
} );

describe( 'installing a theme', function (): void {
    it( 'resets OPcache, signals the queue workers and reports how', function (): void {
        $zipPath = ( $this->buildThemeZip )( 'refresh-theme', [
            'slug'    => 'refresh-theme',
            'name'    => 'Refresh Theme',
            'version' => '1.0.0',
        ] );
        Cache::forget( 'illuminate:queue:restart' );

        $this->manager->installFromZip( $zipPath );

        expect( $this->resetter->resets )->toBe( 1 )
            ->and( $this->manager->opcacheResetMethod() )->toBe( 'fastcgi' )
            ->and( $this->manager->phpReloadRequired() )->toBeFalse()
            ->and( Cache::get( 'illuminate:queue:restart' ) )->not->toBeNull();
    } );

    it( 'does not reset OPcache when the install is rejected', function (): void {
        $zipPath = ( $this->buildThemeZip )( 'rejected-theme', [
            'name'    => 'Rejected Theme',
            'version' => '1.0.0',
        ] );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class );

        expect( $this->resetter->resets )->toBe( 0 );
    } );
} );

describe( 'activating a theme', function (): void {
    beforeEach( function (): void {
        $this->manager->installFromZip( ( $this->buildThemeZip )( 'active-theme', [
            'slug'    => 'active-theme',
            'name'    => 'Active Theme',
            'version' => '1.0.0',
        ] ) );

        $this->resetter->resets = 0;
    } );

    it( 'resets OPcache and reports how', function (): void {
        expect( $this->manager->activateTheme( 'active-theme' ) )->toBeTrue()
            ->and( $this->resetter->resets )->toBe( 1 )
            ->and( $this->manager->opcacheResetMethod() )->toBe( 'fastcgi' );
    } );

    it( 'rebuilds a route cache the site had, in a fresh process', function (): void {
        File::ensureDirectoryExists( dirname( $this->routeCache ) );
        File::put( $this->routeCache, '<?php' );
        Process::fake();

        $this->manager->activateTheme( 'active-theme' );

        Process::assertRan( fn ( PendingProcess $process ): bool => is_array( $process->command )
            && ['artisan', 'route:cache'] === array_slice( $process->command, 1 ) );
    } );

    it( 'flags a PHP-FPM reload when no OPcache reset reaches the web server', function (): void {
        $this->resetter->method = null;

        $this->manager->activateTheme( 'active-theme' );

        expect( $this->manager->opcacheResetMethod() )->toBeNull()
            ->and( $this->manager->phpReloadRequired() )->toBeTrue();
    } );

    it( 'does not reset OPcache for an unknown theme', function (): void {
        expect( fn () => $this->manager->activateTheme( 'missing-theme' ) )
            ->toThrow( ThemeNotFoundException::class );

        expect( $this->resetter->resets )->toBe( 0 );
    } );
} );
