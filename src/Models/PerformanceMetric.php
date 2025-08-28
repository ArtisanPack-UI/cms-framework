<?php

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * PerformanceMetric Model.
 *
 * Represents a performance metric record in the APM system.
 * Used for storing and querying custom metrics like response times,
 * memory usage, and other performance indicators.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.3.0
 *
 * @property int                        $id
 * @property string                     $metric_name        Name of the metric
 * @property float                      $metric_value       Metric value
 * @property string                     $metric_unit        Unit of measurement (ms, mb, etc.)
 * @property array|null                 $tags               Metric tags for categorization
 * @property Carbon                     $recorded_at        When the metric was recorded
 * @property Carbon                     $created_at
 * @property Carbon                     $updated_at
 */
class PerformanceMetric extends Model
{
    /**
     * The table associated with the model.
     *
     * @since 1.3.0
     *
     * @var string
     */
    protected $table = 'performance_metrics';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.3.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'metric_name',
        'metric_value',
        'metric_unit',
        'tags',
        'recorded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.3.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metric_value' => 'decimal:4',
        'tags' => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * Scope a query to filter by metric name.
     *
     * @since 1.3.0
     *
     * @param Builder $query
     * @param string $metricName
     * @return Builder
     */
    public function scopeByMetric(Builder $query, string $metricName): Builder
    {
        return $query->where('metric_name', $metricName);
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
            $query->where('recorded_at', '>=', $from);
        }

        if ($to) {
            $query->where('recorded_at', '<=', $to);
        }

        return $query;
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
     * Get average metric value for a given time period.
     *
     * @since 1.3.0
     *
     * @param string $metricName
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return float
     */
    public static function getAverageValue(string $metricName, $from = null, $to = null): float
    {
        return (float) static::byMetric($metricName)
            ->dateRange($from, $to)
            ->avg('metric_value') ?? 0.0;
    }

    /**
     * Get metric percentiles for a given time period.
     *
     * @since 1.3.0
     *
     * @param string $metricName
     * @param array $percentiles Percentiles to calculate (e.g., [50, 95, 99])
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return array
     */
    public static function getPercentiles(string $metricName, array $percentiles, $from = null, $to = null): array
    {
        $values = static::byMetric($metricName)
            ->dateRange($from, $to)
            ->orderBy('metric_value')
            ->pluck('metric_value')
            ->toArray();

        if (empty($values)) {
            return array_fill_keys($percentiles, 0.0);
        }

        $count = count($values);
        $result = [];

        foreach ($percentiles as $percentile) {
            $index = ($percentile / 100) * ($count - 1);
            $lower = (int) floor($index);
            $upper = (int) ceil($index);
            $weight = $index - $lower;

            $result[$percentile] = $values[$lower] + $weight * ($values[$upper] - $values[$lower]);
        }

        return $result;
    }

    /**
     * Get metric statistics for a given time period.
     *
     * @since 1.3.0
     *
     * @param string $metricName
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return array
     */
    public static function getStatistics(string $metricName, $from = null, $to = null): array
    {
        $metrics = static::byMetric($metricName)
            ->dateRange($from, $to)
            ->selectRaw('
                COUNT(*) as count,
                AVG(metric_value) as avg,
                MIN(metric_value) as min,
                MAX(metric_value) as max,
                STDDEV(metric_value) as stddev
            ')
            ->first();

        return [
            'count' => (int) $metrics->count,
            'avg' => round((float) $metrics->avg, 4),
            'min' => (float) $metrics->min,
            'max' => (float) $metrics->max,
            'stddev' => round((float) ($metrics->stddev ?? 0), 4),
            'percentiles' => static::getPercentiles($metricName, [50, 95, 99], $from, $to),
        ];
    }

    /**
     * Get time series data for a metric.
     *
     * @since 1.3.0
     *
     * @param string $metricName
     * @param string $interval Interval (hour, day, week, month)
     * @param int $periods Number of periods to include
     * @return Collection
     */
    public static function getTimeSeries(string $metricName, string $interval = 'hour', int $periods = 24): Collection
    {
        $dateFormat = match ($interval) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d %H:00:00',
        };

        $startTime = match ($interval) {
            'hour' => now()->subHours($periods),
            'day' => now()->subDays($periods),
            'week' => now()->subWeeks($periods),
            'month' => now()->subMonths($periods),
            default => now()->subHours($periods),
        };

        return static::byMetric($metricName)
            ->where('recorded_at', '>=', $startTime)
            ->selectRaw("
                DATE_FORMAT(recorded_at, '{$dateFormat}') as period,
                COUNT(*) as count,
                AVG(metric_value) as avg_value,
                MIN(metric_value) as min_value,
                MAX(metric_value) as max_value
            ")
            ->groupByRaw("DATE_FORMAT(recorded_at, '{$dateFormat}')")
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'count' => (int) $item->count,
                    'avg_value' => round((float) $item->avg_value, 4),
                    'min_value' => (float) $item->min_value,
                    'max_value' => (float) $item->max_value,
                ];
            });
    }

    /**
     * Clean up old metric records.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays Number of days to retain data
     * @return int Number of records deleted
     */
    public static function cleanup(int $retentionDays = 90): int
    {
        $cutoffDate = now()->subDays($retentionDays);

        return static::where('recorded_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get top metrics by value for a given time period.
     *
     * @since 1.3.0
     *
     * @param string $metricName
     * @param int $limit
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Collection
     */
    public static function getTopValues(string $metricName, int $limit = 10, $from = null, $to = null): Collection
    {
        return static::byMetric($metricName)
            ->dateRange($from, $to)
            ->orderByDesc('metric_value')
            ->limit($limit)
            ->get();
    }
}