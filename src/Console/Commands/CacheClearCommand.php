<?php

declare(strict_types=1);

/**
 * Cache Clear Command
 *
 * Artisan command to selectively clear caches for the CMS framework.
 * Provides fine-grained control over cache clearing with support for
 * tag-based clearing, specific components, and complete cache clearing.
 *
 * @since 1.0.0
 *
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Services\CacheService;
use Illuminate\Console\Command;

/**
 * Cache Clearing Command
 *
 * Provides selective cache clearing capabilities for the CMS framework.
 */
class CacheClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:cache:clear
                            {--components=* : Specific components to clear (users, roles, plugins, content, etc.)}
                            {--tags=* : Specific cache tags to clear}
                            {--all : Clear all CMS framework caches}
                            {--info : Show cache information before clearing}
                            {--force : Force clearing even if cache is disabled}';

    /**
     * The console command description.
     */
    protected $description = 'Clear CMS framework caches selectively or completely';

    /**
     * Cache service instance.
     */
    private CacheService $cacheService;

    /**
     * Create a new command instance.
     */
    public function __construct(CacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->cacheService->isEnabled() && ! $this->option('force')) {
            $this->error('Cache is disabled. Use --force to clear anyway.');

            return 1;
        }

        // Show cache information if requested
        if ($this->option('info')) {
            $this->showCacheInfo();
        }

        $components = $this->option('components');
        $tags = $this->option('tags');
        $clearAll = $this->option('all');

        if ($clearAll) {
            return $this->clearAllCaches();
        }

        if (! empty($tags)) {
            return $this->clearByTags($tags);
        }

        if (! empty($components)) {
            return $this->clearByComponents($components);
        }

        // If no specific options provided, show help
        $this->info('No clearing options specified. Available options:');
        $this->line('');
        $this->line('Clear all CMS caches:');
        $this->line('  <info>php artisan cms:cache:clear --all</info>');
        $this->line('');
        $this->line('Clear specific components:');
        $this->line('  <info>php artisan cms:cache:clear --components=users,roles</info>');
        $this->line('');
        $this->line('Clear by tags:');
        $this->line('  <info>php artisan cms:cache:clear --tags=permissions,plugins</info>');
        $this->line('');
        $this->line('Available components: users, roles, plugins, themes, content, queries, settings');
        $this->line('Available tags: users, roles, permissions, plugins, themes, content, queries, settings, configuration, discovery');

        return 0;
    }

    /**
     * Clear all CMS framework caches.
     */
    private function clearAllCaches(): int
    {
        if (! $this->confirm('This will clear ALL CMS framework caches. Are you sure?')) {
            $this->info('Cache clearing cancelled.');

            return 0;
        }

        $this->info('Clearing all CMS framework caches...');

        $result = $this->cacheService->clearAll();

        if ($result) {
            $this->info('✓ All CMS framework caches cleared successfully!');
        } else {
            $this->error('✗ Failed to clear all caches.');

            return 1;
        }

        $this->showCacheStats();

        return 0;
    }

    /**
     * Clear caches by specific tags.
     */
    private function clearByTags(array $tags): int
    {
        $this->info('Clearing caches by tags: '.implode(', ', $tags));

        $cleared = 0;
        $failed = 0;

        foreach ($tags as $tag) {
            try {
                $result = $this->cacheService->flushByTags([$tag]);
                if ($result) {
                    $this->info("✓ Cleared caches for tag: {$tag}");
                    $cleared++;
                } else {
                    $this->error("✗ Failed to clear caches for tag: {$tag}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error clearing tag '{$tag}': {$e->getMessage()}");
                $failed++;
            }
        }

        $this->line('');
        $this->info("Cache clearing completed. Cleared: {$cleared}, Failed: {$failed}");

        $this->showCacheStats();

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Clear caches by specific components.
     */
    private function clearByComponents(array $components): int
    {
        $this->info('Clearing caches by components: '.implode(', ', $components));

        $componentTagMap = [
            'users' => ['users', 'permissions'],
            'roles' => ['roles', 'permissions'],
            'plugins' => ['plugins', 'discovery'],
            'themes' => ['themes', 'discovery'],
            'content' => ['content', 'posts'],
            'queries' => ['queries', 'database'],
            'settings' => ['settings', 'configuration'],
        ];

        $cleared = 0;
        $failed = 0;

        foreach ($components as $component) {
            if (! isset($componentTagMap[$component])) {
                $this->error("✗ Unknown component: {$component}");
                $failed++;

                continue;
            }

            try {
                $tags = $componentTagMap[$component];
                $result = $this->cacheService->flushByTags($tags);

                if ($result) {
                    $this->info("✓ Cleared caches for component: {$component}");
                    $cleared++;
                } else {
                    $this->error("✗ Failed to clear caches for component: {$component}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error clearing component '{$component}': {$e->getMessage()}");
                $failed++;
            }
        }

        $this->line('');
        $this->info("Cache clearing completed. Cleared: {$cleared}, Failed: {$failed}");

        $this->showCacheStats();

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Show cache information.
     */
    private function showCacheInfo(): void
    {
        $info = $this->cacheService->getInfo();

        $this->info('Current Cache Information:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Cache Enabled', $info['enabled'] ? 'Yes' : 'No'],
                ['Cache Driver', $info['driver']],
                ['Cache Prefix', $info['prefix']],
                ['Store Class', $info['store_class']],
                ['Supports Tags', $info['supports_tags'] ? 'Yes' : 'No'],
            ]
        );

        $this->showCacheStats();
        $this->line('');
    }

    /**
     * Show cache statistics.
     */
    private function showCacheStats(): void
    {
        $stats = $this->cacheService->getStats();

        if (array_sum($stats) > 0) {
            $this->line('');
            $this->info('Cache Statistics (current session):');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Cache Hits', $stats['hits']],
                    ['Cache Misses', $stats['misses']],
                    ['Cache Writes', $stats['writes']],
                    ['Cache Invalidations', $stats['invalidations']],
                ]
            );
        }
    }
}
