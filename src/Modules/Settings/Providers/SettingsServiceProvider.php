<?php

declare( strict_types = 1 );

/**
 * Service provider for the Settings module.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Providers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Settings module services and bootstraps Settings routing/middleware.
 *
 * @since 1.0.0
 */
class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton( SettingsManager::class, fn () => new SettingsManager );
    }

    /**
     * Bootstrap any application services.
     *
     * @since 1.0.0
     */
    public function boot( Router $router ): void
    {
        Route::prefix( 'api/v1' )
            ->middleware( 'api' )
            ->group( __DIR__ . '/../routes/api.php' );
    }
}
