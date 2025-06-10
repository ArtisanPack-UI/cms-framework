<?php
/**
 * Users Service Provider
 *
 * Provides the service registration and bootstrapping for the users feature
 * of the CMS framework.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Users
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Users;

use Illuminate\Support\ServiceProvider;

/**
 * Class for providing users services.
 *
 * Provides the necessary methods to register and boot the users services
 * within the application.
 *
 * @since 1.0.0
 * @see   ServiceProvider
 */
class UsersServiceProvider extends ServiceProvider
{

	/**
	 * Register users services.
	 *
	 * Registers the UsersManager as a singleton service in the application container.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( UsersManager::class, function ( $app ) {
			// UsersManager no longer directly depends on SettingsManager for user settings
			// due to storing settings in the User model itself.
			return new UsersManager();
		} );
	}

	/**
	 * Boot users services.
	 *
	 * This method can be used to register any users-related migrations,
	 * view composers, or other bootstrapping logic.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void
	{
		// No specific bootstrapping logic needed here yet beyond basic service registration.
		// Example: $this->loadMigrationsFrom( __DIR__ . '/../../database/migrations/users' );
	}
}