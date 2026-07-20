<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Support\ThemeLoader;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->themesPath = base_path( 'themes' );
    $this->testSlugs  = [];

    File::ensureDirectoryExists( $this->themesPath );

    config()->set( 'cms.themes.cacheEnabled', false );
    Cache::forget( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ) );

    // Clear filter listeners we may register during a test.
    foreach ( ['ap.themes.frontendStyles', 'ap.themes.editorStyles', 'ap.themes.frontendScripts'] as $hook ) {
        removeAllFilters( $hook );
    }
} );

afterEach( function (): void {
    foreach ( $this->testSlugs as $slug ) {
        $path = $this->themesPath . '/' . $slug;
        if ( File::exists( $path ) ) {
            File::deleteDirectory( $path );
        }
    }

    foreach ( ['ap.themes.frontendStyles', 'ap.themes.editorStyles', 'ap.themes.frontendScripts'] as $hook ) {
        removeAllFilters( $hook );
    }
} );

/**
 * Write a theme.json into a fresh directory and mark it active so
 * ThemeLoader / GlobalStyles / etc. pick it up.
 */
function writeActiveTheme( string $themesPath, string $slug, array &$slugs, ?string $themePhp = null, array $extraManifest = [] ): void
{
    $slugs[] = $slug;

    $path = $themesPath . '/' . $slug;
    File::ensureDirectoryExists( $path );
    File::put( $path . '/theme.json', json_encode( array_merge( [
        'slug'    => $slug,
        'name'    => ucfirst( $slug ),
        'version' => '1.0.0',
    ], $extraManifest ) ) );

    if ( null !== $themePhp ) {
        File::put( $path . '/Theme.php', $themePhp );
    }

    app( SettingsManager::class )->updateSetting( 'themes.activeTheme', $slug );

    app( ThemeLoader::class )->reset();
}

describe( 'ThemeLoader discovery', function (): void {
    it( 'returns null when the active theme ships no Theme.php', function (): void {
        writeActiveTheme( $this->themesPath, 'no-php-theme', $this->testSlugs );

        expect( app( ThemeLoader::class )->activeTheme() )->toBeNull();
    } );

    it( 'instantiates the conventional class and calls the lifecycle methods in order', function (): void {
        $slug = 'lifecycle-theme';

        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs, <<<'PHP'
<?php

namespace Themes\LifecycleTheme;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme as BaseTheme;

class Theme extends BaseTheme
{
    public function registerImageSizes(): void
    {
        $GLOBALS['__theme_lifecycle_events'][] = 'images';
    }

    public function boot(): void
    {
        $GLOBALS['__theme_lifecycle_events'][] = 'boot';
    }

    public function extend(): void
    {
        $GLOBALS['__theme_lifecycle_events'][] = 'extend';
    }
}
PHP );

        $GLOBALS['__theme_lifecycle_events'] = [];

        $theme = app( ThemeLoader::class )->activeTheme();

        expect( $theme )->not->toBeNull();
        expect( $theme->slug() )->toBe( $slug );
        expect( $GLOBALS['__theme_lifecycle_events'] )->toBe( ['images', 'boot', 'extend'] );

        unset( $GLOBALS['__theme_lifecycle_events'] );
    } );

    it( 'caches the theme instance so repeat calls do not re-boot', function (): void {
        writeActiveTheme( $this->themesPath, 'cache-theme', $this->testSlugs, <<<'PHP'
<?php

namespace Themes\CacheTheme;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme as BaseTheme;

class Theme extends BaseTheme
{
    public function boot(): void
    {
        $GLOBALS['__theme_cache_boots'] = ($GLOBALS['__theme_cache_boots'] ?? 0) + 1;
    }
}
PHP );

        $GLOBALS['__theme_cache_boots'] = 0;

        $loader = app( ThemeLoader::class );
        $loader->activeTheme();
        $loader->activeTheme();
        $loader->activeTheme();

        expect( $GLOBALS['__theme_cache_boots'] )->toBe( 1 );

        unset( $GLOBALS['__theme_cache_boots'] );
    } );

    it( 'honors a manifest themeClass override for custom namespaces', function (): void {
        writeActiveTheme( $this->themesPath, 'override-theme', $this->testSlugs, <<<'PHP'
<?php

namespace Vendor\Custom;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme as BaseTheme;

class OverrideTheme extends BaseTheme
{
    public function frontendStyles(): array
    {
        return ['override.css'];
    }
}
PHP , ['themeClass' => 'Vendor\\Custom\\OverrideTheme'] );

        $theme = app( ThemeLoader::class )->activeTheme();

        expect( $theme )->not->toBeNull();
        expect( $theme->frontendStyles() )->toBe( ['override.css'] );
    } );

    it( 'logs and returns null when the loaded class does not extend the Theme base', function (): void {
        writeActiveTheme( $this->themesPath, 'not-a-theme', $this->testSlugs, <<<'PHP'
<?php

namespace Themes\NotATheme;

class Theme
{
    // Missing base class.
}
PHP );

        expect( app( ThemeLoader::class )->activeTheme() )->toBeNull();
    } );

    it( 'logs and returns null when boot() throws so a broken theme does not crash the app', function (): void {
        writeActiveTheme( $this->themesPath, 'crashing-theme', $this->testSlugs, <<<'PHP'
<?php

namespace Themes\CrashingTheme;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme as BaseTheme;

class Theme extends BaseTheme
{
    public function boot(): void
    {
        throw new \RuntimeException('theme is broken');
    }
}
PHP );

        expect( app( ThemeLoader::class )->activeTheme() )->toBeNull();
    } );

    it( 'refuses a themeClass override that resolves to a class declared outside the theme file', function (): void {
        // Define a rogue Theme subclass in this test process, then have an
        // uploaded theme.json try to point at it. The reflection-based
        // provenance check must reject the load.
        eval( 'namespace Rogue\\Vendor; class ImpostorTheme extends \\ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Contracts\\Theme { public function boot(): void { $GLOBALS["__theme_impostor_booted"] = true; } }' );

        writeActiveTheme( $this->themesPath, 'impostor-theme', $this->testSlugs, <<<'PHP'
<?php
// This file exists so ThemeLoader loads it, but declares an unrelated
// class — the manifest override tries to redirect discovery elsewhere.
PHP , ['themeClass' => 'Rogue\\Vendor\\ImpostorTheme'] );

        $GLOBALS['__theme_impostor_booted'] = false;

        expect( app( ThemeLoader::class )->activeTheme() )->toBeNull();
        expect( $GLOBALS['__theme_impostor_booted'] )->toBeFalse();

        unset( $GLOBALS['__theme_impostor_booted'] );
    } );

    it( 'rejects a malformed themeClass override before touching the autoloader', function (): void {
        writeActiveTheme( $this->themesPath, 'bad-classname-theme', $this->testSlugs, <<<'PHP'
<?php
// Intentionally empty; the override should be rejected on shape alone.
PHP , ['themeClass' => 'Not A Valid; Class Name'] );

        expect( app( ThemeLoader::class )->activeTheme() )->toBeNull();
    } );
} );

describe( 'Blade asset directives', function (): void {
    it( '@themeFrontendStyles renders the theme-declared styles as link tags', function (): void {
        writeActiveTheme( $this->themesPath, 'styles-theme', $this->testSlugs, <<<'PHP'
<?php

namespace Themes\StylesTheme;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme as BaseTheme;

class Theme extends BaseTheme
{
    public function frontendStyles(): array
    {
        return [
            'brand' => 'brand.css',
            ['src' => 'print.css', 'media' => 'print'],
        ];
    }
}
PHP );

        app( ThemeLoader::class )->activeTheme();

        $html = Blade::render( '@themeFrontendStyles' );

        expect( $html )->toContain( 'id="brand-css"' )
            ->toContain( '/themes/styles-theme/assets/brand.css' )
            ->toContain( 'id="print-css"' )
            ->toContain( 'media="print"' );
    } );

    it( '@themeFrontendStyles emits an empty string when no theme is loaded', function (): void {
        // No Theme.php shipped: loader returns null.
        writeActiveTheme( $this->themesPath, 'silent-theme', $this->testSlugs );

        expect( Blade::render( '@themeFrontendStyles' ) )->toBe( '' );
    } );

    it( 'ap.themes.frontendStyles filter can add and mutate styles', function (): void {
        writeActiveTheme( $this->themesPath, 'filter-theme', $this->testSlugs, <<<'PHP'
<?php

namespace Themes\FilterTheme;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme as BaseTheme;

class Theme extends BaseTheme
{
    public function frontendStyles(): array
    {
        return ['base.css'];
    }
}
PHP );

        addFilter( 'ap.themes.frontendStyles', function ( array $entries, string $slug ): array {
            $entries[] = ['src' => 'https://cdn.example/vendor.css', 'ver' => '1.0'];

            return $entries;
        } );

        app( ThemeLoader::class )->activeTheme();

        $html = Blade::render( '@themeFrontendStyles' );

        expect( $html )->toContain( '/themes/filter-theme/assets/base.css' )
            ->toContain( 'https://cdn.example/vendor.css' );
    } );

    it( '@themeFrontendScripts renders script tags with defer support', function (): void {
        writeActiveTheme( $this->themesPath, 'scripts-theme', $this->testSlugs, <<<'PHP'
<?php

namespace Themes\ScriptsTheme;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme as BaseTheme;

class Theme extends BaseTheme
{
    public function frontendScripts(): array
    {
        return [
            'menu' => ['src' => 'menu.js', 'defer' => true],
        ];
    }
}
PHP );

        app( ThemeLoader::class )->activeTheme();

        $html = Blade::render( '@themeFrontendScripts' );

        expect( $html )->toContain( 'id="menu-js"' )
            ->toContain( '/themes/scripts-theme/assets/menu.js' )
            ->toMatch( '/<script[^>]* defer[ >]/' );
    } );
} );

describe( 'Theme asset HTTP route', function (): void {
    it( 'serves a CSS file from the theme assets directory', function (): void {
        $slug = 'served-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets' );
        File::put( $this->themesPath . '/' . $slug . '/assets/main.css', 'body { color: red; }' );

        $response = $this->get( '/themes/' . $slug . '/assets/main.css' );

        $response->assertOk();
        expect( $response->headers->get( 'cache-control' ) )->toContain( 'max-age' );
        expect( trim( $response->streamedContent() ?: (string) $response->getContent() ) )->toBe( 'body { color: red; }' );
    } );

    it( 'serves CSS with the text/css Content-Type, not text/plain', function (): void {
        $slug = 'mime-css-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets' );
        File::put( $this->themesPath . '/' . $slug . '/assets/main.css', 'body {}' );

        $response = $this->get( '/themes/' . $slug . '/assets/main.css' );

        $response->assertOk();
        expect( $response->headers->get( 'content-type' ) )->toStartWith( 'text/css' );
    } );

    it( 'serves JS with the application/javascript Content-Type', function (): void {
        $slug = 'mime-js-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets' );
        File::put( $this->themesPath . '/' . $slug . '/assets/main.js', 'var x = 1;' );

        $response = $this->get( '/themes/' . $slug . '/assets/main.js' );

        $response->assertOk();
        expect( $response->headers->get( 'content-type' ) )->toStartWith( 'application/javascript' );
    } );

    it( 'serves a nested asset file', function (): void {
        $slug = 'nested-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets/fonts' );
        File::put( $this->themesPath . '/' . $slug . '/assets/fonts/inter.woff2', 'FONT_BYTES' );

        $this->get( '/themes/' . $slug . '/assets/fonts/inter.woff2' )->assertOk();
    } );

    it( 'returns 404 for a path traversal attempt', function (): void {
        $slug = 'traversal-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        // Put a file OUTSIDE assets/ that the traversal would target.
        File::put( $this->themesPath . '/' . $slug . '/theme.json.attack', 'secret' );

        // Route param uses .* so Laravel does not URL-normalize the traversal
        // for us — we must send the literal segment.
        $this->get( '/themes/' . $slug . '/assets/..%2Ftheme.json.attack' )
            ->assertNotFound();
    } );

    it( 'returns 404 for a backslash traversal attempt', function (): void {
        $slug = 'backslash-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::put( $this->themesPath . '/' . $slug . '/theme.json.attack', 'secret' );

        // Backslash-based traversal must be rejected by the same check on
        // any platform, not only by realpath's after-the-fact boundary.
        $this->get( '/themes/' . $slug . '/assets/..%5Ctheme.json.attack' )
            ->assertNotFound();
    } );

    it( 'returns 404 for a symlink that escapes the assets directory', function (): void {
        if ( ! function_exists( 'symlink' ) ) {
            $this->markTestSkipped( 'symlink() unavailable on this platform.' );
        }

        $slug = 'symlink-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets' );
        File::put( $this->themesPath . '/' . $slug . '/secret.css', 'body{}' );

        // Symlink a file inside assets/ to a file outside assets/. The
        // realpath-anchored boundary check must reject the request.
        $link   = $this->themesPath . '/' . $slug . '/assets/escape.css';
        $target = $this->themesPath . '/' . $slug . '/secret.css';

        if ( file_exists( $link ) ) {
            unlink( $link );
        }

        if ( false === @symlink( $target, $link ) ) {
            $this->markTestSkipped( 'symlink() creation not permitted in this environment.' );
        }

        $this->get( '/themes/' . $slug . '/assets/escape.css' )->assertNotFound();
    } );

    it( 'sends X-Content-Type-Options: nosniff on every asset response', function (): void {
        $slug = 'nosniff-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets' );
        File::put( $this->themesPath . '/' . $slug . '/assets/main.css', 'body{}' );

        $response = $this->get( '/themes/' . $slug . '/assets/main.css' );

        $response->assertOk();
        expect( $response->headers->get( 'x-content-type-options' ) )->toBe( 'nosniff' );
    } );

    it( 'hardens SVG responses with CSP sandbox + attachment disposition', function (): void {
        $slug = 'svg-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets' );
        File::put(
            $this->themesPath . '/' . $slug . '/assets/logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $response = $this->get( '/themes/' . $slug . '/assets/logo.svg' );

        $response->assertOk();
        expect( $response->headers->get( 'content-type' ) )->toStartWith( 'image/svg+xml' );
        expect( $response->headers->get( 'content-security-policy' ) )->toContain( 'sandbox' );
        expect( $response->headers->get( 'content-disposition' ) )->toStartWith( 'attachment' );
    } );

    it( 'returns 404 for a disallowed extension', function (): void {
        $slug = 'disallowed-ext-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets' );
        File::put( $this->themesPath . '/' . $slug . '/assets/hack.php', '<?php echo "boom";' );

        $this->get( '/themes/' . $slug . '/assets/hack.php' )->assertNotFound();
    } );

    it( 'returns 404 for an invalid slug', function (): void {
        $this->get( '/themes/bad..slug/assets/main.css' )->assertNotFound();
    } );

    it( 'returns 404 for a missing file', function (): void {
        $slug = 'missing-file-theme';
        writeActiveTheme( $this->themesPath, $slug, $this->testSlugs );

        File::ensureDirectoryExists( $this->themesPath . '/' . $slug . '/assets' );

        $this->get( '/themes/' . $slug . '/assets/nope.css' )->assertNotFound();
    } );
} );

describe( 'Theme::assetUrl helper', function (): void {
    it( 'builds an absolute URL to a theme asset', function (): void {
        writeActiveTheme( $this->themesPath, 'helper-theme', $this->testSlugs, <<<'PHP'
<?php

namespace Themes\HelperTheme;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme as BaseTheme;

class Theme extends BaseTheme
{
}
PHP );

        $theme = app( ThemeLoader::class )->activeTheme();

        expect( $theme )->not->toBeNull();
        expect( $theme->assetUrl( 'main.css' ) )->toContain( '/themes/helper-theme/assets/main.css' );
    } );
});
