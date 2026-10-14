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
    File::put( $this->assetsDir . '/index-BxQ3k9aZ.js', "// hashed chunk stub\n" );
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
    $response = $this->get( '/plugins/asset-plugin/assets/index-BxQ3k9aZ.js' );

    $response->assertOk();
    expect( $response->headers->get( 'Cache-Control' ) )
        ->toContain( 'immutable' )
        ->toContain( 'max-age=31536000' )
        ->not->toContain( 'no-cache' );
} );

it( 'serves webpack-style hex content hashes as immutable', function (): void {
    File::put( $this->assetsDir . '/main.3f9a1c2e.js', '// webpack chunk' );

    expect( $this->get( '/plugins/asset-plugin/assets/main.3f9a1c2e.js' )->headers->get( 'Cache-Control' ) )
        ->toContain( 'immutable' );
} );

it( 'revalidates files whose names carry no content hash', function ( string $file ): void {
    File::put( $this->assetsDir . '/' . $file, 'x' );

    $response = $this->get( '/plugins/asset-plugin/assets/' . $file );

    $response->assertOk();
    expect( $response->headers->get( 'Cache-Control' ) )
        ->toContain( 'no-cache' )
        ->not->toContain( 'immutable' )
        ->and( $response->headers->get( 'ETag' ) )->not->toBeEmpty();
} )->with( [
    'module federation manifest' => 'mf-manifest.json',
    'library-mode stylesheet'    => 'style.css',
    'public/ copy'               => 'brand.png',
    'short hash-like suffix'     => 'index-abc123.js',
    'word with a digit'          => 'panel-version2.js',
    'pascal-case module name'    => 'chunk-HelloWorldPanel.js',
] );

it( 'answers a matching If-None-Match on an unhashed file with a 304', function (): void {
    File::put( $this->assetsDir . '/mf-manifest.json', '{}' );

    $etag = $this->get( '/plugins/asset-plugin/assets/mf-manifest.json' )->headers->get( 'ETag' );

    $this->get( '/plugins/asset-plugin/assets/mf-manifest.json', ['If-None-Match' => $etag] )
        ->assertStatus( 304 );
} );

it( 'keeps the revalidating policy for an entry that is a symlink', function (): void {
    File::put( $this->assetsDir . '/realEntry-BxQ3k9aZ.js', '// real entry' );
    File::delete( $this->assetsDir . '/remoteEntry.js' );
    symlink( $this->assetsDir . '/realEntry-BxQ3k9aZ.js', $this->assetsDir . '/remoteEntry.js' );

    expect( $this->get( '/plugins/asset-plugin/assets/remoteEntry.js' )->headers->get( 'Cache-Control' ) )
        ->toContain( 'no-cache' )
        ->not->toContain( 'immutable' );
} )->skip( fn (): bool => 'Windows' === PHP_OS_FAMILY, 'Symlinks need elevated rights on Windows.' );

it( 'answers HEAD requests with headers and no body', function (): void {
    $response = $this->call( 'HEAD', '/plugins/asset-plugin/assets/remoteEntry.js' );

    $response->assertOk();
    expect( $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/javascript' )
        ->and( $response->headers->get( 'X-Content-Type-Options' ) )->toBe( 'nosniff' );
} );

it( 'loads after routes registered while providers boot, so a plugin-owned asset route wins', function (): void {
    // ThemesServiceProvider loads its web routes from boot(); the plugin asset
    // route loads from `booted()`, after every provider, including plugin
    // providers that still register their own `/plugins/{slug}/assets/{path}`.
    $names = array_map( fn ( $route ) => $route->getName(), array_values( Route::getRoutes()->getRoutes() ) );

    expect( array_search( 'plugins.assets', $names, true ) )
        ->toBeGreaterThan( array_search( 'themes.assets', $names, true ) );
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
