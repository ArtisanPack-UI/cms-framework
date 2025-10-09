<?php
/**
 * Service provider for the AdminWidgets module.
 *
 * This provider handles the registration of the AdminWidgetManager service
 * within the application container.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Providers
 */

namespace ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Providers;

use Illuminate\Support\ServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Services\AdminWidgetManager;

/**
 * Registers the Admin Widgets services.
 *
 * @since 1.0.0
 */
class AdminWidgetServiceProvider extends ServiceProvider
{

	/**
	 * Register the admin widget manager service as a singleton.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( AdminWidgetManager::class, function ( $app ) {
			return new AdminWidgetManager();
		} );
	}
}