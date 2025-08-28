<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\ErrorLog;
use ArtisanPackUI\CMSFramework\Models\PerformanceMetric;
use ArtisanPackUI\CMSFramework\Models\PerformanceTransaction;
use ArtisanPackUI\CMSFramework\Services\APMService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * APMStatusCommand.
 *
 * Artisan command to check APM system status and health,
 * including provider connectivity, database status, and system metrics.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Console\Commands
 * @since   1.3.0
 */
class APMStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:apm:status
                            {--detailed : Show detailed status information}
                            {--json : Output status as JSON}
                            {--check-providers : Test connectivity to external providers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check APM system status and health';

    /**
     * APMService instance.
     *
     * @var APMService
     */
    protected APMService $apmService;

    /**
     * Create a new command instance.
     *
     * @since 1.3.0
     *
     * @param APMService $apmService
     */
    public function __construct(APMService $apmService)
    {
        parent::__construct();
        $this->apmService = $apmService;
    }

    /**
     * Execute the console command.
     *
     * @since 1.3.0
     *
     * @return int
     */
    public function handle(): int
    {
        $detailed = $this->option('detailed');
        $outputJson = $this->option('json');
        $checkProviders = $this->option('check-providers');

        try {
            $status = $this->gatherStatusInformation($detailed, $checkProviders);

            if ($outputJson) {
                $this->line(json_encode($status, JSON_PRETTY_PRINT));
            } else {
                $this->displayStatus($status, $detailed);
            }

            // Return appropriate exit code based on overall health
            return $status['overall_health'] === 'healthy' ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            if ($outputJson) {
                $this->line(json_encode([
                    'error' => true,
                    'message' => $e->getMessage(),
                ], JSON_PRETTY_PRINT));
            } else {
                $this->error('Failed to gather APM status: ' . $e->getMessage());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Gather comprehensive status information.
     *
     * @since 1.3.0
     *
     * @param bool $detailed
     * @param bool $checkProviders
     * @return array
     */
    protected function gatherStatusInformation(bool $detailed, bool $checkProviders): array
    {
        $status = [
            'timestamp' => now()->toISOString(),
            'apm_enabled' => $this->apmService->isEnabled(),
            'overall_health' => 'healthy',
            'issues' => [],
        ];

        // Basic configuration status
        $status['configuration'] = [
            'metrics_enabled' => config('cms.apm.metrics.enabled', true),
            'error_tracking_enabled' => config('cms.apm.error_tracking.enabled', true),
            'alerts_enabled' => config('cms.apm.alerts.enabled', true),
            'ux_monitoring_enabled' => config('cms.apm.user_experience.enabled', true),
            'dashboard_enabled' => config('cms.apm.dashboard.enabled', true),
        ];

        // Database connectivity and statistics
        $status['database'] = $this->getDatabaseStatus();

        // Provider status
        $status['providers'] = $this->getProviderStatus($checkProviders);

        // System metrics
        if ($detailed) {
            $status['system_metrics'] = $this->getSystemMetrics();
            $status['recent_activity'] = $this->getRecentActivity();
        }

        // Determine overall health
        $status['overall_health'] = $this->determineOverallHealth($status);

        return $status;
    }

    /**
     * Get database status and statistics.
     *
     * @since 1.3.0
     *
     * @return array
     */
    protected function getDatabaseStatus(): array
    {
        $dbStatus = [
            'connected' => true,
            'tables_exist' => true,
            'statistics' => [],
            'issues' => [],
        ];

        try {
            // Test database connectivity
            DB::connection()->getPdo();

            // Check if APM tables exist
            $requiredTables = [
                'performance_metrics',
                'performance_transactions', 
                'error_logs',
            ];

            foreach ($requiredTables as $table) {
                if (!$this->tableExists($table)) {
                    $dbStatus['tables_exist'] = false;
                    $dbStatus['issues'][] = "Table '{$table}' does not exist";
                }
            }

            // Get table statistics if tables exist
            if ($dbStatus['tables_exist']) {
                $dbStatus['statistics'] = [
                    'performance_metrics' => PerformanceMetric::count(),
                    'performance_transactions' => PerformanceTransaction::count(),
                    'error_logs' => ErrorLog::count(),
                    'unresolved_errors' => ErrorLog::unresolved()->count(),
                ];

                // Check for recent activity
                $recentMetrics = PerformanceMetric::where('recorded_at', '>=', now()->subHour())->count();
                $recentTransactions = PerformanceTransaction::where('started_at', '>=', now()->subHour())->count();

                $dbStatus['recent_activity'] = [
                    'metrics_last_hour' => $recentMetrics,
                    'transactions_last_hour' => $recentTransactions,
                ];
            }

        } catch (\Exception $e) {
            $dbStatus['connected'] = false;
            $dbStatus['issues'][] = 'Database connection failed: ' . $e->getMessage();
        }

        return $dbStatus;
    }

    /**
     * Get provider status information.
     *
     * @since 1.3.0
     *
     * @param bool $checkConnectivity
     * @return array
     */
    protected function getProviderStatus(bool $checkConnectivity): array
    {
        $providerStatus = [];

        // Get health status from APM service
        $healthStatus = $this->apmService->getHealthStatus();

        foreach ($healthStatus['providers'] as $name => $status) {
            $providerStatus[$name] = [
                'enabled' => $status['enabled'],
                'configured' => $this->isProviderConfigured($name),
                'health' => $status['health'] ?? ['status' => 'unknown'],
            ];

            // Additional connectivity check if requested
            if ($checkConnectivity && $status['enabled']) {
                $providerStatus[$name]['connectivity'] = $this->testProviderConnectivity($name);
            }
        }

        return $providerStatus;
    }

    /**
     * Get system metrics and performance indicators.
     *
     * @since 1.3.0
     *
     * @return array
     */
    protected function getSystemMetrics(): array
    {
        $oneHourAgo = now()->subHour();
        $oneDayAgo = now()->subDay();

        return [
            'performance' => [
                'avg_response_time_1h' => PerformanceMetric::getAverageValue('http_request_duration', $oneHourAgo),
                'avg_response_time_24h' => PerformanceMetric::getAverageValue('http_request_duration', $oneDayAgo),
                'avg_memory_usage_1h' => PerformanceMetric::getAverageValue('http_request_memory', $oneHourAgo),
                'avg_db_queries_1h' => PerformanceMetric::getAverageValue('http_request_db_queries', $oneHourAgo),
            ],
            'transactions' => PerformanceTransaction::getPerformanceStats(null, $oneDayAgo),
            'errors' => ErrorLog::getErrorStats($oneDayAgo),
            'system_info' => [
                'php_version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'current_memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'peak_memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            ],
        ];
    }

    /**
     * Get recent APM activity summary.
     *
     * @since 1.3.0
     *
     * @return array
     */
    protected function getRecentActivity(): array
    {
        $oneHourAgo = now()->subHour();

        return [
            'last_hour' => [
                'new_metrics' => PerformanceMetric::where('recorded_at', '>=', $oneHourAgo)->count(),
                'new_transactions' => PerformanceTransaction::where('started_at', '>=', $oneHourAgo)->count(),
                'new_errors' => ErrorLog::where('first_seen_at', '>=', $oneHourAgo)->count(),
                'recurring_errors' => ErrorLog::where('last_seen_at', '>=', $oneHourAgo)
                    ->where('occurrence_count', '>', 1)
                    ->count(),
            ],
            'active_transactions' => PerformanceTransaction::whereNull('completed_at')
                ->where('started_at', '>=', now()->subMinutes(10))
                ->count(),
        ];
    }

    /**
     * Determine overall system health.
     *
     * @since 1.3.0
     *
     * @param array $status
     * @return string
     */
    protected function determineOverallHealth(array $status): string
    {
        $issues = [];

        // Check if APM is enabled
        if (!$status['apm_enabled']) {
            $issues[] = 'APM is disabled';
        }

        // Check database status
        if (!$status['database']['connected']) {
            $issues[] = 'Database connection failed';
        } elseif (!$status['database']['tables_exist']) {
            $issues[] = 'APM database tables missing';
        }

        // Check if any providers are having issues
        foreach ($status['providers'] as $name => $providerStatus) {
            if ($providerStatus['enabled'] && isset($providerStatus['health']['status'])) {
                if ($providerStatus['health']['status'] === 'error') {
                    $issues[] = "Provider '{$name}' has errors";
                }
            }
        }

        // Check for high error rates if we have system metrics
        if (isset($status['system_metrics']['errors']['unresolved_count'])) {
            $unresolvedErrors = $status['system_metrics']['errors']['unresolved_count'];
            if ($unresolvedErrors > 100) {
                $issues[] = "High number of unresolved errors ({$unresolvedErrors})";
            }
        }

        $status['issues'] = $issues;

        if (empty($issues)) {
            return 'healthy';
        } elseif (count($issues) < 3) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Display status information in formatted output.
     *
     * @since 1.3.0
     *
     * @param array $status
     * @param bool $detailed
     * @return void
     */
    protected function displayStatus(array $status, bool $detailed): void
    {
        // Overall status header
        $healthColor = match ($status['overall_health']) {
            'healthy' => 'green',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'white',
        };

        $this->newLine();
        $this->line('<fg=' . $healthColor . '>APM System Status: ' . strtoupper($status['overall_health']) . '</>');
        $this->line('Timestamp: ' . $status['timestamp']);
        $this->newLine();

        // Show issues if any
        if (!empty($status['issues'])) {
            $this->error('Issues Found:');
            foreach ($status['issues'] as $issue) {
                $this->line('  • ' . $issue);
            }
            $this->newLine();
        }

        // Configuration status
        $this->info('Configuration:');
        $configTable = [];
        foreach ($status['configuration'] as $key => $value) {
            $configTable[] = [
                str_replace('_', ' ', ucfirst($key)),
                $value ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>',
            ];
        }
        $this->table(['Setting', 'Status'], $configTable);

        // Database status
        $this->info('Database:');
        $dbTable = [
            ['Connected', $status['database']['connected'] ? '<fg=green>Yes</>' : '<fg=red>No</>'],
            ['Tables Exist', $status['database']['tables_exist'] ? '<fg=green>Yes</>' : '<fg=red>No</>'],
        ];

        if (isset($status['database']['statistics'])) {
            foreach ($status['database']['statistics'] as $table => $count) {
                $dbTable[] = [ucfirst(str_replace('_', ' ', $table)), number_format($count)];
            }
        }
        $this->table(['Database', 'Status'], $dbTable);

        // Provider status
        if (!empty($status['providers'])) {
            $this->info('Providers:');
            $providerTable = [];
            foreach ($status['providers'] as $name => $providerStatus) {
                $enabledStatus = $providerStatus['enabled'] ? '<fg=green>Enabled</>' : '<fg=yellow>Disabled</>';
                $configuredStatus = $providerStatus['configured'] ? '<fg=green>Yes</>' : '<fg=red>No</>';
                
                $healthStatus = 'Unknown';
                if (isset($providerStatus['health']['status'])) {
                    $healthStatus = match ($providerStatus['health']['status']) {
                        'healthy', 'ok' => '<fg=green>Healthy</>',
                        'warning' => '<fg=yellow>Warning</>',
                        'error' => '<fg=red>Error</>',
                        default => $providerStatus['health']['status'],
                    };
                }

                $providerTable[] = [
                    ucfirst($name),
                    $enabledStatus,
                    $configuredStatus,
                    $healthStatus,
                ];
            }
            $this->table(['Provider', 'Enabled', 'Configured', 'Health'], $providerTable);
        }

        // Detailed information
        if ($detailed && isset($status['system_metrics'])) {
            $this->info('System Metrics (Last 24 Hours):');
            $metrics = $status['system_metrics'];
            
            $metricsTable = [
                ['Avg Response Time', round($metrics['performance']['avg_response_time_24h'], 2) . ' ms'],
                ['Avg Memory Usage', round($metrics['performance']['avg_memory_usage_1h'], 2) . ' MB'],
                ['Total Requests', number_format($metrics['transactions']['total_count'])],
                ['Error Rate', $metrics['transactions']['error_rate'] . '%'],
                ['Total Errors', number_format($metrics['errors']['total_errors'])],
                ['Unresolved Errors', number_format($metrics['errors']['unresolved_count'])],
            ];
            $this->table(['Metric', 'Value'], $metricsTable);
        }
    }

    /**
     * Check if a database table exists.
     *
     * @since 1.3.0
     *
     * @param string $tableName
     * @return bool
     */
    protected function tableExists(string $tableName): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($tableName);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a provider is properly configured.
     *
     * @since 1.3.0
     *
     * @param string $providerName
     * @return bool
     */
    protected function isProviderConfigured(string $providerName): bool
    {
        return match ($providerName) {
            'newrelic' => !empty(config('cms.apm.providers.newrelic.license_key')),
            'datadog' => !empty(config('cms.apm.providers.datadog.api_key')),
            'sentry' => !empty(config('cms.apm.providers.sentry.dsn')),
            'internal' => true, // Always considered configured
            default => false,
        };
    }

    /**
     * Test connectivity to external provider.
     *
     * @since 1.3.0
     *
     * @param string $providerName
     * @return array
     */
    protected function testProviderConnectivity(string $providerName): array
    {
        // This would implement actual connectivity tests for each provider
        // For now, return a basic status
        return [
            'status' => 'not_tested',
            'message' => 'Connectivity test not implemented for ' . $providerName,
        ];
    }
}