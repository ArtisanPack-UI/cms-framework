<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Providers;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginRegistry;
use Exception;
use Illuminate\Support\ServiceProvider;
use Schema;

class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register PluginManager as singleton
        $this->app->singleton( PluginManager::class, function ( $app ) {
            return new PluginManager;
        } );

        // Register UpdateManager as singleton
        $this->app->singleton( UpdateManager::class, function ( $app ) {
            return new UpdateManager(
                $app->make( PluginManager::class ),
            );
        } );

        // Shared registry for plugin-declared nav entries and federated
        // modules; PluginServiceProvider writes into it, framework filters
        // read from it. Container-keyed by slug/name so re-registration under
        // Octane is idempotent.
        $this->app->singleton( PluginRegistry::class );

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/plugins.php',
            'cms.plugins',
        );
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes( [
            __DIR__ . '/../config/plugins.php' => config_path( 'cms/plugins.php' ),
        ], 'cms-plugins-config' );

        // Load API routes
        $this->loadRoutesFrom( __DIR__ . '/../routes/api.php' );

        // Load migrations
        $this->loadMigrationsFrom( __DIR__ . '/../../../database/migrations' );

        $this->registerPluginRegistryFilters();

        // Load active plugins EARLY in boot cycle
        // Only attempt if the plugins table exists (to handle fresh installations)
        try {
            if ( Schema::hasTable( 'plugins' ) ) {
                $pluginManager = $this->app->make( PluginManager::class );
                $pluginManager->loadActivePlugins();
            }
        } catch ( Exception $e ) {
            // Silently fail during installation/migration
            logger()->debug( 'Plugin loading skipped: ' . $e->getMessage() );
        }
    }

    /**
     * Wire ONE filter callback per hook that surfaces the PluginRegistry
     * contents. Registering once at framework boot avoids the Octane
     * accumulation pattern where per-plugin boot() calls would add a fresh
     * closure to the filter chain on every request.
     */
    protected function registerPluginRegistryFilters(): void
    {
        $registry = $this->app->make( PluginRegistry::class );

        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( $registry ): array {
            foreach ( $registry->navEntries() as $slug => $entry ) {
                $menu[ $slug ] = array_merge( $entry, $menu[ $slug ] ?? [] );
            }

            return $menu;
        } );

        addFilter( 'ap.plugins.federatedModules', function ( array $modules ) use ( $registry ): array {
            return array_merge( $modules, $registry->federatedModules() );
        } );
    }
}
