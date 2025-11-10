<?php

/**
 * ContentTypes Service Provider
 *
 * Registers the ContentTypes module services and bootstraps routes, views, and migrations.
 *
 * @since 2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\ContentTypes\Providers
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Providers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Policies\ContentTypePolicy;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Policies\CustomFieldPolicy;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the ContentTypes module services.
 *
 * @since 2.0.0
 */
class ContentTypesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 2.0.0
     */
    public function register(): void
    {
        // Register ContentTypeManager as singleton
        $this->app->singleton(ContentTypeManager::class, fn () => new ContentTypeManager);

        // Register CustomFieldManager as singleton
        $this->app->singleton(CustomFieldManager::class, fn () => new CustomFieldManager);

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

        // Register policies
        Gate::policy(ContentType::class, ContentTypePolicy::class);
        Gate::policy(CustomField::class, CustomFieldPolicy::class);
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
