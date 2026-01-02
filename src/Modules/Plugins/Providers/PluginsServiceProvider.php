<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Providers;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use Exception;
use Illuminate\Support\ServiceProvider;
use Schema;

class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register PluginManager as singleton
        $this->app->singleton(PluginManager::class, function ($app) {
            return new PluginManager;
        });

        // Register UpdateManager as singleton
        $this->app->singleton(UpdateManager::class, function ($app) {
            return new UpdateManager(
                $app->make(PluginManager::class),
            );
        });

        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/plugins.php',
            'cms.plugins',
        );
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/plugins.php' => config_path('cms/plugins.php'),
        ], 'cms-plugins-config');

        // Load API routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

        // Load active plugins EARLY in boot cycle
        // Only attempt if the plugins table exists (to handle fresh installations)
        try {
            if (Schema::hasTable('plugins')) {
                $pluginManager = $this->app->make(PluginManager::class);
                $pluginManager->loadActivePlugins();
            }
        } catch (Exception $e) {
            // Silently fail during installation/migration
            logger()->debug('Plugin loading skipped: '.$e->getMessage());
        }
    }
}
