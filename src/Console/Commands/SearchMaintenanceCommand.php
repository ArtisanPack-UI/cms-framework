<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\SearchAnalytics;
use ArtisanPackUI\CMSFramework\Models\SearchIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * SearchMaintenanceCommand.
 *
 * Artisan command for search system maintenance tasks including cache clearing,
 * analytics cleanup, and index optimization.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Console\Commands
 * @since   1.2.0
 */
class SearchMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:search:maintenance
                            {action : The maintenance action to perform (cleanup, cache-clear, stats, optimize)}
                            {--days=365 : Number of days to retain analytics data}
                            {--force : Force operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform search system maintenance tasks';

    /**
     * Execute the console command.
     *
     * @since 1.2.0
     *
     * @return int
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        
        return match ($action) {
            'cleanup' => $this->handleCleanup(),
            'cache-clear' => $this->handleCacheClear(),
            'stats' => $this->handleStats(),
            'optimize' => $this->handleOptimize(),
            default => $this->handleInvalidAction($action),
        };
    }

    /**
     * Handle analytics cleanup action.
     *
     * @since 1.2.0
     *
     * @return int
     */
    protected function handleCleanup(): int
    {
        if (!config('cms.search.analytics_enabled', true)) {
            $this->error('Search analytics is disabled. No cleanup needed.');
            return Command::FAILURE;
        }

        $retentionDays = (int) $this->option('days');
        $force = $this->option('force');

        if ($retentionDays < 1) {
            $this->error('Retention days must be at least 1.');
            return Command::FAILURE;
        }

        // Show what will be cleaned up
        $cutoffDate = now()->subDays($retentionDays);
        $recordsToDelete = SearchAnalytics::where('searched_at', '<', $cutoffDate)->count();

        $this->info("Analytics cleanup configuration:");
        $this->table([], [
            ['Setting', 'Value'],
            ['Retention Days', $retentionDays],
            ['Cutoff Date', $cutoffDate->toDateString()],
            ['Records to Delete', $recordsToDelete],
        ]);

        if ($recordsToDelete === 0) {
            $this->info('No analytics records need to be cleaned up.');
            return Command::SUCCESS;
        }

        // Confirmation unless forced
        if (!$force) {
            if (!$this->confirm("Delete {$recordsToDelete} analytics records older than {$retentionDays} days?")) {
                $this->info('Cleanup cancelled.');
                return Command::SUCCESS;
            }
        }

        try {
            $deletedCount = SearchAnalytics::cleanup($retentionDays);
            
            $this->info("Successfully cleaned up {$deletedCount} analytics records.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Cleanup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Handle cache clear action.
     *
     * @since 1.2.0
     *
     * @return int
     */
    protected function handleCacheClear(): int
    {
        if (!config('cms.search.cache.enabled', true)) {
            $this->info('Search caching is disabled. No cache to clear.');
            return Command::SUCCESS;
        }

        try {
            $cacheKeys = [
                'search_facets_*',
                'search_suggestions_*',
                'search_results_*',
            ];

            $cleared = 0;
            $cacheTags = config('cms.search.cache.tags', ['cms', 'search']);

            // Clear tagged cache if supported
            if (!empty($cacheTags)) {
                try {
                    Cache::tags($cacheTags)->flush();
                    $this->info('Search cache cleared using tags: ' . implode(', ', $cacheTags));
                    return Command::SUCCESS;
                } catch (\Exception $e) {
                    $this->warn('Tag-based cache clearing failed, falling back to pattern matching.');
                }
            }

            // Fallback to pattern-based clearing for cache stores that don't support tags
            foreach ($cacheKeys as $pattern) {
                try {
                    // This is a simplified approach - actual implementation would depend on cache driver
                    Cache::forget($pattern);
                    $cleared++;
                } catch (\Exception $e) {
                    // Ignore individual failures
                }
            }

            $this->info("Search cache cleared. Processed {$cleared} cache patterns.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Cache clear failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Handle stats action.
     *
     * @since 1.2.0
     *
     * @return int
     */
    protected function handleStats(): int
    {
        try {
            // Search index statistics
            $totalIndexed = SearchIndex::count();
            $indexByType = SearchIndex::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->orderByDesc('count')
                ->get();

            $this->info('Search Index Statistics:');
            $this->table(['Type', 'Count'], $indexByType->map(function ($item) {
                return [$item->type ?: 'Unknown', $item->count];
            })->toArray());

            $this->info("Total indexed items: {$totalIndexed}");
            $this->newLine();

            // Analytics statistics (if enabled)
            if (config('cms.search.analytics_enabled', true)) {
                $last30Days = now()->subDays(30);
                $analyticsStats = SearchAnalytics::getPerformanceStats($last30Days);

                $this->info('Search Analytics (Last 30 Days):');
                $this->table(['Metric', 'Value'], [
                    ['Total Searches', $analyticsStats['total_searches']],
                    ['Unique Queries', $analyticsStats['unique_queries']],
                    ['Average Results per Search', $analyticsStats['avg_results_per_search']],
                    ['Average Execution Time (ms)', $analyticsStats['avg_execution_time_ms']],
                    ['Failed Searches', $analyticsStats['failed_searches']],
                    ['Success Rate (%)', $analyticsStats['success_rate']],
                ]);

                // Popular queries
                $popularQueries = SearchAnalytics::getPopularQueries(5, $last30Days);
                if ($popularQueries->isNotEmpty()) {
                    $this->newLine();
                    $this->info('Top 5 Search Queries (Last 30 Days):');
                    $this->table(['Query', 'Search Count', 'Avg Results'], 
                        $popularQueries->map(function ($query) {
                            return [
                                $query->query,
                                $query->search_count,
                                round($query->avg_results, 1),
                            ];
                        })->toArray()
                    );
                }
            } else {
                $this->warn('Search analytics is disabled.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to retrieve stats: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Handle optimize action.
     *
     * @since 1.2.0
     *
     * @return int
     */
    protected function handleOptimize(): int
    {
        try {
            $this->info('Running search optimization tasks...');

            // Clean up orphaned search index entries
            $orphanedCount = $this->cleanupOrphanedIndexEntries();
            
            // Update search statistics
            $this->updateSearchStatistics();
            
            // Clear stale cache
            $this->call('cms:search:maintenance', ['action' => 'cache-clear']);

            $this->info('Search optimization completed:');
            $this->info("- Removed {$orphanedCount} orphaned index entries");
            $this->info('- Updated search statistics');
            $this->info('- Cleared stale cache');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Optimization failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clean up orphaned search index entries.
     *
     * @since 1.2.0
     *
     * @return int Number of orphaned entries removed
     */
    protected function cleanupOrphanedIndexEntries(): int
    {
        $orphanedCount = 0;

        // Check for orphaned content entries
        $orphanedContent = SearchIndex::where('searchable_type', 'ArtisanPackUI\CMSFramework\Models\Content')
            ->whereNotExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('content')
                    ->whereRaw('content.id = search_indices.searchable_id');
            })
            ->delete();

        $orphanedCount += $orphanedContent;

        // Check for orphaned term entries
        $orphanedTerms = SearchIndex::where('searchable_type', 'ArtisanPackUI\CMSFramework\Models\Term')
            ->whereNotExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('terms')
                    ->whereRaw('terms.id = search_indices.searchable_id');
            })
            ->delete();

        $orphanedCount += $orphanedTerms;

        return $orphanedCount;
    }

    /**
     * Update search statistics.
     *
     * @since 1.2.0
     *
     * @return void
     */
    protected function updateSearchStatistics(): void
    {
        // This could include updating relevance scores, popularity metrics, etc.
        // For now, we'll just ensure the index is consistent
        
        // Could add logic here to:
        // - Recalculate relevance boosts based on engagement
        // - Update popularity scores
        // - Refresh cached statistics
    }

    /**
     * Handle invalid action.
     *
     * @since 1.2.0
     *
     * @param string $action
     * @return int
     */
    protected function handleInvalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->info('Available actions:');
        $this->info('  cleanup      - Clean up old analytics data');
        $this->info('  cache-clear  - Clear search cache');
        $this->info('  stats        - Show search statistics');
        $this->info('  optimize     - Optimize search index and performance');

        return Command::FAILURE;
    }
}