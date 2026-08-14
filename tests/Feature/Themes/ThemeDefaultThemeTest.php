<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

beforeEach( function (): void {
    $this->manager    = app( ThemeManager::class );
    $this->themesPath = base_path( 'themes' );
    $this->testSlugs  = [];

    File::ensureDirectoryExists( $this->themesPath );

    config()->set( 'cms.themes.cacheEnabled', false );
    Cache::forget( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ) );
} );

afterEach( function (): void {
    foreach ( $this->testSlugs as $slug ) {
        $path = $this->themesPath . '/' . $slug;
        if ( File::exists( $path ) ) {
            File::deleteDirectory( $path );
        }
    }
} );

/**
 * Write a discoverable theme directory without activating it.
 */
function writeDiscoverableTheme( string $themesPath, string $slug, array &$slugs ): string
{
    $slugs[] = $slug;

    $path = $themesPath . '/' . $slug;
    File::ensureDirectoryExists( $path );
    File::put( $path . '/theme.json', json_encode( [
        'slug'    => $slug,
        'name'    => ucfirst( $slug ),
        'version' => '1.0.0',
    ] ) );

    return $path;
}

describe( 'cms.themes.default', function (): void {
    it( 'ships as null so no consumer theme slug is assumed', function (): void {
        $shipped = require __DIR__ . '/../../../src/Modules/Themes/config/themes.php';

        expect( $shipped['default'] )->toBeNull();
    } );

    it( 'is null on a booted application with no CMS_DEFAULT_THEME set', function (): void {
        expect( config( 'cms.themes.default' ) )->toBeNull();
    } );

    it( 'reads the CMS_DEFAULT_THEME env var by that exact name', function (): void {
        // Pins the env key itself. Every other test drives the config value
        // directly, so a rename to CMS_THEMES_DEFAULT would leave the suite
        // green while silently breaking every consumer's .env.
        putenv( 'CMS_DEFAULT_THEME=env-driven-theme' );

        try {
            $config = require __DIR__ . '/../../../src/Modules/Themes/config/themes.php';

            expect( $config['default'] )->toBe( 'env-driven-theme' );
        } finally {
            putenv( 'CMS_DEFAULT_THEME' );
        }
    } );

    it( 'treats a set-but-empty CMS_DEFAULT_THEME as no default', function (): void {
        putenv( 'CMS_DEFAULT_THEME=' );

        try {
            $config = require __DIR__ . '/../../../src/Modules/Themes/config/themes.php';

            config()->set( 'cms.themes.default', $config['default'] );

            expect( app( ThemeManager::class )->getActiveTheme() )->toBeNull();
        } finally {
            putenv( 'CMS_DEFAULT_THEME' );
        }
    } );
} );

describe( 'ThemeManager::getActiveTheme() with no default configured', function (): void {
    it( 'returns null rather than looking up a hard-coded slug', function (): void {
        writeDiscoverableTheme( $this->themesPath, 'some-installed-theme', $this->testSlugs );

        expect( $this->manager->getActiveTheme() )->toBeNull();
    } );

    it( 'returns null even when a theme named digital-shopfront is installed', function (): void {
        // The slug the framework used to hard-code. Installed but never
        // activated, it must not be picked up implicitly — this is the
        // regression #125 exists to prevent.
        writeDiscoverableTheme( $this->themesPath, 'digital-shopfront', $this->testSlugs );

        expect( $this->manager->getActiveTheme() )->toBeNull();
    } );

    it( 'still returns the stored theme once one is activated', function (): void {
        writeDiscoverableTheme( $this->themesPath, 'activated-theme', $this->testSlugs );

        $this->manager->activateTheme( 'activated-theme' );

        expect( $this->manager->getActiveTheme() )
            ->toBeArray()
            ->and( $this->manager->getActiveTheme()['slug'] )->toBe( 'activated-theme' );
    } );

    it( 'falls back to the configured default when one is named', function (): void {
        writeDiscoverableTheme( $this->themesPath, 'configured-default', $this->testSlugs );

        config()->set( 'cms.themes.default', 'configured-default' );

        expect( $this->manager->getActiveTheme() )
            ->toBeArray()
            ->and( $this->manager->getActiveTheme()['slug'] )->toBe( 'configured-default' );
    } );

    it( 'prefers the stored setting over the configured default', function (): void {
        writeDiscoverableTheme( $this->themesPath, 'stored-theme', $this->testSlugs );
        writeDiscoverableTheme( $this->themesPath, 'fallback-theme', $this->testSlugs );

        config()->set( 'cms.themes.default', 'fallback-theme' );
        app( SettingsManager::class )->updateSetting( 'themes.activeTheme', 'stored-theme' );

        expect( $this->manager->getActiveTheme()['slug'] )->toBe( 'stored-theme' );
    } );
} );

describe( 'ThemeManager::markActiveTheme() with no default configured', function (): void {
    it( 'flags every discovered theme inactive', function (): void {
        writeDiscoverableTheme( $this->themesPath, 'theme-one', $this->testSlugs );
        writeDiscoverableTheme( $this->themesPath, 'theme-two', $this->testSlugs );
        // Includes the formerly hard-coded slug: it gets no special treatment.
        writeDiscoverableTheme( $this->themesPath, 'digital-shopfront', $this->testSlugs );

        $themes = $this->manager->discoverThemes();

        expect( $themes )->toHaveCount( 3 );

        foreach ( $themes as $theme ) {
            expect( $theme['is_active'] )->toBeFalse();
        }
    } );

    it( 'flags the configured default active when one is named', function (): void {
        writeDiscoverableTheme( $this->themesPath, 'theme-one', $this->testSlugs );
        writeDiscoverableTheme( $this->themesPath, 'theme-two', $this->testSlugs );

        config()->set( 'cms.themes.default', 'theme-two' );

        $active = collect( $this->manager->discoverThemes() )
            ->filter( fn ( array $theme ): bool => true === $theme['is_active'] )
            ->pluck( 'slug' )
            ->all();

        expect( $active )->toBe( ['theme-two'] );
    } );
} );

describe( 'ThemeManager::registerThemeViewPath() with no default configured', function (): void {
    it( 'prepends no view path', function (): void {
        writeDiscoverableTheme( $this->themesPath, 'unactivated-theme', $this->testSlugs );

        $before = View::getFinder()->getPaths();

        $this->manager->registerThemeViewPath();

        expect( View::getFinder()->getPaths() )->toBe( $before );
    } );

    it( 'prepends the configured default when one is named', function (): void {
        $path = writeDiscoverableTheme( $this->themesPath, 'view-path-theme', $this->testSlugs );

        config()->set( 'cms.themes.default', 'view-path-theme' );

        $this->manager->registerThemeViewPath();

        expect( View::getFinder()->getPaths() )->toContain( $path );
    } );
} );
