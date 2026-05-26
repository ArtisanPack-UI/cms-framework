<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

beforeEach( function (): void {
    $this->manager    = app( ThemeManager::class );
    $this->themesPath = base_path( 'themes' );
    $this->testSlugs  = [];

    File::ensureDirectoryExists( $this->themesPath );

    config()->set( 'cms.themes.cacheEnabled', false );
    Cache::forget( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ) );
} );

afterEach( function (): void {
    // Only delete subdirectories created by this test, not anything else
    // that may live under base_path('themes').
    foreach ( $this->testSlugs as $slug ) {
        $path = $this->themesPath . '/' . $slug;

        if ( File::exists( $path ) ) {
            File::deleteDirectory( $path );
        }
    }
} );

/**
 * Write a theme.json file into a fresh test theme directory and return its path.
 *
 * Tracks the slug into $slugs (passed by reference) so afterEach() can clean up
 * only the directories this test created.
 *
 * @param  string  $themesPath  Themes base path.
 * @param  string  $slug  Theme slug (also the subdirectory name).
 * @param  array  $manifest  theme.json contents.
 * @param  array  $slugs  Reference to a slug-tracking array.
 */
function writeTheme( string $themesPath, string $slug, array $manifest, array &$slugs ): string
{
    $slugs[] = $slug;
    $path    = $themesPath . '/' . $slug;
    File::ensureDirectoryExists( $path );
    File::put( $path . '/theme.json', json_encode( $manifest, JSON_PRETTY_PRINT ) );

    return $path;
}

describe( 'discoverThemes() with extended theme.json validation', function (): void {
    it( 'discovers themes with only legacy cms-framework manifest fields without a warning', function (): void {
        Log::spy();

        writeTheme( $this->themesPath, 'legacy-theme', [
            'name'        => 'Legacy Theme',
            'slug'        => 'legacy-theme',
            'version'     => '1.0.0',
            'description' => 'Legacy.',
            'author'      => 'Test',
            'screenshot'  => 'screenshot.png',
        ], $this->testSlugs );

        $themes = $this->manager->discoverThemes();
        $slugs  = array_column( $themes, 'slug' );

        expect( $slugs )->toContain( 'legacy-theme' );
        Log::shouldNotHaveReceived( 'warning' );
    } );

    it( 'discovers themes carrying full WP-shape settings, styles, customTemplates, templateParts, patterns', function (): void {
        writeTheme( $this->themesPath, 'phase-h-theme', [
            'name'     => 'Phase H Theme',
            'slug'     => 'phase-h-theme',
            'version'  => '1.0.0',
            'settings' => [
                'color' => [
                    'palette' => [
                        ['slug' => 'primary', 'name' => 'Primary', 'color' => '#3b82f6'],
                    ],
                ],
            ],
            'styles' => [
                'color' => ['background' => '#ffffff'],
            ],
            'customTemplates' => [
                ['name' => 'page-with-sidebar', 'title' => 'Page with sidebar'],
            ],
            'templateParts' => [
                ['name' => 'header', 'title' => 'Header', 'area' => 'header'],
            ],
            'patterns' => ['my-namespace/cta'],
        ], $this->testSlugs );

        $themes = $this->manager->discoverThemes();
        $slugs  = array_column( $themes, 'slug' );

        expect( $slugs )->toContain( 'phase-h-theme' );
    } );

    it( 'discovers themes carrying menus.locations as a cms-framework extension', function (): void {
        writeTheme( $this->themesPath, 'menus-theme', [
            'name'    => 'Menus Theme',
            'slug'    => 'menus-theme',
            'version' => '1.0.0',
            'menus'   => [
                'locations' => [
                    'primary' => 'Primary Menu',
                    'footer'  => 'Footer Menu',
                ],
            ],
        ], $this->testSlugs );

        $themes = $this->manager->discoverThemes();
        $slugs  = array_column( $themes, 'slug' );

        expect( $slugs )->toContain( 'menus-theme' );
    } );

    it( 'rejects themes with invalid settings shapes and logs a warning naming the offending key', function (): void {
        Log::spy();

        writeTheme( $this->themesPath, 'broken-theme', [
            'name'     => 'Broken Theme',
            'slug'     => 'broken-theme',
            'version'  => '1.0.0',
            'settings' => [
                'color' => [
                    'palette' => 'not-an-array',
                ],
            ],
        ], $this->testSlugs );

        $themes = $this->manager->discoverThemes();
        $slugs  = array_column( $themes, 'slug' );

        expect( $slugs )->not->toContain( 'broken-theme' );

        Log::shouldHaveReceived( 'warning' )
            ->withArgs( function ( string $message, array $context ): bool {
                return str_contains( $message, 'schema validation' )
                    && isset( $context['offendingKey'] )
                    && str_contains( (string) $context['offendingKey'], 'settings' );
            } )
            ->once();
    } );

    it( 'rejects themes whose theme.json is malformed JSON', function (): void {
        Log::spy();

        $this->testSlugs[] = 'malformed-theme';
        $path              = $this->themesPath . '/malformed-theme';
        File::ensureDirectoryExists( $path );
        File::put( $path . '/theme.json', '{not valid json' );

        $themes = $this->manager->discoverThemes();
        $slugs  = array_column( $themes, 'slug' );

        expect( $slugs )->not->toContain( 'malformed-theme' );

        Log::shouldHaveReceived( 'warning' )
            ->withArgs( function ( string $message ): bool {
                return str_contains( $message, 'parse failed' );
            } )
            ->once();
    } );
});
