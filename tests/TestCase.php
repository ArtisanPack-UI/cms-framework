<?php

namespace Tests;

//phpcs:disable
use ArtisanPackUI\Accessibility\A11yServiceProvider;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Plugins\PluginManager;
use ArtisanPackUI\CMSFramework\Http\Controllers\RoleController;
use ArtisanPackUI\CMSFramework\Http\Controllers\SettingController;
use ArtisanPackUI\CMSFramework\Http\Controllers\UserController;
use ArtisanPackUI\Security\SecurityServiceProvider;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use TorMorten\Eventy\EventServiceProvider;

//phpcs:enable

class TestCase extends Orchestra
{
	use RefreshDatabase;
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
			SanctumServiceProvider::class,
		];
	}

	/**
	 * Setup the test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Ensure the temporary plugins directory for testing is clean
		if ( File::exists( config( 'cms.paths.plugins' ) ) ) {
			File::deleteDirectory( config( 'cms.paths.plugins' ) );
		}
		File::makeDirectory( config( 'cms.paths.plugins' ), 0777, true );

		// Register routes directly to match routes/api.php
		$this->app['router']->middleware( 'api' )->prefix( 'api/cms' )->group( function ( $router ) {
			$router->apiResource( 'users', UserController::class );
			$router->apiResource( 'roles', RoleController::class );
			$router->apiResource( 'settings', SettingController::class );
		} );

		// Configure Sanctum for testing
		$this->app['config']->set( 'sanctum.stateful', [ 'testing' ] );
		$this->app['config']->set( 'sanctum.middleware.verify_csrf_token', false );

		// We don't need to manually initialize active plugins for the test environment
		// as the service provider is designed to skip this during tests.
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

	/**
	 * Define database migrations.
	 *
	 * @return void
	 */
	protected function defineDatabaseMigrations()
	{
		$this->loadLaravelMigrations();
		$this->loadMigrationsFrom( __DIR__ . '/../vendor/laravel/sanctum/database/migrations' );
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
	}
}
