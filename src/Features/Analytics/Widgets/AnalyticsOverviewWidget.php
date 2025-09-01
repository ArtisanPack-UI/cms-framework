<?php

namespace ArtisanPackUI\CMSFramework\Features\Analytics\Widgets;

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;
use ArtisanPackUI\CMSFramework\Contracts\AnalyticsManagerInterface;
use Illuminate\Support\Carbon;

/**
 * Analytics Overview Widget
 *
 * Provides a comprehensive overview of analytics data for the ArtisanPack UI
 * CMS Framework dashboard, including page views, sessions, popular pages,
 * and device breakdown statistics.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Analytics\Widgets
 * @since      1.3.0
 */
class AnalyticsOverviewWidget extends DashboardWidget
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
     * Initialize the widget.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function init(): void
    {
        $this->analytics = app(AnalyticsManagerInterface::class);
    }

    /**
     * Get the widget type.
     *
     * @since 1.3.0
     *
     * @return string
     */
    public function getType(): string
    {
        return 'analytics-overview';
    }

    /**
     * Get the widget name.
     *
     * @since 1.3.0
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Analytics Overview';
    }

    /**
     * Get the widget slug.
     *
     * @since 1.3.0
     *
     * @return string
     */
    public function getSlug(): string
    {
        return 'analytics-overview';
    }

    /**
     * Get the widget description.
     *
     * @since 1.3.0
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return 'Comprehensive overview of website analytics including page views, sessions, and popular pages.';
    }

    /**
     * Render the widget.
     *
     * @since 1.3.0
     *
     * @param string $instanceId Widget instance identifier
     * @param array $data Widget configuration data
     * @return string Rendered widget HTML
     */
    public function render(string $instanceId, array $data): string
    {
        if (!config('artisanpack-cms.analytics.enabled', true) || 
            !config('artisanpack-cms.analytics.dashboard.enabled', true)) {
            return $this->renderDisabled();
        }

        try {
            $settings = $this->getSettings($instanceId, null, []);
            $dateRange = $this->getDateRange($settings);
            
            $analyticsData = $this->getAnalyticsData($dateRange['from'], $dateRange['to']);
            
            return $this->renderWidget($instanceId, $analyticsData, $dateRange, $settings);
            
        } catch (\Exception $e) {
            return $this->renderError($e->getMessage());
        }
    }

    /**
     * Get analytics data for the specified date range.
     *
     * @since 1.3.0
     *
     * @param Carbon|null $from Start date
     * @param Carbon|null $to End date
     * @return array Analytics data
     */
    protected function getAnalyticsData(?Carbon $from, ?Carbon $to): array
    {
        return [
            'page_views' => $this->analytics->getPageViewStats($from, $to),
            'sessions' => $this->analytics->getSessionStats($from, $to),
            'engagement' => $this->analytics->getEngagementMetrics($from, $to),
            'popular_pages' => $this->analytics->getPopularPages(10, $from, $to),
            'device_breakdown' => $this->analytics->getDeviceBreakdown($from, $to),
            'page_view_trends' => $this->analytics->getPageViewTrends(30, $to),
            'session_trends' => $this->analytics->getSessionTrends(30, $to),
        ];
    }

    /**
     * Get date range from settings.
     *
     * @since 1.3.0
     *
     * @param array $settings Widget settings
     * @return array Date range with 'from', 'to', and 'label' keys
     */
    protected function getDateRange(array $settings): array
    {
        $range = $settings['date_range'] ?? config('artisanpack-cms.analytics.dashboard.default_date_range', 30);
        $rangeDays = (int) $range;
        
        $to = now();
        $from = $to->copy()->subDays($rangeDays);
        
        $labels = [
            7 => 'Last 7 days',
            30 => 'Last 30 days',
            90 => 'Last 90 days',
            365 => 'Last year',
        ];
        
        return [
            'from' => $from,
            'to' => $to,
            'label' => $labels[$rangeDays] ?? "Last {$rangeDays} days",
            'days' => $rangeDays,
        ];
    }

    /**
     * Render the main widget content.
     *
     * @since 1.3.0
     *
     * @param string $instanceId Widget instance ID
     * @param array $data Analytics data
     * @param array $dateRange Date range information
     * @param array $settings Widget settings
     * @return string Widget HTML
     */
    protected function renderWidget(string $instanceId, array $data, array $dateRange, array $settings): string
    {
        $cacheKey = "analytics_overview_{$instanceId}_" . md5(serialize([$data, $dateRange, $settings]));
        $cacheDuration = config('artisanpack-cms.analytics.dashboard.cache_duration', 60);
        
        if ($cacheDuration > 0 && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $html = $this->buildWidgetHtml($instanceId, $data, $dateRange, $settings);
        
        if ($cacheDuration > 0) {
            cache()->put($cacheKey, $html, now()->addMinutes($cacheDuration));
        }
        
        return $html;
    }

    /**
     * Build the widget HTML content.
     *
     * @since 1.3.0
     *
     * @param string $instanceId Widget instance ID
     * @param array $data Analytics data
     * @param array $dateRange Date range information
     * @param array $settings Widget settings
     * @return string Widget HTML
     */
    protected function buildWidgetHtml(string $instanceId, array $data, array $dateRange, array $settings): string
    {
        ob_start();
        ?>
        <div class="analytics-overview-widget bg-white rounded-lg shadow-sm border p-6">
            <!-- Widget Header -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">
                    <?= $this->getName() ?>
                </h3>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500"><?= htmlspecialchars($dateRange['label']) ?></span>
                    <select class="analytics-date-range-selector text-sm border-gray-300 rounded-md" 
                            data-instance="<?= htmlspecialchars($instanceId) ?>">
                        <option value="7" <?= $dateRange['days'] === 7 ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="30" <?= $dateRange['days'] === 30 ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="90" <?= $dateRange['days'] === 90 ? 'selected' : '' ?>>Last 90 days</option>
                        <option value="365" <?= $dateRange['days'] === 365 ? 'selected' : '' ?>>Last year</option>
                    </select>
                </div>
            </div>

            <!-- Key Metrics Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <?= $this->renderMetricCard('Page Views', $data['page_views']['total_views'], 'eye') ?>
                <?= $this->renderMetricCard('Unique Sessions', $data['sessions']['total_sessions'], 'users') ?>
                <?= $this->renderMetricCard('Bounce Rate', $data['sessions']['bounce_rate'] . '%', 'trending-down') ?>
                <?= $this->renderMetricCard('Avg. Session Duration', $this->formatDuration($data['sessions']['avg_duration_seconds']), 'clock') ?>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Page Views Trend -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-3">Page Views Trend</h4>
                    <div class="analytics-chart" data-type="line" data-data="<?= htmlspecialchars(json_encode($data['page_view_trends'])) ?>">
                        <?= $this->renderTrendChart($data['page_view_trends'], 'views') ?>
                    </div>
                </div>

                <!-- Device Breakdown -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-3">Device Types</h4>
                    <div class="analytics-chart" data-type="pie" data-data="<?= htmlspecialchars(json_encode($data['device_breakdown'])) ?>">
                        <?= $this->renderDeviceBreakdown($data['device_breakdown']) ?>
                    </div>
                </div>
            </div>

            <!-- Popular Pages -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Popular Pages</h4>
                <?= $this->renderPopularPages($data['popular_pages']) ?>
            </div>

            <!-- Engagement Metrics -->
            <?php if (!empty($data['engagement'])): ?>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Engagement Metrics</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?= $this->renderMetricCard('Engaged Sessions', $data['engagement']['engaged_sessions'], 'heart') ?>
                    <?= $this->renderMetricCard('Engagement Rate', $data['engagement']['engagement_rate'] . '%', 'trending-up') ?>
                    <?= $this->renderMetricCard('Multi-page Sessions', $data['engagement']['multi_page_sessions'], 'document-duplicate') ?>
                    <?= $this->renderMetricCard('Multi-page Rate', $data['engagement']['multi_page_rate'] . '%', 'collection') ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <style>
        .analytics-overview-widget .metric-card {
            @apply bg-white rounded-lg border p-4 text-center;
        }
        .analytics-overview-widget .metric-value {
            @apply text-2xl font-bold text-gray-900;
        }
        .analytics-overview-widget .metric-label {
            @apply text-sm text-gray-500 mt-1;
        }
        .analytics-overview-widget .popular-pages-list {
            @apply space-y-2;
        }
        .analytics-overview-widget .popular-page-item {
            @apply flex justify-between items-center py-2 px-3 bg-white rounded border;
        }
        .analytics-overview-widget .page-path {
            @apply font-medium text-gray-900 truncate flex-1;
        }
        .analytics-overview-widget .page-views {
            @apply text-sm text-gray-500 ml-2;
        }
        .analytics-overview-widget .device-item {
            @apply flex justify-between items-center py-1;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render a metric card.
     *
     * @since 1.3.0
     *
     * @param string $label Metric label
     * @param string|int $value Metric value
     * @param string $icon Icon name
     * @return string Metric card HTML
     */
    protected function renderMetricCard(string $label, $value, string $icon): string
    {
        return sprintf(
            '<div class="metric-card">
                <div class="metric-value">%s</div>
                <div class="metric-label">%s</div>
            </div>',
            htmlspecialchars((string) $value),
            htmlspecialchars($label)
        );
    }

    /**
     * Render popular pages list.
     *
     * @since 1.3.0
     *
     * @param \Illuminate\Support\Collection $pages Popular pages data
     * @return string Popular pages HTML
     */
    protected function renderPopularPages($pages): string
    {
        if ($pages->isEmpty()) {
            return '<p class="text-gray-500 text-sm">No page view data available.</p>';
        }

        $html = '<div class="popular-pages-list">';
        
        foreach ($pages->take(5) as $page) {
            $html .= sprintf(
                '<div class="popular-page-item">
                    <span class="page-path">%s</span>
                    <span class="page-views">%s views</span>
                </div>',
                htmlspecialchars($page->path),
                number_format($page->views)
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render device breakdown.
     *
     * @since 1.3.0
     *
     * @param \Illuminate\Support\Collection $devices Device breakdown data
     * @return string Device breakdown HTML
     */
    protected function renderDeviceBreakdown($devices): string
    {
        if ($devices->isEmpty()) {
            return '<p class="text-gray-500 text-sm">No device data available.</p>';
        }

        $html = '<div class="space-y-2">';
        $total = $devices->sum('views');
        
        foreach ($devices as $device) {
            $percentage = $total > 0 ? round(($device->views / $total) * 100, 1) : 0;
            $html .= sprintf(
                '<div class="device-item">
                    <span class="font-medium">%s</span>
                    <span class="text-sm text-gray-500">%s%% (%s)</span>
                </div>',
                htmlspecialchars(ucfirst($device->device_type ?: 'Unknown')),
                $percentage,
                number_format($device->views)
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render a simple trend chart.
     *
     * @since 1.3.0
     *
     * @param \Illuminate\Support\Collection $trends Trend data
     * @param string $metric Metric name
     * @return string Chart HTML
     */
    protected function renderTrendChart($trends, string $metric): string
    {
        if ($trends->isEmpty()) {
            return '<p class="text-gray-500 text-sm">No trend data available.</p>';
        }

        // Simple ASCII-style chart for basic display
        $max = $trends->max($metric);
        $html = '<div class="text-xs text-gray-600">';
        
        foreach ($trends->take(7) as $trend) { // Show last 7 data points
            $height = $max > 0 ? (int)(($trend->{$metric} / $max) * 20) : 0;
            $html .= sprintf(
                '<div class="flex items-end space-x-1 mb-1">
                    <div class="w-8 text-right">%s</div>
                    <div class="flex-1 bg-blue-200" style="height: %dpx;"></div>
                    <div class="w-16">%s</div>
                </div>',
                date('m/d', strtotime($trend->date)),
                max(2, $height),
                number_format($trend->{$metric})
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Format duration in seconds to human readable format.
     *
     * @since 1.3.0
     *
     * @param float|null $seconds Duration in seconds
     * @return string Formatted duration
     */
    protected function formatDuration(?float $seconds): string
    {
        if (!$seconds) {
            return '0s';
        }

        if ($seconds < 60) {
            return round($seconds) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . 'm ' . round($remainingSeconds) . 's';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    /**
     * Render disabled state.
     *
     * @since 1.3.0
     *
     * @return string Disabled widget HTML
     */
    protected function renderDisabled(): string
    {
        return '<div class="bg-gray-100 rounded-lg p-6 text-center">
            <p class="text-gray-500">Analytics dashboard is disabled.</p>
        </div>';
    }

    /**
     * Render error state.
     *
     * @since 1.3.0
     *
     * @param string $message Error message
     * @return string Error widget HTML
     */
    protected function renderError(string $message): string
    {
        return sprintf(
            '<div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <p class="text-red-600">Analytics Error: %s</p>
            </div>',
            htmlspecialchars($message)
        );
    }

    /**
     * Define the widget (called during registration).
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function define(): void
    {
        // Widget is defined by the class itself
    }
}