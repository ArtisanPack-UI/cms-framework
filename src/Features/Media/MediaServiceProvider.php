<?php
/**
 * Media Service Provider
 *
 * Provides the service registration and bootstrapping for the media feature
 * of the CMS framework. This service provider is responsible for defining
 * the registration and bootstrapping process related to the media functionality.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Media
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Media;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

// Assuming a PSR-3 compliant logger is available.

/**
 * Class for providing media services
 *
 * Provides the necessary methods to register and boot the media services within the application.
 *
 * @since 1.0.0
 * @see   ServiceProvider
 */
class MediaServiceProvider extends ServiceProvider
{

	/**
	 * Register media services
	 *
	 * Registers the MediaManager as a singleton service in the application container.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( MediaManager::class, function ( $app ) {
			// Resolve the logger from the container. Laravel's default logger is Psr\Log\LoggerInterface.
			return new MediaManager( $app->make( LoggerInterface::class ) );
		} );
	}

	/**
	 * Boot media services
	 *
	 * This method is used for bootstrapping logic related to media,
	 * such as registering routes or event listeners.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void
	{
		// Load routes for media, media categories, and media tags
		//$this->loadRoutesFrom( __DIR__ . '/../../../routes/media.php' );
	}
}
