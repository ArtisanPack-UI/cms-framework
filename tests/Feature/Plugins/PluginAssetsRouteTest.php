<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
 * Each test serves a throwaway plugin tree under storage/framework/testing,
 * registered straight into the PluginRegistry the way a booted plugin's
 * relative federated_module entry registers it.
 */
beforeEach( function (): void {
    $this->pluginRoot = storage_path( 'framework/testing/plugin-assets-' . bin2hex( random_bytes( 6 ) ) );
    $this->assetsDir  = $this->pluginRoot . '/dist/assets';

    File::ensureDirectoryExists( $this->assetsDir . '/__federation_shared_@inertiajs' );
    File::put( $this->assetsDir . '/remoteEntry.js', "// remoteEntry stub\n" );
    File::put( $this->assetsDir . '/index-abc123.js', "// hashed chunk stub\n" );
    File::put( $this->assetsDir . '/style-abc123.css', "body{}\n" );
    File::put( $this->assetsDir . '/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>' );
    File::put( $this->assetsDir . '/shell.php', '<?php echo "nope";' );
    File::put( $this->assetsDir . '/__federation_shared_@inertiajs/react-AbC_12-x.js', "// shared chunk stub\n" );
    File::put( $this->pluginRoot . '/plugin.json', '{"slug":"asset-plugin"}' );
    File::ensureDirectoryExists( $this->assetsDir . '/nested.js' );

    app( PluginRegistry::class )->setAssetDirectory( 'asset-plugin', $this->assetsDir, 'remoteEntry.js' );
} );

afterEach( function (): void {
    File::deleteDirectory( $this->pluginRoot );
} );

it( 'registers the named plugin asset route', function (): void {
    expect( Route::has( 'plugins.assets' ) )->toBeTrue()
        ->and( route( 'plugins.assets', ['slug' => 'asset-plugin', 'path' => 'remoteEntry.js'] ) )
        ->toEndWith( '/plugins/asset-plugin/assets/remoteEntry.js' );
} );

it( 'serves the federation entry with a revalidating cache policy', function (): void {
    $response = $this->get( '/plugins/asset-plugin/assets/remoteEntry.js' );

    $response->assertOk();
    expect( $response->headers->get( 'Cache-Control' ) )->toContain( 'no-cache' )->toContain( 'public' )
        ->and( $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/javascript' )
        ->and( $response->headers->get( 'X-Content-Type-Options' ) )->toBe( 'nosniff' )
        ->and( $response->headers->get( 'ETag' ) )->not->toBeEmpty()
        ->and( $response->headers->get( 'Last-Modified' ) )->not->toBeEmpty();
} );

it( 'answers a matching If-None-Match on the entry with a 304', function (): void {
    $etag = $this->get( '/plugins/asset-plugin/assets/remoteEntry.js' )->headers->get( 'ETag' );

    $this->get( '/plugins/asset-plugin/assets/remoteEntry.js', ['If-None-Match' => $etag] )
        ->assertStatus( 304 );
} );

it( 'serves hashed chunks as immutable for a year', function (): void {
    $response = $this->get( '/plugins/asset-plugin/assets/index-abc123.js' );

    $response->assertOk();
    expect( $response->headers->get( 'Cache-Control' ) )
        ->toContain( 'immutable' )
        ->toContain( 'max-age=31536000' )
        ->not->toContain( 'no-cache' );
} );

it( 'serves stylesheets as text/css', function (): void {
    $response = $this->get( '/plugins/asset-plugin/assets/style-abc123.css' );

    $response->assertOk();
    expect( $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/css' );
} );

it( 'serves a federation shared chunk from a nested directory', function (): void {
    $this->get( '/plugins/asset-plugin/assets/__federation_shared_@inertiajs/react-AbC_12-x.js' )
        ->assertOk();
} );

it( 'sandboxes svg responses', function (): void {
    $response = $this->get( '/plugins/asset-plugin/assets/logo.svg' );

    $response->assertOk();
    expect( $response->headers->get( 'Content-Security-Policy' ) )->toContain( 'sandbox' )
        ->and( $response->headers->get( 'Content-Disposition' ) )->toBe( 'attachment' );
} );

it( 'returns 404 for a plugin that registered no asset directory', function (): void {
    $this->get( '/plugins/other-plugin/assets/remoteEntry.js' )->assertNotFound();
} );

it( 'returns 404 for a disallowed extension', function (): void {
    $this->get( '/plugins/asset-plugin/assets/shell.php' )->assertNotFound();
} );

it( 'returns 404 for a missing file or a directory', function ( string $path ): void {
    $this->get( '/plugins/asset-plugin/assets/' . $path )->assertNotFound();
} )->with( [
    'missing file' => 'missing-abc.js',
    'directory'    => 'nested.js',
] );

it( 'never serves files outside the asset directory', function (): void {
    // `..` survives the route's character class, so containment is what
    // keeps the manifest and source out of reach.
    $this->get( '/plugins/asset-plugin/assets/..%2F..%2Fplugin.json' )->assertNotFound();
    $this->get( '/plugins/asset-plugin/assets/../../plugin.json' )->assertNotFound();
} );

it( 'returns 404 for dot-files and dot-directories', function ( string $path ): void {
    File::ensureDirectoryExists( $this->assetsDir . '/.vite' );
    File::put( $this->assetsDir . '/.vite/manifest.json', '{}' );
    File::put( $this->assetsDir . '/.hidden.js', '// hidden' );

    $this->get( '/plugins/asset-plugin/assets/' . $path )->assertNotFound();
} )->with( [
    'dot-directory' => '.vite/manifest.json',
    'dot-file'      => '.hidden.js',
] );

it( 'returns 404 for a symlink that escapes the asset directory', function (): void {
    File::put( $this->pluginRoot . '/secret.js', '// outside' );
    symlink( $this->pluginRoot . '/secret.js', $this->assetsDir . '/escape.js' );

    $this->get( '/plugins/asset-plugin/assets/escape.js' )->assertNotFound();
} );

it( 'returns 404 when the registered directory has not been built yet', function (): void {
    app( PluginRegistry::class )->setAssetDirectory( 'unbuilt-plugin', $this->pluginRoot . '/missing-dist', 'remoteEntry.js' );

    $this->get( '/plugins/unbuilt-plugin/assets/remoteEntry.js' )->assertNotFound();
} );
