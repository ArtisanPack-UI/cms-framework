<?php

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SearchAnalytics Model.
 *
 * Tracks search queries and analytics data for the ArtisanPack UI CMS Framework.
 * Used for understanding search patterns, popular queries, and system performance.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.2.0
 *
 * @property int                        $id
 * @property string                     $query              The search query string
 * @property array|null                 $filters            Applied search filters
 * @property int                        $result_count       Number of search results
 * @property float|null                 $click_through_rate Click-through rate
 * @property int|null                   $user_id            User who performed search
 * @property string|null                $ip_address_hash    Hashed IP address for privacy
 * @property string|null                $user_agent         User agent string
 * @property int|null                   $execution_time_ms  Query execution time in milliseconds
 * @property Carbon                     $searched_at        When the search was performed
 * @property Carbon                     $created_at
 * @property Carbon                     $updated_at
 */
class SearchAnalytics extends Model
{
    /**
     * The table associated with the model.
     *
     * @since 1.2.0
     *
     * @var string
     */
    protected $table = 'search_analytics';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.2.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'query',
        'filters',
        'result_count',
        'click_through_rate',
        'user_id',
        'ip_address_hash',
        'user_agent',
        'execution_time_ms',
        'searched_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.2.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'filters' => 'array',
        'click_through_rate' => 'decimal:4',
        'searched_at' => 'datetime',
    ];

    /**
     * Get the user who performed the search.
     *
     * @since 1.2.0
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
     * @since 1.2.0
     *
     * @param Builder $query
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Builder
     */
    public function scopeDateRange(Builder $query, $from = null, $to = null): Builder
    {
        if ($from) {
            $query->where('searched_at', '>=', $from);
        }

        if ($to) {
            $query->where('searched_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope a query to filter by user.
     *
     * @since 1.2.0
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
     * Scope a query to filter searches with no results.
     *
     * @since 1.2.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeNoResults(Builder $query): Builder
    {
        return $query->where('result_count', 0);
    }

    /**
     * Scope a query to filter searches with results.
     *
     * @since 1.2.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithResults(Builder $query): Builder
    {
        return $query->where('result_count', '>', 0);
    }

    /**
     * Create a new analytics entry for a search query.
     *
     * @since 1.2.0
     *
     * @param string $query The search query
     * @param array $filters Applied filters
     * @param int $resultCount Number of results returned
     * @param int|null $executionTimeMs Query execution time
     * @param int|null $userId User ID (optional)
     * @param string|null $ipAddress IP address (will be hashed)
     * @param string|null $userAgent User agent string
     * @return static
     */
    public static function logSearch(
        string $query,
        array $filters = [],
        int $resultCount = 0,
        ?int $executionTimeMs = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): static {
        return static::create([
            'query' => substr($query, 0, 500), // Limit query length
            'filters' => $filters,
            'result_count' => $resultCount,
            'execution_time_ms' => $executionTimeMs,
            'user_id' => $userId,
            'ip_address_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'searched_at' => now(),
        ]);
    }

    /**
     * Get the most popular search queries within a date range.
     *
     * @since 1.2.0
     *
     * @param int $limit Number of results to return
     * @param Carbon|string|null $from Start date
     * @param Carbon|string|null $to End date
     * @return Collection
     */
    public static function getPopularQueries(
        int $limit = 10,
        $from = null,
        $to = null
    ): Collection {
        return static::query()
            ->dateRange($from, $to)
            ->selectRaw('query, COUNT(*) as search_count, AVG(result_count) as avg_results')
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get queries that returned no results (failed searches).
     *
     * @since 1.2.0
     *
     * @param int $limit Number of results to return
     * @param Carbon|string|null $from Start date
     * @param Carbon|string|null $to End date
     * @return Collection
     */
    public static function getFailedQueries(
        int $limit = 10,
        $from = null,
        $to = null
    ): Collection {
        return static::query()
            ->dateRange($from, $to)
            ->noResults()
            ->selectRaw('query, COUNT(*) as search_count')
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get search performance statistics.
     *
     * @since 1.2.0
     *
     * @param Carbon|string|null $from Start date
     * @param Carbon|string|null $to End date
     * @return array
     */
    public static function getPerformanceStats($from = null, $to = null): array
    {
        $query = static::query()->dateRange($from, $to);

        $stats = $query
            ->selectRaw('
                COUNT(*) as total_searches,
                COUNT(DISTINCT query) as unique_queries,
                AVG(result_count) as avg_results,
                AVG(execution_time_ms) as avg_execution_time,
                COUNT(CASE WHEN result_count = 0 THEN 1 END) as failed_searches
            ')
            ->first();

        $totalSearches = (int) $stats->total_searches;
        $failedSearches = (int) $stats->failed_searches;

        return [
            'total_searches' => $totalSearches,
            'unique_queries' => (int) $stats->unique_queries,
            'avg_results_per_search' => round((float) $stats->avg_results, 2),
            'avg_execution_time_ms' => round((float) $stats->avg_execution_time, 2),
            'failed_searches' => $failedSearches,
            'success_rate' => $totalSearches > 0 
                ? round((($totalSearches - $failedSearches) / $totalSearches) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get search trends over time (daily aggregated data).
     *
     * @since 1.2.0
     *
     * @param int $days Number of days to include
     * @param Carbon|null $endDate End date (defaults to now)
     * @return Collection
     */
    public static function getSearchTrends(int $days = 30, ?Carbon $endDate = null): Collection
    {
        $endDate = $endDate ?: now();
        $startDate = $endDate->copy()->subDays($days);

        return static::query()
            ->dateRange($startDate, $endDate)
            ->selectRaw('
                DATE(searched_at) as search_date,
                COUNT(*) as search_count,
                COUNT(DISTINCT query) as unique_queries,
                AVG(result_count) as avg_results,
                COUNT(CASE WHEN result_count = 0 THEN 1 END) as failed_searches
            ')
            ->groupByRaw('DATE(searched_at)')
            ->orderBy('search_date')
            ->get();
    }

    /**
     * Clean up old analytics data beyond retention period.
     *
     * @since 1.2.0
     *
     * @param int $retentionDays Number of days to retain data
     * @return int Number of records deleted
     */
    public static function cleanup(int $retentionDays = 365): int
    {
        $cutoffDate = now()->subDays($retentionDays);

        return static::where('searched_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get the most active search users.
     *
     * @since 1.2.0
     *
     * @param int $limit Number of results to return
     * @param Carbon|string|null $from Start date
     * @param Carbon|string|null $to End date
     * @return Collection
     */
    public static function getTopSearchUsers(
        int $limit = 10,
        $from = null,
        $to = null
    ): Collection {
        return static::query()
            ->dateRange($from, $to)
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->selectRaw('user_id, COUNT(*) as search_count, COUNT(DISTINCT query) as unique_queries')
            ->groupBy('user_id')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }
}