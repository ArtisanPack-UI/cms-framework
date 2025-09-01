<?php

declare(strict_types=1);

/**
 * Cache Warm Command
 *
 * Artisan command to warm critical caches for improved application performance.
 * Pre-populates cache entries for users, roles, plugins, content, and other
 * frequently accessed data to reduce response times on first access.
 *
 * @since 1.0.0
 *
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Plugin;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Setting;
use ArtisanPackUI\CMSFramework\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cache Warming Command
 *
 * Warms critical application caches to improve performance.
 */
class CacheWarmCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:cache:warm
                            {--items=* : Specific cache items to warm (all_roles, all_settings, etc.)}
                            {--chunk=100 : Number of items to process per chunk}
                            {--delay=100 : Delay between chunks in milliseconds}
                            {--force : Force warming even if cache is disabled}';

    /**
     * The console command description.
     */
    protected $description = 'Warm critical caches for improved performance';

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
            $this->error('Cache is disabled. Use --force to warm anyway.');

            return 1;
        }

        $items = $this->option('items');
        $chunkSize = (int) $this->option('chunk');
        $delay = (int) $this->option('delay');

        if (empty($items)) {
            $items = [
                'all_roles',
                'all_settings',
                'all_installed_plugins',
                'active_plugins',
                'published_content',
                'content_types',
            ];
        }

        $this->info('Starting cache warming process...');
        $this->info('Items to warm: '.implode(', ', $items));

        $progressBar = $this->output->createProgressBar(count($items));
        $progressBar->start();

        foreach ($items as $item) {
            try {
                $this->warmCacheItem($item, $chunkSize, $delay);
                $progressBar->advance();
                $this->line(''); // New line for clean output
                $this->info("✓ Warmed cache for: {$item}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to warm cache for: {$item} - {$e->getMessage()}");
            }
        }

        $progressBar->finish();
        $this->line('');
        $this->info('Cache warming completed!');

        // Show cache statistics
        $stats = $this->cacheService->getStats();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Cache Hits', $stats['hits']],
                ['Cache Misses', $stats['misses']],
                ['Cache Writes', $stats['writes']],
                ['Cache Invalidations', $stats['invalidations']],
            ]
        );

        return 0;
    }

    /**
     * Warm a specific cache item.
     */
    private function warmCacheItem(string $item, int $chunkSize, int $delay): void
    {
        switch ($item) {
            case 'all_roles':
                $this->warmAllRoles();
                break;
            case 'all_settings':
                $this->warmAllSettings();
                break;
            case 'all_installed_plugins':
                $this->warmInstalledPlugins();
                break;
            case 'active_plugins':
                $this->warmActivePlugins();
                break;
            case 'published_content':
                $this->warmPublishedContent($chunkSize, $delay);
                break;
            case 'content_types':
                $this->warmContentTypes();
                break;
            case 'user_permissions':
                $this->warmUserPermissions($chunkSize, $delay);
                break;
            case 'role_capabilities':
                $this->warmRoleCapabilities();
                break;
            default:
                throw new \InvalidArgumentException("Unknown cache item: {$item}");
        }
    }

    /**
     * Warm all roles cache.
     */
    private function warmAllRoles(): void
    {
        $roles = Role::all();
        $this->cacheService->put('roles', 'all_roles', $roles);
        $this->line("  Warmed roles cache ({$roles->count()} roles)");
    }

    /**
     * Warm all settings cache.
     */
    private function warmAllSettings(): void
    {
        $settings = Setting::all()->keyBy('key');
        $this->cacheService->put('settings', 'all_settings', $settings);
        $this->line("  Warmed settings cache ({$settings->count()} settings)");
    }

    /**
     * Warm installed plugins cache.
     */
    private function warmInstalledPlugins(): void
    {
        $plugins = Plugin::all();
        $this->cacheService->put('plugins', 'all_installed', $plugins);
        $this->line("  Warmed installed plugins cache ({$plugins->count()} plugins)");
    }

    /**
     * Warm active plugins cache.
     */
    private function warmActivePlugins(): void
    {
        $activePlugins = Plugin::where('is_active', true)->get();
        $this->cacheService->put('plugins', 'active_plugins', $activePlugins);
        $this->line("  Warmed active plugins cache ({$activePlugins->count()} active plugins)");
    }

    /**
     * Warm published content cache.
     */
    private function warmPublishedContent(int $chunkSize, int $delay): void
    {
        $totalContent = Content::where('status', 'published')
            ->where('published_at', '<=', now())
            ->count();

        $this->line("  Warming published content cache ({$totalContent} items)...");

        Content::where('status', 'published')
            ->where('published_at', '<=', now())
            ->chunk($chunkSize, function ($contents) use ($delay) {
                foreach ($contents as $content) {
                    // Cache individual content items
                    $this->cacheService->put(
                        'content',
                        'content_item',
                        $content,
                        ['id' => $content->id]
                    );

                    // Cache content by type
                    $contentByType = Content::where('type', $content->type)
                        ->where('status', 'published')
                        ->where('published_at', '<=', now())
                        ->take(50) // Limit for performance
                        ->get();

                    $this->cacheService->put(
                        'content',
                        'content_by_type',
                        $contentByType,
                        ['type' => $content->type]
                    );
                }

                if ($delay > 0) {
                    usleep($delay * 1000); // Convert ms to microseconds
                }
            });

        // Cache overall published content list
        $publishedContent = Content::where('status', 'published')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->take(100) // Recent published content
            ->get();

        $this->cacheService->put('content', 'published_content', $publishedContent);
    }

    /**
     * Warm content types cache.
     */
    private function warmContentTypes(): void
    {
        $contentTypes = DB::table('content')
            ->select('type')
            ->distinct()
            ->pluck('type');

        foreach ($contentTypes as $type) {
            $typeContent = Content::where('type', $type)
                ->where('status', 'published')
                ->where('published_at', '<=', now())
                ->take(50)
                ->get();

            $this->cacheService->put(
                'content',
                'content_type',
                $typeContent,
                ['type' => $type]
            );
        }

        $this->line("  Warmed content types cache ({$contentTypes->count()} types)");
    }

    /**
     * Warm user permissions cache.
     */
    private function warmUserPermissions(int $chunkSize, int $delay): void
    {
        $totalUsers = DB::table('users')->count();
        $this->line("  Warming user permissions cache ({$totalUsers} users)...");

        // This is a placeholder - in practice, you'd warm the most active users
        $this->line('  User permissions warming would require specific user activity data');
    }

    /**
     * Warm role capabilities cache.
     */
    private function warmRoleCapabilities(): void
    {
        $roles = Role::all();

        foreach ($roles as $role) {
            if (! empty($role->capabilities)) {
                foreach ($role->capabilities as $capability) {
                    // Warm individual capability checks
                    $this->cacheService->put(
                        'roles',
                        'role_capabilities',
                        $role->hasCapability($capability),
                        ['role_id' => $role->id, 'capability' => $capability]
                    );
                }
            }
        }

        $this->line("  Warmed role capabilities cache ({$roles->count()} roles)");
    }
}
