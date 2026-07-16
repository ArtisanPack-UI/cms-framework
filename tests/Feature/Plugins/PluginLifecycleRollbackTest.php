<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach( function (): void {
    $this->manager         = app( PluginManager::class );
    $this->testPluginsPath = __DIR__ . '/../../Support/Plugins';
    $this->pluginsPath     = base_path( 'plugins' );
    File::ensureDirectoryExists( $this->pluginsPath );
} );

afterEach( function (): void {
    if ( File::exists( $this->pluginsPath . '/plugin-with-migrations' ) ) {
        File::deleteDirectory( $this->pluginsPath . '/plugin-with-migrations' );
    }
    Schema::dropIfExists( 'plugin_test_table' );
} );

it( 'rolls back plugin state when activation raises mid-flight', function (): void {
    Plugin::create( [
        'slug'             => 'broken-plugin',
        'name'             => 'Broken Plugin',
        'version'          => '1.0.0',
        'is_active'        => false,
        'service_provider' => 'NonExistent\\ServiceProvider\\ThatDoesNotExist',
        'meta'             => ['slug' => 'broken-plugin'],
    ] );

    try {
        $this->manager->activate( 'broken-plugin' );
    } catch ( Throwable $e ) {
        // Expected — nonexistent provider blows up during registration.
    }

    $plugin = Plugin::where( 'slug', 'broken-plugin' )->first();

    expect( $plugin->is_active )->toBeFalse();
} );

it( 'rolls back plugin migrations on delete when the manifest opts in', function (): void {
    File::copyDirectory(
        $this->testPluginsPath . '/plugin-with-migrations',
        $this->pluginsPath . '/plugin-with-migrations',
    );

    $manifest                                  = json_decode( File::get( $this->pluginsPath . '/plugin-with-migrations/plugin.json' ), true );
    $manifest['rollback_migrations_on_delete'] = true;

    Plugin::create( [
        'slug'      => $manifest['slug'],
        'name'      => $manifest['name'],
        'version'   => $manifest['version'],
        'is_active' => false,
        'meta'      => $manifest,
    ] );

    $this->manager->activate( 'plugin-with-migrations' );

    expect( Schema::hasTable( 'plugin_test_table' ) )->toBeTrue();

    $this->manager->delete( 'plugin-with-migrations' );

    expect( Schema::hasTable( 'plugin_test_table' ) )->toBeFalse();
} );

it( 'refuses to touch a migrations_path that resolves outside the plugin directory', function (): void {
    // Simulate a legacy plugin row that pre-dates the schema validator: meta
    // has a traversal-y migrations_path. The runtime guard in
    // resolveSecureMigrationsPath should reject it silently.
    File::copyDirectory(
        $this->testPluginsPath . '/valid-plugin',
        $this->pluginsPath . '/valid-plugin',
    );

    Plugin::create( [
        'slug'      => 'valid-plugin',
        'name'      => 'Valid Plugin',
        'version'   => '1.0.0',
        'is_active' => false,
        'meta'      => [
            'slug'                          => 'valid-plugin',
            'migrations_path'               => '../../database/migrations',
            'rollback_migrations_on_delete' => true,
        ],
    ] );

    // Snapshot: the framework's own plugins table exists.
    expect( Schema::hasTable( 'plugins' ) )->toBeTrue();

    $this->manager->delete( 'valid-plugin' );

    // The framework schema must NOT have been rolled back.
    expect( Schema::hasTable( 'plugins' ) )->toBeTrue();
} );

it( 'leaves plugin migrations in place when opt-in flag is absent', function (): void {
    File::copyDirectory(
        $this->testPluginsPath . '/plugin-with-migrations',
        $this->pluginsPath . '/plugin-with-migrations',
    );

    $manifest = json_decode( File::get( $this->pluginsPath . '/plugin-with-migrations/plugin.json' ), true );

    Plugin::create( [
        'slug'      => $manifest['slug'],
        'name'      => $manifest['name'],
        'version'   => $manifest['version'],
        'is_active' => false,
        'meta'      => $manifest,
    ] );

    $this->manager->activate( 'plugin-with-migrations' );
    $this->manager->delete( 'plugin-with-migrations' );

    // Table persists because we did not opt in to rollback.
    expect( Schema::hasTable( 'plugin_test_table' ) )->toBeTrue();
} );
