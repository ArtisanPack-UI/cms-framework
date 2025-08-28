<?php

namespace ArtisanPackUI\CMSFramework\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * System Monitoring Service
 *
 * Collects comprehensive system metrics, performance data, and monitoring
 * information for the ArtisanPack UI CMS Framework.
 *
 * @package    ArtisanPackUI\CMSFramework\Services
 * @since      1.4.0
 */
class SystemMonitoringService
{
    /**
     * Collect comprehensive system metrics.
     *
     * @since 1.4.0
     *
     * @return array System metrics data
     */
    public function collectMetrics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'system' => $this->getSystemMetrics(),
            'performance' => $this->getPerformanceMetrics(),
            'resources' => $this->getResourceMetrics(),
            'services' => $this->getServiceMetrics(),
        ];
    }

    /**
     * Get system-level metrics.
     *
     * @since 1.4.0
     *
     * @return array System metrics
     */
    protected function getSystemMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'uptime_seconds' => $this->getUptime(),
            'load_average' => $this->getLoadAverage(),
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
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'response_times' => $this->getAverageResponseTimes(),
        ];
    }

    /**
     * Get resource usage metrics.
     *
     * @since 1.4.0
     *
     * @return array Resource metrics
     */
    protected function getResourceMetrics(): array
    {
        return [
            'disk_usage' => $this->getDiskUsage(),
            'cpu_usage' => $this->getCpuUsage(),
        ];
    }

    /**
     * Get service availability metrics.
     *
     * @since 1.4.0
     *
     * @return array Service metrics
     */
    protected function getServiceMetrics(): array
    {
        return [
            'database' => $this->checkDatabaseMetrics(),
            'cache' => $this->checkCacheMetrics(),
            'queue' => $this->checkQueueMetrics(),
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
            if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
                $uptime = file_get_contents('/proc/uptime');
                if ($uptime !== false) {
                    return (int) floatval(explode(' ', $uptime)[0]);
                }
            }
        } catch (Exception $e) {
            Log::debug('Could not get system uptime: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get system load average.
     *
     * @since 1.4.0
     *
     * @return array|null Load average
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
            Log::debug('Could not get load average: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get disk usage information.
     *
     * @since 1.4.0
     *
     * @return array|null Disk usage data
     */
    protected function getDiskUsage(): ?array
    {
        try {
            $path = storage_path();
            if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
                $freeBytes = disk_free_space($path);
                $totalBytes = disk_total_space($path);
                
                if ($freeBytes !== false && $totalBytes !== false) {
                    return [
                        'free_bytes' => $freeBytes,
                        'total_bytes' => $totalBytes,
                        'used_bytes' => $totalBytes - $freeBytes,
                        'usage_percent' => round((($totalBytes - $freeBytes) / $totalBytes) * 100, 2),
                    ];
                }
            }
        } catch (Exception $e) {
            Log::debug('Could not get disk usage: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get CPU usage (simplified).
     *
     * @since 1.4.0
     *
     * @return float|null CPU usage percentage
     */
    protected function getCpuUsage(): ?float
    {
        try {
            if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/loadavg')) {
                $load = sys_getloadavg();
                if ($load !== false) {
                    // Simplified CPU usage based on 1-minute load average
                    return round($load[0] * 100, 2);
                }
            }
        } catch (Exception $e) {
            Log::debug('Could not get CPU usage: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get average response times from cache.
     *
     * @since 1.4.0
     *
     * @return array|null Response time data
     */
    protected function getAverageResponseTimes(): ?array
    {
        try {
            return Cache::get('system_monitoring.response_times', [
                'average_ms' => 0,
                'samples' => 0,
                'last_updated' => null,
            ]);
        } catch (Exception $e) {
            Log::debug('Could not get response times: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check database metrics.
     *
     * @since 1.4.0
     *
     * @return array Database metrics
     */
    protected function checkDatabaseMetrics(): array
    {
        try {
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $queryTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $queryTime,
                'connection' => config('database.default'),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache metrics.
     *
     * @since 1.4.0
     *
     * @return array Cache metrics
     */
    protected function checkCacheMetrics(): array
    {
        try {
            $startTime = microtime(true);
            $testKey = 'monitor_test_' . time();
            Cache::put($testKey, 'test', 60);
            Cache::get($testKey);
            Cache::forget($testKey);
            $operationTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'operation_time_ms' => $operationTime,
                'driver' => config('cache.default'),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue metrics.
     *
     * @since 1.4.0
     *
     * @return array Queue metrics
     */
    protected function checkQueueMetrics(): array
    {
        try {
            $connection = config('queue.default');
            return [
                'status' => 'healthy',
                'connection' => $connection,
                'driver' => config("queue.connections.{$connection}.driver"),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}