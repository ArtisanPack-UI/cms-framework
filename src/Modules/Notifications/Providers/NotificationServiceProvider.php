<?php

/**
 * Notification Service Provider
 *
 * Registers the Notification module services and bootstraps routes, views, and migrations.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Providers;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Managers\NotificationManager;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Policies\NotificationPolicy;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Notification module services.
 *
 * @since 2.0.0
 */
class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 2.0.0
     */
    public function register(): void
    {
        // Register NotificationManager as singleton
        $this->app->singleton(NotificationManager::class, fn () => new NotificationManager);

        // Load helpers
        $this->loadHelpers();
    }

    /**
     * Bootstrap any application services.
     *
     * @since 2.0.0
     */
    public function boot(Router $router): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load API routes
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'notifications');

        // Register policy
        Gate::policy(Notification::class, NotificationPolicy::class);
    }

    /**
     * Load helper functions.
     *
     * @since 2.0.0
     */
    protected function loadHelpers(): void
    {
        $helpersPath = __DIR__.'/../helpers.php';

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }
}
