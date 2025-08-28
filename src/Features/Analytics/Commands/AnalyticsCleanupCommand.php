<?php

namespace ArtisanPackUI\CMSFramework\Features\Analytics\Commands;

use ArtisanPackUI\CMSFramework\Contracts\AnalyticsManagerInterface;
use Illuminate\Console\Command;

/**
 * Analytics Cleanup Command
 *
 * Handles automated cleanup of old analytics data based on configured
 * retention policies for the ArtisanPack UI CMS Framework analytics system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Analytics\Commands
 * @since      1.3.0
 */
class AnalyticsCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 1.3.0
     *
     * @var string
     */
    protected $signature = 'analytics:cleanup 
                           {--days= : Number of days to retain data (overrides config)}
                           {--dry-run : Show what would be deleted without actually deleting}
                           {--force : Force cleanup without confirmation}
                           {--batch-size=1000 : Number of records to delete per batch}';

    /**
     * The console command description.
     *
     * @since 1.3.0
     *
     * @var string
     */
    protected $description = 'Clean up old analytics data based on retention policy';

    /**
     * The analytics manager instance.
     *
     * @since 1.3.0
     *
     * @var AnalyticsManagerInterface
     */
    protected AnalyticsManagerInterface $analytics;

    /**
     * Create a new command instance.
     *
     * @since 1.3.0
     *
     * @param AnalyticsManagerInterface $analytics
     */
    public function __construct(AnalyticsManagerInterface $analytics)
    {
        parent::__construct();
        $this->analytics = $analytics;
    }

    /**
     * Execute the console command.
     *
     * @since 1.3.0
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $this->info('Analytics Data Cleanup');
        $this->info('=====================');

        // Get retention days from option or config
        $retentionDays = $this->option('days') 
            ? (int) $this->option('days')
            : config('artisanpack-cms.analytics.retention.retention_days', 365);

        // Validate retention days
        if ($retentionDays <= 0) {
            if ($retentionDays === 0) {
                $this->warn('Data retention is disabled (retention_days = 0). No cleanup will be performed.');
            } else {
                $this->error('Invalid retention days specified. Must be a positive number.');
            }
            return 1;
        }

        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        if ($batchSize <= 0) {
            $batchSize = config('artisanpack-cms.analytics.retention.cleanup_batch_size', 1000);
        }

        // Show configuration
        $this->displayConfiguration($retentionDays, $isDryRun, $batchSize);

        // Check if analytics is enabled
        if (!config('artisanpack-cms.analytics.enabled', true)) {
            $this->warn('Analytics system is disabled. No cleanup will be performed.');
            return 0;
        }

        // Get data counts before cleanup
        $beforeCounts = $this->getDataCounts();
        $this->displayDataCounts('Current Data Counts', $beforeCounts);

        // Calculate what will be deleted
        $cutoffDate = now()->subDays($retentionDays);
        $deleteCounts = $this->getDeleteCounts($cutoffDate);
        
        if ($deleteCounts['page_views'] === 0 && $deleteCounts['sessions'] === 0) {
            $this->info('No data older than ' . $retentionDays . ' days found. Nothing to clean up.');
            return 0;
        }

        $this->displayDataCounts('Data to be deleted (older than ' . $cutoffDate->format('Y-m-d H:i:s') . ')', $deleteCounts);

        // Confirm deletion unless forced or dry run
        if (!$isDryRun && !$this->option('force')) {
            $totalRecords = $deleteCounts['page_views'] + $deleteCounts['sessions'];
            if (!$this->confirm("Are you sure you want to delete {$totalRecords} records?")) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        // Perform cleanup
        if ($isDryRun) {
            $this->info('DRY RUN: No data was actually deleted.');
            return 0;
        }

        $this->info('Starting cleanup...');
        
        $startTime = microtime(true);
        $results = $this->performCleanup($retentionDays);
        $endTime = microtime(true);

        if (isset($results['error'])) {
            $this->error('Cleanup failed: ' . $results['error']);
            return 1;
        }

        // Display results
        $this->displayCleanupResults($results, $endTime - $startTime);

        // Show final data counts
        $afterCounts = $this->getDataCounts();
        $this->displayDataCounts('Final Data Counts', $afterCounts);

        $this->info('Cleanup completed successfully!');
        return 0;
    }

    /**
     * Display the cleanup configuration.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays Number of days to retain
     * @param bool $isDryRun Whether this is a dry run
     * @param int $batchSize Batch size for deletion
     * @return void
     */
    protected function displayConfiguration(int $retentionDays, bool $isDryRun, int $batchSize): void
    {
        $this->info('Configuration:');
        $this->line('  Retention Days: ' . $retentionDays);
        $this->line('  Mode: ' . ($isDryRun ? 'DRY RUN (no data will be deleted)' : 'LIVE'));
        $this->line('  Batch Size: ' . number_format($batchSize));
        $this->line('  Cutoff Date: ' . now()->subDays($retentionDays)->format('Y-m-d H:i:s'));
        $this->newLine();
    }

    /**
     * Get current data counts.
     *
     * @since 1.3.0
     *
     * @return array Data counts
     */
    protected function getDataCounts(): array
    {
        try {
            return [
                'page_views' => \DB::table('page_view_analytics')->count(),
                'sessions' => \DB::table('user_session_analytics')->count(),
            ];
        } catch (\Exception $e) {
            $this->error('Failed to get data counts: ' . $e->getMessage());
            return ['page_views' => 0, 'sessions' => 0];
        }
    }

    /**
     * Get counts of data that will be deleted.
     *
     * @since 1.3.0
     *
     * @param \Carbon\Carbon $cutoffDate Cutoff date for deletion
     * @return array Delete counts
     */
    protected function getDeleteCounts(\Carbon\Carbon $cutoffDate): array
    {
        try {
            return [
                'page_views' => \DB::table('page_view_analytics')
                    ->where('viewed_at', '<', $cutoffDate)
                    ->count(),
                'sessions' => \DB::table('user_session_analytics')
                    ->where('session_started_at', '<', $cutoffDate)
                    ->count(),
            ];
        } catch (\Exception $e) {
            $this->error('Failed to get delete counts: ' . $e->getMessage());
            return ['page_views' => 0, 'sessions' => 0];
        }
    }

    /**
     * Display data counts in a formatted table.
     *
     * @since 1.3.0
     *
     * @param string $title Table title
     * @param array $counts Data counts
     * @return void
     */
    protected function displayDataCounts(string $title, array $counts): void
    {
        $this->info($title . ':');
        $this->table(
            ['Data Type', 'Count'],
            [
                ['Page Views', number_format($counts['page_views'])],
                ['Sessions', number_format($counts['sessions'])],
                ['Total', number_format($counts['page_views'] + $counts['sessions'])],
            ]
        );
    }

    /**
     * Perform the actual cleanup operation.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays Number of days to retain
     * @return array Cleanup results
     */
    protected function performCleanup(int $retentionDays): array
    {
        try {
            return $this->analytics->cleanupOldData($retentionDays);
        } catch (\Exception $e) {
            $this->error('Cleanup operation failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Display cleanup results.
     *
     * @since 1.3.0
     *
     * @param array $results Cleanup results
     * @param float $executionTime Execution time in seconds
     * @return void
     */
    protected function displayCleanupResults(array $results, float $executionTime): void
    {
        $this->info('Cleanup Results:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Page Views Deleted', number_format($results['page_views_deleted'])],
                ['Sessions Deleted', number_format($results['sessions_deleted'])],
                ['Total Records Deleted', number_format($results['page_views_deleted'] + $results['sessions_deleted'])],
                ['Retention Days', $results['retention_days']],
                ['Execution Time', number_format($executionTime, 2) . ' seconds'],
                ['Cleanup Date', $results['cleanup_date']],
            ]
        );
    }

    /**
     * Get analytics system status for debugging.
     *
     * @since 1.3.0
     *
     * @return array System status
     */
    protected function getSystemStatus(): array
    {
        return [
            'analytics_enabled' => config('artisanpack-cms.analytics.enabled', true),
            'auto_cleanup_enabled' => config('artisanpack-cms.analytics.retention.auto_cleanup', true),
            'retention_days' => config('artisanpack-cms.analytics.retention.retention_days', 365),
            'cleanup_frequency' => config('artisanpack-cms.analytics.retention.cleanup_frequency', 'daily'),
            'batch_size' => config('artisanpack-cms.analytics.retention.cleanup_batch_size', 1000),
            'database_accessible' => $this->checkDatabaseAccess(),
        ];
    }

    /**
     * Check if analytics database tables are accessible.
     *
     * @since 1.3.0
     *
     * @return bool Whether database is accessible
     */
    protected function checkDatabaseAccess(): bool
    {
        try {
            \DB::table('page_view_analytics')->limit(1)->count();
            \DB::table('user_session_analytics')->limit(1)->count();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}