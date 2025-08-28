<?php

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * UserSessionAnalytics Model.
 *
 * Tracks user session analytics data for the ArtisanPack UI CMS Framework.
 * Used for understanding user engagement patterns, session duration, and behavior
 * while maintaining privacy compliance through data anonymization.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.3.0
 *
 * @property int                        $id
 * @property string                     $session_hash       Hashed session identifier
 * @property int|null                   $user_id            User associated with session (if authenticated)
 * @property string|null                $ip_address_hash    Hashed IP address for privacy
 * @property string|null                $user_agent_hash    Hashed user agent for privacy
 * @property string|null                $country_code       Country code from IP geolocation
 * @property string|null                $device_type        Device type (mobile, tablet, desktop)
 * @property string|null                $browser_family     Browser family (Chrome, Firefox, Safari, etc.)
 * @property string|null                $os_family          Operating system family
 * @property string|null                $landing_page       First page visited in session
 * @property string|null                $exit_page          Last page visited in session
 * @property int                        $page_views         Total page views in session
 * @property int|null                   $duration_seconds   Session duration in seconds
 * @property bool                       $is_bounce          Whether session was a bounce (single page view)
 * @property bool                       $is_bot             Whether the session appears to be from a bot
 * @property Carbon                     $session_started_at When the session started
 * @property Carbon|null                $session_ended_at   When the session ended
 * @property Carbon                     $created_at
 * @property Carbon                     $updated_at
 */
class UserSessionAnalytics extends Model
{
    /**
     * The table associated with the model.
     *
     * @since 1.3.0
     *
     * @var string
     */
    protected $table = 'user_session_analytics';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.3.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'session_hash',
        'user_id',
        'ip_address_hash',
        'user_agent_hash',
        'country_code',
        'device_type',
        'browser_family',
        'os_family',
        'landing_page',
        'exit_page',
        'page_views',
        'duration_seconds',
        'is_bounce',
        'is_bot',
        'session_started_at',
        'session_ended_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.3.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_bounce' => 'boolean',
        'is_bot' => 'boolean',
        'session_started_at' => 'datetime',
        'session_ended_at' => 'datetime',
    ];

    /**
     * Get the user associated with the session.
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
            $query->where('session_started_at', '>=', $from);
        }

        if ($to) {
            $query->where('session_started_at', '<=', $to);
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
     * Scope a query to get bounce sessions only.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeBounces(Builder $query): Builder
    {
        return $query->where('is_bounce', true);
    }

    /**
     * Start or update a session with privacy-compliant data anonymization.
     *
     * @since 1.3.0
     *
     * @param string $sessionId
     * @param string $landingPage
     * @param int|null $userId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param array $deviceInfo
     * @return static
     */
    public static function startSession(
        string $sessionId,
        string $landingPage,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $deviceInfo = []
    ): static {
        $sessionHash = hash('sha256', $sessionId);
        
        return static::updateOrCreate(
            ['session_hash' => $sessionHash],
            [
                'user_id' => $userId,
                'ip_address_hash' => $ipAddress ? hash('sha256', $ipAddress . config('app.key')) : null,
                'user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
                'country_code' => $deviceInfo['country_code'] ?? null,
                'device_type' => $deviceInfo['device_type'] ?? null,
                'browser_family' => $deviceInfo['browser_family'] ?? null,
                'os_family' => $deviceInfo['os_family'] ?? null,
                'landing_page' => $landingPage,
                'page_views' => 1,
                'is_bot' => $deviceInfo['is_bot'] ?? false,
                'session_started_at' => now(),
            ]
        );
    }

    /**
     * Update session with page view and potentially end session.
     *
     * @since 1.3.0
     *
     * @param string $sessionId
     * @param string $currentPage
     * @param bool $endSession
     * @return static|null
     */
    public static function updateSession(
        string $sessionId,
        string $currentPage,
        bool $endSession = false
    ): ?static {
        $sessionHash = hash('sha256', $sessionId);
        $session = static::where('session_hash', $sessionHash)->first();

        if (!$session) {
            return null;
        }

        $session->page_views = $session->page_views + 1;
        $session->exit_page = $currentPage;
        $session->is_bounce = ($session->page_views <= 1);

        if ($endSession) {
            $session->session_ended_at = now();
            $session->duration_seconds = $session->session_started_at
                ->diffInSeconds($session->session_ended_at);
        }

        $session->save();

        return $session;
    }

    /**
     * End a session and calculate final metrics.
     *
     * @since 1.3.0
     *
     * @param string $sessionId
     * @param string|null $exitPage
     * @return static|null
     */
    public static function endSession(string $sessionId, ?string $exitPage = null): ?static
    {
        $sessionHash = hash('sha256', $sessionId);
        $session = static::where('session_hash', $sessionHash)->first();

        if (!$session || $session->session_ended_at) {
            return $session;
        }

        $session->session_ended_at = now();
        $session->duration_seconds = $session->session_started_at
            ->diffInSeconds($session->session_ended_at);
        
        if ($exitPage) {
            $session->exit_page = $exitPage;
        }

        $session->is_bounce = ($session->page_views <= 1);
        $session->save();

        return $session;
    }

    /**
     * Get session statistics for a date range.
     *
     * @since 1.3.0
     *
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return array
     */
    public static function getSessionStats($from = null, $to = null): array
    {
        $query = static::query()
            ->excludeBots()
            ->dateRange($from, $to);

        $totalSessions = $query->count();
        $bounceSessions = $query->clone()->bounces()->count();
        $uniqueUsers = $query->whereNotNull('user_id')->distinct('user_id')->count();
        
        $avgDuration = $query->whereNotNull('duration_seconds')->avg('duration_seconds');
        $avgPageViews = $query->avg('page_views');

        return [
            'total_sessions' => $totalSessions,
            'bounce_sessions' => $bounceSessions,
            'bounce_rate' => $totalSessions > 0 ? round(($bounceSessions / $totalSessions) * 100, 2) : 0,
            'unique_users' => $uniqueUsers,
            'avg_duration_seconds' => $avgDuration ? round($avgDuration, 2) : null,
            'avg_duration_minutes' => $avgDuration ? round($avgDuration / 60, 2) : null,
            'avg_page_views' => $avgPageViews ? round($avgPageViews, 2) : null,
        ];
    }

    /**
     * Get device type breakdown for sessions.
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
            ->select('device_type', \DB::raw('COUNT(*) as sessions'))
            ->excludeBots()
            ->dateRange($from, $to)
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->orderByDesc('sessions')
            ->get();
    }

    /**
     * Get session duration distribution.
     *
     * @since 1.3.0
     *
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getSessionDurationDistribution($from = null, $to = null): Collection
    {
        return static::query()
            ->select(
                \DB::raw('
                    CASE 
                        WHEN duration_seconds < 30 THEN "0-30s"
                        WHEN duration_seconds < 60 THEN "30-60s"
                        WHEN duration_seconds < 300 THEN "1-5m"
                        WHEN duration_seconds < 600 THEN "5-10m"
                        WHEN duration_seconds < 1800 THEN "10-30m"
                        ELSE "30m+"
                    END as duration_range
                '),
                \DB::raw('COUNT(*) as sessions')
            )
            ->excludeBots()
            ->dateRange($from, $to)
            ->whereNotNull('duration_seconds')
            ->groupBy('duration_range')
            ->orderBy('duration_range')
            ->get();
    }

    /**
     * Get popular landing pages.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getPopularLandingPages(int $limit = 10, $from = null, $to = null): Collection
    {
        return static::query()
            ->select('landing_page', \DB::raw('COUNT(*) as sessions'))
            ->excludeBots()
            ->dateRange($from, $to)
            ->whereNotNull('landing_page')
            ->groupBy('landing_page')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();
    }

    /**
     * Get popular exit pages.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getPopularExitPages(int $limit = 10, $from = null, $to = null): Collection
    {
        return static::query()
            ->select('exit_page', \DB::raw('COUNT(*) as sessions'))
            ->excludeBots()
            ->dateRange($from, $to)
            ->whereNotNull('exit_page')
            ->groupBy('exit_page')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();
    }

    /**
     * Get session trends over time.
     *
     * @since 1.3.0
     *
     * @param int $days
     * @param Carbon|null $endDate
     * @return Collection
     */
    public static function getSessionTrends(int $days = 30, ?Carbon $endDate = null): Collection
    {
        $endDate = $endDate ?: now();
        $startDate = $endDate->copy()->subDays($days);

        return static::query()
            ->select(
                \DB::raw('DATE(session_started_at) as date'),
                \DB::raw('COUNT(*) as sessions'),
                \DB::raw('AVG(duration_seconds) as avg_duration'),
                \DB::raw('AVG(page_views) as avg_page_views')
            )
            ->excludeBots()
            ->dateRange($startDate, $endDate)
            ->groupBy(\DB::raw('DATE(session_started_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Clean up old session analytics data based on retention policy.
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
            ->where('session_started_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get user engagement metrics.
     *
     * @since 1.3.0
     *
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return array
     */
    public static function getEngagementMetrics($from = null, $to = null): array
    {
        $stats = static::getSessionStats($from, $to);
        
        $longSessions = static::query()
            ->excludeBots()
            ->dateRange($from, $to)
            ->where('duration_seconds', '>', 300) // More than 5 minutes
            ->count();

        $multiPageSessions = static::query()
            ->excludeBots()
            ->dateRange($from, $to)
            ->where('page_views', '>', 1)
            ->count();

        return array_merge($stats, [
            'engaged_sessions' => $longSessions,
            'engagement_rate' => $stats['total_sessions'] > 0 
                ? round(($longSessions / $stats['total_sessions']) * 100, 2) 
                : 0,
            'multi_page_sessions' => $multiPageSessions,
            'multi_page_rate' => $stats['total_sessions'] > 0 
                ? round(($multiPageSessions / $stats['total_sessions']) * 100, 2) 
                : 0,
        ]);
    }
}