<?php

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ErrorLog Model.
 *
 * Represents an error log record in the APM system.
 * Used for tracking errors and exceptions with deduplication,
 * occurrence counting, and resolution management.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.3.0
 *
 * @property int                        $id
 * @property string                     $error_hash         Hash of error signature for deduplication
 * @property string                     $exception_class    Exception class name
 * @property string                     $message            Error message
 * @property string|null                $file               File where error occurred
 * @property int|null                   $line               Line number where error occurred
 * @property string|null                $stack_trace        Full stack trace
 * @property array|null                 $context            Additional error context
 * @property int                        $occurrence_count   Number of times this error occurred
 * @property Carbon                     $first_seen_at      When error was first seen
 * @property Carbon                     $last_seen_at       When error was last seen
 * @property Carbon|null                $resolved_at        When error was resolved
 * @property string                     $severity_level     Error severity (error, warning, critical, etc.)
 * @property int|null                   $user_id            User associated with error
 * @property string|null                $request_url        Request URL when error occurred
 * @property string|null                $request_method     HTTP request method
 * @property string|null                $user_agent         User agent string
 * @property string|null                $ip_address         IP address of requester
 * @property array|null                 $tags               Error tags for categorization
 * @property Carbon                     $created_at
 * @property Carbon                     $updated_at
 */
class ErrorLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @since 1.3.0
     *
     * @var string
     */
    protected $table = 'error_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.3.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'error_hash',
        'exception_class',
        'message',
        'file',
        'line',
        'stack_trace',
        'context',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'severity_level',
        'user_id',
        'request_url',
        'request_method',
        'user_agent',
        'ip_address',
        'tags',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.3.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'tags' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user associated with the error.
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
     * Scope a query to filter by exception class.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param string $exceptionClass
     * @return Builder
     */
    public function scopeByException(Builder $query, string $exceptionClass): Builder
    {
        return $query->where('exception_class', $exceptionClass);
    }

    /**
     * Scope a query to filter by severity level.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param string $severityLevel
     * @return Builder
     */
    public function scopeBySeverity(Builder $query, string $severityLevel): Builder
    {
        return $query->where('severity_level', $severityLevel);
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
            $query->where('last_seen_at', '>=', $from);
        }

        if ($to) {
            $query->where('last_seen_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope a query to filter unresolved errors.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope a query to filter resolved errors.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Scope a query to filter frequent errors.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param int $threshold Minimum occurrence count
     * @return Builder
     */
    public function scopeFrequent(Builder $query, int $threshold = 10): Builder
    {
        return $query->where('occurrence_count', '>=', $threshold);
    }

    /**
     * Scope a query to filter by tags.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param array $tags Key-value pairs to match
     * @return Builder
     */
    public function scopeWithTags(Builder $query, array $tags): Builder
    {
        foreach ($tags as $key => $value) {
            $query->whereJsonContains('tags->' . $key, $value);
        }

        return $query;
    }

    /**
     * Check if the error is resolved.
     *
     * @since 1.3.0
     *
     * @return bool
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Mark the error as resolved.
     *
     * @since 1.3.0
     *
     * @return bool
     */
    public function markAsResolved(): bool
    {
        $this->resolved_at = now();
        return $this->save();
    }

    /**
     * Mark the error as unresolved.
     *
     * @since 1.3.0
     *
     * @return bool
     */
    public function markAsUnresolved(): bool
    {
        $this->resolved_at = null;
        return $this->save();
    }

    /**
     * Increment occurrence count and update last seen timestamp.
     *
     * @since 1.3.0
     *
     * @return bool
     */
    public function incrementOccurrence(): bool
    {
        $this->occurrence_count++;
        $this->last_seen_at = now();
        return $this->save();
    }

    /**
     * Generate error hash for deduplication.
     *
     * @since 1.3.0
     *
     * @param \Throwable $exception
     * @param array $context
     * @return string
     */
    public static function generateErrorHash(\Throwable $exception, array $context = []): string
    {
        $signature = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        return hash('sha256', serialize($signature));
    }

    /**
     * Create or update error log entry.
     *
     * @since 1.3.0
     *
     * @param \Throwable $exception
     * @param array $context
     * @return static
     */
    public static function createOrUpdate(\Throwable $exception, array $context = []): static
    {
        $errorHash = static::generateErrorHash($exception, $context);
        $now = now();

        $errorLog = static::where('error_hash', $errorHash)->first();

        if ($errorLog) {
            // Update existing error
            $errorLog->incrementOccurrence();
            return $errorLog;
        }

        // Create new error log entry
        return static::create([
            'error_hash' => $errorHash,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
            'context' => $context,
            'occurrence_count' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'severity_level' => static::determineSeverityLevel($exception),
            'user_id' => $context['user_id'] ?? null,
            'request_url' => $context['request_url'] ?? null,
            'request_method' => $context['request_method'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'tags' => $context['tags'] ?? null,
        ]);
    }

    /**
     * Determine severity level from exception type.
     *
     * @since 1.3.0
     *
     * @param \Throwable $exception
     * @return string
     */
    protected static function determineSeverityLevel(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \ErrorException => match ($exception->getSeverity()) {
                E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'critical',
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
                E_NOTICE, E_USER_NOTICE => 'notice',
                E_DEPRECATED, E_USER_DEPRECATED => 'deprecated',
                default => 'error',
            },
            $exception instanceof \RuntimeException => 'critical',
            $exception instanceof \LogicException => 'error',
            $exception instanceof \InvalidArgumentException => 'warning',
            default => 'error',
        };
    }

    /**
     * Get error statistics.
     *
     * @since 1.3.0
     *
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return array
     */
    public static function getErrorStats($from = null, $to = null): array
    {
        $stats = static::dateRange($from, $to)
            ->selectRaw('
                COUNT(*) as total_errors,
                SUM(occurrence_count) as total_occurrences,
                COUNT(CASE WHEN resolved_at IS NULL THEN 1 END) as unresolved_count,
                COUNT(CASE WHEN severity_level = "critical" THEN 1 END) as critical_count,
                COUNT(CASE WHEN severity_level = "error" THEN 1 END) as error_count,
                COUNT(CASE WHEN severity_level = "warning" THEN 1 END) as warning_count,
                AVG(occurrence_count) as avg_occurrences
            ')
            ->first();

        return [
            'total_errors' => (int) $stats->total_errors,
            'total_occurrences' => (int) $stats->total_occurrences,
            'unresolved_count' => (int) $stats->unresolved_count,
            'critical_count' => (int) $stats->critical_count,
            'error_count' => (int) $stats->error_count,
            'warning_count' => (int) $stats->warning_count,
            'avg_occurrences' => round((float) $stats->avg_occurrences, 2),
            'resolution_rate' => $stats->total_errors > 0
                ? round((($stats->total_errors - $stats->unresolved_count) / $stats->total_errors) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * Get top errors by occurrence count.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getTopErrors(int $limit = 10, $from = null, $to = null): Collection
    {
        return static::dateRange($from, $to)
            ->orderByDesc('occurrence_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get errors by exception class.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getErrorsByClass(int $limit = 20, $from = null, $to = null): Collection
    {
        return static::dateRange($from, $to)
            ->selectRaw('
                exception_class,
                COUNT(*) as error_count,
                SUM(occurrence_count) as total_occurrences,
                COUNT(CASE WHEN resolved_at IS NULL THEN 1 END) as unresolved_count,
                AVG(occurrence_count) as avg_occurrences
            ')
            ->groupBy('exception_class')
            ->orderByDesc('total_occurrences')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'exception_class' => $item->exception_class,
                    'error_count' => (int) $item->error_count,
                    'total_occurrences' => (int) $item->total_occurrences,
                    'unresolved_count' => (int) $item->unresolved_count,
                    'avg_occurrences' => round((float) $item->avg_occurrences, 2),
                    'resolution_rate' => $item->error_count > 0
                        ? round((($item->error_count - $item->unresolved_count) / $item->error_count) * 100, 2)
                        : 0.0,
                ];
            });
    }

    /**
     * Get time series data for error occurrences.
     *
     * @since 1.3.0
     *
     * @param string $interval Interval (hour, day, week)
     * @param int $periods Number of periods to include
     * @param string|null $severityLevel Filter by severity level
     * @return Collection
     */
    public static function getTimeSeries(string $interval = 'hour', int $periods = 24, ?string $severityLevel = null): Collection
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

        $query = static::where('last_seen_at', '>=', $startTime);

        if ($severityLevel) {
            $query = $query->bySeverity($severityLevel);
        }

        return $query
            ->selectRaw("
                DATE_FORMAT(last_seen_at, '{$dateFormat}') as period,
                COUNT(*) as error_count,
                SUM(occurrence_count) as total_occurrences,
                COUNT(CASE WHEN severity_level = 'critical' THEN 1 END) as critical_count
            ")
            ->groupByRaw("DATE_FORMAT(last_seen_at, '{$dateFormat}')")
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'error_count' => (int) $item->error_count,
                    'total_occurrences' => (int) $item->total_occurrences,
                    'critical_count' => (int) $item->critical_count,
                ];
            });
    }

    /**
     * Clean up old error records.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays Number of days to retain data
     * @param bool $onlyResolved Only delete resolved errors
     * @return int Number of records deleted
     */
    public static function cleanup(int $retentionDays = 90, bool $onlyResolved = true): int
    {
        $cutoffDate = now()->subDays($retentionDays);

        $query = static::where('last_seen_at', '<', $cutoffDate);

        if ($onlyResolved) {
            $query = $query->resolved();
        }

        return $query->delete();
    }

    /**
     * Get recent unresolved errors.
     *
     * @since 1.3.0
     *
     * @param int $limit
     * @param int $hours Hours to look back
     * @return Collection
     */
    public static function getRecentUnresolved(int $limit = 50, int $hours = 24): Collection
    {
        return static::unresolved()
            ->where('last_seen_at', '>=', now()->subHours($hours))
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get();
    }
}