<?php

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * PageViewAnalytics Model.
 *
 * Tracks page view analytics data for the ArtisanPack UI CMS Framework.
 * Used for understanding usage patterns, popular pages, and user behavior
 * while maintaining privacy compliance through data anonymization.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.3.0
 *
 * @property int                        $id
 * @property string                     $url                The requested URL
 * @property string                     $path               The URL path without domain
 * @property string|null                $referrer_hash      Hashed referrer URL for privacy
 * @property string|null                $session_hash       Hashed session identifier
 * @property int|null                   $user_id            User who viewed the page (if authenticated)
 * @property string|null                $ip_address_hash    Hashed IP address for privacy
 * @property string|null                $user_agent_hash    Hashed user agent for privacy
 * @property string|null                $country_code       Country code from IP geolocation
 * @property string|null                $device_type        Device type (mobile, tablet, desktop)
 * @property string|null                $browser_family     Browser family (Chrome, Firefox, Safari, etc.)
 * @property string|null                $os_family          Operating system family
 * @property int|null                   $response_time_ms   Page response time in milliseconds
 * @property int|null                   $page_load_time_ms  Client-side page load time
 * @property bool                       $is_bot             Whether the request appears to be from a bot
 * @property Carbon                     $viewed_at          When the page was viewed
 * @property Carbon                     $created_at
 * @property Carbon                     $updated_at
 */
class PageViewAnalytics extends Model
{
    /**
     * The table associated with the model.
     *
     * @since 1.3.0
     *
     * @var string
     */
    protected $table = 'page_view_analytics';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.3.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'url',
        'path',
        'referrer_hash',
        'session_hash',
        'user_id',
        'ip_address_hash',
        'user_agent_hash',
        'country_code',
        'device_type',
        'browser_family',
        'os_family',
        'response_time_ms',
        'page_load_time_ms',
        'is_bot',
        'viewed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.3.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_bot' => 'boolean',
        'viewed_at' => 'datetime',
    ];

    /**
     * Get the user who viewed the page.
     *
     * @since 1.3.0
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to filter by date range.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Builder
     */
    public function scopeDateRange(Builder $query, $from = null, $to = null): Builder
    {
        if ($from) {
            $query->where('viewed_at', '>=', $from);
        }

        if ($to) {
            $query->where('viewed_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope a query to filter by user.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to exclude bot traffic.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeExcludeBots(Builder $query): Builder
    {
        return $query->where('is_bot', false);
    }

    /**
     * Scope a query to filter by device type.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param string $deviceType
     * @return Builder
     */
    public function scopeByDeviceType(Builder $query, string $deviceType): Builder
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Log a page view with privacy-compliant data anonymization.
     *
     * @since 1.3.0
     *
     * @param string $url
     * @param string $path
     * @param string|null $referrer
     * @param string|null $sessionId
     * @param int|null $userId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param array $deviceInfo
     * @param int|null $responseTimeMs
     * @param int|null $pageLoadTimeMs
     * @return static
     */
    public static function logPageView(
        string $url,
        string $path,
        ?string $referrer = null,
        ?string $sessionId = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $deviceInfo = [],
        ?int $responseTimeMs = null,
        ?int $pageLoadTimeMs = null
    ): static {
        return static::create([
            'url' => $url,
            'path' => $path,
            'referrer_hash' => $referrer ? hash('sha256', $referrer) : null,
            'session_hash' => $sessionId ? hash('sha256', $sessionId) : null,
            'user_id' => $userId,
            'ip_address_hash' => $ipAddress ? hash('sha256', $ipAddress . config('app.key')) : null,
            'user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
            'country_code' => $deviceInfo['country_code'] ?? null,
            'device_type' => $deviceInfo['device_type'] ?? null,
            'browser_family' => $deviceInfo['browser_family'] ?? null,
            'os_family' => $deviceInfo['os_family'] ?? null,
            'response_time_ms' => $responseTimeMs,
            'page_load_time_ms' => $pageLoadTimeMs,
            'is_bot' => $deviceInfo['is_bot'] ?? false,
            'viewed_at' => now(),
        ]);
    }

    /**
     * Get the most popular pages within a date range.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getPopularPages(int $limit = 10, $from = null, $to = null): Collection
    {
        return static::query()
            ->select('path', \DB::raw('COUNT(*) as views'))
            ->excludeBots()
            ->dateRange($from, $to)
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    /**
     * Get page view statistics for a date range.
     *
     * @since 1.3.0
     *
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return array
     */
    public static function getPageViewStats($from = null, $to = null): array
    {
        $query = static::query()
            ->excludeBots()
            ->dateRange($from, $to);

        $totalViews = $query->count();
        $uniqueUsers = $query->whereNotNull('user_id')->distinct('user_id')->count();
        $uniqueSessions = $query->whereNotNull('session_hash')->distinct('session_hash')->count();

        $avgResponseTime = $query->whereNotNull('response_time_ms')->avg('response_time_ms');
        $avgPageLoadTime = $query->whereNotNull('page_load_time_ms')->avg('page_load_time_ms');

        return [
            'total_views' => $totalViews,
            'unique_users' => $uniqueUsers,
            'unique_sessions' => $uniqueSessions,
            'avg_response_time_ms' => $avgResponseTime ? round($avgResponseTime, 2) : null,
            'avg_page_load_time_ms' => $avgPageLoadTime ? round($avgPageLoadTime, 2) : null,
        ];
    }

    /**
     * Get device type breakdown within a date range.
     *
     * @since 1.3.0
     *
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getDeviceBreakdown($from = null, $to = null): Collection
    {
        return static::query()
            ->select('device_type', \DB::raw('COUNT(*) as views'))
            ->excludeBots()
            ->dateRange($from, $to)
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->orderByDesc('views')
            ->get();
    }

    /**
     * Get page view trends over time.
     *
     * @since 1.3.0
     *
     * @param int $days
     * @param Carbon|null $endDate
     * @return Collection
     */
    public static function getPageViewTrends(int $days = 30, ?Carbon $endDate = null): Collection
    {
        $endDate = $endDate ?: now();
        $startDate = $endDate->copy()->subDays($days);

        return static::query()
            ->select(\DB::raw('DATE(viewed_at) as date'), \DB::raw('COUNT(*) as views'))
            ->excludeBots()
            ->dateRange($startDate, $endDate)
            ->groupBy(\DB::raw('DATE(viewed_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Clean up old analytics data based on retention policy.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays
     * @return int Number of deleted records
     */
    public static function cleanup(int $retentionDays = 365): int
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        return static::query()
            ->where('viewed_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get referrer statistics within a date range.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getReferrerStats(int $limit = 10, $from = null, $to = null): Collection
    {
        return static::query()
            ->select('referrer_hash', \DB::raw('COUNT(*) as views'))
            ->excludeBots()
            ->dateRange($from, $to)
            ->whereNotNull('referrer_hash')
            ->groupBy('referrer_hash')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $item->referrer_type = 'External'; // Since we hash referrers, we can only categorize as external
                return $item;
            });
    }
}