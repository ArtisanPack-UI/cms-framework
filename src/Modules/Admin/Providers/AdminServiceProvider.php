<?php

declare( strict_types=1 );

/**
 * Service provider for the Admin module.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Providers;

use ArtisanPackUI\CMSFramework\Modules\Admin\Http\Middleware\CheckAdminCapability;
use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminPageManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Registers admin module services and bootstraps admin routing/middleware.
 *
 * @since 1.0.0
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton( AdminMenuManager::class, fn () => new AdminMenuManager );
        $this->app->singleton( AdminPageManager::class, fn () => new AdminPageManager );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/admin.php',
            'cms.admin',
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @since 1.0.0
     */
    public function boot( Router $router ): void
    {
        // Also tagged `cms-framework-config` so the umbrella tag publishes
        // every module's config in one command.
        $this->publishes( [
            __DIR__ . '/../config/admin.php' => config_path( 'cms/admin.php' ),
        ], [ 'cms-admin-config', 'cms-framework-config' ] );

        $router->aliasMiddleware( 'admin.can', CheckAdminCapability::class );
        $this->app->booted( function (): void {
            app( AdminPageManager::class )->registerRoutes();
        } );
    }
}
