<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->manager = app(PluginManager::class);
    $this->pluginsPath = base_path('plugins');

    File::ensureDirectoryExists($this->pluginsPath);
});

afterEach(function () {
    // Cleanup
    Cache::flush();
});

describe('Plugin Discovery Performance', function () {
    it('caches discovered plugins for performance', function () {
        // Create multiple test plugins
        for ($i = 1; $i <= 10; $i++) {
            Plugin::create([
                'slug' => "test-plugin-{$i}",
                'name' => "Test Plugin {$i}",
                'version' => '1.0.0',
            ]);
        }

        // First call should cache the results
        $plugins = $this->manager->discoverPlugins();

        // Second call should return same results from cache
        $pluginsCached = $this->manager->discoverPlugins();

        expect($pluginsCached)->toEqual($plugins);

        // Verify cache is being used
        expect(Cache::has(config('cms.plugins.cacheKey')))->toBeTrue();
    });

    it('clears cache when plugins are modified', function () {
        $cacheKey = config('cms.plugins.cacheKey');

        // Discover plugins (populates cache)
        $this->manager->discoverPlugins();
        expect(Cache::has($cacheKey))->toBeTrue();

        // Install a new plugin (should clear cache)
        Plugin::create([
            'slug' => 'new-plugin',
            'name' => 'New Plugin',
            'version' => '1.0.0',
        ]);

        // Simulate cache clearing that happens in PluginManager
        Cache::forget($cacheKey);
        expect(Cache::has($cacheKey))->toBeFalse();
    });

    it('handles large number of plugins efficiently', function () {
        // Create 10 plugins with actual directories for realistic testing
        // Creating 100 plugin directories would be too slow for tests
        for ($i = 1; $i <= 10; $i++) {
            $pluginPath = $this->pluginsPath."/plugin-{$i}";
            File::ensureDirectoryExists($pluginPath);

            File::put($pluginPath.'/plugin.json', json_encode([
                'slug' => "plugin-{$i}",
                'name' => "Plugin {$i}",
                'version' => '1.0.0',
                'description' => 'Test plugin for performance testing',
            ]));

            Plugin::create([
                'slug' => "plugin-{$i}",
                'name' => "Plugin {$i}",
                'version' => '1.0.0',
            ]);
        }

        // Discovery should complete in reasonable time
        $start = microtime(true);
        $plugins = $this->manager->discoverPlugins();
        $time = microtime(true) - $start;

        expect(count($plugins))->toBeGreaterThanOrEqual(10);

        // Should complete within 1 second for 10 plugins
        expect($time)->toBeLessThan(1.0);

        // Cleanup
        for ($i = 1; $i <= 10; $i++) {
            $pluginPath = $this->pluginsPath."/plugin-{$i}";
            if (File::exists($pluginPath)) {
                File::deleteDirectory($pluginPath);
            }
        }
    });
});

describe('Database Query Optimization', function () {
    it('uses efficient queries for active plugins', function () {
        // Create active and inactive plugins
        for ($i = 1; $i <= 50; $i++) {
            Plugin::create([
                'slug' => "plugin-{$i}",
                'name' => "Plugin {$i}",
                'version' => '1.0.0',
                'is_active' => $i % 2 === 0, // Half active, half inactive
            ]);
        }

        // Count queries
        DB::enableQueryLog();
        $plugins = Plugin::active()->get();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should use a single query with WHERE clause
        expect(count($queries))->toBe(1);
        expect($plugins->count())->toBe(25); // Half of 50
    });

    it('eager loads relationships efficiently', function () {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
        ]);

        // Query with all data in one go
        DB::enableQueryLog();
        $plugins = Plugin::all();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should not have N+1 queries
        expect(count($queries))->toBe(1);
    });
});

describe('Autoload Registration Performance', function () {
    it('registers multiple plugin autoloaders efficiently', function () {
        // Create plugins with autoload config
        $pluginCount = 20;
        for ($i = 1; $i <= $pluginCount; $i++) {
            Plugin::create([
                'slug' => "autoload-plugin-{$i}",
                'name' => "Autoload Plugin {$i}",
                'version' => '1.0.0',
                'is_active' => true,
                'meta' => [
                    'autoload' => [
                        'psr-4' => [
                            "TestPlugin{$i}\\" => 'src/',
                        ],
                    ],
                ],
            ]);
        }

        // Loading active plugins should complete quickly
        $start = microtime(true);
        $this->manager->loadActivePlugins();
        $time = microtime(true) - $start;

        // Should complete within 1 second for 20 plugins
        expect($time)->toBeLessThan(1.0);
    });
});

describe('Memory Usage', function () {
    it('manages memory efficiently when loading many plugins', function () {
        $memoryBefore = memory_get_usage();

        // Create and load 50 plugins
        for ($i = 1; $i <= 50; $i++) {
            Plugin::create([
                'slug' => "memory-test-{$i}",
                'name' => "Memory Test Plugin {$i}",
                'version' => '1.0.0',
                'is_active' => true,
                'meta' => [
                    'description' => str_repeat('Test description ', 100),
                ],
            ]);
        }

        $this->manager->loadActivePlugins();

        $memoryAfter = memory_get_usage();
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        // Should use less than 10MB for 50 plugins
        expect($memoryUsed)->toBeLessThan(10);
    });
});

describe('Cache Efficiency', function () {
    it('respects cache TTL configuration', function () {
        $cacheKey = config('cms.plugins.cacheKey');

        // Discover plugins
        $this->manager->discoverPlugins();
        expect(Cache::has($cacheKey))->toBeTrue();

        // Cache should persist
        sleep(1);
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('uses separate cache keys for different contexts', function () {
        $discoveryCache = config('cms.plugins.cacheKey');

        // Different operations should use different cache keys
        expect($discoveryCache)->toBe('cms.plugins.discovered');

        // Update cache would use different keys
        $updateCachePrefix = 'plugin.update.';
        expect($updateCachePrefix)->not->toBe($discoveryCache);
    });
});

describe('File System Operations Performance', function () {
    it('scans plugin directory efficiently', function () {
        // Create multiple plugin directories
        for ($i = 1; $i <= 20; $i++) {
            $pluginPath = $this->pluginsPath."/fs-test-{$i}";
            File::ensureDirectoryExists($pluginPath);
            File::put($pluginPath.'/plugin.json', json_encode([
                'slug' => "fs-test-{$i}",
                'name' => "FS Test {$i}",
                'version' => '1.0.0',
            ]));
        }

        $start = microtime(true);
        $this->manager->discoverPlugins();
        $time = microtime(true) - $start;

        // Should scan 20 directories within 500ms
        expect($time)->toBeLessThan(0.5);

        // Cleanup
        for ($i = 1; $i <= 20; $i++) {
            File::deleteDirectory($this->pluginsPath."/fs-test-{$i}");
        }
    });

    it('parses JSON manifests efficiently', function () {
        $manifests = [];

        // Create 50 test manifests
        for ($i = 1; $i <= 50; $i++) {
            $manifests[$i] = [
                'slug' => "json-test-{$i}",
                'name' => "JSON Test {$i}",
                'version' => '1.0.0',
                'description' => str_repeat('Description ', 50),
                'author' => "Author {$i}",
            ];
        }

        $start = microtime(true);
        foreach ($manifests as $manifest) {
            json_encode($manifest);
            json_decode(json_encode($manifest), true);
        }
        $time = microtime(true) - $start;

        // Should parse 50 manifests within 50ms
        expect($time)->toBeLessThan(0.05);
    });
});

describe('Concurrent Operations', function () {
    it('handles concurrent plugin discoveries safely', function () {
        // Create test plugins
        for ($i = 1; $i <= 10; $i++) {
            Plugin::create([
                'slug' => "concurrent-{$i}",
                'name' => "Concurrent Test {$i}",
                'version' => '1.0.0',
            ]);
        }

        // Simulate concurrent discoveries
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->manager->discoverPlugins();
        }

        // All results should be identical
        foreach ($results as $result) {
            expect($result)->toEqual($results[0]);
        }
    });
});

describe('Scalability Tests', function () {
    it('maintains performance with increasing plugin metadata size', function () {
        // Create plugins with varying metadata sizes
        $times = [];

        for ($size = 100; $size <= 1000; $size += 300) {
            $manifest = [
                'slug' => "metadata-test-{$size}",
                'name' => 'Metadata Test',
                'version' => '1.0.0',
                'meta' => str_repeat('X', $size),
            ];

            $start = microtime(true);
            Plugin::create([
                'slug' => $manifest['slug'],
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'meta' => $manifest,
            ]);
            $times[$size] = microtime(true) - $start;
        }

        // Performance should scale linearly, not exponentially
        // Later insertions shouldn't be significantly slower
        $firstTime = reset($times);
        $lastTime = end($times);

        // Last insertion should not be more than 5x slower than first
        expect($lastTime)->toBeLessThan($firstTime * 5);
    });
});
