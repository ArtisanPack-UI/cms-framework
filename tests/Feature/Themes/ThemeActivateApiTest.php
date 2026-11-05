<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->admin      = grantPermissions( TestUser::factory()->create(), 'manage-themes' );
    $this->themesPath = testThemesDirectory();
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

function seedInstalledTheme( string $themesPath, string $slug, array &$slugs ): void
{
    $slugs[] = $slug;

    File::ensureDirectoryExists( $themesPath . '/' . $slug );
    File::put( $themesPath . '/' . $slug . '/theme.json', json_encode( [
        'slug'    => $slug,
        'name'    => ucfirst( $slug ),
        'version' => '1.0.0',
    ] ) );
}

describe( 'POST /v1/themes/{slug}/activate', function (): void {
    it( 'activates an installed theme', function (): void {
        $this->actingAs( $this->admin );

        seedInstalledTheme( $this->themesPath, 'api-activate-theme', $this->testSlugs );

        $response = $this->postJson( '/v1/themes/api-activate-theme/activate' );

        $response->assertOk()
            ->assertJsonPath( 'theme.slug', 'api-activate-theme' );
    } );

    it( 'reports how OPcache was reset, and a reload notice when it was not (#453)', function ( ?string $method, bool $reloadRequired ): void {
        $this->actingAs( $this->admin );
        app( WebOpcacheResetter::class )->method = $method;

        seedInstalledTheme( $this->themesPath, 'api-refresh-theme', $this->testSlugs );

        $response = $this->postJson( '/v1/themes/api-refresh-theme/activate' )
            ->assertOk()
            ->assertJsonPath( 'opcache_reset_method', $method )
            ->assertJsonPath( 'php_reload_required', $reloadRequired );

        if ( $reloadRequired ) {
            $response->assertJsonPath( 'warning', 'The theme was activated, but PHP-FPM\'s OPcache could not be reset. Reload PHP-FPM so the site runs the theme.' );
        } else {
            $response->assertJsonMissingPath( 'warning' );
        }
    } )->with( [
        'reset'     => ['fastcgi', false],
        'not reset' => [null, true],
    ] );

    it( 'returns 422 with an errors bag keyed by slug for an unknown theme', function (): void {
        $this->actingAs( $this->admin );

        $response = $this->postJson( '/v1/themes/api-ghost-theme/activate' );

        $response->assertStatus( 422 )
            ->assertJsonValidationErrors( [
                'slug' => 'Theme "api-ghost-theme" not found.',
            ] );
    } );

    it( 'requires authentication', function (): void {
        $this->postJson( '/v1/themes/api-activate-theme/activate' )->assertStatus( 401 );
    } );

    it( 'forbids an authenticated user without manage-themes from activating', function (): void {
        $this->actingAs( TestUser::factory()->create() );

        $this->postJson( '/v1/themes/api-activate-theme/activate' )->assertForbidden();
    } );
} );
