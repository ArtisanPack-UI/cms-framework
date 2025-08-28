<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Models\ErrorLog;
use ArtisanPackUI\CMSFramework\Models\PerformanceMetric;
use ArtisanPackUI\CMSFramework\Models\PerformanceTransaction;
use ArtisanPackUI\CMSFramework\Services\APMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use TorMorten\Eventy\Facades\Eventy;

/**
 * APMController.
 *
 * Handles all APM dashboard API endpoints for the ArtisanPack UI CMS Framework.
 * Provides performance metrics, error statistics, transaction data, and system health.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Http\Controllers
 * @since   1.3.0
 */
class APMController extends Controller
{
    /**
     * APMService instance.
     *
     * @var APMService
     */
    protected APMService $apmService;

    /**
     * Create a new APMController instance.
     *
     * @since 1.3.0
     *
     * @param APMService $apmService
     */
    public function __construct(APMService $apmService)
    {
        $this->apmService = $apmService;
    }

    /**
     * Get APM dashboard overview.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function overview(Request $request): JsonResponse
    {
        if (!config('cms.apm.dashboard.enabled', true)) {
            return response()->json([
                'error' => 'APM dashboard is disabled',
                'message' => 'APM dashboard functionality has been disabled in the configuration',
            ], 503);
        }

        $validated = $request->validate([
            'hours' => 'nullable|integer|min:1|max:168', // Max 1 week
        ]);

        $hours = $validated['hours'] ?? 24;
        $from = now()->subHours($hours);
        $to = now();

        try {
            // Get performance statistics
            $performanceStats = PerformanceTransaction::getPerformanceStats(null, $from, $to);

            // Get error statistics
            $errorStats = ErrorLog::getErrorStats($from, $to);

            // Get key metrics
            $responseTimeAvg = PerformanceMetric::getAverageValue('http_request_duration', $from, $to);
            $memoryUsageAvg = PerformanceMetric::getAverageValue('http_request_memory', $from, $to);
            $dbQueriesAvg = PerformanceMetric::getAverageValue('http_request_db_queries', $from, $to);

            // Get health status
            $healthStatus = $this->apmService->getHealthStatus();

            $overview = [
                'time_range' => [
                    'from' => $from->toISOString(),
                    'to' => $to->toISOString(),
                    'hours' => $hours,
                ],
                'performance' => $performanceStats,
                'errors' => $errorStats,
                'key_metrics' => [
                    'avg_response_time_ms' => $responseTimeAvg,
                    'avg_memory_usage_mb' => $memoryUsageAvg,
                    'avg_db_queries' => $dbQueriesAvg,
                ],
                'health_status' => $healthStatus,
            ];

            // Allow filtering through Eventy hooks
            $overview = Eventy::filter('ap.cms.apm.dashboard_overview', $overview, $request);

            return response()->json([
                'success' => true,
                'data' => $overview,
            ]);

        } catch (\Exception $e) {
            \Log::error('APM dashboard overview error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to load dashboard overview',
                'message' => 'An error occurred while loading dashboard data. Please try again.',
            ], 500);
        }
    }

    /**
     * Get performance metrics data.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function metrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric' => 'required|string|max:100',
            'interval' => ['nullable', Rule::in(['hour', 'day', 'week'])],
            'periods' => 'nullable|integer|min:1|max:168',
            'tags' => 'nullable|array',
        ]);

        $metric = $validated['metric'];
        $interval = $validated['interval'] ?? 'hour';
        $periods = $validated['periods'] ?? 24;
        $tags = $validated['tags'] ?? [];

        try {
            // Get time series data
            $timeSeries = PerformanceMetric::getTimeSeries($metric, $interval, $periods);

            // Get statistics
            $from = match ($interval) {
                'hour' => now()->subHours($periods),
                'day' => now()->subDays($periods),
                'week' => now()->subWeeks($periods),
                default => now()->subHours($periods),
            };

            $stats = PerformanceMetric::getStatistics($metric, $from);

            $data = [
                'metric_name' => $metric,
                'time_series' => $timeSeries,
                'statistics' => $stats,
                'interval' => $interval,
                'periods' => $periods,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error('APM metrics API error', [
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load metrics data',
                'message' => 'An error occurred while loading metrics. Please try again.',
            ], 500);
        }
    }

    /**
     * Get transaction performance data.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interval' => ['nullable', Rule::in(['hour', 'day', 'week'])],
            'periods' => 'nullable|integer|min:1|max:168',
            'transaction_name' => 'nullable|string|max:255',
            'include_slowest' => 'nullable|boolean',
            'include_endpoints' => 'nullable|boolean',
        ]);

        $interval = $validated['interval'] ?? 'hour';
        $periods = $validated['periods'] ?? 24;
        $transactionName = $validated['transaction_name'] ?? null;

        try {
            $from = match ($interval) {
                'hour' => now()->subHours($periods),
                'day' => now()->subDays($periods),
                'week' => now()->subWeeks($periods),
                default => now()->subHours($periods),
            };

            $data = [
                'time_series' => PerformanceTransaction::getTimeSeries($interval, $periods, $transactionName),
                'performance_stats' => PerformanceTransaction::getPerformanceStats($transactionName, $from),
            ];

            if ($validated['include_slowest'] ?? true) {
                $data['slowest_transactions'] = PerformanceTransaction::getSlowestTransactions(10, $from);
            }

            if ($validated['include_endpoints'] ?? true) {
                $data['endpoint_performance'] = PerformanceTransaction::getEndpointPerformance(20, $from);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error('APM transactions API error', [
                'transaction_name' => $transactionName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load transaction data',
                'message' => 'An error occurred while loading transactions. Please try again.',
            ], 500);
        }
    }

    /**
     * Get error tracking data.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function errors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interval' => ['nullable', Rule::in(['hour', 'day', 'week'])],
            'periods' => 'nullable|integer|min:1|max:168',
            'severity' => ['nullable', Rule::in(['critical', 'error', 'warning', 'notice'])],
            'include_top_errors' => 'nullable|boolean',
            'include_by_class' => 'nullable|boolean',
            'include_recent' => 'nullable|boolean',
        ]);

        $interval = $validated['interval'] ?? 'hour';
        $periods = $validated['periods'] ?? 24;
        $severity = $validated['severity'] ?? null;

        try {
            $from = match ($interval) {
                'hour' => now()->subHours($periods),
                'day' => now()->subDays($periods),
                'week' => now()->subWeeks($periods),
                default => now()->subHours($periods),
            };

            $data = [
                'error_stats' => ErrorLog::getErrorStats($from),
                'time_series' => ErrorLog::getTimeSeries($interval, $periods, $severity),
            ];

            if ($validated['include_top_errors'] ?? true) {
                $data['top_errors'] = ErrorLog::getTopErrors(10, $from);
            }

            if ($validated['include_by_class'] ?? true) {
                $data['errors_by_class'] = ErrorLog::getErrorsByClass(20, $from);
            }

            if ($validated['include_recent'] ?? true) {
                $data['recent_unresolved'] = ErrorLog::getRecentUnresolved(20);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error('APM errors API error', [
                'severity' => $severity,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load error data',
                'message' => 'An error occurred while loading errors. Please try again.',
            ], 500);
        }
    }

    /**
     * Get APM system status and health.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $status = [
                'apm_enabled' => $this->apmService->isEnabled(),
                'providers' => $this->apmService->getHealthStatus()['providers'],
                'configuration' => [
                    'metrics_enabled' => config('cms.apm.metrics.enabled', true),
                    'error_tracking_enabled' => config('cms.apm.error_tracking.enabled', true),
                    'alerts_enabled' => config('cms.apm.alerts.enabled', true),
                    'ux_monitoring_enabled' => config('cms.apm.user_experience.enabled', true),
                ],
                'database_stats' => [
                    'performance_metrics' => PerformanceMetric::count(),
                    'performance_transactions' => PerformanceTransaction::count(),
                    'error_logs' => ErrorLog::count(),
                    'unresolved_errors' => ErrorLog::unresolved()->count(),
                ],
                'system_info' => [
                    'php_version' => phpversion(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            \Log::error('APM status API error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load APM status',
                'message' => 'An error occurred while loading system status. Please try again.',
            ], 500);
        }
    }

    /**
     * Mark error as resolved.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @param int $errorId
     * @return JsonResponse
     */
    public function resolveError(Request $request, int $errorId): JsonResponse
    {
        try {
            $error = ErrorLog::findOrFail($errorId);
            $error->markAsResolved();

            return response()->json([
                'success' => true,
                'message' => 'Error marked as resolved',
                'data' => [
                    'error_id' => $errorId,
                    'resolved_at' => $error->resolved_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('APM resolve error API error', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to resolve error',
                'message' => 'An error occurred while resolving the error. Please try again.',
            ], 500);
        }
    }

    /**
     * Mark error as unresolved.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @param int $errorId
     * @return JsonResponse
     */
    public function unresolveError(Request $request, int $errorId): JsonResponse
    {
        try {
            $error = ErrorLog::findOrFail($errorId);
            $error->markAsUnresolved();

            return response()->json([
                'success' => true,
                'message' => 'Error marked as unresolved',
                'data' => [
                    'error_id' => $errorId,
                    'resolved_at' => null,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('APM unresolve error API error', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to unresolve error',
                'message' => 'An error occurred while unresolving the error. Please try again.',
            ], 500);
        }
    }

    /**
     * Get real-time metrics for dashboard widgets.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function realtime(Request $request): JsonResponse
    {
        try {
            $now = now();
            $oneHourAgo = $now->copy()->subHour();

            // Get recent metrics
            $recentMetrics = [
                'avg_response_time' => PerformanceMetric::getAverageValue('http_request_duration', $oneHourAgo, $now),
                'avg_memory_usage' => PerformanceMetric::getAverageValue('http_request_memory', $oneHourAgo, $now),
                'total_requests' => PerformanceTransaction::where('started_at', '>=', $oneHourAgo)->count(),
                'error_count' => ErrorLog::where('last_seen_at', '>=', $oneHourAgo)->count(),
                'unresolved_errors' => ErrorLog::unresolved()->count(),
            ];

            // Get current active transactions (started but not completed)
            $activeTransactions = PerformanceTransaction::whereNull('completed_at')
                ->where('started_at', '>=', $now->copy()->subMinutes(10))
                ->count();

            $data = [
                'timestamp' => $now->toISOString(),
                'metrics' => $recentMetrics,
                'active_transactions' => $activeTransactions,
                'system_health' => $this->apmService->isEnabled() ? 'healthy' : 'disabled',
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error('APM realtime API error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load realtime data',
                'message' => 'An error occurred while loading realtime metrics. Please try again.',
            ], 500);
        }
    }
}