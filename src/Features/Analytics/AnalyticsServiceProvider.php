<?php

namespace ArtisanPackUI\CMSFramework\Features\Analytics;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use ArtisanPackUI\CMSFramework\Features\Analytics\Commands\AnalyticsCleanupCommand;
use ArtisanPackUI\CMSFramework\Features\Analytics\Middleware\AnalyticsTrackingMiddleware;

/**
 * Analytics Service Provider
 *
 * Registers analytics-related services, middleware, commands, and scheduled tasks
 * for the ArtisanPack UI CMS Framework analytics system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Analytics
 * @since      1.3.0
 */
class AnalyticsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function register(): void
    {
        // Register analytics configuration
        $this->mergeConfigFrom(
            __DIR__ . '/config/analytics.php',
            'artisanpack-cms.analytics'
        );

        // Register analytics manager as singleton
        $this->app->singleton(AnalyticsManager::class, function ($app) {
            return new AnalyticsManager($app);
        });

        // Register analytics logger as singleton
        $this->app->singleton(AnalyticsLogger::class, function ($app) {
            return new AnalyticsLogger($app->make(AnalyticsManager::class));
        });

        // Register analytics contracts
        $this->app->bind(
            \ArtisanPackUI\CMSFramework\Contracts\AnalyticsManagerInterface::class,
            AnalyticsManager::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function boot(): void
    {
        // Register analytics middleware
        $this->registerMiddleware();

        // Register analytics commands
        $this->registerCommands();

        // Register scheduled tasks
        $this->registerScheduledTasks();

        // Publish analytics configuration
        $this->publishes([
            __DIR__ . '/config/analytics.php' => config_path('artisanpack-cms-analytics.php'),
        ], 'artisanpack-cms-config');

        // Load analytics migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register analytics routes if enabled
        if (config('artisanpack-cms.analytics.enable_api_endpoints', false)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/analytics.php');
        }
    }

    /**
     * Register analytics middleware.
     *
     * @since 1.3.0
     *
     * @return void
     */
    protected function registerMiddleware(): void
    {
        // Register analytics tracking middleware
        $this->app['router']->aliasMiddleware('analytics.tracking', AnalyticsTrackingMiddleware::class);

        // Auto-register middleware if enabled in config
        if (config('artisanpack-cms.analytics.auto_track_page_views', true)) {
            $this->app['router']->pushMiddlewareToGroup('web', AnalyticsTrackingMiddleware::class);
        }
    }

    /**
     * Register analytics commands.
     *
     * @since 1.3.0
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AnalyticsCleanupCommand::class,
            ]);
        }
    }

    /**
     * Register scheduled tasks for analytics.
     *
     * @since 1.3.0
     *
     * @return void
     */
    protected function registerScheduledTasks(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Schedule analytics cleanup based on configuration
            $retentionDays = config('artisanpack-cms.analytics.retention_days', 365);
            $cleanupFrequency = config('artisanpack-cms.analytics.cleanup_frequency', 'daily');

            if ($retentionDays > 0) {
                $schedule->command('analytics:cleanup', ['--days=' . $retentionDays])
                    ->{$cleanupFrequency}()
                    ->onOneServer()
                    ->withoutOverlapping();
            }
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @since 1.3.0
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            AnalyticsManager::class,
            AnalyticsLogger::class,
            \ArtisanPackUI\CMSFramework\Contracts\AnalyticsManagerInterface::class,
        ];
    }
}