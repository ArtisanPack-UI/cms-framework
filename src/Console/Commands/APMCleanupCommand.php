<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\ErrorLog;
use ArtisanPackUI\CMSFramework\Models\PerformanceMetric;
use ArtisanPackUI\CMSFramework\Models\PerformanceTransaction;
use Illuminate\Console\Command;

/**
 * APMCleanupCommand.
 *
 * Artisan command to clean up old APM data including metrics, transactions,
 * and error logs based on configured retention policies.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Console\Commands
 * @since   1.3.0
 */
class APMCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:apm:cleanup
                            {--metrics-days=90 : Number of days to retain metrics data}
                            {--transactions-days=90 : Number of days to retain transaction data}
                            {--errors-days=365 : Number of days to retain error logs}
                            {--errors-resolved-only : Only delete resolved errors}
                            {--dry-run : Show what would be cleaned without actually doing it}
                            {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old APM data (metrics, transactions, error logs)';

    /**
     * Execute the console command.
     *
     * @since 1.3.0
     *
     * @return int
     */
    public function handle(): int
    {
        // Check if APM is enabled
        if (!config('cms.apm.enabled', true)) {
            $this->error('APM is disabled in configuration.');
            return Command::FAILURE;
        }

        $metricsRetentionDays = (int) $this->option('metrics-days');
        $transactionsRetentionDays = (int) $this->option('transactions-days');
        $errorsRetentionDays = (int) $this->option('errors-days');
        $errorsResolvedOnly = $this->option('errors-resolved-only');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Validate retention days
        if ($metricsRetentionDays < 1 || $transactionsRetentionDays < 1 || $errorsRetentionDays < 1) {
            $this->error('Retention days must be at least 1.');
            return Command::FAILURE;
        }

        // Show what will be cleaned
        $this->info('APM Cleanup Configuration:');
        $this->table([], [
            ['Data Type', 'Retention Days', 'Options'],
            ['Metrics', $metricsRetentionDays, ''],
            ['Transactions', $transactionsRetentionDays, ''],
            ['Error Logs', $errorsRetentionDays, $errorsResolvedOnly ? 'Resolved only' : 'All'],
        ]);

        if ($isDryRun) {
            $this->warn('DRY RUN: No actual cleanup will be performed.');
        }

        // Calculate what would be deleted
        $metricsToDelete = $this->getMetricsToDelete($metricsRetentionDays);
        $transactionsToDelete = $this->getTransactionsToDelete($transactionsRetentionDays);
        $errorsToDelete = $this->getErrorsToDelete($errorsRetentionDays, $errorsResolvedOnly);

        $this->info('Records to be deleted:');
        $this->table([], [
            ['Data Type', 'Records'],
            ['Metrics', $metricsToDelete],
            ['Transactions', $transactionsToDelete],
            ['Error Logs', $errorsToDelete],
        ]);

        if ($metricsToDelete === 0 && $transactionsToDelete === 0 && $errorsToDelete === 0) {
            $this->info('No records need to be cleaned up.');
            return Command::SUCCESS;
        }

        // Confirmation unless forced or dry run
        if (!$isDryRun && !$force) {
            $totalRecords = $metricsToDelete + $transactionsToDelete + $errorsToDelete;
            if (!$this->confirm("Delete {$totalRecords} total records?")) {
                $this->info('Cleanup cancelled.');
                return Command::SUCCESS;
            }
        }

        if ($isDryRun) {
            $this->info('Dry run completed. No records were deleted.');
            return Command::SUCCESS;
        }

        // Perform cleanup
        $startTime = microtime(true);
        
        $actualDeleted = [
            'metrics' => 0,
            'transactions' => 0,
            'errors' => 0,
        ];

        // Clean up metrics
        if ($metricsToDelete > 0) {
            $this->info('Cleaning up performance metrics...');
            $actualDeleted['metrics'] = PerformanceMetric::cleanup($metricsRetentionDays);
        }

        // Clean up transactions
        if ($transactionsToDelete > 0) {
            $this->info('Cleaning up performance transactions...');
            $actualDeleted['transactions'] = PerformanceTransaction::cleanup($transactionsRetentionDays);
        }

        // Clean up errors
        if ($errorsToDelete > 0) {
            $this->info('Cleaning up error logs...');
            $actualDeleted['errors'] = ErrorLog::cleanup($errorsRetentionDays, $errorsResolvedOnly);
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        $totalDeleted = array_sum($actualDeleted);

        $this->info('Cleanup completed successfully:');
        $this->table([], [
            ['Data Type', 'Records Deleted'],
            ['Metrics', $actualDeleted['metrics']],
            ['Transactions', $actualDeleted['transactions']],
            ['Error Logs', $actualDeleted['errors']],
            ['Total', $totalDeleted],
        ]);

        $this->info("Execution time: {$executionTime} seconds");

        return Command::SUCCESS;
    }

    /**
     * Get count of metrics that would be deleted.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays
     * @return int
     */
    protected function getMetricsToDelete(int $retentionDays): int
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        return PerformanceMetric::where('recorded_at', '<', $cutoffDate)->count();
    }

    /**
     * Get count of transactions that would be deleted.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays
     * @return int
     */
    protected function getTransactionsToDelete(int $retentionDays): int
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        return PerformanceTransaction::where('started_at', '<', $cutoffDate)->count();
    }

    /**
     * Get count of errors that would be deleted.
     *
     * @since 1.3.0
     *
     * @param int $retentionDays
     * @param bool $onlyResolved
     * @return int
     */
    protected function getErrorsToDelete(int $retentionDays, bool $onlyResolved): int
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        $query = ErrorLog::where('last_seen_at', '<', $cutoffDate);
        
        if ($onlyResolved) {
            $query = $query->resolved();
        }
        
        return $query->count();
    }
}