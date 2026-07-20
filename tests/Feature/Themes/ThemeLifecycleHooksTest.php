<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->manager    = app( ThemeManager::class );
    $this->themesPath = base_path( 'themes' );
    $this->tmpPath    = storage_path( 'app/themes-hooks-tmp' );
    $this->testSlugs  = [];

    File::ensureDirectoryExists( $this->themesPath );
    File::ensureDirectoryExists( $this->tmpPath );

    config()->set( 'cms.themes.cacheEnabled', false );
    Cache::forget( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ) );

    // Clear any lingering listeners from previous tests for our hook names.
    foreach ( ['ap.cmsFramework.theme.installing', 'ap.cmsFramework.theme.installed', 'ap.cmsFramework.theme.activating', 'ap.cmsFramework.theme.activated'] as $hook ) {
        removeAllActions( $hook );
    }
} );

afterEach( function (): void {
    foreach ( $this->testSlugs as $slug ) {
        $path = $this->themesPath . '/' . $slug;
        if ( File::exists( $path ) ) {
            File::deleteDirectory( $path );
        }
    }

    if ( File::isDirectory( $this->tmpPath ) ) {
        File::deleteDirectory( $this->tmpPath );
    }

    foreach ( ['ap.cmsFramework.theme.installing', 'ap.cmsFramework.theme.installed', 'ap.cmsFramework.theme.activating', 'ap.cmsFramework.theme.activated'] as $hook ) {
        removeAllActions( $hook );
    }
} );

/**
 * Build a ZIP with a single theme directory and given theme.json contents.
 */
function buildHookThemeZip( string $tmpPath, string $slug, array $manifest, array &$slugs ): string
{
    $slugs[] = $slug;

    $zipPath = $tmpPath . '/' . $slug . '.zip';
    if ( file_exists( $zipPath ) ) {
        unlink( $zipPath );
    }

    $zip = new ZipArchive;
    if ( true !== $zip->open( $zipPath, ZipArchive::CREATE ) ) {
        throw new RuntimeException( "Failed to create test ZIP at {$zipPath}" );
    }

    $zip->addEmptyDir( $slug );
    $zip->addFromString( $slug . '/theme.json', json_encode( $manifest ) );
    $zip->close();

    return $zipPath;
}

/**
 * Write a theme.json file into a fresh test theme directory so activateTheme()
 * can find it.
 */
function writeHookTheme( string $themesPath, string $slug, array $manifest, array &$slugs ): void
{
    $slugs[] = $slug;
    $path    = $themesPath . '/' . $slug;
    File::ensureDirectoryExists( $path );
    File::put( $path . '/theme.json', json_encode( $manifest ) );
}

describe( 'theme install lifecycle hooks', function (): void {
    it( 'fires theme.installing and theme.installed in order with slug + manifest payload', function (): void {
        $manifest = [
            'slug'    => 'hooks-install',
            'name'    => 'Hooks Install',
            'version' => '1.0.0',
        ];

        $events = [];

        addAction( 'ap.cmsFramework.theme.installing', function ( string $slug, array $payload ) use ( &$events ): void {
            $events[] = ['installing', $slug, $payload];
        } );

        addAction( 'ap.cmsFramework.theme.installed', function ( string $slug, array $payload ) use ( &$events ): void {
            $events[] = ['installed', $slug, $payload];
        } );

        $zipPath = buildHookThemeZip( $this->tmpPath, 'hooks-install', $manifest, $this->testSlugs );

        $this->manager->installFromZip( $zipPath );

        expect( $events )->toHaveCount( 2 );
        expect( $events[0][0] )->toBe( 'installing' );
        expect( $events[0][1] )->toBe( 'hooks-install' );
        expect( $events[0][2] )->toMatchArray( $manifest );
        expect( $events[1][0] )->toBe( 'installed' );
        expect( $events[1][1] )->toBe( 'hooks-install' );
        expect( $events[1][2] )->toMatchArray( $manifest );
    } );

    it( 'rolls back the extracted directory when a theme.installing listener throws a non-Exception Throwable', function (): void {
        $manifest = [
            'slug'    => 'hooks-error',
            'name'    => 'Hooks Error',
            'version' => '1.0.0',
        ];

        addAction( 'ap.cmsFramework.theme.installing', function (): void {
            // Error (parent of TypeError, etc.) is a Throwable but not an Exception.
            throw new Error( 'fatal in listener' );
        } );

        $zipPath = buildHookThemeZip( $this->tmpPath, 'hooks-error', $manifest, $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( Error::class, 'fatal in listener' );

        expect( File::exists( $this->themesPath . '/hooks-error' ) )->toBeFalse();
    } );

    it( 'aborts the install and rolls back the extracted directory when a theme.installing listener throws', function (): void {
        $manifest = [
            'slug'    => 'hooks-abort',
            'name'    => 'Hooks Abort',
            'version' => '1.0.0',
        ];

        $installedFired = false;

        addAction( 'ap.cmsFramework.theme.installing', function (): void {
            throw new RuntimeException( 'vetoed by listener' );
        } );

        addAction( 'ap.cmsFramework.theme.installed', function () use ( &$installedFired ): void {
            $installedFired = true;
        } );

        $zipPath = buildHookThemeZip( $this->tmpPath, 'hooks-abort', $manifest, $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( RuntimeException::class, 'vetoed by listener' );

        expect( $installedFired )->toBeFalse();
        expect( File::exists( $this->themesPath . '/hooks-abort' ) )->toBeFalse();
    } );
} );

describe( 'theme activate lifecycle hooks', function (): void {
    it( 'fires theme.activating and theme.activated in order with slug + manifest payload', function (): void {
        $manifest = [
            'slug'    => 'hooks-activate',
            'name'    => 'Hooks Activate',
            'version' => '1.0.0',
        ];

        writeHookTheme( $this->themesPath, 'hooks-activate', $manifest, $this->testSlugs );

        $events = [];

        addAction( 'ap.cmsFramework.theme.activating', function ( string $slug, array $payload ) use ( &$events ): void {
            $events[] = ['activating', $slug, $payload];
        } );

        addAction( 'ap.cmsFramework.theme.activated', function ( string $slug, array $payload ) use ( &$events ): void {
            $events[] = ['activated', $slug, $payload];
        } );

        $this->manager->activateTheme( 'hooks-activate' );

        expect( $events )->toHaveCount( 2 );
        expect( $events[0][0] )->toBe( 'activating' );
        expect( $events[0][1] )->toBe( 'hooks-activate' );
        expect( $events[0][2] )->toMatchArray( $manifest );
        expect( $events[1][0] )->toBe( 'activated' );
        expect( $events[1][1] )->toBe( 'hooks-activate' );
        expect( $events[1][2] )->toMatchArray( $manifest );
    } );

    it( 'aborts activation and does not persist the active theme setting when a theme.activating listener throws', function (): void {
        writeHookTheme( $this->themesPath, 'hooks-veto-source', [
            'slug'    => 'hooks-veto-source',
            'name'    => 'Source',
            'version' => '1.0.0',
        ], $this->testSlugs );

        writeHookTheme( $this->themesPath, 'hooks-veto-target', [
            'slug'    => 'hooks-veto-target',
            'name'    => 'Target',
            'version' => '1.0.0',
        ], $this->testSlugs );

        // Establish a known starting state.
        app( SettingsManager::class )->updateSetting( 'themes.activeTheme', 'hooks-veto-source' );

        $activatedFired = false;

        addAction( 'ap.cmsFramework.theme.activating', function (): void {
            throw new RuntimeException( 'blocked' );
        } );

        addAction( 'ap.cmsFramework.theme.activated', function () use ( &$activatedFired ): void {
            $activatedFired = true;
        } );

        expect( fn () => $this->manager->activateTheme( 'hooks-veto-target' ) )
            ->toThrow( RuntimeException::class, 'blocked' );

        expect( $activatedFired )->toBeFalse();
        expect( app( SettingsManager::class )->getSetting( 'themes.activeTheme' ) )->toBe( 'hooks-veto-source' );
    } );

    it( 'does not fire any hooks when the theme does not exist', function (): void {
        $fired = false;

        addAction( 'ap.cmsFramework.theme.activating', function () use ( &$fired ): void {
            $fired = true;
        } );

        expect( fn () => $this->manager->activateTheme( 'does-not-exist' ) )
            ->toThrow( ThemeNotFoundException::class );

        expect( $fired )->toBeFalse();
    } );
} );
