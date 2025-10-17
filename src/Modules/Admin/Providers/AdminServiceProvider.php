<?php
/**
 * Service provider for the Admin module.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Admin\Providers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Providers;

use ArtisanPackUI\CMSFramework\Modules\Admin\Http\Middleware\CheckAdminCapability;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminPageManager;

/**
 * Registers admin module services and bootstraps admin routing/middleware.
 *
 * @since 2.0.0
 */
class AdminServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 *
	 * @since 2.0.0
	 */
	public function register(): void
	{
		$this->app->singleton( AdminMenuManager::class, fn() => new AdminMenuManager() );
		$this->app->singleton( AdminPageManager::class, fn() => new AdminPageManager() );
	}

	/**
	 * Bootstrap any application services.
	 *
	 * @since 2.0.0
	 */
	public function boot( Router $router ): void
	{
		$router->aliasMiddleware( 'admin.can', CheckAdminCapability::class );
		$this->app->booted( function () {
			app( AdminPageManager::class )->registerRoutes();
		} );
	}
}