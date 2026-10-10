<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
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
    if ( File::exists( base_path( 'later-batch-migrations' ) ) ) {
        File::deleteDirectory( base_path( 'later-batch-migrations' ) );
    }
    Schema::dropIfExists( 'plugin_test_table' );
    Schema::dropIfExists( 'later_batch_table' );
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

it( 'rolls back plugin migrations on delete after a later migration batch has run', function (): void {
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

    // Run an unrelated migration afterwards so the plugin's migrations are no
    // longer in the last global batch (#355).
    $laterPath = base_path( 'later-batch-migrations' );
    File::ensureDirectoryExists( $laterPath );
    File::put( $laterPath . '/2099_01_01_000000_create_later_batch_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create( 'later_batch_table', function ( Blueprint $table ): void {
                    $table->id();
                } );
            }

            public function down(): void
            {
                Schema::dropIfExists( 'later_batch_table' );
            }
        };
        PHP );

    Artisan::call( 'migrate', [
        '--path'  => 'later-batch-migrations',
        '--force' => true,
    ] );

    expect( Schema::hasTable( 'plugin_test_table' ) )->toBeTrue()
        ->and( Schema::hasTable( 'later_batch_table' ) )->toBeTrue();

    $this->manager->delete( 'plugin-with-migrations' );

    expect( Schema::hasTable( 'plugin_test_table' ) )->toBeFalse()
        ->and( Schema::hasTable( 'later_batch_table' ) )->toBeTrue();
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

it( 'runs and resets plugin migrations when the plugins directory is a symlink outside base_path', function (): void {
    $realPluginsPath = sys_get_temp_dir() . '/cms-plugins-symlink-target-' . uniqid();
    $linkPath        = base_path( 'symlinked-plugins' );

    File::ensureDirectoryExists( $realPluginsPath );
    File::copyDirectory(
        $this->testPluginsPath . '/plugin-with-migrations',
        $realPluginsPath . '/plugin-with-migrations',
    );

    if ( File::exists( $linkPath ) || is_link( $linkPath ) ) {
        File::delete( $linkPath );
    }

    if ( ! @symlink( $realPluginsPath, $linkPath ) ) {
        File::deleteDirectory( $realPluginsPath );
        $this->markTestSkipped( 'Symlinks are not supported on this platform.' );
    }

    config()->set( 'cms.plugins.directory', 'symlinked-plugins' );

    $manifest                                  = json_decode( File::get( $realPluginsPath . '/plugin-with-migrations/plugin.json' ), true );
    $manifest['rollback_migrations_on_delete'] = true;

    Plugin::create( [
        'slug'      => $manifest['slug'],
        'name'      => $manifest['name'],
        'version'   => $manifest['version'],
        'is_active' => false,
        'meta'      => $manifest,
    ] );

    try {
        $this->manager->activate( 'plugin-with-migrations' );

        expect( Schema::hasTable( 'plugin_test_table' ) )->toBeTrue();

        $this->manager->delete( 'plugin-with-migrations' );

        expect( Schema::hasTable( 'plugin_test_table' ) )->toBeFalse();
    } finally {
        if ( is_link( $linkPath ) ) {
            unlink( $linkPath );
        }
        File::deleteDirectory( $realPluginsPath );
    }
} );
