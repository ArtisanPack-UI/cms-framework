<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeStylesheetReader;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->themesPath = base_path( 'themes' );
    $this->themeSlug  = 'test-theme';
    $this->themeRoot  = $this->themesPath . '/' . $this->themeSlug;

    File::ensureDirectoryExists( $this->themeRoot );
    File::put( $this->themeRoot . '/theme.json', json_encode( [
        'name' => 'Test',
        'slug' => $this->themeSlug,
    ] ) );
} );

afterEach( function (): void {
    File::deleteDirectory( $this->themeRoot );
} );

/**
 * Build a ThemeStylesheetReader whose ThemeManager mock returns the given
 * active-theme payload. Passing a non-array (e.g. null) makes
 * `getActiveTheme()` return null.
 */
function makeReader( mixed $activeTheme = null ): ThemeStylesheetReader
{
    $manager = mock( ThemeManager::class );
    $manager->shouldReceive( 'getActiveTheme' )->andReturn( $activeTheme );
    $manager->shouldReceive( 'validateSlug' )->andReturnUsing(
        static fn ( string $slug ): bool => (bool) preg_match( '/^[a-zA-Z0-9_-]+$/', $slug ),
    );
    $manager->shouldReceive( 'getThemesPath' )->andReturn( base_path( 'themes' ) );

    return new ThemeStylesheetReader( $manager );
}

describe( 'ThemeStylesheetReader::frontendStylesheet()', function (): void {
    it( 'returns an empty string when no theme is active', function (): void {
        expect( makeReader()->frontendStylesheet() )->toBe( '' );
    } );

    it( 'returns an empty string when style.css is absent', function (): void {
        expect( makeReader( ['slug' => $this->themeSlug] )->frontendStylesheet() )->toBe( '' );
    } );

    it( 'returns the file contents when style.css is present', function (): void {
        File::put( $this->themeRoot . '/style.css', 'body { color: #333; }' );

        expect( makeReader( ['slug' => $this->themeSlug] )->frontendStylesheet() )
            ->toBe( 'body { color: #333; }' );
    } );
} );

describe( 'ThemeStylesheetReader::editorStylesheet()', function (): void {
    it( 'returns the file contents when editor.css is present', function (): void {
        File::put( $this->themeRoot . '/editor.css', '.canvas-only { padding: 0; }' );

        expect( makeReader( ['slug' => $this->themeSlug] )->editorStylesheet() )
            ->toBe( '.canvas-only { padding: 0; }' );
    } );

    it( 'returns an empty string when editor.css is absent', function (): void {
        expect( makeReader( ['slug' => $this->themeSlug] )->editorStylesheet() )->toBe( '' );
    } );
} );

describe( 'ThemeStylesheetReader::read()', function (): void {
    it( 'accepts an arbitrary bare filename when the file exists', function (): void {
        File::put( $this->themeRoot . '/print.css', '@media print { a { color: black; } }' );

        expect( makeReader( ['slug' => $this->themeSlug] )->read( 'print.css' ) )
            ->toBe( '@media print { a { color: black; } }' );
    } );

    it( 'rejects a filename containing a path separator', function (): void {
        File::put( $this->themeRoot . '/print.css', 'x' );

        expect( makeReader( ['slug' => $this->themeSlug] )->read( '../style.css' ) )->toBe( '' )
            ->and( makeReader( ['slug' => $this->themeSlug] )->read( 'sub/style.css' ) )->toBe( '' )
            ->and( makeReader( ['slug' => $this->themeSlug] )->read( 'sub\\style.css' ) )->toBe( '' );
    } );

    it( 'rejects a filename containing traversal segments', function (): void {
        expect( makeReader( ['slug' => $this->themeSlug] )->read( '..style.css' ) )->toBe( '' );
    } );

    it( 'rejects an empty filename', function (): void {
        expect( makeReader( ['slug' => $this->themeSlug] )->read( '' ) )->toBe( '' );
    } );
} );

describe( 'ThemeStylesheetReader::readWrapped()', function (): void {
    it( 'wraps present contents with a devtools-friendly banner', function (): void {
        File::put( $this->themeRoot . '/editor.css', '.x { color: red; }' );

        expect( makeReader( ['slug' => $this->themeSlug] )->readWrapped( 'editor.css' ) )
            ->toBe( "/* === editor.css === */\n.x { color: red; }" );
    } );

    it( 'returns an empty string when the file is absent so array_filter drops it', function (): void {
        expect( makeReader( ['slug' => $this->themeSlug] )->readWrapped( 'editor.css' ) )->toBe( '' );
    } );
} );

describe( 'ThemeStylesheetReader::lastModified()', function (): void {
    it( 'returns null when no theme is active', function (): void {
        expect( makeReader()->lastModified() )->toBeNull();
    } );

    it( 'returns null when neither conventional file exists', function (): void {
        expect( makeReader( ['slug' => $this->themeSlug] )->lastModified() )->toBeNull();
    } );

    it( 'returns the freshest mtime across style.css and editor.css when present', function (): void {
        File::put( $this->themeRoot . '/style.css', 'x' );
        touch( $this->themeRoot . '/style.css', 1_700_000_000 );

        File::put( $this->themeRoot . '/editor.css', 'y' );
        touch( $this->themeRoot . '/editor.css', 1_700_500_000 );

        expect( makeReader( ['slug' => $this->themeSlug] )->lastModified() )->toBe( 1_700_500_000 );
    } );
} );

describe( 'ThemeStylesheetReader slug + traversal safety', function (): void {
    it( 'rejects a slug that fails ThemeManager::validateSlug()', function (): void {
        expect( makeReader( ['slug' => '../evil'] )->frontendStylesheet() )->toBe( '' )
            ->and( makeReader( ['slug' => 'foo/bar'] )->editorStylesheet() )->toBe( '' );
    } );

    it( 'rejects a missing slug key on the manifest', function (): void {
        expect( makeReader( ['name' => 'Test'] )->frontendStylesheet() )->toBe( '' );
    } );

    it( 'rejects a non-string slug', function (): void {
        expect( makeReader( ['slug' => 123] )->frontendStylesheet() )->toBe( '' );
    } );
} );

describe( 'ThemeStylesheetReader memoization', function (): void {
    it( 'calls ThemeManager::getActiveTheme() exactly once across multiple reads', function (): void {
        File::put( $this->themeRoot . '/style.css', 'a' );
        File::put( $this->themeRoot . '/editor.css', 'b' );

        $manager = mock( ThemeManager::class );
        $manager->shouldReceive( 'getActiveTheme' )
            ->once()
            ->andReturn( ['slug' => $this->themeSlug] );
        $manager->shouldReceive( 'validateSlug' )
            ->andReturnUsing( static fn ( string $s ): bool => true );
        $manager->shouldReceive( 'getThemesPath' )
            ->andReturn( base_path( 'themes' ) );

        $reader = new ThemeStylesheetReader( $manager );

        $reader->frontendStylesheet();
        $reader->editorStylesheet();
        $reader->readWrapped( 'style.css' );
        $reader->lastModified();

        // Mockery's `->once()` on the mock enforces the assertion at teardown.
        expect( true )->toBeTrue();
    } );
} );
