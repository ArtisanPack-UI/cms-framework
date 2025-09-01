<?php

namespace ArtisanPackUI\CMSFramework\Features\Analytics\Middleware;

use ArtisanPackUI\CMSFramework\Contracts\AnalyticsManagerInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Analytics Tracking Middleware
 *
 * Automatically tracks page views and sessions for web requests in the
 * ArtisanPack UI CMS Framework. This middleware handles privacy-compliant
 * data collection, request timing, and session management.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Analytics\Middleware
 * @since      1.3.0
 */
class AnalyticsTrackingMiddleware
{
    /**
     * The analytics manager instance.
     *
     * @since 1.3.0
     *
     * @var AnalyticsManagerInterface
     */
    protected AnalyticsManagerInterface $analytics;

    /**
     * Create a new analytics tracking middleware instance.
     *
     * @since 1.3.0
     *
     * @param AnalyticsManagerInterface $analytics
     */
    public function __construct(AnalyticsManagerInterface $analytics)
    {
        $this->analytics = $analytics;
    }

    /**
     * Handle an incoming request and track analytics data.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @param Closure $next The next middleware closure
     * @return Response The HTTP response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Record the start time for performance tracking
        $startTime = microtime(true);

        // Check if tracking is enabled and should proceed
        if (!$this->shouldTrack($request)) {
            return $next($request);
        }

        // Check if this is a new session
        $isNewSession = $this->isNewSession($request);

        // Process the request
        $response = $next($request);

        // Track analytics after the response is generated (async if possible)
        $this->trackAfterResponse($request, $response, $startTime, $isNewSession);

        return $response;
    }

    /**
     * Determine if analytics tracking should proceed for this request.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @return bool Whether tracking should proceed
     */
    protected function shouldTrack(Request $request): bool
    {
        // Quick check if analytics is globally disabled
        if (!config('artisanpack-cms.analytics.enabled', true)) {
            return false;
        }

        // Check if auto tracking is enabled
        if (!config('artisanpack-cms.analytics.auto_track_page_views', true)) {
            return false;
        }

        // Only track GET requests (avoid tracking form submissions, API calls, etc.)
        if (!$request->isMethod('GET')) {
            return false;
        }

        // Use analytics manager to check if tracking is enabled and request should be excluded
        if (!$this->analytics->isTrackingEnabled($request)) {
            return false;
        }

        if ($this->analytics->shouldExcludeRequest($request)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this is a new session.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @return bool Whether this is a new session
     */
    protected function isNewSession(Request $request): bool
    {
        // Check if session tracking is enabled
        if (!config('artisanpack-cms.analytics.track_sessions', true)) {
            return false;
        }

        $session = $request->session();
        
        // Check if session has our analytics tracking marker
        $analyticsSessionKey = 'analytics_session_started';
        
        if (!$session->has($analyticsSessionKey)) {
            // Mark this session as having analytics tracking
            $session->put($analyticsSessionKey, true);
            return true;
        }

        return false;
    }

    /**
     * Track analytics data after the response is generated.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @param Response $response The HTTP response
     * @param float $startTime The request start time
     * @param bool $isNewSession Whether this is a new session
     * @return void
     */
    protected function trackAfterResponse(
        Request $request,
        Response $response,
        float $startTime,
        bool $isNewSession
    ): void {
        // Only track successful responses (2xx and 3xx status codes)
        if ($response->getStatusCode() >= 400) {
            return;
        }

        try {
            $options = [
                'start_time' => $startTime,
                'response_status' => $response->getStatusCode(),
            ];

            // Track page view with performance data
            if (config('artisanpack-cms.analytics.auto_track_page_views', true)) {
                $this->analytics->trackPageView($request, $options);
            }

            // Track session if enabled
            if (config('artisanpack-cms.analytics.track_sessions', true)) {
                $sessionOptions = [
                    'new_session' => $isNewSession,
                    'response_status' => $response->getStatusCode(),
                ];

                $this->analytics->trackSession($request, $sessionOptions);
            }

            // Store last activity time for session timeout tracking
            $this->updateSessionActivity($request);

        } catch (\Exception $e) {
            // Log error but don't break the application
            \Log::warning('Analytics middleware tracking failed: ' . $e->getMessage(), [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
            ]);
        }
    }

    /**
     * Update session last activity time for timeout detection.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @return void
     */
    protected function updateSessionActivity(Request $request): void
    {
        try {
            $session = $request->session();
            $session->put('analytics_last_activity', time());
        } catch (\Exception $e) {
            // Silently fail if session is not available
        }
    }

    /**
     * Handle session termination (called when session is ending).
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @param string|null $exitPage The exit page URL
     * @return void
     */
    public function terminateSession(Request $request, ?string $exitPage = null): void
    {
        if (!config('artisanpack-cms.analytics.track_sessions', true)) {
            return;
        }

        try {
            $sessionId = $request->session()->getId();
            $exitPage = $exitPage ?? $request->path();
            
            $this->analytics->endSession($sessionId, $exitPage);
        } catch (\Exception $e) {
            \Log::warning('Analytics session termination failed: ' . $e->getMessage());
        }
    }

    /**
     * Check for session timeout and end inactive sessions.
     *
     * This method can be called periodically to clean up inactive sessions.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @return bool Whether the session was timed out
     */
    public function checkSessionTimeout(Request $request): bool
    {
        if (!config('artisanpack-cms.analytics.track_sessions', true)) {
            return false;
        }

        try {
            $session = $request->session();
            $lastActivity = $session->get('analytics_last_activity');
            
            if (!$lastActivity) {
                return false;
            }

            $timeoutMinutes = config('artisanpack-cms.analytics.sessions.timeout_minutes', 30);
            $timeoutSeconds = $timeoutMinutes * 60;
            
            if (time() - $lastActivity > $timeoutSeconds) {
                // Session has timed out
                $this->terminateSession($request);
                $session->forget('analytics_session_started');
                $session->forget('analytics_last_activity');
                return true;
            }
        } catch (\Exception $e) {
            \Log::warning('Analytics session timeout check failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Get analytics middleware configuration for debugging.
     *
     * @since 1.3.0
     *
     * @return array Configuration array
     */
    public function getConfiguration(): array
    {
        return [
            'enabled' => config('artisanpack-cms.analytics.enabled', true),
            'auto_track_page_views' => config('artisanpack-cms.analytics.auto_track_page_views', true),
            'track_sessions' => config('artisanpack-cms.analytics.track_sessions', true),
            'track_performance' => config('artisanpack-cms.analytics.performance.track_response_times', true),
            'session_timeout_minutes' => config('artisanpack-cms.analytics.sessions.timeout_minutes', 30),
            'bot_tracking' => config('artisanpack-cms.analytics.bot_detection.track_bots', true),
            'consent_required' => config('artisanpack-cms.analytics.privacy.require_consent', false),
        ];
    }
}