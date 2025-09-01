<?php

namespace ArtisanPackUI\CMSFramework\Http\Middleware;

use ArtisanPackUI\CMSFramework\Services\APMService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * APMMiddleware.
 *
 * Middleware for automatic Application Performance Monitoring.
 * Captures request/response metrics, database queries, memory usage,
 * and other performance indicators for all HTTP requests.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Http\Middleware
 * @since   1.3.0
 */
class APMMiddleware
{
    /**
     * APMService instance.
     *
     * @var APMService
     */
    protected APMService $apmService;

    /**
     * Query count at request start.
     *
     * @var int
     */
    protected int $startQueryCount = 0;

    /**
     * Memory usage at request start.
     *
     * @var int
     */
    protected int $startMemoryUsage = 0;

    /**
     * Create a new APMMiddleware instance.
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
     * Handle an incoming request.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @param Closure $next
     * @return BaseResponse
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        // Skip APM if disabled
        if (!$this->apmService->isEnabled()) {
            return $next($request);
        }

        // Skip monitoring for certain paths
        if ($this->shouldSkipMonitoring($request)) {
            return $next($request);
        }

        // Capture initial metrics
        $this->startQueryCount = $this->getQueryCount();
        $this->startMemoryUsage = memory_get_usage(true);

        // Generate transaction name
        $transactionName = $this->generateTransactionName($request);

        // Set user context if authenticated
        if ($request->user()) {
            $this->apmService->setUser($request->user()->id, [
                'name' => $request->user()->name ?? null,
                'email' => $request->user()->email ?? null,
            ]);
        }

        // Start transaction
        $transactionId = $this->apmService->startTransaction($transactionName, [
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'request_url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        // Add custom attributes
        $this->apmService->addCustomAttributes([
            'route_name' => $request->route()?->getName(),
            'route_action' => $request->route()?->getActionName(),
            'is_ajax' => $request->ajax(),
            'content_type' => $request->header('Content-Type'),
        ]);

        $startTime = microtime(true);
        $exception = null;
        $response = null;

        try {
            // Process request
            $response = $next($request);
            
            return $response;
        } catch (\Throwable $e) {
            $exception = $e;
            
            // Record error in APM
            $this->apmService->recordError($e, [
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'transaction_id' => $transactionId,
            ]);

            throw $e;
        } finally {
            // Calculate metrics
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $memoryUsage = (memory_get_usage(true) - $this->startMemoryUsage) / 1024 / 1024; // Convert to MB
            $queryCount = $this->getQueryCount() - $this->startQueryCount;
            
            // End transaction with additional metadata
            $this->apmService->endTransaction($transactionId, [
                'http_status_code' => $response?->getStatusCode() ?? ($exception ? 500 : 200),
                'response_size_bytes' => $this->getResponseSize($response),
                'db_query_count' => $queryCount,
                'memory_usage_mb' => $memoryUsage,
                'duration_ms' => $duration,
                'has_exception' => $exception !== null,
            ]);

            // Track individual metrics
            $this->trackIndividualMetrics($request, $response, $duration, $memoryUsage, $queryCount, $exception);
        }
    }

    /**
     * Check if monitoring should be skipped for this request.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldSkipMonitoring(Request $request): bool
    {
        // Skip health check endpoints
        $skipPaths = [
            'health',
            'health-check',
            'status',
            'ping',
            '_debugbar',
            'telescope',
        ];

        $path = $request->path();
        
        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        // Skip based on configuration
        $skipPatterns = config('cms.apm.skip_patterns', []);
        foreach ($skipPatterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate transaction name from request.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @return string
     */
    protected function generateTransactionName(Request $request): string
    {
        // Use route name if available
        if ($request->route() && $request->route()->getName()) {
            return $request->method() . ' ' . $request->route()->getName();
        }

        // Use route action if available
        if ($request->route() && $request->route()->getActionName()) {
            $action = $request->route()->getActionName();
            $controller = class_basename(explode('@', $action)[0]);
            $method = explode('@', $action)[1] ?? 'handle';
            return $request->method() . ' ' . $controller . '@' . $method;
        }

        // Fallback to path with parameter substitution
        $path = $request->path();
        if ($request->route()) {
            $uri = $request->route()->uri();
            // Replace parameters with placeholders
            $path = preg_replace('/\{[^}]+\}/', '*', $uri);
        }

        return $request->method() . ' ' . $path;
    }

    /**
     * Get current database query count.
     *
     * @since 1.3.0
     *
     * @return int
     */
    protected function getQueryCount(): int
    {
        return count(DB::getQueryLog());
    }

    /**
     * Get response size in bytes.
     *
     * @since 1.3.0
     *
     * @param BaseResponse|null $response
     * @return int
     */
    protected function getResponseSize(?BaseResponse $response): int
    {
        if (!$response) {
            return 0;
        }

        $content = $response->getContent();
        
        return is_string($content) ? strlen($content) : 0;
    }

    /**
     * Track individual performance metrics.
     *
     * @since 1.3.0
     *
     * @param Request $request
     * @param BaseResponse|null $response
     * @param float $duration
     * @param float $memoryUsage
     * @param int $queryCount
     * @param \Throwable|null $exception
     * @return void
     */
    protected function trackIndividualMetrics(
        Request $request,
        ?BaseResponse $response,
        float $duration,
        float $memoryUsage,
        int $queryCount,
        ?\Throwable $exception
    ): void {
        $tags = [
            'method' => $request->method(),
            'status' => $response?->getStatusCode() ?? ($exception ? 500 : 200),
            'route' => $request->route()?->getName() ?: 'unknown',
        ];

        // Track response time
        $this->apmService->trackMetric('http_request_duration', $duration, $tags, 'ms');

        // Track memory usage
        $this->apmService->trackMetric('http_request_memory', $memoryUsage, $tags, 'mb');

        // Track database queries
        $this->apmService->trackMetric('http_request_db_queries', $queryCount, $tags, 'count');

        // Track response size
        if ($response) {
            $responseSize = $this->getResponseSize($response);
            $this->apmService->trackMetric('http_response_size', $responseSize, $tags, 'bytes');
        }

        // Track error rate
        if ($exception) {
            $errorTags = array_merge($tags, [
                'exception_class' => get_class($exception),
            ]);
            $this->apmService->trackMetric('http_request_errors', 1, $errorTags, 'count');
        }

        // Track slow requests
        $slowThreshold = config('cms.apm.performance.slow_query_threshold', 1000);
        if ($duration > $slowThreshold) {
            $this->apmService->trackMetric('http_slow_requests', 1, $tags, 'count');
        }

        // Track status code distribution
        $statusCode = $response?->getStatusCode() ?? ($exception ? 500 : 200);
        $statusGroup = floor($statusCode / 100) . 'xx';
        $statusTags = array_merge($tags, ['status_group' => $statusGroup]);
        $this->apmService->trackMetric('http_status_codes', 1, $statusTags, 'count');

        // Custom metrics through Eventy hooks
        $customMetrics = \TorMorten\Eventy\Facades\Eventy::filter('ap.cms.apm.custom_metrics', [], [
            'request' => $request,
            'response' => $response,
            'duration' => $duration,
            'memory_usage' => $memoryUsage,
            'query_count' => $queryCount,
            'exception' => $exception,
            'tags' => $tags,
        ]);

        foreach ($customMetrics as $metric) {
            $this->apmService->trackMetric(
                $metric['name'],
                $metric['value'],
                $metric['tags'] ?? [],
                $metric['unit'] ?? 'count'
            );
        }
    }
}