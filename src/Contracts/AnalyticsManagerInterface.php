<?php

namespace ArtisanPackUI\CMSFramework\Contracts;

use Illuminate\Support\Collection;
use Illuminate\Http\Request;

/**
 * Analytics Manager Interface
 *
 * Defines the contract for the ArtisanPack UI CMS Framework analytics system.
 * This interface ensures consistent implementation of privacy-compliant
 * usage analytics functionality.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Contracts
 * @since      1.3.0
 */
interface AnalyticsManagerInterface
{
    /**
     * Track a page view with privacy-compliant data collection.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request to track
     * @param array $options Additional tracking options
     * @return bool Whether the page view was successfully tracked
     */
    public function trackPageView(Request $request, array $options = []): bool;

    /**
     * Start or update a user session.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request
     * @param array $options Additional session options
     * @return bool Whether the session was successfully started/updated
     */
    public function trackSession(Request $request, array $options = []): bool;

    /**
     * End a user session.
     *
     * @since 1.3.0
     *
     * @param string $sessionId The session identifier
     * @param string|null $exitPage The exit page URL
     * @return bool Whether the session was successfully ended
     */
    public function endSession(string $sessionId, ?string $exitPage = null): bool;

    /**
     * Check if analytics tracking is enabled and user has consented.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (for consent checking)
     * @return bool Whether tracking is enabled and permitted
     */
    public function isTrackingEnabled(?Request $request = null): bool;

    /**
     * Check if the request should be excluded from tracking.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request to check
     * @return bool Whether the request should be excluded
     */
    public function shouldExcludeRequest(Request $request): bool;

    /**
     * Detect if the request appears to be from a bot.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request to analyze
     * @return bool Whether the request appears to be from a bot
     */
    public function isBotRequest(Request $request): bool;

    /**
     * Get device information from the request.
     *
     * @since 1.3.0
     *
     * @param Request $request The HTTP request to analyze
     * @return array Device information array
     */
    public function getDeviceInfo(Request $request): array;

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
    public function getPageViewStats($from = null, $to = null, array $options = []): array;

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
    public function getSessionStats($from = null, $to = null, array $options = []): array;

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
    public function getPopularPages(int $limit = 10, $from = null, $to = null): Collection;

    /**
     * Get device type breakdown for a date range.
     *
     * @since 1.3.0
     *
     * @param \DateTime|string|null $from Start date
     * @param \DateTime|string|null $to End date
     * @return Collection Device breakdown collection
     */
    public function getDeviceBreakdown($from = null, $to = null): Collection;

    /**
     * Get page view trends over time.
     *
     * @since 1.3.0
     *
     * @param int $days Number of days to analyze
     * @param \DateTime|null $endDate End date (defaults to now)
     * @return Collection Trends data collection
     */
    public function getPageViewTrends(int $days = 30, ?\DateTime $endDate = null): Collection;

    /**
     * Get session trends over time.
     *
     * @since 1.3.0
     *
     * @param int $days Number of days to analyze
     * @param \DateTime|null $endDate End date (defaults to now)
     * @return Collection Trends data collection
     */
    public function getSessionTrends(int $days = 30, ?\DateTime $endDate = null): Collection;

    /**
     * Set user consent for analytics tracking.
     *
     * @since 1.3.0
     *
     * @param bool $hasConsent Whether the user has consented
     * @param Request|null $request The HTTP request (for cookie setting)
     * @return bool Whether consent was successfully set
     */
    public function setUserConsent(bool $hasConsent, ?Request $request = null): bool;

    /**
     * Check if user has consented to analytics tracking.
     *
     * @since 1.3.0
     *
     * @param Request|null $request The HTTP request (for consent checking)
     * @return bool Whether the user has consented
     */
    public function hasUserConsent(?Request $request = null): bool;

    /**
     * Export user's analytics data (GDPR compliance).
     *
     * @since 1.3.0
     *
     * @param int|null $userId User ID (null for current session data)
     * @param string|null $sessionId Session ID for anonymous data
     * @return array User's analytics data
     */
    public function exportUserData(?int $userId = null, ?string $sessionId = null): array;

    /**
     * Delete user's analytics data (GDPR compliance).
     *
     * @since 1.3.0
     *
     * @param int|null $userId User ID (null for current session data)
     * @param string|null $sessionId Session ID for anonymous data
     * @return bool Whether data was successfully deleted
     */
    public function deleteUserData(?int $userId = null, ?string $sessionId = null): bool;

    /**
     * Clean up old analytics data based on retention policy.
     *
     * @since 1.3.0
     *
     * @param int|null $retentionDays Retention period in days (uses config if null)
     * @return array Cleanup results
     */
    public function cleanupOldData(?int $retentionDays = null): array;

    /**
     * Get engagement metrics for a date range.
     *
     * @since 1.3.0
     *
     * @param \DateTime|string|null $from Start date
     * @param \DateTime|string|null $to End date
     * @return array Engagement metrics array
     */
    public function getEngagementMetrics($from = null, $to = null): array;
}