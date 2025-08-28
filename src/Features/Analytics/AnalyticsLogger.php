<?php

namespace ArtisanPackUI\CMSFramework\Features\Analytics;

use ArtisanPackUI\CMSFramework\Contracts\AnalyticsManagerInterface;
use Illuminate\Http\Request;

/**
 * Analytics Logger
 *
 * Provides a simplified interface for logging analytics events in the
 * ArtisanPack UI CMS Framework. This class wraps the AnalyticsManager
 * to provide convenient methods for common analytics tracking tasks.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Analytics
 * @since      1.3.0
 */
class AnalyticsLogger
{
    /**
     * The analytics manager instance.
     *
     * @since 1.3.0
     *
     * @var AnalyticsManagerInterface
     */
    protected AnalyticsManagerInterface $manager;

    /**
     * Create a new analytics logger instance.
     *
     * @since 1.3.0
     *
     * @param AnalyticsManagerInterface $manager
     */
    public function __construct(AnalyticsManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Log a page view for the current request.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (uses current request if null)
     * @param array $options Additional tracking options
     * @return bool Whether the page view was successfully tracked
     */
    public function logPageView(?Request $request = null, array $options = []): bool
    {
        $request = $request ?? request();
        if (!$request) {
            return false;
        }

        return $this->manager->trackPageView($request, $options);
    }

    /**
     * Log the start of a new session.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (uses current request if null)
     * @param array $options Additional session options
     * @return bool Whether the session was successfully started
     */
    public function logSessionStart(?Request $request = null, array $options = []): bool
    {
        $request = $request ?? request();
        if (!$request) {
            return false;
        }

        return $this->manager->trackSession($request, array_merge($options, [
            'new_session' => true
        ]));
    }

    /**
     * Log a session update (page navigation within session).
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (uses current request if null)
     * @param array $options Additional session options
     * @return bool Whether the session was successfully updated
     */
    public function logSessionUpdate(?Request $request = null, array $options = []): bool
    {
        $request = $request ?? request();
        if (!$request) {
            return false;
        }

        return $this->manager->trackSession($request, $options);
    }

    /**
     * Log the end of a session.
     *
     * @since 1.3.0
     *
     * @param string|null $sessionId The session ID (uses current session if null)
     * @param string|null $exitPage The exit page URL
     * @return bool Whether the session was successfully ended
     */
    public function logSessionEnd(?string $sessionId = null, ?string $exitPage = null): bool
    {
        $sessionId = $sessionId ?? session()->getId();
        if (!$sessionId) {
            return false;
        }

        return $this->manager->endSession($sessionId, $exitPage);
    }

    /**
     * Log a custom analytics event (page view with custom options).
     *
     * @since 1.3.0
     *
     * @param string $eventType The type of event ('page_view', 'session_start', etc.)
     * @param array $data Additional event data
     * @param Request|null $request The HTTP request (uses current request if null)
     * @return bool Whether the event was successfully logged
     */
    public function logEvent(string $eventType, array $data = [], ?Request $request = null): bool
    {
        $request = $request ?? request();
        if (!$request) {
            return false;
        }

        switch ($eventType) {
            case 'page_view':
                return $this->manager->trackPageView($request, $data);
            
            case 'session_start':
                return $this->manager->trackSession($request, array_merge($data, [
                    'new_session' => true
                ]));
            
            case 'session_update':
                return $this->manager->trackSession($request, $data);
            
            case 'session_end':
                $sessionId = $data['session_id'] ?? session()->getId();
                $exitPage = $data['exit_page'] ?? null;
                return $this->manager->endSession($sessionId, $exitPage);
            
            default:
                // For unknown event types, log as page view with additional context
                return $this->manager->trackPageView($request, array_merge($data, [
                    'event_type' => $eventType
                ]));
        }
    }

    /**
     * Log user consent for analytics tracking.
     *
     * @since 1.3.0
     *
     * @param bool $hasConsent Whether the user has consented
     * @param Request|null $request The HTTP request (uses current request if null)
     * @return bool Whether consent was successfully set
     */
    public function logConsent(bool $hasConsent, ?Request $request = null): bool
    {
        $request = $request ?? request();
        return $this->manager->setUserConsent($hasConsent, $request);
    }

    /**
     * Check if analytics tracking is currently enabled.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (uses current request if null)
     * @return bool Whether tracking is enabled
     */
    public function isTrackingEnabled(?Request $request = null): bool
    {
        $request = $request ?? request();
        return $this->manager->isTrackingEnabled($request);
    }

    /**
     * Check if the current request should be excluded from tracking.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (uses current request if null)
     * @return bool Whether the request should be excluded
     */
    public function shouldExcludeRequest(?Request $request = null): bool
    {
        $request = $request ?? request();
        if (!$request) {
            return true;
        }

        return $this->manager->shouldExcludeRequest($request);
    }

    /**
     * Check if the current request appears to be from a bot.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (uses current request if null)
     * @return bool Whether the request appears to be from a bot
     */
    public function isBotRequest(?Request $request = null): bool
    {
        $request = $request ?? request();
        if (!$request) {
            return false;
        }

        return $this->manager->isBotRequest($request);
    }

    /**
     * Export analytics data for the current user (GDPR compliance).
     *
     * @since 1.3.0
     *
     * @param int|null $userId User ID (uses current authenticated user if null)
     * @param string|null $sessionId Session ID (uses current session if null)
     * @return array User's analytics data
     */
    public function exportUserData(?int $userId = null, ?string $sessionId = null): array
    {
        $userId = $userId ?? auth()->id();
        $sessionId = $sessionId ?? session()->getId();
        
        return $this->manager->exportUserData($userId, $sessionId);
    }

    /**
     * Delete analytics data for the current user (GDPR compliance).
     *
     * @since 1.3.0
     *
     * @param int|null $userId User ID (uses current authenticated user if null)
     * @param string|null $sessionId Session ID (uses current session if null)
     * @return bool Whether data was successfully deleted
     */
    public function deleteUserData(?int $userId = null, ?string $sessionId = null): bool
    {
        $userId = $userId ?? auth()->id();
        $sessionId = $sessionId ?? session()->getId();
        
        return $this->manager->deleteUserData($userId, $sessionId);
    }

    /**
     * Get the underlying analytics manager instance.
     *
     * @since 1.3.0
     *
     * @return AnalyticsManagerInterface The analytics manager
     */
    public function getManager(): AnalyticsManagerInterface
    {
        return $this->manager;
    }

    /**
     * Perform a quick analytics health check.
     *
     * @since 1.3.0
     *
     * @return array Health check results
     */
    public function healthCheck(): array
    {
        try {
            $config = config('artisanpack-cms.analytics');
            $request = request();
            
            return [
                'enabled' => $config['enabled'] ?? false,
                'tracking_enabled' => $request ? $this->manager->isTrackingEnabled($request) : false,
                'consent_required' => $config['privacy']['require_consent'] ?? false,
                'bot_detection_enabled' => $config['bot_detection']['enabled'] ?? false,
                'retention_days' => $config['retention']['retention_days'] ?? 0,
                'auto_cleanup' => $config['retention']['auto_cleanup'] ?? false,
                'database_accessible' => $this->checkDatabaseAccess(),
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Check if the analytics database tables are accessible.
     *
     * @since 1.3.0
     *
     * @return bool Whether database access is working
     */
    protected function checkDatabaseAccess(): bool
    {
        try {
            // Try to count records from both analytics tables
            \DB::table('page_view_analytics')->count();
            \DB::table('user_session_analytics')->count();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}