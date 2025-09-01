<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Health Check Controller
 *
 * Provides comprehensive health check endpoints for monitoring system status,
 * dependencies, and overall application health in the ArtisanPack UI CMS Framework.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.4.0
 */
class HealthCheckController extends Controller
{
    /**
     * Basic health check endpoint - lightweight ping.
     *
     * Returns a simple OK response to verify the application is responding.
     * This endpoint is designed to be as lightweight as possible for load balancer
     * health checks and basic availability monitoring.
     *
     * @since 1.4.0
     *
     * @return JsonResponse Basic health status
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'service' => 'artisanpack-cms',
            'version' => config('app.version', '1.0.0'),
        ]);
    }

    /**
     * Comprehensive system health check.
     *
     * Performs detailed health checks across all system components including
     * database, cache, queue, storage, and application-specific checks.
     *
     * @since 1.4.0
     *
     * @param Request $request HTTP request with optional query parameters
     * @return JsonResponse Comprehensive health status
     */
    public function health(Request $request): JsonResponse
    {
        $includeDetails = $request->query('details', 'false') === 'true';
        $checks = $request->query('checks', 'all');
        
        $startTime = microtime(true);
        
        try {
            $results = $this->performHealthChecks($checks, $includeDetails);
            $overallStatus = $this->calculateOverallStatus($results);
            
            $response = [
                'status' => $overallStatus,
                'timestamp' => now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'checks' => $results,
            ];

            if ($includeDetails) {
                $response['system_info'] = $this->getSystemInfo();
                $response['performance_metrics'] = $this->getPerformanceMetrics();
            }

            $httpStatus = $overallStatus === 'healthy' ? 200 : 503;
            return response()->json($response, $httpStatus);
            
        } catch (Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => 'Health check system failure',
                'checks' => ['application' => ['status' => 'unhealthy', 'error' => 'System error']],
            ], 503);
        }
    }

    /**
     * Database-specific health check.
     *
     * Performs comprehensive database connectivity and performance checks.
     *
     * @since 1.4.0
     *
     * @return JsonResponse Database health status
     */
    public function database(): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->checkDatabase(true);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'status' => $result['status'],
                'timestamp' => now()->toISOString(),
                'response_time_ms' => $responseTime,
                'database' => $result,
            ], $result['status'] === 'healthy' ? 200 : 503);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'database' => [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ],
            ], 503);
        }
    }

    /**
     * Dependencies health check.
     *
     * Checks the health of external dependencies like cache, queue, and storage.
     *
     * @since 1.4.0
     *
     * @return JsonResponse Dependencies health status
     */
    public function dependencies(): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $results = [
                'cache' => $this->checkCache(true),
                'queue' => $this->checkQueue(true),
                'storage' => $this->checkStorage(true),
            ];
            
            $overallStatus = $this->calculateOverallStatus($results);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'status' => $overallStatus,
                'timestamp' => now()->toISOString(),
                'response_time_ms' => $responseTime,
                'dependencies' => $results,
            ], $overallStatus === 'healthy' ? 200 : 503);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'dependencies' => [
                    'error' => $e->getMessage(),
                ],
            ], 503);
        }
    }

    /**
     * Readiness check endpoint.
     *
     * Determines if the application is ready to serve traffic by checking
     * critical dependencies and initialization status.
     *
     * @since 1.4.0
     *
     * @return JsonResponse Readiness status
     */
    public function ready(): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Check critical components required for serving traffic
            $criticalChecks = [
                'database' => $this->checkDatabase(false),
                'application' => $this->checkApplication(false),
            ];
            
            $isReady = collect($criticalChecks)->every(fn($check) => $check['status'] === 'healthy');
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'status' => $isReady ? 'ready' : 'not_ready',
                'timestamp' => now()->toISOString(),
                'response_time_ms' => $responseTime,
                'checks' => $criticalChecks,
            ], $isReady ? 200 : 503);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'not_ready',
                'timestamp' => now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Liveness check endpoint.
     *
     * Determines if the application is alive and should continue running.
     * This is a basic check that doesn't depend on external services.
     *
     * @since 1.4.0
     *
     * @return JsonResponse Liveness status
     */
    public function live(): JsonResponse
    {
        try {
            // Basic application liveness checks
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $memoryUsagePercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
            
            // Consider unhealthy if memory usage is above 95%
            $isAlive = $memoryUsagePercent < 95;
            
            return response()->json([
                'status' => $isAlive ? 'alive' : 'dead',
                'timestamp' => now()->toISOString(),
                'memory_usage_bytes' => $memoryUsage,
                'memory_usage_percent' => round($memoryUsagePercent, 2),
                'uptime_seconds' => $this->getUptime(),
            ], $isAlive ? 200 : 503);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'dead',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Perform health checks based on specified criteria.
     *
     * @since 1.4.0
     *
     * @param string $checks Comma-separated list of checks to perform or 'all'
     * @param bool $includeDetails Whether to include detailed information
     * @return array Health check results
     */
    protected function performHealthChecks(string $checks, bool $includeDetails): array
    {
        $availableChecks = [
            'application' => fn() => $this->checkApplication($includeDetails),
            'database' => fn() => $this->checkDatabase($includeDetails),
            'cache' => fn() => $this->checkCache($includeDetails),
            'queue' => fn() => $this->checkQueue($includeDetails),
            'storage' => fn() => $this->checkStorage($includeDetails),
        ];

        if ($checks === 'all') {
            $checksToRun = array_keys($availableChecks);
        } else {
            $checksToRun = array_filter(
                explode(',', $checks),
                fn($check) => array_key_exists(trim($check), $availableChecks)
            );
        }

        $results = [];
        foreach ($checksToRun as $checkName) {
            try {
                $checkName = trim($checkName);
                $results[$checkName] = $availableChecks[$checkName]();
            } catch (Exception $e) {
                $results[$checkName] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $results;
    }

    /**
     * Check application health.
     *
     * @since 1.4.0
     *
     * @param bool $includeDetails Whether to include detailed information
     * @return array Application health status
     */
    protected function checkApplication(bool $includeDetails): array
    {
        $result = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ];

        try {
            // Check basic application functionality
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $memoryUsagePercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
            
            if ($memoryUsagePercent > 90) {
                $result['status'] = 'degraded';
                $result['warnings'][] = 'High memory usage: ' . round($memoryUsagePercent, 2) . '%';
            }

            if ($includeDetails) {
                $result['memory_usage_bytes'] = $memoryUsage;
                $result['memory_usage_percent'] = round($memoryUsagePercent, 2);
                $result['php_version'] = PHP_VERSION;
                $result['laravel_version'] = app()->version();
                $result['environment'] = config('app.env');
                $result['debug_mode'] = config('app.debug');
            }

        } catch (Exception $e) {
            $result['status'] = 'unhealthy';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check database connectivity and health.
     *
     * @since 1.4.0
     *
     * @param bool $includeDetails Whether to include detailed information
     * @return array Database health status
     */
    protected function checkDatabase(bool $includeDetails): array
    {
        $result = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ];

        try {
            $startTime = microtime(true);
            
            // Basic connectivity test
            $connectionName = config('database.default');
            $connection = DB::connection($connectionName);
            $pdo = $connection->getPdo();
            
            // Simple query test
            $testResult = DB::select('SELECT 1 as test');
            
            $queryTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($queryTime > 1000) { // Query took more than 1 second
                $result['status'] = 'degraded';
                $result['warnings'][] = 'Slow database response: ' . $queryTime . 'ms';
            }

            if ($includeDetails) {
                $result['connection'] = $connectionName;
                $result['driver'] = $connection->getDriverName();
                $result['query_time_ms'] = $queryTime;
                
                // Get database-specific information
                try {
                    if ($connection->getDriverName() === 'mysql') {
                        $variables = DB::select("SHOW VARIABLES WHERE Variable_name IN ('version', 'max_connections', 'threads_connected')");
                        $result['mysql_info'] = collect($variables)->pluck('Value', 'Variable_name')->toArray();
                    }
                } catch (Exception $e) {
                    $result['warnings'][] = 'Could not retrieve database details: ' . $e->getMessage();
                }
            }

        } catch (Exception $e) {
            $result['status'] = 'unhealthy';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check cache system health.
     *
     * @since 1.4.0
     *
     * @param bool $includeDetails Whether to include detailed information
     * @return array Cache health status
     */
    protected function checkCache(bool $includeDetails): array
    {
        $result = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ];

        try {
            $startTime = microtime(true);
            $testKey = 'health_check_' . time();
            $testValue = 'test_value_' . uniqid();
            
            // Test cache write
            Cache::put($testKey, $testValue, 60);
            
            // Test cache read
            $retrievedValue = Cache::get($testKey);
            
            // Clean up test key
            Cache::forget($testKey);
            
            $operationTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($retrievedValue !== $testValue) {
                $result['status'] = 'unhealthy';
                $result['error'] = 'Cache read/write test failed';
            } elseif ($operationTime > 500) { // Cache operation took more than 500ms
                $result['status'] = 'degraded';
                $result['warnings'][] = 'Slow cache response: ' . $operationTime . 'ms';
            }

            if ($includeDetails) {
                $result['driver'] = config('cache.default');
                $result['operation_time_ms'] = $operationTime;
                
                // Redis-specific information
                if (config('cache.default') === 'redis') {
                    try {
                        $redis = Cache::store('redis')->getRedis();
                        $info = $redis->info();
                        $result['redis_info'] = [
                            'version' => $info['redis_version'] ?? 'unknown',
                            'connected_clients' => $info['connected_clients'] ?? 'unknown',
                            'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                        ];
                    } catch (Exception $e) {
                        $result['warnings'][] = 'Could not retrieve Redis details: ' . $e->getMessage();
                    }
                }
            }

        } catch (Exception $e) {
            $result['status'] = 'unhealthy';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check queue system health.
     *
     * @since 1.4.0
     *
     * @param bool $includeDetails Whether to include detailed information
     * @return array Queue health status
     */
    protected function checkQueue(bool $includeDetails): array
    {
        $result = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ];

        try {
            $connection = config('queue.default');
            
            // Basic queue connection test
            $queue = Queue::connection($connection);
            
            if ($includeDetails) {
                $result['connection'] = $connection;
                $result['driver'] = config("queue.connections.{$connection}.driver");
                
                // Try to get queue size information if supported
                try {
                    if ($connection === 'redis') {
                        $redis = app('redis')->connection(config("queue.connections.{$connection}.connection", 'default'));
                        $defaultQueue = config("queue.connections.{$connection}.queue", 'default');
                        $queueSize = $redis->llen("queues:{$defaultQueue}");
                        $result['queue_size'] = $queueSize;
                        
                        if ($queueSize > 1000) {
                            $result['status'] = 'degraded';
                            $result['warnings'][] = 'Large queue backlog: ' . $queueSize . ' jobs';
                        }
                    }
                } catch (Exception $e) {
                    $result['warnings'][] = 'Could not retrieve queue details: ' . $e->getMessage();
                }
            }

        } catch (Exception $e) {
            $result['status'] = 'unhealthy';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check storage system health.
     *
     * @since 1.4.0
     *
     * @param bool $includeDetails Whether to include detailed information
     * @return array Storage health status
     */
    protected function checkStorage(bool $includeDetails): array
    {
        $result = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ];

        try {
            $testFileName = 'health_check_' . time() . '.txt';
            $testContent = 'health check test content';
            
            $startTime = microtime(true);
            
            // Test storage write
            Storage::put($testFileName, $testContent);
            
            // Test storage read
            $retrievedContent = Storage::get($testFileName);
            
            // Test storage delete
            Storage::delete($testFileName);
            
            $operationTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($retrievedContent !== $testContent) {
                $result['status'] = 'unhealthy';
                $result['error'] = 'Storage read/write test failed';
            } elseif ($operationTime > 1000) { // Storage operation took more than 1 second
                $result['status'] = 'degraded';
                $result['warnings'][] = 'Slow storage response: ' . $operationTime . 'ms';
            }

            if ($includeDetails) {
                $result['default_disk'] = config('filesystems.default');
                $result['operation_time_ms'] = $operationTime;
                
                // Check disk space if local storage
                if (config('filesystems.default') === 'local') {
                    $storagePath = storage_path();
                    if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
                        $freeSpace = disk_free_space($storagePath);
                        $totalSpace = disk_total_space($storagePath);
                        
                        if ($freeSpace !== false && $totalSpace !== false) {
                            $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
                            $result['disk_usage_percent'] = round($usedPercent, 2);
                            $result['disk_free_bytes'] = $freeSpace;
                            
                            if ($usedPercent > 90) {
                                $result['status'] = 'degraded';
                                $result['warnings'][] = 'High disk usage: ' . round($usedPercent, 2) . '%';
                            }
                        }
                    }
                }
            }

        } catch (Exception $e) {
            $result['status'] = 'unhealthy';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Calculate overall system status based on individual check results.
     *
     * @since 1.4.0
     *
     * @param array $results Individual health check results
     * @return string Overall system status
     */
    protected function calculateOverallStatus(array $results): string
    {
        $statuses = collect($results)->pluck('status');
        
        if ($statuses->contains('unhealthy')) {
            return 'unhealthy';
        }
        
        if ($statuses->contains('degraded')) {
            return 'degraded';
        }
        
        return 'healthy';
    }

    /**
     * Get system information.
     *
     * @since 1.4.0
     *
     * @return array System information
     */
    protected function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'debug_mode' => config('app.debug'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'operating_system' => php_uname(),
        ];
    }

    /**
     * Get performance metrics.
     *
     * @since 1.4.0
     *
     * @return array Performance metrics
     */
    protected function getPerformanceMetrics(): array
    {
        return [
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_usage_bytes' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'execution_time_limit' => ini_get('max_execution_time'),
            'uptime_seconds' => $this->getUptime(),
            'load_average' => $this->getLoadAverage(),
        ];
    }

    /**
     * Get system uptime in seconds.
     *
     * @since 1.4.0
     *
     * @return int|null Uptime in seconds
     */
    protected function getUptime(): ?int
    {
        try {
            if (function_exists('sys_getloadavg') && PHP_OS_FAMILY === 'Linux') {
                $uptime = file_get_contents('/proc/uptime');
                if ($uptime !== false) {
                    return (int) floatval(explode(' ', $uptime)[0]);
                }
            }
        } catch (Exception $e) {
            // Uptime not available on this system
        }
        
        return null;
    }

    /**
     * Get system load average.
     *
     * @since 1.4.0
     *
     * @return array|null Load average values
     */
    protected function getLoadAverage(): ?array
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                if ($load !== false) {
                    return [
                        '1min' => round($load[0], 2),
                        '5min' => round($load[1], 2),
                        '15min' => round($load[2], 2),
                    ];
                }
            }
        } catch (Exception $e) {
            // Load average not available on this system
        }
        
        return null;
    }

    /**
     * Parse memory limit string to bytes.
     *
     * @since 1.4.0
     *
     * @param string $memoryLimit Memory limit string (e.g., '128M', '1G')
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
}