<?php

declare( strict_types=1 );

/**
 * Feature coverage for the `ap.cmsFramework.plugin.hookRegistered` action
 * introduced in 2.5.0 (issue #196 / Wave 5).
 *
 * Uses the same `valid-plugin` fixture that {@see PluginLifecycleTest} exercises
 * so any change in the plugin activation flow is covered by both suites.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.5.0
 */

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->manager         = app( PluginManager::class );
    $this->testPluginsPath = __DIR__ . '/../../Support/Plugins';
    $this->pluginsPath     = base_path( 'plugins' );

    File::ensureDirectoryExists( $this->pluginsPath );
} );

afterEach( function (): void {
    removeAllActions( 'ap.cmsFramework.plugin.hookRegistered' );

    if ( File::exists( $this->pluginsPath . '/valid-plugin' ) ) {
        File::deleteDirectory( $this->pluginsPath . '/valid-plugin' );
    }
} );

it( 'fires ap.cmsFramework.plugin.hookRegistered after the plugin service provider is registered', function (): void {
    File::copyDirectory(
        $this->testPluginsPath . '/valid-plugin',
        $this->pluginsPath . '/valid-plugin',
    );

    $manifest = json_decode( File::get( $this->pluginsPath . '/valid-plugin/plugin.json' ), true );

    Plugin::create( [
        'slug'             => $manifest['slug'],
        'name'             => $manifest['name'],
        'version'          => $manifest['version'],
        'is_active'        => false,
        'service_provider' => $manifest['service_provider'] ?? null,
        'meta'             => $manifest,
    ] );

    $received = [];

    addAction(
        'ap.cmsFramework.plugin.hookRegistered',
        function ( string $slug, array $hooks ) use ( & $received ): void {
            $received[] = compact( 'slug', 'hooks' );
        },
    );

    $this->manager->activate( 'valid-plugin' );

    expect( $received )->toHaveCount( 1 );
    expect( $received[ 0 ][ 'slug' ] )->toBe( 'valid-plugin' );
    expect( $received[ 0 ][ 'hooks' ] )->toBe( [] );
} );

it( 'does not fire ap.cmsFramework.plugin.hookRegistered when activation rolls back', function (): void {
    // A nonexistent service-provider class raises inside the transaction —
    // same shape as PluginLifecycleRollbackTest's broken-plugin scenario.
    // The action must NOT reach subscribers because activation never
    // completes and any side effects they trigger would leak past the
    // rollback.
    Plugin::create( [
        'slug'             => 'broken-hook-plugin',
        'name'             => 'Broken Hook Plugin',
        'version'          => '1.0.0',
        'is_active'        => false,
        'service_provider' => 'NonExistent\\ServiceProvider\\ThatDoesNotExist',
        'meta'             => ['slug' => 'broken-hook-plugin'],
    ] );

    $callCount = 0;
    addAction( 'ap.cmsFramework.plugin.hookRegistered', function () use ( & $callCount ): void {
        $callCount++;
    } );

    try {
        $this->manager->activate( 'broken-hook-plugin' );
    } catch ( Throwable $e ) {
        // Expected — nonexistent provider blows up during registration.
    }

    expect( $callCount )->toBe( 0 );
} );

it( 'forwards the manifest hooks array to subscribers when the plugin declares one', function (): void {
    File::copyDirectory(
        $this->testPluginsPath . '/valid-plugin',
        $this->pluginsPath . '/valid-plugin',
    );

    $manifestPath          = $this->pluginsPath . '/valid-plugin/plugin.json';
    $manifest              = json_decode( File::get( $manifestPath ), true );
    $manifest[ 'hooks' ]   = [ 'ap.example.foo', 'ap.example.bar' ];
    File::put( $manifestPath, json_encode( $manifest ) );

    Plugin::create( [
        'slug'             => $manifest['slug'],
        'name'             => $manifest['name'],
        'version'          => $manifest['version'],
        'is_active'        => false,
        'service_provider' => $manifest['service_provider'] ?? null,
        'meta'             => $manifest,
    ] );

    $captured = [];

    addAction(
        'ap.cmsFramework.plugin.hookRegistered',
        function ( string $slug, array $hooks ) use ( & $captured ): void {
            $captured = $hooks;
        },
    );

    $this->manager->activate( 'valid-plugin' );

    expect( $captured )->toBe( [ 'ap.example.foo', 'ap.example.bar' ] );
} );
