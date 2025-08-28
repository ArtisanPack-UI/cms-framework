<?php

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * PerformanceTransaction Model.
 *
 * Represents a performance transaction record in the APM system.
 * Used for tracking complete request/response cycles with detailed
 * performance metrics like duration, memory usage, and database queries.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.3.0
 *
 * @property int                        $id
 * @property string                     $transaction_id     Unique transaction identifier
 * @property string                     $transaction_name   Name of the transaction
 * @property float|null                 $duration_ms        Duration in milliseconds
 * @property float|null                 $memory_usage_mb    Memory usage in MB
 * @property int                        $db_query_count     Number of database queries
 * @property float                      $db_query_time_ms   Total database query time
 * @property int|null                   $http_status_code   HTTP response status code
 * @property int|null                   $user_id            User who initiated transaction
 * @property string|null                $request_path       Request URL path
 * @property string|null                $request_method     HTTP request method
 * @property Carbon                     $started_at         When transaction started
 * @property Carbon|null                $completed_at       When transaction completed
 * @property array|null                 $metadata           Additional transaction metadata
 * @property Carbon                     $created_at
 * @property Carbon                     $updated_at
 */
class PerformanceTransaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @since 1.3.0
     *
     * @var string
     */
    protected $table = 'performance_transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.3.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'transaction_name',
        'duration_ms',
        'memory_usage_mb',
        'db_query_count',
        'db_query_time_ms',
        'http_status_code',
        'user_id',
        'request_path',
        'request_method',
        'started_at',
        'completed_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.3.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration_ms' => 'decimal:2',
        'memory_usage_mb' => 'decimal:2',
        'db_query_time_ms' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that initiated the transaction.
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
     * Scope a query to filter by transaction name.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param string $transactionName
     * @return Builder
     */
    public function scopeByName(Builder $query, string $transactionName): Builder
    {
        return $query->where('transaction_name', $transactionName);
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
            $query->where('started_at', '>=', $from);
        }

        if ($to) {
            $query->where('started_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope a query to filter by HTTP status code.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param int $statusCode
     * @return Builder
     */
    public function scopeByStatus(Builder $query, int $statusCode): Builder
    {
        return $query->where('http_status_code', $statusCode);
    }

    /**
     * Scope a query to filter by status range.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param string $range (2xx, 3xx, 4xx, 5xx)
     * @return Builder
     */
    public function scopeByStatusRange(Builder $query, string $range): Builder
    {
        $start = (int) substr($range, 0, 1) * 100;
        $end = $start + 99;

        return $query->whereBetween('http_status_code', [$start, $end]);
    }

    /**
     * Scope a query to filter slow transactions.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param float $threshold Duration threshold in milliseconds
     * @return Builder
     */
    public function scopeSlow(Builder $query, float $threshold = 1000.0): Builder
    {
        return $query->where('duration_ms', '>', $threshold);
    }

    /**
     * Scope a query to filter completed transactions.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Check if the transaction is completed.
     *
     * @since 1.3.0
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Check if the transaction is slow based on threshold.
     *
     * @since 1.3.0
     *
     * @param float $threshold Duration threshold in milliseconds
     * @return bool
     */
    public function isSlow(float $threshold = 1000.0): bool
    {
        return $this->duration_ms !== null && $this->duration_ms > $threshold;
    }

    /**
     * Get transaction performance statistics.
     *
     * @since 1.3.0
     *
     * @param string|null $transactionName
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return array
     */
    public static function getPerformanceStats(?string $transactionName = null, $from = null, $to = null): array
    {
        $query = static::completed();

        if ($transactionName) {
            $query = $query->byName($transactionName);
        }

        $stats = $query->dateRange($from, $to)
            ->selectRaw('
                COUNT(*) as total_count,
                AVG(duration_ms) as avg_duration,
                MIN(duration_ms) as min_duration,
                MAX(duration_ms) as max_duration,
                AVG(memory_usage_mb) as avg_memory,
                AVG(db_query_count) as avg_db_queries,
                AVG(db_query_time_ms) as avg_db_time,
                COUNT(CASE WHEN http_status_code >= 400 THEN 1 END) as error_count,
                COUNT(CASE WHEN duration_ms > 1000 THEN 1 END) as slow_count
            ')
            ->first();

        return [
            'total_count' => (int) $stats->total_count,
            'avg_duration_ms' => round((float) $stats->avg_duration, 2),
            'min_duration_ms' => (float) $stats->min_duration,
            'max_duration_ms' => (float) $stats->max_duration,
            'avg_memory_mb' => round((float) $stats->avg_memory, 2),
            'avg_db_queries' => round((float) $stats->avg_db_queries, 2),
            'avg_db_time_ms' => round((float) $stats->avg_db_time, 2),
            'error_count' => (int) $stats->error_count,
            'slow_count' => (int) $stats->slow_count,
            'error_rate' => $stats->total_count > 0 
                ? round(($stats->error_count / $stats->total_count) * 100, 2)
                : 0.0,
            'slow_rate' => $stats->total_count > 0 
                ? round(($stats->slow_count / $stats->total_count) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * Get top slowest transactions.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getSlowestTransactions(int $limit = 10, $from = null, $to = null): Collection
    {
        return static::completed()
            ->dateRange($from, $to)
            ->orderByDesc('duration_ms')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transactions by endpoint performance.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getEndpointPerformance(int $limit = 20, $from = null, $to = null): Collection
    {
        return static::completed()
            ->dateRange($from, $to)
            ->selectRaw('
                transaction_name,
                COUNT(*) as request_count,
                AVG(duration_ms) as avg_duration,
                MIN(duration_ms) as min_duration,
                MAX(duration_ms) as max_duration,
                COUNT(CASE WHEN http_status_code >= 400 THEN 1 END) as error_count,
                COUNT(CASE WHEN duration_ms > 1000 THEN 1 END) as slow_count
            ')
            ->groupBy('transaction_name')
            ->orderByDesc('request_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'transaction_name' => $item->transaction_name,
                    'request_count' => (int) $item->request_count,
                    'avg_duration_ms' => round((float) $item->avg_duration, 2),
                    'min_duration_ms' => (float) $item->min_duration,
                    'max_duration_ms' => (float) $item->max_duration,
                    'error_count' => (int) $item->error_count,
                    'slow_count' => (int) $item->slow_count,
                    'error_rate' => $item->request_count > 0 
                        ? round(($item->error_count / $item->request_count) * 100, 2)
                        : 0.0,
                    'slow_rate' => $item->request_count > 0 
                        ? round(($item->slow_count / $item->request_count) * 100, 2)
                        : 0.0,
                ];
            });
    }

    /**
     * Get time series data for transaction performance.
     *
     * @since 1.3.0
     *
     * @param string $interval Interval (hour, day, week)
     * @param int $periods Number of periods to include
     * @param string|null $transactionName
     * @return Collection
     */
    public static function getTimeSeries(string $interval = 'hour', int $periods = 24, ?string $transactionName = null): Collection
    {
        $dateFormat = match ($interval) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            default => '%Y-%m-%d %H:00:00',
        };

        $startTime = match ($interval) {
            'hour' => now()->subHours($periods),
            'day' => now()->subDays($periods),
            'week' => now()->subWeeks($periods),
            default => now()->subHours($periods),
        };

        $query = static::completed()
            ->where('started_at', '>=', $startTime);

        if ($transactionName) {
            $query = $query->byName($transactionName);
        }

        return $query
            ->selectRaw("
                DATE_FORMAT(started_at, '{$dateFormat}') as period,
                COUNT(*) as request_count,
                AVG(duration_ms) as avg_duration,
                COUNT(CASE WHEN http_status_code >= 400 THEN 1 END) as error_count
            ")
            ->groupByRaw("DATE_FORMAT(started_at, '{$dateFormat}')")
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'request_count' => (int) $item->request_count,
                    'avg_duration_ms' => round((float) $item->avg_duration, 2),
                    'error_count' => (int) $item->error_count,
                    'error_rate' => $item->request_count > 0 
                        ? round(($item->error_count / $item->request_count) * 100, 2)
                        : 0.0,
                ];
            });
    }

    /**
     * Clean up old transaction records.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays Number of days to retain data
     * @return int Number of records deleted
     */
    public static function cleanup(int $retentionDays = 90): int
    {
        $cutoffDate = now()->subDays($retentionDays);

        return static::where('started_at', '<', $cutoffDate)->delete();
    }
}