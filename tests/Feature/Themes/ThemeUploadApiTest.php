<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->admin       = TestUser::factory()->create();
    $this->themesPath  = base_path( 'themes' );
    $this->tmpPath     = storage_path( 'app/themes-upload-test' );
    $this->testSlugs   = [];

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

function makeUploadedThemeZip( string $tmpPath, string $slug, array $manifest, array &$slugs ): UploadedFile
{
    $slugs[] = $slug;
    $zipPath = $tmpPath . '/' . $slug . '.zip';

    $zip = new ZipArchive;
    if ( true !== $zip->open( $zipPath, ZipArchive::CREATE ) ) {
        throw new RuntimeException( "Failed to create test ZIP at {$zipPath}" );
    }
    $zip->addEmptyDir( $slug );
    $zip->addFromString( $slug . '/theme.json', json_encode( $manifest ) );
    $zip->close();

    return new UploadedFile( $zipPath, $slug . '.zip', 'application/zip', null, true );
}

describe( 'POST /v1/themes (upload)', function (): void {
    it( 'installs a valid theme uploaded as a ZIP', function (): void {
        $this->actingAs( $this->admin );

        $manifest = [
            'slug'    => 'api-upload-theme',
            'name'    => 'API Upload Theme',
            'version' => '1.0.0',
        ];

        $file = makeUploadedThemeZip( $this->tmpPath, 'api-upload-theme', $manifest, $this->testSlugs );

        $response = $this->postJson( '/v1/themes', [
            'theme_zip' => $file,
        ] );

        $response->assertCreated()
            ->assertJson( [
                'theme' => [
                    'slug' => 'api-upload-theme',
                    'name' => 'API Upload Theme',
                ],
            ] );

        expect( File::exists( $this->themesPath . '/api-upload-theme/theme.json' ) )->toBeTrue();
    } );

    it( 'returns 422 when no file is uploaded', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->postJson( '/v1/themes', [] );

        $response->assertStatus( 422 );
    } );

    it( 'returns 422 when the slug is already installed', function (): void {
        $this->actingAs( $this->admin );

        // Seeded directory and ZIP both use the same slug — makeUploadedThemeZip
        // already tracks it for cleanup via $this->testSlugs.
        $existing = $this->themesPath . '/dup-api-theme';
        File::ensureDirectoryExists( $existing );
        File::put( $existing . '/theme.json', json_encode( [
            'slug'    => 'dup-api-theme',
            'name'    => 'Dup',
            'version' => '1.0.0',
        ] ) );

        $file = makeUploadedThemeZip(
            $this->tmpPath,
            'dup-api-theme',
            ['slug' => 'dup-api-theme', 'name' => 'Dup', 'version' => '1.0.0'],
            $this->testSlugs,
        );

        $response = $this->postJson( '/v1/themes', ['theme_zip' => $file] );

        $response->assertStatus( 422 )
            ->assertJsonFragment( ['message' => "Theme 'dup-api-theme' is already installed."] );
    } );

    it( 'requires authentication', function (): void {
        $response = $this->postJson( '/v1/themes', [] );

        $response->assertStatus( 401 );
    } );
});
