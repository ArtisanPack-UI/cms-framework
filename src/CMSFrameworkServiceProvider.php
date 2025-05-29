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
		$this->app->singleton( 'cmsframework', function ( $app ) {
			return new CMSFramework();
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
		new CMSFramework();
		$this->loadMigrationsFrom( $this->getMigrationDirectories() );
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
		return Eventy::filter( 'ap.migrations.directories', [] );
	}
}
