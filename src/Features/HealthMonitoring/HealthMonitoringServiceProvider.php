<?php

namespace ArtisanPackUI\CMSFramework\Features\HealthMonitoring;

use ArtisanPackUI\CMSFramework\Console\Commands\SystemDiagnosticsCommand;
use ArtisanPackUI\CMSFramework\Services\SystemMonitoringService;
use ArtisanPackUI\CMSFramework\Services\HealthAlertService;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\SystemHealthWidget;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;

/**
 * Health Monitoring Service Provider
 *
 * Registers health monitoring services, commands, routes, and scheduled tasks
 * for the ArtisanPack UI CMS Framework health monitoring system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\HealthMonitoring
 * @since      1.4.0
 */
class HealthMonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function register(): void
    {
        // Register health monitoring configuration
        $this->mergeConfigFrom(
            __DIR__ . '/config/health-monitoring.php',
            'health-monitoring'
        );

        // Register system monitoring service as singleton
        $this->app->singleton(SystemMonitoringService::class, function ($app) {
            return new SystemMonitoringService();
        });

        // Register health alert service as singleton
        $this->app->singleton(HealthAlertService::class, function ($app) {
            return new HealthAlertService();
        });

        // Register system health widget
        $this->app->singleton(SystemHealthWidget::class, function ($app) {
            $widget = new SystemHealthWidget();
            $widget->init();
            return $widget;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function boot(): void
    {
        // Register health monitoring commands
        $this->registerCommands();

        // Register health check routes
        $this->registerRoutes();

        // Register scheduled tasks
        $this->registerScheduledTasks();

        // Register dashboard widgets
        $this->registerDashboardWidgets();

        // Publish configuration files
        $this->registerPublications();

        // Register middleware
        $this->registerMiddleware();
    }

    /**
     * Register health monitoring commands.
     *
     * @since 1.4.0
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SystemDiagnosticsCommand::class,
            ]);
        }
    }

    /**
     * Register health check routes.
     *
     * @since 1.4.0
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/health.php');
    }

    /**
     * Register scheduled tasks for health monitoring.
     *
     * @since 1.4.0
     *
     * @return void
     */
    protected function registerScheduledTasks(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Schedule automated health checks
            if (config('health-monitoring.automated_checks.enabled', false)) {
                $frequency = config('health-monitoring.automated_checks.frequency', 'hourly');
                $schedule->call([$this, 'runAutomatedHealthCheck'])
                    ->{$frequency}()
                    ->onOneServer()
                    ->withoutOverlapping()
                    ->name('health-monitoring-automated-check');
            }

            // Schedule system metrics collection
            if (config('health-monitoring.metrics.collection_enabled', true)) {
                $interval = config('health-monitoring.metrics.collection_interval', 5);
                $schedule->call([$this, 'collectSystemMetrics'])
                    ->everyMinutes($interval)
                    ->onOneServer()
                    ->withoutOverlapping()
                    ->name('health-monitoring-metrics-collection');
            }

            // Schedule alert cleanup
            if (config('health-monitoring.alerts.cleanup_enabled', true)) {
                $schedule->call([$this, 'cleanupOldAlerts'])
                    ->daily()
                    ->onOneServer()
                    ->name('health-monitoring-alert-cleanup');
            }
        });
    }

    /**
     * Register dashboard widgets.
     *
     * @since 1.4.0
     *
     * @return void
     */
    protected function registerDashboardWidgets(): void
    {
        if (config('health-monitoring.dashboard.enabled', true)) {
            // Register the system health widget with the dashboard widgets manager
            if (class_exists('ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager')) {
                $this->callAfterResolving(
                    'ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager',
                    function ($manager) {
                        $manager->register(SystemHealthWidget::class);
                    }
                );
            }
        }
    }

    /**
     * Register configuration publications.
     *
     * @since 1.4.0
     *
     * @return void
     */
    protected function registerPublications(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/config/health-monitoring.php' => config_path('health-monitoring.php'),
        ], 'artisanpack-cms-health-config');

        // Publish views if any
        if (is_dir(__DIR__ . '/resources/views')) {
            $this->publishes([
                __DIR__ . '/resources/views' => resource_path('views/vendor/health-monitoring'),
            ], 'artisanpack-cms-health-views');
        }
    }

    /**
     * Register middleware.
     *
     * @since 1.4.0
     *
     * @return void
     */
    protected function registerMiddleware(): void
    {
        // Add middleware to exclude health check endpoints from certain behaviors
        $kernel = $this->app->make(Kernel::class);
        
        // Optionally register global middleware for performance monitoring
        if (config('health-monitoring.performance.track_all_requests', false)) {
            // This would track performance metrics for all requests
            // Implementation depends on specific requirements
        }
    }

    /**
     * Run automated health check (scheduled task).
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function runAutomatedHealthCheck(): void
    {
        try {
            $healthAlertService = $this->app->make(HealthAlertService::class);
            $monitoringService = $this->app->make(SystemMonitoringService::class);
            
            // Collect system metrics
            $metrics = $monitoringService->collectMetrics();
            
            // Check for issues and send alerts
            $this->checkMetricsForAlerts($metrics, $healthAlertService);
            
            \Log::info('Automated health check completed', [
                'timestamp' => now()->toISOString(),
                'metrics_collected' => !empty($metrics),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Automated health check failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Collect system metrics (scheduled task).
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function collectSystemMetrics(): void
    {
        try {
            $monitoringService = $this->app->make(SystemMonitoringService::class);
            $metrics = $monitoringService->collectMetrics();
            
            // Store metrics in cache for dashboard widgets
            \Cache::put('system_monitoring_metrics', $metrics, now()->addMinutes(10));
            
            \Log::debug('System metrics collected', [
                'timestamp' => $metrics['timestamp'] ?? now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('System metrics collection failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Clean up old alerts (scheduled task).
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function cleanupOldAlerts(): void
    {
        try {
            $healthAlertService = $this->app->make(HealthAlertService::class);
            $retentionDays = config('health-monitoring.alerts.retention_days', 7);
            
            // Clean up alerts older than retention period
            $recentAlerts = $healthAlertService->getRecentAlerts(100); // Get more for filtering
            $cutoffDate = now()->subDays($retentionDays);
            
            $filteredAlerts = array_filter($recentAlerts, function ($alert) use ($cutoffDate) {
                $alertDate = \Carbon\Carbon::parse($alert['timestamp'] ?? now());
                return $alertDate->greaterThan($cutoffDate);
            });
            
            // Update cache with filtered alerts
            \Cache::put('health_alerts_recent', array_values($filteredAlerts), now()->addHours(24));
            
            \Log::info('Health alerts cleanup completed', [
                'retention_days' => $retentionDays,
                'alerts_retained' => count($filteredAlerts),
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Health alerts cleanup failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Check metrics for alert conditions.
     *
     * @since 1.4.0
     *
     * @param array $metrics System metrics
     * @param HealthAlertService $alertService Alert service instance
     * @return void
     */
    protected function checkMetricsForAlerts(array $metrics, HealthAlertService $alertService): void
    {
        // Check disk usage
        if (isset($metrics['resources']['disk_usage']['usage_percent'])) {
            $diskUsage = $metrics['resources']['disk_usage']['usage_percent'];
            $criticalThreshold = config('health-monitoring.thresholds.disk.critical', 95);
            $warningThreshold = config('health-monitoring.thresholds.disk.warning', 85);
            
            if ($diskUsage >= $criticalThreshold) {
                $alertService->sendCriticalAlert(
                    'disk',
                    'Disk usage critically high',
                    ['usage_percent' => $diskUsage]
                );
            } elseif ($diskUsage >= $warningThreshold) {
                $alertService->sendWarningAlert(
                    'disk',
                    'Disk usage high',
                    ['usage_percent' => $diskUsage]
                );
            }
        }
        
        // Check memory usage
        if (isset($metrics['performance']['memory_usage_bytes'])) {
            $memoryUsage = $metrics['performance']['memory_usage_bytes'];
            $memoryLimit = $this->parseMemoryLimit($metrics['performance']['memory_limit'] ?? '128M');
            
            if ($memoryLimit > 0) {
                $memoryPercent = ($memoryUsage / $memoryLimit) * 100;
                $criticalThreshold = config('health-monitoring.thresholds.memory.critical', 95);
                $warningThreshold = config('health-monitoring.thresholds.memory.warning', 85);
                
                if ($memoryPercent >= $criticalThreshold) {
                    $alertService->sendCriticalAlert(
                        'memory',
                        'Memory usage critically high',
                        ['usage_percent' => round($memoryPercent, 2)]
                    );
                } elseif ($memoryPercent >= $warningThreshold) {
                    $alertService->sendWarningAlert(
                        'memory',
                        'Memory usage high',
                        ['usage_percent' => round($memoryPercent, 2)]
                    );
                }
            }
        }
        
        // Check service availability
        if (isset($metrics['services'])) {
            foreach ($metrics['services'] as $serviceName => $serviceData) {
                if (isset($serviceData['status']) && $serviceData['status'] === 'unhealthy') {
                    $alertService->sendCriticalAlert(
                        $serviceName,
                        ucfirst($serviceName) . ' service is unhealthy',
                        $serviceData
                    );
                }
            }
        }
    }

    /**
     * Parse memory limit string to bytes.
     *
     * @since 1.4.0
     *
     * @param string $memoryLimit Memory limit string
     * @return int Memory limit in bytes
     */
    protected function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        
        if ($memoryLimit === '-1') {
            return -1; // Unlimited
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $memoryLimit,
        };
    }

    /**
     * Get the services provided by the provider.
     *
     * @since 1.4.0
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            SystemMonitoringService::class,
            HealthAlertService::class,
            SystemHealthWidget::class,
        ];
    }
}