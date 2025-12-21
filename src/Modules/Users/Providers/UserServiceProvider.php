<?php

declare( strict_types = 1 );

/**
 * User Service Provider for the CMS Framework Users Module.
 *
 * This service provider handles the registration and bootstrapping of user-related
 * services including role management, permission management, migrations, and API routes.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Providers;

use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\RoleManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for user-related functionality within the CMS Framework.
 *
 * Registers role and permission managers as singletons and bootstraps
 * database migrations and API routes for the users module.
 *
 * @since 1.0.0
 */
class UserServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Registers the RoleManager and PermissionManager as singleton instances
     * within the application container for dependency injection.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton( RoleManager::class, fn () => new RoleManager );
        $this->app->singleton( PermissionManager::class, fn () => new PermissionManager );
    }

    /**
     * Bootstrap any application services.
     *
     * Loads database migrations for the users module and registers API routes
     * with the 'api/v1' prefix and 'api' middleware group.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        Route::prefix( 'api/v1' )
            ->middleware( 'api' )
            ->group( __DIR__ . '/../routes/api.php' );
    }
}
