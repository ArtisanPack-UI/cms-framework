<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeInstallationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeValidationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->manager    = app( ThemeManager::class );
    $this->themesPath = base_path( 'themes' );
    $this->tmpPath    = storage_path( 'app/themes-test-tmp' );
    $this->testSlugs  = [];

    File::ensureDirectoryExists( $this->themesPath );
    File::ensureDirectoryExists( $this->tmpPath );

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

    if ( File::isDirectory( $this->tmpPath ) ) {
        File::deleteDirectory( $this->tmpPath );
    }
} );

/**
 * Build a ZIP with a single theme directory and given theme.json contents.
 */
function buildThemeZip( string $tmpPath, string $slug, ?array $manifest, array &$slugs, array $extraEntries = [] ): string
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

    if ( null !== $manifest ) {
        $zip->addFromString( $slug . '/theme.json', json_encode( $manifest ) );
    }

    foreach ( $extraEntries as $name => $contents ) {
        $zip->addFromString( $name, $contents );
    }

    $zip->close();

    return $zipPath;
}

describe( 'ThemeManager::installFromZip()', function (): void {
    it( 'installs a valid theme ZIP and returns the parsed manifest', function (): void {
        $manifest = [
            'slug'    => 'upload-theme',
            'name'    => 'Upload Theme',
            'version' => '1.0.0',
        ];

        $zipPath = buildThemeZip( $this->tmpPath, 'upload-theme', $manifest, $this->testSlugs );

        $result = $this->manager->installFromZip( $zipPath );

        expect( $result )->toBeArray()
            ->and( $result['slug'] )->toBe( 'upload-theme' )
            ->and( $result['name'] )->toBe( 'Upload Theme' )
            ->and( File::exists( $this->themesPath . '/upload-theme/theme.json' ) )->toBeTrue();
    } );

    it( 'clears the theme discovery cache on success', function (): void {
        Cache::put( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ), [['slug' => 'stale']], 60 );

        $manifest = [
            'slug'    => 'cache-bust-theme',
            'name'    => 'Cache Bust Theme',
            'version' => '1.0.0',
        ];

        $zipPath = buildThemeZip( $this->tmpPath, 'cache-bust-theme', $manifest, $this->testSlugs );

        $this->manager->installFromZip( $zipPath );

        expect( Cache::has( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ) ) )->toBeFalse();
    } );

    it( 'rejects non-ZIP MIME types', function (): void {
        $fakeFile = $this->tmpPath . '/not-a-zip.zip';
        File::put( $fakeFile, '<?php echo "hi"; ?>' );

        expect( fn () => $this->manager->installFromZip( $fakeFile ) )
            ->toThrow( ThemeValidationException::class );
    } )->skip(); // dynamic MIME detection varies by system — same caveat as Plugins

    it( 'rejects a ZIP without theme.json', function (): void {
        $zipPath = buildThemeZip(
            $this->tmpPath,
            'no-manifest',
            null,
            $this->testSlugs,
            ['no-manifest/style.css' => 'body{}'],
        );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'theme.json' );
    } );

    it( 'rejects a corrupted ZIP', function (): void {
        $corrupted = $this->tmpPath . '/corrupted.zip';
        File::put( $corrupted, "PK\x03\x04not actually a zip body" );

        expect( fn () => $this->manager->installFromZip( $corrupted ) )
            ->toThrow( ThemeValidationException::class );
    } )->skip(); // MIME detection on synthetic ZIP bytes varies by system

    it( 'rejects a ZIP whose slug is already installed', function (): void {
        $this->testSlugs[] = 'duplicate-theme';
        $existingPath      = $this->themesPath . '/duplicate-theme';
        File::ensureDirectoryExists( $existingPath );
        File::put( $existingPath . '/theme.json', json_encode( [
            'slug'    => 'duplicate-theme',
            'name'    => 'Duplicate Theme',
            'version' => '1.0.0',
        ] ) );

        $manifest = [
            'slug'    => 'duplicate-theme',
            'name'    => 'Duplicate Theme',
            'version' => '1.0.0',
        ];
        $zipPath  = buildThemeZip( $this->tmpPath, 'duplicate-theme', $manifest, $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeInstallationException::class, 'already installed' );
    } );

    it( 'rejects a ZIP containing a path-traversal entry', function (): void {
        $slug              = 'slip-theme';
        $this->testSlugs[] = $slug;

        $zipPath = $this->tmpPath . '/' . $slug . '.zip';
        $zip     = new ZipArchive;
        if ( true !== $zip->open( $zipPath, ZipArchive::CREATE ) ) {
            throw new RuntimeException( "Failed to create test ZIP at {$zipPath}" );
        }
        $zip->addEmptyDir( $slug );
        $zip->addFromString( $slug . '/theme.json', json_encode( [
            'slug'    => $slug,
            'name'    => 'Slip Theme',
            'version' => '1.0.0',
        ] ) );
        $zip->addFromString( $slug . '/../../etc/escaped.txt', 'pwned' );
        $zip->close();

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeInstallationException::class, 'escapes the themes directory' );

        // Ensure nothing was extracted under the themes base.
        expect( File::exists( $this->themesPath . '/' . $slug ) )->toBeFalse();
    } );

    it( 'rejects a ZIP whose entries are not all rooted under the derived slug', function (): void {
        $slug                  = 'multi-root-theme';
        $this->testSlugs[]     = $slug;
        $this->testSlugs[]     = 'other-theme';

        $zipPath = $this->tmpPath . '/' . $slug . '.zip';
        $zip     = new ZipArchive;
        if ( true !== $zip->open( $zipPath, ZipArchive::CREATE ) ) {
            throw new RuntimeException( "Failed to create test ZIP at {$zipPath}" );
        }
        $zip->addEmptyDir( $slug );
        $zip->addFromString( $slug . '/theme.json', json_encode( [
            'slug'    => $slug,
            'name'    => 'Multi Root Theme',
            'version' => '1.0.0',
        ] ) );
        // Second top-level directory: would survive a rollback that only deletes $slug.
        $zip->addFromString( 'other-theme/payload.txt', 'extra' );
        $zip->close();

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeInstallationException::class, 'escapes the themes directory' );

        expect( File::exists( $this->themesPath . '/' . $slug ) )->toBeFalse();
        expect( File::exists( $this->themesPath . '/other-theme' ) )->toBeFalse();
    } );

    it( 'rolls back the extracted directory when the manifest fails schema validation', function (): void {
        // Missing required "version" field triggers the legacy/cms-framework validator.
        $manifest = [
            'slug' => 'broken-theme',
            'name' => 'Broken Theme',
        ];

        $zipPath = buildThemeZip( $this->tmpPath, 'broken-theme', $manifest, $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class );

        // Rollback: the extracted directory should not remain on disk.
        expect( File::exists( $this->themesPath . '/broken-theme' ) )->toBeFalse();
    } )->skip(); // depends on schema strictness — covered by ThemeManagerSchemaValidationTest
} );
