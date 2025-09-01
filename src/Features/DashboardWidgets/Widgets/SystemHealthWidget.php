<?php

namespace ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets;

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;
use ArtisanPackUI\CMSFramework\Services\SystemMonitoringService;
use ArtisanPackUI\CMSFramework\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Cache;

/**
 * System Health Dashboard Widget
 *
 * Provides a comprehensive dashboard widget displaying system health status,
 * performance metrics, service availability, and real-time monitoring data
 * for the ArtisanPack UI CMS Framework.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets
 * @since      1.4.0
 */
class SystemHealthWidget extends DashboardWidget
{
    /**
     * The system monitoring service instance.
     *
     * @since 1.4.0
     *
     * @var SystemMonitoringService
     */
    protected SystemMonitoringService $monitoringService;

    /**
     * Initialize the widget.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function init(): void
    {
        $this->monitoringService = app(SystemMonitoringService::class);
    }

    /**
     * Get the widget type.
     *
     * @since 1.4.0
     *
     * @return string
     */
    public function getType(): string
    {
        return 'system-health';
    }

    /**
     * Get the widget name.
     *
     * @since 1.4.0
     *
     * @return string
     */
    public function getName(): string
    {
        return 'System Health Monitor';
    }

    /**
     * Get the widget slug.
     *
     * @since 1.4.0
     *
     * @return string
     */
    public function getSlug(): string
    {
        return 'system-health-monitor';
    }

    /**
     * Get the widget description.
     *
     * @since 1.4.0
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return 'Real-time monitoring of system health, performance metrics, and service availability.';
    }

    /**
     * Render the widget.
     *
     * @since 1.4.0
     *
     * @param string $instanceId Widget instance identifier
     * @param array $data Widget configuration data
     * @return string Rendered widget HTML
     */
    public function render(string $instanceId, array $data): string
    {
        try {
            $settings = $this->getSettings($instanceId, null, []);
            $refreshInterval = $settings['refresh_interval'] ?? 30; // seconds
            
            // Get cached data or fetch fresh data
            $cacheKey = "system_health_widget_{$instanceId}";
            $cacheDuration = max(5, min(60, $refreshInterval)); // Cache between 5-60 seconds
            
            $healthData = Cache::remember($cacheKey, now()->addSeconds($cacheDuration), function() {
                return $this->collectHealthData();
            });
            
            return $this->renderWidget($instanceId, $healthData, $settings);
            
        } catch (\Exception $e) {
            return $this->renderError($e->getMessage());
        }
    }

    /**
     * Collect comprehensive health data.
     *
     * @since 1.4.0
     *
     * @return array Health data
     */
    protected function collectHealthData(): array
    {
        $startTime = microtime(true);
        
        try {
            // Get basic health check
            $healthController = new HealthCheckController();
            
            // Simulate request for health check
            $request = request();
            $healthResponse = $healthController->health($request);
            $healthData = json_decode($healthResponse->getContent(), true);
            
            // Get detailed system metrics
            $metrics = $this->monitoringService->collectMetrics();
            
            // Calculate overall health score
            $healthScore = $this->calculateHealthScore($healthData, $metrics);
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'overall_status' => $healthData['status'],
                'health_score' => $healthScore,
                'response_time_ms' => $responseTime,
                'checks' => $healthData['checks'] ?? [],
                'metrics' => $metrics,
                'alerts' => $this->generateAlerts($healthData, $metrics),
                'timestamp' => now()->toISOString(),
            ];
            
        } catch (\Exception $e) {
            return [
                'overall_status' => 'unhealthy',
                'health_score' => 0,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Calculate overall health score (0-100).
     *
     * @since 1.4.0
     *
     * @param array $healthData Health check data
     * @param array $metrics System metrics
     * @return int Health score
     */
    protected function calculateHealthScore(array $healthData, array $metrics): int
    {
        $score = 100;
        
        // Deduct points for unhealthy services
        if (isset($healthData['checks'])) {
            foreach ($healthData['checks'] as $check) {
                if ($check['status'] === 'unhealthy') {
                    $score -= 25; // Major penalty for unhealthy service
                } elseif ($check['status'] === 'degraded') {
                    $score -= 10; // Minor penalty for degraded service
                }
            }
        }
        
        // Deduct points for resource usage issues
        if (isset($metrics['resources']['disk_usage']['usage_percent'])) {
            $diskUsage = $metrics['resources']['disk_usage']['usage_percent'];
            if ($diskUsage > 95) {
                $score -= 20;
            } elseif ($diskUsage > 85) {
                $score -= 10;
            }
        }
        
        // Memory usage penalty
        if (isset($metrics['performance']['memory_usage_bytes'])) {
            $memoryUsage = $metrics['performance']['memory_usage_bytes'];
            $memoryLimit = $this->parseMemoryLimit($metrics['performance']['memory_limit'] ?? '128M');
            
            if ($memoryLimit > 0) {
                $memoryPercent = ($memoryUsage / $memoryLimit) * 100;
                if ($memoryPercent > 95) {
                    $score -= 20;
                } elseif ($memoryPercent > 85) {
                    $score -= 10;
                }
            }
        }
        
        return max(0, min(100, $score));
    }

    /**
     * Generate alerts based on health data and metrics.
     *
     * @since 1.4.0
     *
     * @param array $healthData Health check data
     * @param array $metrics System metrics
     * @return array Alerts
     */
    protected function generateAlerts(array $healthData, array $metrics): array
    {
        $alerts = [];
        
        // Check for service failures
        if (isset($healthData['checks'])) {
            foreach ($healthData['checks'] as $serviceName => $check) {
                if ($check['status'] === 'unhealthy') {
                    $alerts[] = [
                        'level' => 'critical',
                        'service' => $serviceName,
                        'message' => ucfirst($serviceName) . ' service is unhealthy',
                        'details' => $check['error'] ?? 'Unknown error',
                    ];
                } elseif ($check['status'] === 'degraded') {
                    $alerts[] = [
                        'level' => 'warning',
                        'service' => $serviceName,
                        'message' => ucfirst($serviceName) . ' service is degraded',
                        'details' => implode(', ', $check['warnings'] ?? []),
                    ];
                }
            }
        }
        
        // Check resource usage
        if (isset($metrics['resources']['disk_usage']['usage_percent'])) {
            $diskUsage = $metrics['resources']['disk_usage']['usage_percent'];
            if ($diskUsage > 95) {
                $alerts[] = [
                    'level' => 'critical',
                    'service' => 'disk',
                    'message' => 'Disk usage critically high',
                    'details' => "Disk usage: {$diskUsage}%",
                ];
            } elseif ($diskUsage > 85) {
                $alerts[] = [
                    'level' => 'warning',
                    'service' => 'disk',
                    'message' => 'Disk usage high',
                    'details' => "Disk usage: {$diskUsage}%",
                ];
            }
        }
        
        return $alerts;
    }

    /**
     * Render the main widget content.
     *
     * @since 1.4.0
     *
     * @param string $instanceId Widget instance ID
     * @param array $data Health data
     * @param array $settings Widget settings
     * @return string Widget HTML
     */
    protected function renderWidget(string $instanceId, array $data, array $settings): string
    {
        $refreshInterval = $settings['refresh_interval'] ?? 30;
        
        ob_start();
        ?>
        <div class="system-health-widget bg-white rounded-lg shadow-sm border p-6" data-refresh="<?= $refreshInterval ?>">
            <!-- Widget Header -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">
                    System Health Monitor
                </h3>
                <div class="flex items-center space-x-2">
                    <?= $this->renderStatusBadge($data['overall_status']) ?>
                    <div class="text-sm text-gray-500">
                        Score: <span class="font-medium <?= $this->getScoreColor($data['health_score'] ?? 0) ?>">
                            <?= $data['health_score'] ?? 0 ?>/100
                        </span>
                    </div>
                </div>
            </div>

            <?php if (isset($data['error'])): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-600">Error: <?= htmlspecialchars($data['error']) ?></p>
                </div>
            <?php else: ?>
                
                <!-- Alerts Section -->
                <?php if (!empty($data['alerts'])): ?>
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Active Alerts</h4>
                        <div class="space-y-2">
                            <?php foreach ($data['alerts'] as $alert): ?>
                                <?= $this->renderAlert($alert) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Service Status Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <?php if (isset($data['checks'])): ?>
                        <?php foreach ($data['checks'] as $service => $check): ?>
                            <?= $this->renderServiceCard($service, $check) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Performance Metrics -->
                <?php if (isset($data['metrics']['performance'])): ?>
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Performance Metrics</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <?= $this->renderPerformanceMetrics($data['metrics']['performance']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Resource Usage -->
                <?php if (isset($data['metrics']['resources'])): ?>
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Resource Usage</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?= $this->renderResourceMetrics($data['metrics']['resources']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System Information -->
                <div class="pt-4 border-t border-gray-200">
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>Last updated: <?= date('H:i:s', strtotime($data['timestamp'])) ?></span>
                        <span>Response time: <?= $data['response_time_ms'] ?? 0 ?>ms</span>
                    </div>
                </div>

            <?php endif; ?>
        </div>

        <style>
        .system-health-widget .service-card {
            @apply bg-gray-50 rounded-lg p-3 text-center;
        }
        .system-health-widget .service-status {
            @apply inline-block w-3 h-3 rounded-full mr-2;
        }
        .system-health-widget .service-status.healthy {
            @apply bg-green-500;
        }
        .system-health-widget .service-status.degraded {
            @apply bg-yellow-500;
        }
        .system-health-widget .service-status.unhealthy {
            @apply bg-red-500;
        }
        .system-health-widget .metric-bar {
            @apply w-full bg-gray-200 rounded-full h-2;
        }
        .system-health-widget .metric-bar-fill {
            @apply h-2 rounded-full transition-all duration-300;
        }
        .system-health-widget .alert-critical {
            @apply bg-red-50 border-red-200 text-red-800 px-3 py-2 rounded-md text-sm;
        }
        .system-health-widget .alert-warning {
            @apply bg-yellow-50 border-yellow-200 text-yellow-800 px-3 py-2 rounded-md text-sm;
        }
        </style>

        <script>
        // Auto-refresh functionality
        (function() {
            const widget = document.querySelector('[data-refresh]');
            if (widget) {
                const refreshInterval = parseInt(widget.getAttribute('data-refresh')) * 1000;
                setInterval(() => {
                    // Trigger widget refresh (implementation depends on dashboard system)
                    if (window.refreshWidget) {
                        window.refreshWidget('<?= $instanceId ?>');
                    }
                }, refreshInterval);
            }
        })();
        </script>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render status badge.
     *
     * @since 1.4.0
     *
     * @param string $status System status
     * @return string Status badge HTML
     */
    protected function renderStatusBadge(string $status): string
    {
        $colors = [
            'healthy' => 'bg-green-100 text-green-800',
            'degraded' => 'bg-yellow-100 text-yellow-800',
            'unhealthy' => 'bg-red-100 text-red-800',
        ];
        
        $color = $colors[$status] ?? 'bg-gray-100 text-gray-800';
        
        return sprintf(
            '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s</span>',
            $color,
            ucfirst($status)
        );
    }

    /**
     * Render service status card.
     *
     * @since 1.4.0
     *
     * @param string $service Service name
     * @param array $check Service check data
     * @return string Service card HTML
     */
    protected function renderServiceCard(string $service, array $check): string
    {
        return sprintf(
            '<div class="service-card">
                <div class="flex items-center justify-center mb-2">
                    <span class="service-status %s"></span>
                    <span class="text-sm font-medium">%s</span>
                </div>
                <div class="text-xs text-gray-500">%s</div>
            </div>',
            $check['status'] ?? 'unknown',
            ucfirst($service),
            isset($check['response_time_ms']) ? $check['response_time_ms'] . 'ms' : 'N/A'
        );
    }

    /**
     * Render performance metrics.
     *
     * @since 1.4.0
     *
     * @param array $performance Performance data
     * @return string Performance metrics HTML
     */
    protected function renderPerformanceMetrics(array $performance): string
    {
        $html = '';
        
        if (isset($performance['memory_usage_bytes'])) {
            $memoryUsage = $performance['memory_usage_bytes'];
            $memoryLimit = $this->parseMemoryLimit($performance['memory_limit'] ?? '128M');
            $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
            
            $html .= sprintf(
                '<div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-sm font-medium text-gray-900">Memory Usage</div>
                    <div class="metric-bar mt-2">
                        <div class="metric-bar-fill %s" style="width: %s%%"></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">%s / %s (%.1f%%)</div>
                </div>',
                $this->getUsageColor($memoryPercent),
                min(100, $memoryPercent),
                $this->formatBytes($memoryUsage),
                $performance['memory_limit'],
                $memoryPercent
            );
        }
        
        return $html;
    }

    /**
     * Render resource metrics.
     *
     * @since 1.4.0
     *
     * @param array $resources Resource data
     * @return string Resource metrics HTML
     */
    protected function renderResourceMetrics(array $resources): string
    {
        $html = '';
        
        if (isset($resources['disk_usage'])) {
            $disk = $resources['disk_usage'];
            $html .= sprintf(
                '<div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-sm font-medium text-gray-900">Disk Usage</div>
                    <div class="metric-bar mt-2">
                        <div class="metric-bar-fill %s" style="width: %s%%"></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">%s / %s (%.1f%%)</div>
                </div>',
                $this->getUsageColor($disk['usage_percent']),
                $disk['usage_percent'],
                $this->formatBytes($disk['used_bytes']),
                $this->formatBytes($disk['total_bytes']),
                $disk['usage_percent']
            );
        }
        
        return $html;
    }

    /**
     * Render alert.
     *
     * @since 1.4.0
     *
     * @param array $alert Alert data
     * @return string Alert HTML
     */
    protected function renderAlert(array $alert): string
    {
        $class = $alert['level'] === 'critical' ? 'alert-critical' : 'alert-warning';
        
        return sprintf(
            '<div class="%s border">
                <strong>%s:</strong> %s
                <div class="text-xs mt-1">%s</div>
            </div>',
            $class,
            ucfirst($alert['level']),
            $alert['message'],
            $alert['details'] ?? ''
        );
    }

    /**
     * Get color class for health score.
     *
     * @since 1.4.0
     *
     * @param int $score Health score
     * @return string Color class
     */
    protected function getScoreColor(int $score): string
    {
        if ($score >= 90) return 'text-green-600';
        if ($score >= 70) return 'text-yellow-600';
        return 'text-red-600';
    }

    /**
     * Get color class for usage percentages.
     *
     * @since 1.4.0
     *
     * @param float $percent Usage percentage
     * @return string Color class
     */
    protected function getUsageColor(float $percent): string
    {
        if ($percent >= 90) return 'bg-red-500';
        if ($percent >= 70) return 'bg-yellow-500';
        return 'bg-green-500';
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
            return -1;
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
     * Render error state.
     *
     * @since 1.4.0
     *
     * @param string $message Error message
     * @return string Error widget HTML
     */
    protected function renderError(string $message): string
    {
        return sprintf(
            '<div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <p class="text-red-600">System Health Monitor Error: %s</p>
            </div>',
            htmlspecialchars($message)
        );
    }

    /**
     * Define the widget (called during registration).
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function define(): void
    {
        // Widget is defined by the class itself
    }
}