<?php
/**
 * Service provider for the CMS Framework.
 *
 * This class handles the registration and bootstrapping of the framework, including loading migrations and views and
 * providing necessary hooks for customization using the Eventy system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework;

use ArtisanPackUI\CMSFramework\Features\Pages\PagesServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Plugins\PluginManager;
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Auth\TwoFactorAuthServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Media\MediaServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Users\UsersServiceProvider;
use Illuminate\Support\ServiceProvider;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Registers and bootstraps the CMS Framework within the application.
 *
 * The service provider is responsible for binding the framework to the container,
 * initializing necessary components during the bootstrapping process,
 * and loading framework-specific resources, such as migrations.
 *
 * @since 1.0.0
 */
class CMSFrameworkServiceProvider extends ServiceProvider
{

	/**
	 * Registers a singleton instance of the CMSFramework within the application container.
	 *
	 * This method is called by the Laravel framework during the bootstrapping process to run the CMS framework.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFrameworkServiceProvider
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @return void
	 */
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/cms.php', 'cms'
		);
		$this->app->register( SettingsServiceProvider::class );
		$this->app->register( UsersServiceProvider::class );
		$this->app->register( AuthServiceProvider::class );
		$this->app->register( AuditLogServiceProvider::class );
		$this->app->register( TwoFactorAuthServiceProvider::class );
		$this->app->register( MediaServiceProvider::class );
		$this->app->register( PagesServiceProvider::class );
		$this->app->singleton( CMSManager::class, function ( $app ) {
			return new CMSManager();
		} );
		$this->app->singleton( PluginManager::class, function ( $app ) {
			return new PluginManager();
		} );
	}

	/**
	 * Boots the CMS framework and loads database migration files.
	 *
	 * This method is triggered during the Laravel bootstrapping process to initialize
	 * the CMS framework and register migration paths for the system.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFrameworkServiceProvider
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @return void
	 */
	public function boot(): void
	{
		$this->loadMigrationsFrom( $this->getMigrationDirectories() );
		$this->loadViewsFromDirectories( $this->getViewsDirectories() );
		// Load the main API routes
		$this->loadRoutesFrom( __DIR__ . '/../routes/api.php' );
		$this->publishes( [
			__DIR__ . '/../config/cms.php' => config_path( 'cms.php' ),
		], 'cms-config' );
		// Publish Sanctum's configuration.
		// This will allow the main application to publish them if needed.
		$this->publishes( [
			__DIR__ . '/../../vendor/laravel/sanctum/config/sanctum.php' => config_path( 'sanctum.php' ),
		], 'sanctum-config' );
	}

	/**
	 * Returns an array of migration directories to load.
	 *
	 * This method is used to allow for customization of the migration directories
	 * by other modules.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFrameworkServiceProvider
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @return array List of migration directories.
	 */
	public function getMigrationDirectories(): array
	{
		$defaultDirectories = [
			__DIR__ . '/../database/migrations',
			__DIR__ . '/../../vendor/laravel/sanctum/database/migrations',
		];

		/**
		 * Loads the migration directories from the modules.
		 *
		 * Grabs the migration directories from the modules that have been registered and returns them as an array.
		 *
		 * @since 1.0.0
		 *
		 * @param array $directories List of directories to load migrations from.
		 */
		return Eventy::filter( 'ap.cms.migrations.directories', $defaultDirectories );
	}

	/**
	 * Loads views from the specified directories.
	 *
	 * This method is used to allow for customization of the view directories
	 * by other modules.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFrameworkServiceProvider
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param array $directories List of directories to load views from.
	 *
	 * @return void
	 */
	public function loadViewsFromDirectories( array $directories ): void
	{
		if ( $directories ) {
			foreach ( $directories as $directory ) {
				$this->loadViewsFrom( $directory['path'], $directory['namespace'] );
			}
		}
	}

	/**
	 * Returns an array of view directories to load.
	 *
	 * This method is used to allow for customization of the view directories
	 * by other modules.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFrameworkServiceProvider
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @return array List of view directories.
	 */
	public function getViewsDirectories(): array
	{
		/**
		 * Loads the view directories from the modules.
		 *
		 * Grabs the view directories from the modules that have been registered and returns them as an array.
		 * The returned array includes the path and namespace for each view directory.
		 *
		 * @since 1.0.0
		 *
		 * @param array $directories List of directories to load views from.
		 * @return array {
		 *                           List of view directories.
		 *
		 * @type string $path        Path to the view directory.
		 * @type string $namespace   Namespace for the view directory.
		 *                           }
		 */
		return Eventy::filter( 'ap.cms.views.directories', [] );
	}
}
