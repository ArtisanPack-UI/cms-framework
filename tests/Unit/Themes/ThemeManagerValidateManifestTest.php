<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeValidationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->manager    = app( ThemeManager::class );
    $this->themesPath = base_path( 'themes' );
    $this->tmpPath    = storage_path( 'app/themes-validate-tmp' );
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
 * Build a theme ZIP with the given manifest contents.
 */
function buildValidateZip( string $tmpPath, string $slug, array $manifest, array &$slugs ): string
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

describe( 'ThemeManager::validateManifest() via installFromZip()', function (): void {
    it( 'accepts a minimal valid manifest with slug, name, and version', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'minimal-theme', [
            'slug'    => 'minimal-theme',
            'name'    => 'Minimal Theme',
            'version' => '1.0.0',
        ], $this->testSlugs );

        $result = $this->manager->installFromZip( $zipPath );

        expect( $result['slug'] )->toBe( 'minimal-theme' );
    } );

    it( 'rejects a manifest missing slug', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'no-slug', [
            'name'    => 'No Slug',
            'version' => '1.0.0',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'Missing required field: slug' );

        expect( File::exists( $this->themesPath . '/no-slug' ) )->toBeFalse();
    } );

    it( 'rejects a manifest missing name', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'no-name', [
            'slug'    => 'no-name',
            'version' => '1.0.0',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'Missing required field: name' );
    } );

    it( 'rejects a manifest missing version', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'no-version', [
            'slug' => 'no-version',
            'name' => 'No Version',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'Missing required field: version' );
    } );

    it( 'rejects a manifest with an invalid slug format', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'bad-slug-theme', [
            'slug'    => 'bad slug!',
            'name'    => 'Bad Slug Theme',
            'version' => '1.0.0',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'Invalid slug format' );
    } );

    it( 'rejects a manifest with a non-semver version', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'bad-version', [
            'slug'    => 'bad-version',
            'name'    => 'Bad Version',
            'version' => '1.x',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'Invalid version format' );
    } );

    it( 'rejects a manifest whose version contains an injection suffix', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'inject-version', [
            'slug'    => 'inject-version',
            'name'    => 'Injection',
            'version' => "1.0.0'; DROP TABLE",
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'Invalid version format' );
    } );

    it( 'accepts a screenshot with an allowed extension', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'shot-theme', [
            'slug'       => 'shot-theme',
            'name'       => 'Shot Theme',
            'version'    => '1.0.0',
            'screenshot' => 'screenshot.png',
        ], $this->testSlugs );

        $result = $this->manager->installFromZip( $zipPath );

        expect( $result['screenshot'] )->toBe( 'screenshot.png' );
    } );

    it( 'rejects a screenshot containing a forward slash', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'slash-shot', [
            'slug'       => 'slash-shot',
            'name'       => 'Slash Shot',
            'version'    => '1.0.0',
            'screenshot' => '../escape.png',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, "Field 'screenshot' must be a filename" );
    } );

    it( 'rejects a screenshot containing a backslash', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'backslash-shot', [
            'slug'       => 'backslash-shot',
            'name'       => 'Backslash Shot',
            'version'    => '1.0.0',
            'screenshot' => '..\\escape.png',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, "Field 'screenshot' must be a filename" );
    } );

    it( 'rejects a screenshot with a disallowed extension', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'bad-ext-shot', [
            'slug'       => 'bad-ext-shot',
            'name'       => 'Bad Ext Shot',
            'version'    => '1.0.0',
            'screenshot' => 'screenshot.gif',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'allowed extension' );
    } );

    it( 'accepts a manifest with all allowed screenshot extensions', function ( string $extension ): void {
        $slug    = "ext-{$extension}-theme";
        $zipPath = buildValidateZip( $this->tmpPath, $slug, [
            'slug'       => $slug,
            'name'       => "Ext {$extension}",
            'version'    => '1.0.0',
            'screenshot' => "screenshot.{$extension}",
        ], $this->testSlugs );

        $result = $this->manager->installFromZip( $zipPath );

        expect( $result['screenshot'] )->toBe( "screenshot.{$extension}" );
    } )->with( ['png', 'jpg', 'jpeg', 'webp'] );

    it( 'rejects an invalid requires field', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'bad-requires', [
            'slug'     => 'bad-requires',
            'name'     => 'Bad Requires',
            'version'  => '1.0.0',
            'requires' => 'latest',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, "Field 'requires'" );
    } );

    it( 'accepts a valid requires field', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'good-requires', [
            'slug'     => 'good-requires',
            'name'     => 'Good Requires',
            'version'  => '1.0.0',
            'requires' => '2.5.1',
        ], $this->testSlugs );

        $result = $this->manager->installFromZip( $zipPath );

        expect( $result['requires'] )->toBe( '2.5.1' );
    } );

    it( 'rejects a templates bucket that is not an array', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'bad-templates', [
            'slug'      => 'bad-templates',
            'name'      => 'Bad Templates',
            'version'   => '1.0.0',
            'templates' => [
                'layouts' => 'oops',
            ],
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, "Field 'templates.layouts'" );
    } );

    it( 'rejects a templates bucket whose entries are not strings', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'mixed-templates', [
            'slug'      => 'mixed-templates',
            'name'      => 'Mixed Templates',
            'version'   => '1.0.0',
            'templates' => [
                'pages' => ['home', 42],
            ],
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, 'strings only' );
    } );

    it( 'accepts valid templates buckets', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'good-templates', [
            'slug'      => 'good-templates',
            'name'      => 'Good Templates',
            'version'   => '1.0.0',
            'templates' => [
                'layouts'  => ['default', 'wide'],
                'pages'    => ['home', 'about'],
                'partials' => ['header', 'footer'],
            ],
        ], $this->testSlugs );

        $result = $this->manager->installFromZip( $zipPath );

        expect( $result['templates']['layouts'] )->toBe( ['default', 'wide'] );
    } );

    it( 'rejects a non-boolean supports value', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'bad-supports', [
            'slug'     => 'bad-supports',
            'name'     => 'Bad Supports',
            'version'  => '1.0.0',
            'supports' => [
                'menus' => 'yes',
            ],
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class, "Field 'supports.menus'" );
    } );

    it( 'accepts boolean supports values', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'good-supports', [
            'slug'     => 'good-supports',
            'name'     => 'Good Supports',
            'version'  => '1.0.0',
            'supports' => [
                'menus'   => true,
                'widgets' => false,
            ],
        ], $this->testSlugs );

        $result = $this->manager->installFromZip( $zipPath );

        expect( $result['supports']['menus'] )->toBeTrue();
        expect( $result['supports']['widgets'] )->toBeFalse();
    } );

    it( 'passes through an opaque keystone namespace', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'keystone-theme', [
            'slug'     => 'keystone-theme',
            'name'     => 'Keystone Theme',
            'version'  => '1.0.0',
            'keystone' => [
                'installer' => 'KeystoneInstaller',
                'seed'      => [
                    'pages' => ['home', 'about'],
                ],
            ],
        ], $this->testSlugs );

        $result = $this->manager->installFromZip( $zipPath );

        expect( $result['keystone']['installer'] )->toBe( 'KeystoneInstaller' );
        expect( $result['keystone']['seed']['pages'] )->toBe( ['home', 'about'] );
    } );

    it( 'rejects optional fields that are present but null', function ( string $field ): void {
        $slug    = "null-{$field}-theme";
        $zipPath = buildValidateZip( $this->tmpPath, $slug, [
            'slug'    => $slug,
            'name'    => "Null {$field}",
            'version' => '1.0.0',
            $field    => null,
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class );

        expect( File::exists( $this->themesPath . '/' . $slug ) )->toBeFalse();
    } )->with( ['screenshot', 'requires', 'templates', 'supports'] );

    it( 'rolls back the extracted theme directory when validation fails', function (): void {
        $zipPath = buildValidateZip( $this->tmpPath, 'rollback-theme', [
            'slug'    => 'rollback-theme',
            'name'    => 'Rollback Theme',
            'version' => 'not-semver',
        ], $this->testSlugs );

        expect( fn () => $this->manager->installFromZip( $zipPath ) )
            ->toThrow( ThemeValidationException::class );

        expect( File::exists( $this->themesPath . '/rollback-theme' ) )->toBeFalse();
    } );
} );
