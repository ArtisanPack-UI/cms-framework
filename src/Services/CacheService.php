<?php

declare(strict_types=1);

/**
 * CMS Framework Cache Service
 *
 * Provides comprehensive caching functionality for the CMS framework with support
 * for tag-based invalidation, cache warming, monitoring, and performance optimization.
 * Handles caching for users, roles, plugins, themes, content, and database queries.
 *
 * @since 1.0.0
 *
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Services;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Cache Service Class
 *
 * Centralized caching service for the CMS framework providing methods for
 * caching, retrieval, invalidation, and cache warming operations.
 */
class CacheService
{
    /**
     * Cache repository instance.
     */
    private Repository $cache;

    /**
     * Cache configuration.
     */
    private array $config;

    /**
     * Cache key prefix.
     */
    private string $prefix;

    /**
     * Cache statistics for monitoring.
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'invalidations' => 0,
    ];

    /**
     * Create a new cache service instance.
     */
    public function __construct()
    {
        $this->config = Config::get('cms-cache', []);
        $this->prefix = $this->config['prefix'] ?? 'cms_framework';

        // Initialize cache repository with configured driver
        $driver = $this->config['driver'] ?? Config::get('cache.default', 'file');
        $this->cache = Cache::store($driver);
    }

    /**
     * Check if caching is enabled globally.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Check if caching is enabled for a specific component.
     */
    public function isEnabledFor(string $component): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->config[$component]['enabled'] ?? true;
    }

    /**
     * Get cache key with prefix.
     */
    public function getKey(string $component, string $keyTemplate, array $params = []): string
    {
        $key = $keyTemplate;

        // Replace placeholders in key template
        foreach ($params as $placeholder => $value) {
            $key = str_replace('{'.$placeholder.'}', (string) $value, $key);
        }

        return $this->prefix.'_'.$component.'_'.$key;
    }

    /**
     * Get TTL for a specific component.
     */
    public function getTtl(string $component): int
    {
        return $this->config[$component]['ttl'] ?? $this->config['default_ttl'] ?? 3600;
    }

    /**
     * Get cache tags for a component.
     */
    public function getTags(string $component): array
    {
        return $this->config[$component]['tags'] ?? [];
    }

    /**
     * Cache a value with automatic key generation and TTL.
     */
    public function put(string $component, string $keyTemplate, $value, array $params = [], ?int $ttl = null): bool
    {
        if (! $this->isEnabledFor($component)) {
            return false;
        }

        $key = $this->getKey($component, $keyTemplate, $params);
        $ttl = $ttl ?? $this->getTtl($component);
        $tags = $this->getTags($component);

        $this->stats['writes']++;

        try {
            if (! empty($tags) && method_exists($this->cache->getStore(), 'tags')) {
                return $this->cache->tags($tags)->put($key, $value, $ttl);
            }

            return $this->cache->put($key, $value, $ttl);
        } catch (\Exception $e) {
            $this->logError('Cache put failed', $key, $e);

            return false;
        }
    }

    /**
     * Get cached value.
     */
    public function get(string $component, string $keyTemplate, array $params = [], $default = null)
    {
        if (! $this->isEnabledFor($component)) {
            return $default;
        }

        $key = $this->getKey($component, $keyTemplate, $params);

        try {
            $value = $this->cache->get($key);

            if ($value !== null) {
                $this->stats['hits']++;
                $this->logCacheHit($key);

                return $value;
            }

            $this->stats['misses']++;
            $this->logCacheMiss($key);

            return $default;
        } catch (\Exception $e) {
            $this->logError('Cache get failed', $key, $e);

            return $default;
        }
    }

    /**
     * Remember (get or cache) a value.
     */
    public function remember(string $component, string $keyTemplate, \Closure $callback, array $params = [], ?int $ttl = null)
    {
        if (! $this->isEnabledFor($component)) {
            return $callback();
        }

        $key = $this->getKey($component, $keyTemplate, $params);
        $ttl = $ttl ?? $this->getTtl($component);
        $tags = $this->getTags($component);

        try {
            if (! empty($tags) && method_exists($this->cache->getStore(), 'tags')) {
                $value = $this->cache->tags($tags)->remember($key, $ttl, $callback);
            } else {
                $value = $this->cache->remember($key, $ttl, $callback);
            }

            if ($this->cache->has($key)) {
                $this->stats['hits']++;
                $this->logCacheHit($key);
            } else {
                $this->stats['misses']++;
                $this->stats['writes']++;
                $this->logCacheMiss($key);
            }

            return $value;
        } catch (\Exception $e) {
            $this->logError('Cache remember failed', $key, $e);

            return $callback();
        }
    }

    /**
     * Forget (remove) cached value.
     */
    public function forget(string $component, string $keyTemplate, array $params = []): bool
    {
        $key = $this->getKey($component, $keyTemplate, $params);

        try {
            return $this->cache->forget($key);
        } catch (\Exception $e) {
            $this->logError('Cache forget failed', $key, $e);

            return false;
        }
    }

    /**
     * Flush cache by tags.
     */
    public function flushByTags(array $tags): bool
    {
        try {
            if (method_exists($this->cache->getStore(), 'tags')) {
                $this->cache->tags($tags)->flush();
                $this->stats['invalidations']++;
                $this->logInvalidation('tags', implode(',', $tags));

                return true;
            }

            // Fallback: flush entire cache if tags not supported
            $this->cache->flush();
            $this->stats['invalidations']++;
            $this->logInvalidation('full', 'all (tags not supported)');

            return true;
        } catch (\Exception $e) {
            $this->logError('Cache flush by tags failed', implode(',', $tags), $e);

            return false;
        }
    }

    /**
     * Invalidate cache for a specific model event.
     */
    public function invalidateForModel(string $modelClass, string $event): void
    {
        $modelName = class_basename($modelClass);
        $invalidationRules = $this->config['invalidation'][$modelName][$event] ?? [];

        foreach ($invalidationRules as $tags) {
            if (is_string($tags)) {
                $tags = [$tags];
            }
            $this->flushByTags($tags);
        }
    }

    /**
     * Warm cache for critical data.
     */
    public function warmCache(array $items = []): void
    {
        if (! $this->config['warming']['enabled'] ?? true) {
            return;
        }

        $itemsToWarm = $items ?: ($this->config['warming']['items'] ?? []);
        $chunkSize = $this->config['warming']['chunk_size'] ?? 100;
        $delay = $this->config['warming']['delay_between_chunks'] ?? 100;

        foreach (array_chunk($itemsToWarm, $chunkSize) as $chunk) {
            foreach ($chunk as $item) {
                $this->warmCacheItem($item);
            }

            if ($delay > 0) {
                usleep($delay * 1000); // Convert milliseconds to microseconds
            }
        }
    }

    /**
     * Warm a specific cache item.
     */
    private function warmCacheItem(string $item): void
    {
        try {
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
                    $this->warmPublishedContent();
                    break;
                case 'all_themes':
                    $this->warmAllThemes();
                    break;
            }
        } catch (\Exception $e) {
            $this->logError('Cache warming failed for item', $item, $e);
        }
    }

    /**
     * Warm all roles cache.
     */
    private function warmAllRoles(): void
    {
        // This would typically call the appropriate service/repository
        // For now, we'll just set up the cache structure
        $this->put('roles', 'all_roles', []);
    }

    /**
     * Warm all settings cache.
     */
    private function warmAllSettings(): void
    {
        $this->put('settings', 'all_settings', []);
    }

    /**
     * Warm installed plugins cache.
     */
    private function warmInstalledPlugins(): void
    {
        $this->put('plugins', 'all_installed', []);
    }

    /**
     * Warm active plugins cache.
     */
    private function warmActivePlugins(): void
    {
        $this->put('plugins', 'active_plugins', []);
    }

    /**
     * Warm published content cache.
     */
    private function warmPublishedContent(): void
    {
        $this->put('content', 'published_content', []);
    }

    /**
     * Warm all themes cache.
     */
    private function warmAllThemes(): void
    {
        $this->put('themes', 'all_themes', []);
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset cache statistics.
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'invalidations' => 0,
        ];
    }

    /**
     * Log cache hit if monitoring is enabled.
     */
    private function logCacheHit(string $key): void
    {
        if ($this->config['monitoring']['log_hits'] ?? false) {
            Log::debug('Cache hit', ['key' => $key]);
        }
    }

    /**
     * Log cache miss if monitoring is enabled.
     */
    private function logCacheMiss(string $key): void
    {
        if ($this->config['monitoring']['log_misses'] ?? false) {
            Log::debug('Cache miss', ['key' => $key]);
        }
    }

    /**
     * Log cache invalidation.
     */
    private function logInvalidation(string $type, string $target): void
    {
        if ($this->config['monitoring']['log_invalidations'] ?? true) {
            Log::info('Cache invalidated', [
                'type' => $type,
                'target' => $target,
            ]);
        }
    }

    /**
     * Log cache errors.
     */
    private function logError(string $message, string $key, \Exception $e): void
    {
        Log::error($message, [
            'key' => $key,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Clear all CMS framework caches.
     */
    public function clearAll(): bool
    {
        try {
            // Try tag-based clearing first
            if (method_exists($this->cache->getStore(), 'tags')) {
                $allTags = ['users', 'roles', 'permissions', 'plugins', 'themes',
                    'content', 'queries', 'settings', 'configuration', 'discovery'];
                $this->flushByTags($allTags);
            } else {
                // Fallback to pattern-based clearing
                $this->cache->flush();
            }

            $this->stats['invalidations']++;
            $this->logInvalidation('clear_all', 'cms_framework');

            return true;
        } catch (\Exception $e) {
            $this->logError('Clear all cache failed', 'all', $e);

            return false;
        }
    }

    /**
     * Get cache info and diagnostics.
     */
    public function getInfo(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'driver' => $this->config['driver'] ?? 'unknown',
            'prefix' => $this->prefix,
            'stats' => $this->getStats(),
            'config' => $this->config,
            'store_class' => get_class($this->cache->getStore()),
            'supports_tags' => method_exists($this->cache->getStore(), 'tags'),
        ];
    }
}
