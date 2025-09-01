<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Services\SystemMonitoringService;
use ArtisanPackUI\CMSFramework\Services\HealthAlertService;
use ArtisanPackUI\CMSFramework\Http\Controllers\HealthCheckController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Exception;

/**
 * System Diagnostics Command
 *
 * Provides comprehensive system diagnostics, troubleshooting utilities,
 * and performance analysis tools for the ArtisanPack UI CMS Framework.
 *
 * @package    ArtisanPackUI\CMSFramework\Console\Commands
 * @since      1.4.0
 */
class SystemDiagnosticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 1.4.0
     *
     * @var string
     */
    protected $signature = 'system:diagnostics
                           {--health : Run comprehensive health checks}
                           {--performance : Analyze system performance}
                           {--dependencies : Check dependency status}
                           {--resources : Check system resources}
                           {--config : Validate system configuration}
                           {--alerts : Show recent alerts}
                           {--export= : Export diagnostics to file (json|txt)}
                           {--verbose : Show detailed output}
                           {--fix : Attempt to fix common issues}';

    /**
     * The console command description.
     *
     * @since 1.4.0
     *
     * @var string
     */
    protected $description = 'Run comprehensive system diagnostics and troubleshooting';

    /**
     * The system monitoring service instance.
     *
     * @since 1.4.0
     *
     * @var SystemMonitoringService
     */
    protected SystemMonitoringService $monitoringService;

    /**
     * The health alert service instance.
     *
     * @since 1.4.0
     *
     * @var HealthAlertService
     */
    protected HealthAlertService $alertService;

    /**
     * Create a new command instance.
     *
     * @since 1.4.0
     *
     * @param SystemMonitoringService $monitoringService
     * @param HealthAlertService $alertService
     */
    public function __construct(
        SystemMonitoringService $monitoringService,
        HealthAlertService $alertService
    ) {
        parent::__construct();
        $this->monitoringService = $monitoringService;
        $this->alertService = $alertService;
    }

    /**
     * Execute the console command.
     *
     * @since 1.4.0
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $this->info('🔧 System Diagnostics Tool');
        $this->info('==========================');
        $this->newLine();

        $diagnostics = [];
        $startTime = microtime(true);

        try {
            // Determine which checks to run
            $runHealth = $this->option('health') || !$this->hasSpecificOptions();
            $runPerformance = $this->option('performance') || !$this->hasSpecificOptions();
            $runDependencies = $this->option('dependencies') || !$this->hasSpecificOptions();
            $runResources = $this->option('resources') || !$this->hasSpecificOptions();
            $runConfig = $this->option('config') || !$this->hasSpecificOptions();
            $runAlerts = $this->option('alerts') || !$this->hasSpecificOptions();

            // Run diagnostics
            if ($runHealth) {
                $diagnostics['health'] = $this->runHealthDiagnostics();
            }

            if ($runPerformance) {
                $diagnostics['performance'] = $this->runPerformanceDiagnostics();
            }

            if ($runDependencies) {
                $diagnostics['dependencies'] = $this->runDependencyDiagnostics();
            }

            if ($runResources) {
                $diagnostics['resources'] = $this->runResourceDiagnostics();
            }

            if ($runConfig) {
                $diagnostics['configuration'] = $this->runConfigurationDiagnostics();
            }

            if ($runAlerts) {
                $diagnostics['alerts'] = $this->runAlertDiagnostics();
            }

            // Calculate overall status
            $overallStatus = $this->calculateOverallStatus($diagnostics);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Display summary
            $this->displaySummary($overallStatus, $executionTime);

            // Export results if requested
            if ($exportFormat = $this->option('export')) {
                $this->exportDiagnostics($diagnostics, $exportFormat);
            }

            // Attempt fixes if requested
            if ($this->option('fix')) {
                $this->attemptFixes($diagnostics);
            }

            return $overallStatus === 'healthy' ? 0 : 1;

        } catch (Exception $e) {
            $this->error('Diagnostics failed: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Check if user specified any specific diagnostic options.
     *
     * @since 1.4.0
     *
     * @return bool Whether specific options were provided
     */
    protected function hasSpecificOptions(): bool
    {
        return $this->option('health') ||
               $this->option('performance') ||
               $this->option('dependencies') ||
               $this->option('resources') ||
               $this->option('config') ||
               $this->option('alerts');
    }

    /**
     * Run health diagnostics.
     *
     * @since 1.4.0
     *
     * @return array Health diagnostic results
     */
    protected function runHealthDiagnostics(): array
    {
        $this->info('🏥 Running Health Diagnostics...');

        try {
            $healthController = new HealthCheckController();
            $request = request();
            $request->query->set('details', 'true');
            
            $healthResponse = $healthController->health($request);
            $healthData = json_decode($healthResponse->getContent(), true);

            $this->displayHealthResults($healthData);
            
            return $healthData;

        } catch (Exception $e) {
            $this->error('Health diagnostics failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run performance diagnostics.
     *
     * @since 1.4.0
     *
     * @return array Performance diagnostic results
     */
    protected function runPerformanceDiagnostics(): array
    {
        $this->info('⚡ Running Performance Diagnostics...');

        try {
            $metrics = $this->monitoringService->collectMetrics();
            $performance = $metrics['performance'] ?? [];
            
            $this->displayPerformanceResults($performance);
            
            return $performance;

        } catch (Exception $e) {
            $this->error('Performance diagnostics failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run dependency diagnostics.
     *
     * @since 1.4.0
     *
     * @return array Dependency diagnostic results
     */
    protected function runDependencyDiagnostics(): array
    {
        $this->info('🔗 Running Dependency Diagnostics...');

        $results = [];

        try {
            // Database check
            $results['database'] = $this->checkDatabase();
            
            // Cache check
            $results['cache'] = $this->checkCache();
            
            // Queue check
            $results['queue'] = $this->checkQueue();
            
            // Storage check
            $results['storage'] = $this->checkStorage();

            $this->displayDependencyResults($results);
            
            return $results;

        } catch (Exception $e) {
            $this->error('Dependency diagnostics failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run resource diagnostics.
     *
     * @since 1.4.0
     *
     * @return array Resource diagnostic results
     */
    protected function runResourceDiagnostics(): array
    {
        $this->info('💾 Running Resource Diagnostics...');

        try {
            $metrics = $this->monitoringService->collectMetrics();
            $resources = $metrics['resources'] ?? [];
            
            $this->displayResourceResults($resources);
            
            return $resources;

        } catch (Exception $e) {
            $this->error('Resource diagnostics failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run configuration diagnostics.
     *
     * @since 1.4.0
     *
     * @return array Configuration diagnostic results
     */
    protected function runConfigurationDiagnostics(): array
    {
        $this->info('⚙️  Running Configuration Diagnostics...');

        $results = [
            'environment' => $this->checkEnvironment(),
            'permissions' => $this->checkPermissions(),
            'extensions' => $this->checkPHPExtensions(),
            'settings' => $this->checkCriticalSettings(),
        ];

        $this->displayConfigurationResults($results);
        
        return $results;
    }

    /**
     * Run alert diagnostics.
     *
     * @since 1.4.0
     *
     * @return array Alert diagnostic results
     */
    protected function runAlertDiagnostics(): array
    {
        $this->info('🚨 Running Alert Diagnostics...');

        try {
            $recentAlerts = $this->alertService->getRecentAlerts(10);
            $alertStats = $this->alertService->getAlertStats();
            
            $results = [
                'recent_alerts' => $recentAlerts,
                'statistics' => $alertStats,
            ];

            $this->displayAlertResults($results);
            
            return $results;

        } catch (Exception $e) {
            $this->error('Alert diagnostics failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Display health diagnostic results.
     *
     * @since 1.4.0
     *
     * @param array $healthData Health data
     * @return void
     */
    protected function displayHealthResults(array $healthData): void
    {
        $status = $healthData['status'] ?? 'unknown';
        $color = $this->getStatusColor($status);
        
        $this->line("<fg={$color}>Overall Status: " . strtoupper($status) . "</>");
        $this->line("Response Time: " . ($healthData['response_time_ms'] ?? 0) . "ms");
        $this->newLine();

        if (isset($healthData['checks'])) {
            $headers = ['Service', 'Status', 'Details'];
            $rows = [];

            foreach ($healthData['checks'] as $service => $check) {
                $statusColor = $this->getStatusColor($check['status'] ?? 'unknown');
                $details = '';
                
                if (isset($check['error'])) {
                    $details = $check['error'];
                } elseif (isset($check['warnings'])) {
                    $details = implode(', ', $check['warnings']);
                } elseif (isset($check['response_time_ms'])) {
                    $details = $check['response_time_ms'] . 'ms';
                }

                $rows[] = [
                    ucfirst($service),
                    "<fg={$statusColor}>" . strtoupper($check['status'] ?? 'unknown') . "</fg>",
                    $details,
                ];
            }

            $this->table($headers, $rows);
        }
    }

    /**
     * Display performance diagnostic results.
     *
     * @since 1.4.0
     *
     * @param array $performance Performance data
     * @return void
     */
    protected function displayPerformanceResults(array $performance): void
    {
        if (isset($performance['memory_usage_bytes'])) {
            $memoryUsage = $this->formatBytes($performance['memory_usage_bytes']);
            $memoryLimit = $performance['memory_limit'] ?? 'unlimited';
            $this->line("Memory Usage: {$memoryUsage} / {$memoryLimit}");
        }

        if (isset($performance['memory_peak_bytes'])) {
            $peakMemory = $this->formatBytes($performance['memory_peak_bytes']);
            $this->line("Peak Memory: {$peakMemory}");
        }

        $this->newLine();
    }

    /**
     * Display dependency diagnostic results.
     *
     * @since 1.4.0
     *
     * @param array $results Dependency results
     * @return void
     */
    protected function displayDependencyResults(array $results): void
    {
        $headers = ['Dependency', 'Status', 'Details'];
        $rows = [];

        foreach ($results as $dependency => $result) {
            $status = $result['status'] ?? 'unknown';
            $statusColor = $this->getStatusColor($status);
            $details = $result['details'] ?? $result['error'] ?? 'OK';

            $rows[] = [
                ucfirst($dependency),
                "<fg={$statusColor}>" . strtoupper($status) . "</fg>",
                $details,
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Display resource diagnostic results.
     *
     * @since 1.4.0
     *
     * @param array $resources Resource data
     * @return void
     */
    protected function displayResourceResults(array $resources): void
    {
        if (isset($resources['disk_usage'])) {
            $disk = $resources['disk_usage'];
            $this->line("Disk Usage: {$disk['usage_percent']}% ({$this->formatBytes($disk['used_bytes'])} / {$this->formatBytes($disk['total_bytes'])})");
        }

        if (isset($resources['cpu_usage'])) {
            $this->line("CPU Usage: {$resources['cpu_usage']}%");
        }

        $this->newLine();
    }

    /**
     * Display configuration diagnostic results.
     *
     * @since 1.4.0
     *
     * @param array $results Configuration results
     * @return void
     */
    protected function displayConfigurationResults(array $results): void
    {
        foreach ($results as $category => $categoryResults) {
            $this->line("<fg=yellow>" . ucfirst($category) . ":</>");
            
            if (is_array($categoryResults)) {
                foreach ($categoryResults as $item => $status) {
                    $color = is_bool($status) ? ($status ? 'green' : 'red') : 'white';
                    $statusText = is_bool($status) ? ($status ? 'OK' : 'FAIL') : $status;
                    $this->line("  {$item}: <fg={$color}>{$statusText}</>");
                }
            }
            
            $this->newLine();
        }
    }

    /**
     * Display alert diagnostic results.
     *
     * @since 1.4.0
     *
     * @param array $results Alert results
     * @return void
     */
    protected function displayAlertResults(array $results): void
    {
        $stats = $results['statistics'] ?? [];
        
        $this->line("Alert Statistics:");
        $this->line("  Critical: {$stats['critical_count']}");
        $this->line("  Warning: {$stats['warning_count']}");
        $this->line("  Info: {$stats['info_count']}");
        $this->newLine();

        $recentAlerts = $results['recent_alerts'] ?? [];
        
        if (!empty($recentAlerts)) {
            $this->line("Recent Alerts:");
            
            foreach (array_slice($recentAlerts, 0, 5) as $alert) {
                $severity = strtoupper($alert['severity'] ?? 'unknown');
                $service = $alert['service'] ?? 'unknown';
                $message = $alert['message'] ?? 'No message';
                $timestamp = $alert['timestamp'] ?? 'unknown';
                
                $color = match ($alert['severity'] ?? 'info') {
                    'critical' => 'red',
                    'warning' => 'yellow',
                    default => 'white',
                };
                
                $this->line("  <fg={$color}>[{$severity}]</> {$service}: {$message} ({$timestamp})");
            }
        } else {
            $this->line("No recent alerts.");
        }
        
        $this->newLine();
    }

    /**
     * Check database connectivity.
     *
     * @since 1.4.0
     *
     * @return array Database check results
     */
    protected function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return [
                'status' => 'healthy',
                'details' => 'Connection successful',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connectivity.
     *
     * @since 1.4.0
     *
     * @return array Cache check results
     */
    protected function checkCache(): array
    {
        try {
            $testKey = 'diagnostic_test_' . time();
            Cache::put($testKey, 'test', 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($retrieved === 'test') {
                return [
                    'status' => 'healthy',
                    'details' => 'Read/write successful',
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Cache read/write test failed',
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue connectivity.
     *
     * @since 1.4.0
     *
     * @return array Queue check results
     */
    protected function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            Queue::connection($connection);
            
            return [
                'status' => 'healthy',
                'details' => "Connection '{$connection}' accessible",
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage connectivity.
     *
     * @since 1.4.0
     *
     * @return array Storage check results
     */
    protected function checkStorage(): array
    {
        try {
            $testFile = 'diagnostic_test_' . time() . '.txt';
            Storage::put($testFile, 'test');
            $retrieved = Storage::get($testFile);
            Storage::delete($testFile);
            
            if ($retrieved === 'test') {
                return [
                    'status' => 'healthy',
                    'details' => 'Read/write successful',
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Storage read/write test failed',
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check environment configuration.
     *
     * @since 1.4.0
     *
     * @return array Environment check results
     */
    protected function checkEnvironment(): array
    {
        return [
            'APP_ENV' => config('app.env'),
            'APP_DEBUG' => config('app.debug') ? 'enabled' : 'disabled',
            'PHP_VERSION' => PHP_VERSION,
            'LARAVEL_VERSION' => app()->version(),
        ];
    }

    /**
     * Check file permissions.
     *
     * @since 1.4.0
     *
     * @return array Permission check results
     */
    protected function checkPermissions(): array
    {
        $paths = [
            'storage' => storage_path(),
            'cache' => storage_path('framework/cache'),
            'logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $results = [];
        
        foreach ($paths as $name => $path) {
            $results[$name] = is_writable($path);
        }

        return $results;
    }

    /**
     * Check required PHP extensions.
     *
     * @since 1.4.0
     *
     * @return array Extension check results
     */
    protected function checkPHPExtensions(): array
    {
        $required = [
            'openssl', 'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath'
        ];

        $results = [];
        
        foreach ($required as $extension) {
            $results[$extension] = extension_loaded($extension);
        }

        return $results;
    }

    /**
     * Check critical application settings.
     *
     * @since 1.4.0
     *
     * @return array Settings check results
     */
    protected function checkCriticalSettings(): array
    {
        return [
            'APP_KEY_SET' => !empty(config('app.key')),
            'DATABASE_CONFIGURED' => !empty(config('database.default')),
            'CACHE_CONFIGURED' => !empty(config('cache.default')),
            'QUEUE_CONFIGURED' => !empty(config('queue.default')),
        ];
    }

    /**
     * Calculate overall diagnostic status.
     *
     * @since 1.4.0
     *
     * @param array $diagnostics All diagnostic results
     * @return string Overall status
     */
    protected function calculateOverallStatus(array $diagnostics): string
    {
        $hasUnhealthy = false;
        $hasDegraded = false;

        foreach ($diagnostics as $category => $results) {
            if (isset($results['status'])) {
                if ($results['status'] === 'unhealthy' || $results['status'] === 'error') {
                    $hasUnhealthy = true;
                } elseif ($results['status'] === 'degraded') {
                    $hasDegraded = true;
                }
            } elseif (is_array($results)) {
                // Check nested results
                foreach ($results as $item) {
                    if (is_array($item) && isset($item['status'])) {
                        if ($item['status'] === 'unhealthy' || $item['status'] === 'error') {
                            $hasUnhealthy = true;
                        } elseif ($item['status'] === 'degraded') {
                            $hasDegraded = true;
                        }
                    }
                }
            }
        }

        if ($hasUnhealthy) {
            return 'unhealthy';
        } elseif ($hasDegraded) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * Display diagnostic summary.
     *
     * @since 1.4.0
     *
     * @param string $overallStatus Overall system status
     * @param float $executionTime Execution time in milliseconds
     * @return void
     */
    protected function displaySummary(string $overallStatus, float $executionTime): void
    {
        $this->newLine();
        $this->info('📊 Diagnostic Summary');
        $this->info('====================');
        
        $color = $this->getStatusColor($overallStatus);
        $this->line("<fg={$color}>Overall Status: " . strtoupper($overallStatus) . "</>");
        $this->line("Execution Time: {$executionTime}ms");
        $this->line("Timestamp: " . now()->toDateTimeString());
    }

    /**
     * Export diagnostic results to file.
     *
     * @since 1.4.0
     *
     * @param array $diagnostics Diagnostic results
     * @param string $format Export format (json|txt)
     * @return void
     */
    protected function exportDiagnostics(array $diagnostics, string $format): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "system_diagnostics_{$timestamp}.{$format}";
        $path = storage_path("logs/{$filename}");

        try {
            if ($format === 'json') {
                file_put_contents($path, json_encode($diagnostics, JSON_PRETTY_PRINT));
            } else {
                $content = "System Diagnostics Report\n";
                $content .= "Generated: " . now()->toDateTimeString() . "\n\n";
                $content .= print_r($diagnostics, true);
                file_put_contents($path, $content);
            }

            $this->info("Diagnostics exported to: {$path}");
        } catch (Exception $e) {
            $this->error("Failed to export diagnostics: " . $e->getMessage());
        }
    }

    /**
     * Attempt to fix common issues.
     *
     * @since 1.4.0
     *
     * @param array $diagnostics Diagnostic results
     * @return void
     */
    protected function attemptFixes(array $diagnostics): void
    {
        $this->info('🔧 Attempting to fix common issues...');
        
        // Clear caches
        $this->line('Clearing application caches...');
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        
        $this->info('Basic fixes applied. Re-run diagnostics to check status.');
    }

    /**
     * Get color for status display.
     *
     * @since 1.4.0
     *
     * @param string $status Status string
     * @return string Color name
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'healthy' => 'green',
            'degraded' => 'yellow',
            'unhealthy', 'error' => 'red',
            default => 'white',
        };
    }

    /**
     * Format bytes to human readable format.
     *
     * @since 1.4.0
     *
     * @param int $bytes Number of bytes
     * @return string Formatted string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}