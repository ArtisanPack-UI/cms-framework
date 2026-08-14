<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\MetadataClient;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    $this->admin      = grantPermissions( TestUser::factory()->create(), 'manage-themes' );
    $this->themesPath = base_path( 'themes' );
    $this->tmpPath    = storage_path( 'app/theme-update-api-tmp' );
    $this->testSlugs  = [];

    File::ensureDirectoryExists( $this->themesPath );
    File::ensureDirectoryExists( $this->tmpPath );

    config()->set( 'cms.themes.cacheEnabled', false );
    config()->set( 'cms.updates.cache_enabled', false );
    config()->set( 'cms.updates.allow_unverified_updates', true );
    config()->set( 'cms.themes.backupPath', 'app/theme-update-api-tmp/backups' );
    Cache::flush();

    MetadataClient::useHttpFacadeBridge();
} );

afterEach( function (): void {
    MetadataClient::reset();

    foreach ( $this->testSlugs as $slug ) {
        $path = $this->themesPath . '/' . $slug;
        if ( File::exists( $path ) ) {
            File::deleteDirectory( $path );
        }
    }

    $stagingPath = $this->themesPath . '/.updates';
    if ( File::isDirectory( $stagingPath ) ) {
        File::deleteDirectory( $stagingPath );
    }

    if ( File::isDirectory( $this->tmpPath ) ) {
        File::deleteDirectory( $this->tmpPath );
    }
} );

/**
 * Install a theme directory on disk and track it for cleanup.
 */
function installApiTestTheme( string $themesPath, string $slug, array $manifest, array &$slugs, array $extraFiles = [] ): string
{
    $slugs[] = $slug;

    $path = $themesPath . '/' . $slug;
    File::ensureDirectoryExists( $path );
    File::put( $path . '/theme.json', json_encode( $manifest ) );

    foreach ( $extraFiles as $relative => $contents ) {
        File::put( $path . '/' . $relative, $contents );
    }

    return $path;
}

/**
 * Build an update ZIP whose single top-level directory is $slug.
 */
function buildApiThemeUpdateZip( string $tmpPath, string $slug, array $manifest, array $extraFiles = [] ): string
{
    $zipPath = $tmpPath . '/' . $slug . '-update-' . uniqid() . '.zip';

    $zip = new ZipArchive;
    if ( true !== $zip->open( $zipPath, ZipArchive::CREATE ) ) {
        throw new RuntimeException( "Failed to create test ZIP at {$zipPath}" );
    }

    $zip->addEmptyDir( $slug );
    $zip->addFromString( $slug . '/theme.json', json_encode( $manifest ) );

    foreach ( $extraFiles as $relative => $contents ) {
        $zip->addFromString( $slug . '/' . $relative, $contents );
    }

    $zip->close();

    return $zipPath;
}

/**
 * Fake a GitHub release feed, single-release lookup, and archive download.
 */
function fakeApiThemeRelease( string $slug, string $tag, ?string $zipPath = null ): void
{
    $asset = [
        'name'                 => $slug . '.zip',
        'browser_download_url' => "https://github.com/owner/repo/releases/download/{$tag}/{$slug}.zip",
    ];

    $fakes = [
        'https://api.github.com/repos/owner/repo/releases' => Http::response( [
            [
                'id'           => 1,
                'tag_name'     => $tag,
                'prerelease'   => false,
                'body'         => null,
                'published_at' => '2026-08-01T00:00:00Z',
                'html_url'     => "https://github.com/owner/repo/releases/tag/{$tag}",
                'zipball_url'  => "https://api.github.com/repos/owner/repo/zipball/{$tag}",
                'assets'       => [$asset],
            ],
        ] ),
        "https://api.github.com/repos/owner/repo/releases/tags/{$tag}" => Http::response( [
            'tag_name' => $tag,
            'body'     => null,
            'assets'   => [$asset],
        ] ),
    ];

    if ( null !== $zipPath ) {
        $fakes[ $asset['browser_download_url'] ] = Http::response( File::get( $zipPath ) );
    }

    Http::fake( $fakes );
}

describe( 'GET /v1/themes/updates', function (): void {
    it( 'lists themes with an update available', function (): void {
        $this->actingAs( $this->admin );

        installApiTestTheme( $this->themesPath, 'api-stale-theme', [
            'slug'    => 'api-stale-theme',
            'name'    => 'API Stale Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        fakeApiThemeRelease( 'api-stale-theme', 'v2.0.0' );

        $response = $this->getJson( '/v1/themes/updates' );

        $response->assertOk()
            ->assertJsonPath( 'updates.api-stale-theme.version', '2.0.0' )
            ->assertJsonPath( 'updates.api-stale-theme.current', '1.0.0' );
    } );

    it( 'is not swallowed by the show route', function (): void {
        $this->actingAs( $this->admin );

        Http::fake();

        $response = $this->getJson( '/v1/themes/updates' );

        $response->assertOk()->assertJsonStructure( ['updates'] );
    } );

    it( 'requires authentication', function (): void {
        $this->getJson( '/v1/themes/updates' )->assertStatus( 401 );
    } );
} );

describe( 'POST /v1/themes/{slug}/update', function (): void {
    it( 'updates a theme and reports the new version', function (): void {
        $this->actingAs( $this->admin );

        installApiTestTheme( $this->themesPath, 'api-update-theme', [
            'slug'    => 'api-update-theme',
            'name'    => 'API Update Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs, ['index.blade.php' => 'old'] );

        $zipPath = buildApiThemeUpdateZip( $this->tmpPath, 'api-update-theme', [
            'slug'    => 'api-update-theme',
            'name'    => 'API Update Theme',
            'version' => '2.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], ['index.blade.php' => 'new'] );

        fakeApiThemeRelease( 'api-update-theme', 'v2.0.0', $zipPath );

        $response = $this->postJson( '/v1/themes/api-update-theme/update' );

        $response->assertOk()
            ->assertJsonPath( 'updated', true )
            ->assertJsonPath( 'theme.version', '2.0.0' );

        expect( File::get( $this->themesPath . '/api-update-theme/index.blade.php' ) )->toBe( 'new' );
    } );

    it( 'reports no-op when the theme is already current', function (): void {
        $this->actingAs( $this->admin );

        installApiTestTheme( $this->themesPath, 'api-current-theme', [
            'slug'    => 'api-current-theme',
            'name'    => 'API Current Theme',
            'version' => '2.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        fakeApiThemeRelease( 'api-current-theme', 'v2.0.0' );

        $response = $this->postJson( '/v1/themes/api-current-theme/update' );

        $response->assertOk()->assertJsonPath( 'updated', false );
    } );

    it( 'returns a 422 errors bag keyed by slug for a theme that is not installed', function (): void {
        $this->actingAs( $this->admin );

        // Matches activate() and the plugin module: the slug is form input, so
        // an unknown one is a 422 keyed by `slug`, not a 404.
        $this->postJson( '/v1/themes/api-ghost-theme/update' )
            ->assertStatus( 422 )
            ->assertJsonValidationErrors( ['slug'] );
    } );

    it( 'returns a validation error bag when the update fails', function (): void {
        $this->actingAs( $this->admin );

        installApiTestTheme( $this->themesPath, 'api-broken-theme', [
            'slug'    => 'api-broken-theme',
            'name'    => 'API Broken Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs, ['index.blade.php' => 'original'] );

        // An archive carrying a different theme is rejected during staging,
        // before anything replaces the installed theme.
        $zipPath = buildApiThemeUpdateZip( $this->tmpPath, 'api-impostor-theme', [
            'slug'    => 'api-impostor-theme',
            'name'    => 'API Impostor Theme',
            'version' => '2.0.0',
        ] );

        fakeApiThemeRelease( 'api-broken-theme', 'v2.0.0', $zipPath );

        $response = $this->postJson( '/v1/themes/api-broken-theme/update' );

        $response->assertStatus( 422 )->assertJsonValidationErrors( ['slug'] );

        expect( File::get( $this->themesPath . '/api-broken-theme/index.blade.php' ) )->toBe( 'original' );
    } );

    it( 'requires authentication', function (): void {
        $this->postJson( '/v1/themes/api-update-theme/update' )->assertStatus( 401 );
    } );

    it( 'forbids an authenticated user without manage-themes from updating or checking updates', function (): void {
        // Authenticated, but without the `manage-themes` ability. Update
        // discovery is gated too: it makes one outbound request per theme.
        $this->actingAs( TestUser::factory()->create() );

        $this->postJson( '/v1/themes/api-update-theme/update' )->assertForbidden();
        $this->getJson( '/v1/themes/updates' )->assertForbidden();
    } );
} );
