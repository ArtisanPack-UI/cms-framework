<?php
/**
 * Dashboard Widgets Service Provider
 *
 * Provides the service registration and bootstrapping for the dashboard widgets feature
 * of the CMS framework. This service provider is responsible for defining
 * the registration and bootstrapping process related to the dashboard widgets functionality.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\DashboardWidgets
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\DashboardWidgets;

use Illuminate\Support\ServiceProvider;

/**
 * Class for providing dashboard widget services
 *
 * Provides the necessary methods to register and boot the dashboard widget services within the application.
 *
 * @since 1.1.0
 * @see   ServiceProvider
 */
class DashboardWidgetsServiceProvider extends ServiceProvider
{
	/**
	 * Register dashboard widget services.
	 *
	 * Registers the DashboardWidgetsManager as a singleton service in the application container.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( DashboardWidgetsManager::class, function ( $app ) {
			return new DashboardWidgetsManager();
		} );
	}

	/**
	 * Boot dashboard widget services.
	 *
	 * This method can be used for any bootstrapping logic related to dashboard widgets.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void
	{
		// No specific migrations needed here if user widget settings are part of the existing User model's settings column.
	}
}