<?php
/**
 * Pages Service Provider
 *
 * Provides the service registration and bootstrapping for the website pages feature
 * of the CMS framework. This service provider is responsible for defining
 * the registration and bootstrapping process related to the website pages functionality.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Pages
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Pages;

use Illuminate\Support\ServiceProvider;

/**
 * Class for providing website page services.
 *
 * Provides the necessary methods to register and boot the website pages services within the application.
 *
 * @since 1.0.0
 * @see   ServiceProvider
 */
class PagesServiceProvider extends ServiceProvider
{

	/**
	 * Register website pages services.
	 *
	 * Registers the PagesManager as a singleton service in the application container.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( PagesManager::class, function ( $app ) {
			return new PagesManager();
		} );
	}

	/**
	 * Boot website pages services.
	 *
	 * Performs any bootstrapping actions for the website pages feature, such as
	 * configuring cache prefixes or loading routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void
	{
		// Example: If you want to use a specific cache prefix for pages, similar to settings.
		// config( [ 'cache.stores.file.prefix' => config( 'cache.stores.file.prefix' ) . '.website_pages_cache' ] );
	}
}