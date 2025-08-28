<?php

namespace ArtisanPackUI\CMSFramework\Features\Analytics;

use ArtisanPackUI\CMSFramework\Contracts\AnalyticsManagerInterface;
use ArtisanPackUI\CMSFramework\Models\PageViewAnalytics;
use ArtisanPackUI\CMSFramework\Models\UserSessionAnalytics;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Analytics Manager
 *
 * Implements privacy-compliant usage analytics for the ArtisanPack UI CMS Framework.
 * This class handles data collection, privacy compliance, GDPR features, and
 * statistical analysis while maintaining user privacy through data anonymization.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Analytics
 * @since      1.3.0
 */
class AnalyticsManager implements AnalyticsManagerInterface
{
    /**
     * The application instance.
     *
     * @since 1.3.0
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Create a new analytics manager instance.
     *
     * @since 1.3.0
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Track a page view with privacy-compliant data collection.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request to track
     * @param array $options Additional tracking options
     * @return bool Whether the page view was successfully tracked
     */
    public function trackPageView(Request $request, array $options = []): bool
    {
        if (!$this->isTrackingEnabled($request)) {
            return false;
        }

        if ($this->shouldExcludeRequest($request)) {
            return false;
        }

        try {
            $deviceInfo = $this->getDeviceInfo($request);
            $startTime = $options['start_time'] ?? null;
            $responseTime = $startTime ? (int)((microtime(true) - $startTime) * 1000) : null;

            // Limit response time to configured maximum to ignore outliers
            $maxResponseTime = config('artisanpack-cms.analytics.performance.max_response_time_ms', 30000);
            if ($responseTime && $responseTime > $maxResponseTime) {
                $responseTime = null;
            }

            PageViewAnalytics::logPageView(
                url: $request->fullUrl(),
                path: $request->path(),
                referrer: $request->header('referer'),
                sessionId: $request->session()->getId(),
                userId: $request->user()?->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                deviceInfo: $deviceInfo,
                responseTimeMs: $responseTime,
                pageLoadTimeMs: $options['page_load_time'] ?? null
            );

            return true;
        } catch (\Exception $e) {
            // Log error but don't throw to avoid breaking the application
            \Log::warning('Analytics page view tracking failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Start or update a user session.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @param array $options Additional session options
     * @return bool Whether the session was successfully started/updated
     */
    public function trackSession(Request $request, array $options = []): bool
    {
        if (!$this->isTrackingEnabled($request)) {
            return false;
        }

        if (!config('artisanpack-cms.analytics.track_sessions', true)) {
            return false;
        }

        if ($this->shouldExcludeRequest($request)) {
            return false;
        }

        try {
            $deviceInfo = $this->getDeviceInfo($request);
            $sessionId = $request->session()->getId();
            $isNewSession = $options['new_session'] ?? false;

            if ($isNewSession) {
                UserSessionAnalytics::startSession(
                    sessionId: $sessionId,
                    landingPage: $request->path(),
                    userId: $request->user()?->id,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    deviceInfo: $deviceInfo
                );
            } else {
                UserSessionAnalytics::updateSession(
                    sessionId: $sessionId,
                    currentPage: $request->path(),
                    endSession: $options['end_session'] ?? false
                );
            }

            return true;
        } catch (\Exception $e) {
            \Log::warning('Analytics session tracking failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * End a user session.
     *
     * @since 1.3.0
     *
     * @param string $sessionId The session identifier
     * @param string|null $exitPage The exit page URL
     * @return bool Whether the session was successfully ended
     */
    public function endSession(string $sessionId, ?string $exitPage = null): bool
    {
        try {
            UserSessionAnalytics::endSession($sessionId, $exitPage);
            return true;
        } catch (\Exception $e) {
            \Log::warning('Analytics session end failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if analytics tracking is enabled and user has consented.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (for consent checking)
     * @return bool Whether tracking is enabled and permitted
     */
    public function isTrackingEnabled(?Request $request = null): bool
    {
        // Check if analytics is globally enabled
        if (!config('artisanpack-cms.analytics.enabled', true)) {
            return false;
        }

        // Check user consent if required
        if (config('artisanpack-cms.analytics.privacy.require_consent', false)) {
            return $this->hasUserConsent($request);
        }

        return true;
    }

    /**
     * Check if the request should be excluded from tracking.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request to check
     * @return bool Whether the request should be excluded
     */
    public function shouldExcludeRequest(Request $request): bool
    {
        // Check excluded paths
        $excludedPaths = config('artisanpack-cms.analytics.exclusions.excluded_paths', []);
        foreach ($excludedPaths as $pattern) {
            if (Str::is($pattern, $request->path())) {
                return true;
            }
        }

        // Check excluded IPs
        $excludedIps = config('artisanpack-cms.analytics.exclusions.excluded_ips', []);
        $clientIp = $request->ip();
        foreach ($excludedIps as $excludedIp) {
            if ($this->ipMatches($clientIp, $excludedIp)) {
                return true;
            }
        }

        // Check excluded user agents
        $excludedUserAgents = config('artisanpack-cms.analytics.exclusions.excluded_user_agents', []);
        $userAgent = $request->userAgent() ?? '';
        foreach ($excludedUserAgents as $pattern) {
            if (Str::contains(strtolower($userAgent), strtolower($pattern))) {
                return true;
            }
        }

        // Check if bot should be excluded
        if ($this->isBotRequest($request) && !config('artisanpack-cms.analytics.bot_detection.track_bots', true)) {
            return true;
        }

        return false;
    }

    /**
     * Detect if the request appears to be from a bot.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request to analyze
     * @return bool Whether the request appears to be from a bot
     */
    public function isBotRequest(Request $request): bool
    {
        if (!config('artisanpack-cms.analytics.bot_detection.enabled', true)) {
            return false;
        }

        $userAgent = strtolower($request->userAgent() ?? '');
        $botPatterns = config('artisanpack-cms.analytics.bot_detection.bot_patterns', []);

        foreach ($botPatterns as $pattern) {
            if (Str::contains($userAgent, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get device information from the request.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request to analyze
     * @return array Device information array
     */
    public function getDeviceInfo(Request $request): array
    {
        $userAgent = $request->userAgent() ?? '';
        $info = [
            'device_type' => 'desktop',
            'browser_family' => null,
            'os_family' => null,
            'is_bot' => $this->isBotRequest($request),
            'country_code' => null,
        ];

        if (!config('artisanpack-cms.analytics.device_detection.enabled', true)) {
            return $info;
        }

        // Device type detection
        $mobilePatterns = config('artisanpack-cms.analytics.device_detection.mobile_patterns', []);
        $tabletPatterns = config('artisanpack-cms.analytics.device_detection.tablet_patterns', []);

        foreach ($tabletPatterns as $pattern) {
            if (Str::contains($userAgent, $pattern)) {
                $info['device_type'] = 'tablet';
                break;
            }
        }

        if ($info['device_type'] === 'desktop') {
            foreach ($mobilePatterns as $pattern) {
                if (Str::contains($userAgent, $pattern)) {
                    $info['device_type'] = 'mobile';
                    break;
                }
            }
        }

        // Browser detection
        if (config('artisanpack-cms.analytics.device_detection.detect_browser', true)) {
            $info['browser_family'] = $this->detectBrowser($userAgent);
        }

        // OS detection
        if (config('artisanpack-cms.analytics.device_detection.detect_os', true)) {
            $info['os_family'] = $this->detectOS($userAgent);
        }

        // Country detection (implement geolocation if needed)
        if (config('artisanpack-cms.analytics.privacy.collect_country_data', true)) {
            // This would integrate with a geolocation service
            // $info['country_code'] = $this->detectCountry($request->ip());
        }

        return $info;
    }

    /**
     * Get page view statistics for a date range.
     *
     * @since 1.3.0
     *
     * @param \DateTime|string|null $from Start date
     * @param \DateTime|string|null $to End date
     * @param array $options Additional query options
     * @return array Statistics array
     */
    public function getPageViewStats($from = null, $to = null, array $options = []): array
    {
        return PageViewAnalytics::getPageViewStats($from, $to);
    }

    /**
     * Get session statistics for a date range.
     *
     * @since 1.3.0
     *
     * @param \DateTime|string|null $from Start date
     * @param \DateTime|string|null $to End date
     * @param array $options Additional query options
     * @return array Statistics array
     */
    public function getSessionStats($from = null, $to = null, array $options = []): array
    {
        return UserSessionAnalytics::getSessionStats($from, $to);
    }

    /**
     * Get popular pages for a date range.
     *
     * @since 1.3.0
     *
     * @param int $limit Maximum number of results
     * @param \DateTime|string|null $from Start date
     * @param \DateTime|string|null $to End date
     * @return Collection Popular pages collection
     */
    public function getPopularPages(int $limit = 10, $from = null, $to = null): Collection
    {
        return PageViewAnalytics::getPopularPages($limit, $from, $to);
    }

    /**
     * Get device type breakdown for a date range.
     *
     * @since 1.3.0
     *
     * @param \DateTime|string|null $from Start date
     * @param \DateTime|string|null $to End date
     * @return Collection Device breakdown collection
     */
    public function getDeviceBreakdown($from = null, $to = null): Collection
    {
        return PageViewAnalytics::getDeviceBreakdown($from, $to);
    }

    /**
     * Get page view trends over time.
     *
     * @since 1.3.0
     *
     * @param int $days Number of days to analyze
     * @param \DateTime|null $endDate End date (defaults to now)
     * @return Collection Trends data collection
     */
    public function getPageViewTrends(int $days = 30, ?\DateTime $endDate = null): Collection
    {
        $carbonEndDate = $endDate ? \Carbon\Carbon::parse($endDate) : null;
        return PageViewAnalytics::getPageViewTrends($days, $carbonEndDate);
    }

    /**
     * Get session trends over time.
     *
     * @since 1.3.0
     *
     * @param int $days Number of days to analyze
     * @param \DateTime|null $endDate End date (defaults to now)
     * @return Collection Trends data collection
     */
    public function getSessionTrends(int $days = 30, ?\DateTime $endDate = null): Collection
    {
        $carbonEndDate = $endDate ? \Carbon\Carbon::parse($endDate) : null;
        return UserSessionAnalytics::getSessionTrends($days, $carbonEndDate);
    }

    /**
     * Set user consent for analytics tracking.
     *
     * @since 1.3.0
     *
     * @param bool $hasConsent Whether the user has consented
     * @param Request|null $request The HTTP request (for cookie setting)
     * @return bool Whether consent was successfully set
     */
    public function setUserConsent(bool $hasConsent, ?Request $request = null): bool
    {
        $cookieName = config('artisanpack-cms.analytics.privacy.consent_cookie_name', 'analytics_consent');
        $lifetime = config('artisanpack-cms.analytics.privacy.consent_cookie_lifetime', 365);
        
        Cookie::queue(Cookie::make(
            $cookieName,
            $hasConsent ? 'granted' : 'denied',
            $lifetime * 24 * 60 // Convert days to minutes
        ));

        return true;
    }

    /**
     * Check if user has consented to analytics tracking.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (for consent checking)
     * @return bool Whether the user has consented
     */
    public function hasUserConsent(?Request $request = null): bool
    {
        $request = $request ?? request();
        if (!$request) {
            return config('artisanpack-cms.analytics.privacy.default_consent', false);
        }

        $cookieName = config('artisanpack-cms.analytics.privacy.consent_cookie_name', 'analytics_consent');
        $consent = $request->cookie($cookieName);

        if ($consent === null) {
            return config('artisanpack-cms.analytics.privacy.default_consent', false);
        }

        return $consent === 'granted';
    }

    /**
     * Export user's analytics data (GDPR compliance).
     *
     * @since 1.3.0
     *
     * @param int|null $userId User ID (null for current session data)
     * @param string|null $sessionId Session ID for anonymous data
     * @return array User's analytics data
     */
    public function exportUserData(?int $userId = null, ?string $sessionId = null): array
    {
        $data = [
            'page_views' => [],
            'sessions' => [],
            'export_date' => now()->toISOString(),
        ];

        if ($userId) {
            $data['page_views'] = PageViewAnalytics::where('user_id', $userId)->get()->toArray();
            $data['sessions'] = UserSessionAnalytics::where('user_id', $userId)->get()->toArray();
        } elseif ($sessionId) {
            $sessionHash = hash('sha256', $sessionId);
            $data['page_views'] = PageViewAnalytics::where('session_hash', $sessionHash)->get()->toArray();
            $data['sessions'] = UserSessionAnalytics::where('session_hash', $sessionHash)->get()->toArray();
        }

        return $data;
    }

    /**
     * Delete user's analytics data (GDPR compliance).
     *
     * @since 1.3.0
     *
     * @param int|null $userId User ID (null for current session data)
     * @param string|null $sessionId Session ID for anonymous data
     * @return bool Whether data was successfully deleted
     */
    public function deleteUserData(?int $userId = null, ?string $sessionId = null): bool
    {
        try {
            if ($userId) {
                PageViewAnalytics::where('user_id', $userId)->delete();
                UserSessionAnalytics::where('user_id', $userId)->delete();
            } elseif ($sessionId) {
                $sessionHash = hash('sha256', $sessionId);
                PageViewAnalytics::where('session_hash', $sessionHash)->delete();
                UserSessionAnalytics::where('session_hash', $sessionHash)->delete();
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Analytics data deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up old analytics data based on retention policy.
     *
     * @since 1.3.0
     *
     * @param int|null $retentionDays Retention period in days (uses config if null)
     * @return array Cleanup results
     */
    public function cleanupOldData(?int $retentionDays = null): array
    {
        $retentionDays = $retentionDays ?? config('artisanpack-cms.analytics.retention.retention_days', 365);
        
        if ($retentionDays <= 0) {
            return ['page_views_deleted' => 0, 'sessions_deleted' => 0];
        }

        try {
            $pageViewsDeleted = PageViewAnalytics::cleanup($retentionDays);
            $sessionsDeleted = UserSessionAnalytics::cleanup($retentionDays);

            return [
                'page_views_deleted' => $pageViewsDeleted,
                'sessions_deleted' => $sessionsDeleted,
                'retention_days' => $retentionDays,
                'cleanup_date' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            \Log::error('Analytics cleanup failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get engagement metrics for a date range.
     *
     * @since 1.3.0
     *
     * @param \DateTime|string|null $from Start date
     * @param \DateTime|string|null $to End date
     * @return array Engagement metrics array
     */
    public function getEngagementMetrics($from = null, $to = null): array
    {
        return UserSessionAnalytics::getEngagementMetrics($from, $to);
    }

    /**
     * Check if an IP address matches a pattern (supports CIDR notation).
     *
     * @since 1.3.0
     *
     * @param string $ip The IP address to check
     * @param string $pattern The pattern to match against
     * @return bool Whether the IP matches the pattern
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        if ($ip === $pattern) {
            return true;
        }

        // Check CIDR notation
        if (Str::contains($pattern, '/')) {
            [$subnet, $bits] = explode('/', $pattern);
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnet &= $mask;
            return ($ip & $mask) === $subnet;
        }

        return false;
    }

    /**
     * Detect browser family from user agent.
     *
     * @since 1.3.0
     *
     * @param string $userAgent The user agent string
     * @return string|null The browser family
     */
    protected function detectBrowser(string $userAgent): ?string
    {
        $browsers = [
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Edge' => 'Edge',
            'Opera' => 'Opera',
        ];

        foreach ($browsers as $pattern => $name) {
            if (Str::contains($userAgent, $pattern)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Detect operating system from user agent.
     *
     * @since 1.3.0
     *
     * @param string $userAgent The user agent string
     * @return string|null The operating system family
     */
    protected function detectOS(string $userAgent): ?string
    {
        $systems = [
            'Windows' => 'Windows',
            'Macintosh' => 'macOS',
            'Linux' => 'Linux',
            'Android' => 'Android',
            'iOS' => 'iOS',
        ];

        foreach ($systems as $pattern => $name) {
            if (Str::contains($userAgent, $pattern)) {
                return $name;
            }
        }

        return null;
    }
}