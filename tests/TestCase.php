<?php

namespace Tests;

//phpcs:disable
use ArtisanPackUI\Accessibility\A11yServiceProvider;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Plugins\PluginManager;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use TorMorten\Eventy\EventServiceProvider;

//phpcs:enable

class TestCase extends Orchestra
{
    /**
     * Define environment setup.
     *
     * @param Application $app
     * @return void
     */
    protected function getEnvironmentSetUp( $app )
    {
        // Setup default database connection for tests (in-memory SQLite is fast)
        $app['config']->set( 'database.default', 'testing' );
        $app['config']->set( 'database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ] );

        // Set the plugin path for testing to a temporary directory.
        // This prevents polluting your actual application's plugin directory during tests.
        $app['config']->set( 'cms.paths.plugins', sys_get_temp_dir() . '/artisanpack_test_plugins' );
        $app['config']->set( 'cms.paths.themes', sys_get_temp_dir() . '/artisanpack_test_themes' ); // If you have themes too
    }

    protected function getPackageProviders( $app )
    {
        return [
            CMSFrameworkServiceProvider::class,
            EventServiceProvider::class,
            A11yServiceProvider::class,
            SecurityServiceProvider::class,
        ];
    }

    /**
     * Setup the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp(); // IMPORTANT: This calls the RefreshDatabase trait's setup

        // Ensure the temporary plugins directory for testing is clean
        if ( File::exists( config( 'cms.paths.plugins' ) ) ) {
            File::deleteDirectory( config( 'cms.paths.plugins' ) );
        }
        File::makeDirectory( config( 'cms.paths.plugins' ), 0777, true );


        // --- IMPORTANT: Manually initialize active plugins for the test environment ---
        // This ensures the plugins table exists and is migrated *before* the query.
        app( PluginManager::class )->initializeActivePlugins();
        // -----------------------------------------------------------------------------
    }

    /**
     * Clean up the test environment after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clean up the temporary plugins directory created for testing
        if ( File::exists( config( 'cms.paths.plugins' ) ) ) {
            File::deleteDirectory( config( 'cms.paths.plugins' ) );
        }

        parent::tearDown();
    }
}
