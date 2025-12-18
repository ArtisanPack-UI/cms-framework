<?php

/**
 * Core Service Provider.
 *
 * Registers core services used across the CMS Framework such as the AssetManager.
 *
 * @since      2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Providers;

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\AssetManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\CheckForUpdateCommand;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\CheckForUpdateScheduled;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\PerformUpdateCommand;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\RollbackUpdateCommand;
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
     */
    public function register(): void
    {
        $this->app->singleton(AssetManager::class, function ($app) {
            return new AssetManager;
        });

        // Merge update configuration
        $this->mergeConfigFrom(
            __DIR__.'/../Updates/config/updates.php',
            'cms.updates'
        );
    }

    /**
     * Bootstrap the core services.
     *
     * @since 2.0.0
     */
    public function boot(): void
    {
        // Publish update configuration
        $this->publishes([
            __DIR__.'/../Updates/config/updates.php' => config_path('cms/updates.php'),
        ], 'cms-updates-config');

        // Register update commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckForUpdateCommand::class,
                PerformUpdateCommand::class,
                RollbackUpdateCommand::class,
                CheckForUpdateScheduled::class,
            ]);
        }
    }
}
