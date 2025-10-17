<?php
/**
 * Core Service Provider.
 *
 * Registers core services used across the CMS Framework such as the AssetManager.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Core\Providers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Providers;

use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Services\AdminWidgetManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Managers\AssetManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers core services with the application container.
 *
 * @since 2.0.0
 */
class CoreServiceProvider extends ServiceProvider
{
	/**
	 * Register the core singleton services.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( AssetManager::class, function ( $app ) {
			return new AssetManager();
		} );
	}
}