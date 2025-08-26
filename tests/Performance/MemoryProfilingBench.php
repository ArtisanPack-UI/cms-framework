<?php

namespace Tests\Performance;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use ArtisanPackUI\CMSFramework\Models\Term;
use PhpBench\Attributes as Bench;

/**
 * Memory Usage Profiling Benchmarks for CMS Framework
 * 
 * This class analyzes memory usage patterns including:
 * - Memory leak detection
 * - Peak memory usage measurement
 * - Garbage collection impact analysis
 * - Large object handling performance
 * - Memory-intensive operation profiling
 * 
 * @package Tests\Performance
 */
class MemoryProfilingBench
{
    private $app;
    private $baselineMemory;

    public function __construct()
    {
        $this->app = $GLOBALS['laravel_app'];
        $this->baselineMemory = memory_get_usage(true);
    }

    /**
     * Measure memory usage during user creation operations
     */
    #[Bench\BeforeMethods('recordBaselineMemory')]
    #[Bench\AfterMethods('measureMemoryUsage')]
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['memory', 'user', 'creation'])]
    public function benchUserCreationMemoryUsage()
    {
        $initialMemory = memory_get_usage(true);
        
        // Create 100 users and measure memory growth
        for ($i = 1; $i <= 100; $i++) {
            $userData = [
                'username' => 'memtest_user_' . $i . '_' . uniqid(),
                'email' => 'memtest_' . $i . '_' . uniqid() . '@example.com',
                'password' => bcrypt('password123'),
                'first_name' => 'MemTest',
                'last_name' => 'User' . $i,
                'bio' => str_repeat('This is a bio for memory testing purposes. ', 10)
            ];
            
            User::create($userData);
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryGrowth = $finalMemory - $initialMemory;
        
        // Store memory metrics for analysis
        $this->recordMemoryMetric('user_creation', $initialMemory, $finalMemory, $memoryGrowth);
        
        // Force garbage collection to test cleanup
        gc_collect_cycles();
        
        return $memoryGrowth;
    }

    /**
     * Detect potential memory leaks in content operations
     */
    #[Bench\BeforeMethods('seedBasicData')]
    #[Bench\Revs(20)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['memory', 'content', 'leak-detection'])]
    public function benchContentMemoryLeakDetection()
    {
        $memoryReadings = [];
        
        // Perform multiple cycles of content creation/deletion to detect leaks
        for ($cycle = 1; $cycle <= 10; $cycle++) {
            $cycleStartMemory = memory_get_usage(true);
            
            // Create content items
            $createdContent = [];
            for ($i = 1; $i <= 50; $i++) {
                $content = Content::create([
                    'title' => "Memory Leak Test Content {$cycle}_{$i}",
                    'content' => str_repeat('Memory leak detection content. ', 100),
                    'excerpt' => "Memory test excerpt {$cycle}_{$i}",
                    'content_type' => 'post',
                    'status' => 'published',
                    'slug' => "memory-leak-test-{$cycle}-{$i}-" . uniqid(),
                    'author_id' => 1,
                    'published_at' => now()
                ]);
                
                $createdContent[] = $content;
            }
            
            // Delete all created content
            foreach ($createdContent as $content) {
                $content->delete();
            }
            
            // Clear any potential references
            unset($createdContent);
            
            // Force garbage collection
            gc_collect_cycles();
            
            $cycleEndMemory = memory_get_usage(true);
            $memoryReadings[] = [
                'cycle' => $cycle,
                'start_memory' => $cycleStartMemory,
                'end_memory' => $cycleEndMemory,
                'memory_diff' => $cycleEndMemory - $cycleStartMemory
            ];
        }
        
        // Analyze memory leak patterns
        $this->analyzeMemoryLeakPattern($memoryReadings);
    }

    /**
     * Measure peak memory usage during large dataset operations
     */
    #[Bench\BeforeMethods('seedLargeDataset')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['memory', 'peak-usage', 'large-dataset'])]
    public function benchPeakMemoryUsage()
    {
        $initialMemory = memory_get_usage(true);
        $peakMemory = $initialMemory;
        
        // Load large datasets and track peak memory
        $allUsers = User::with(['role', 'content'])->get();
        $peakMemory = max($peakMemory, memory_get_usage(true));
        
        $allContent = Content::with(['author', 'terms'])->get();
        $peakMemory = max($peakMemory, memory_get_usage(true));
        
        // Process data in memory-intensive ways
        $processedUsers = $allUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'content_count' => $user->content->count(),
                'role_name' => $user->role?->name ?? 'No Role',
                'profile_data' => [
                    'full_name' => ($user->first_name ?? '') . ' ' . ($user->last_name ?? ''),
                    'bio_length' => strlen($user->bio ?? ''),
                    'has_website' => !empty($user->website)
                ]
            ];
        });
        $peakMemory = max($peakMemory, memory_get_usage(true));
        
        $contentAnalysis = $allContent->map(function ($content) {
            return [
                'id' => $content->id,
                'title' => $content->title,
                'word_count' => str_word_count($content->content ?? ''),
                'char_count' => strlen($content->content ?? ''),
                'excerpt' => substr($content->content ?? '', 0, 200),
                'author_name' => $content->author?->username ?? 'Unknown',
                'status' => $content->status,
                'published_date' => $content->published_at?->format('Y-m-d')
            ];
        });
        $peakMemory = max($peakMemory, memory_get_usage(true));
        
        // Group and aggregate data
        $usersByRole = $processedUsers->groupBy('role_name');
        $contentByAuthor = $contentAnalysis->groupBy('author_name');
        $peakMemory = max($peakMemory, memory_get_usage(true));
        
        $finalMemory = memory_get_usage(true);
        $totalMemoryUsed = $peakMemory - $initialMemory;
        
        // Clean up
        unset($allUsers, $allContent, $processedUsers, $contentAnalysis, $usersByRole, $contentByAuthor);
        gc_collect_cycles();
        
        $this->recordMemoryMetric('peak_usage', $initialMemory, $finalMemory, $totalMemoryUsed, $peakMemory);
        
        return $totalMemoryUsed;
    }

    /**
     * Analyze garbage collection impact on performance
     */
    #[Bench\Revs(20)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['memory', 'garbage-collection', 'performance'])]
    public function benchGarbageCollectionImpact()
    {
        $gcStats = [];
        
        // Test performance with manual garbage collection
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Create and destroy objects to generate garbage
        $objects = [];
        for ($i = 1; $i <= 1000; $i++) {
            $objects[] = [
                'id' => $i,
                'data' => str_repeat('Garbage collection test data. ', 20),
                'timestamp' => microtime(true),
                'nested' => [
                    'level1' => [
                        'level2' => [
                            'data' => str_repeat('Nested data for GC testing. ', 10)
                        ]
                    ]
                ]
            ];
        }
        
        $beforeGcMemory = memory_get_usage(true);
        $beforeGcTime = microtime(true);
        
        // Clear references to generate garbage
        unset($objects);
        
        // Measure garbage collection impact
        $gcStartTime = microtime(true);
        $cyclesCollected = gc_collect_cycles();
        $gcEndTime = microtime(true);
        
        $afterGcMemory = memory_get_usage(true);
        $endTime = microtime(true);
        
        $gcStats = [
            'cycles_collected' => $cyclesCollected,
            'gc_time' => $gcEndTime - $gcStartTime,
            'total_time' => $endTime - $startTime,
            'memory_before_gc' => $beforeGcMemory,
            'memory_after_gc' => $afterGcMemory,
            'memory_freed' => $beforeGcMemory - $afterGcMemory,
            'gc_overhead_percent' => (($gcEndTime - $gcStartTime) / ($endTime - $startTime)) * 100
        ];
        
        $this->recordGcStats($gcStats);
        
        return $gcStats['gc_time'];
    }

    /**
     * Test memory usage with large object collections
     */
    #[Bench\BeforeMethods('seedBasicData')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['memory', 'large-objects', 'collections'])]
    public function benchLargeObjectCollections()
    {
        $initialMemory = memory_get_usage(true);
        
        // Create large collections of objects
        $largeCollection = collect();
        
        for ($i = 1; $i <= 5000; $i++) {
            $largeObject = [
                'id' => $i,
                'title' => "Large Object {$i}",
                'content' => str_repeat('Large object content for memory testing. ', 50),
                'metadata' => [
                    'created_at' => now(),
                    'tags' => array_fill(0, 20, "tag_{$i}_" . rand(1000, 9999)),
                    'properties' => array_fill_keys(range('a', 'z'), rand(1, 1000))
                ],
                'relations' => array_fill(0, 10, [
                    'type' => 'related_object',
                    'data' => str_repeat('Related object data. ', 10)
                ])
            ];
            
            $largeCollection->push($largeObject);
        }
        
        $afterCreationMemory = memory_get_usage(true);
        
        // Perform operations on large collection
        $filtered = $largeCollection->filter(function ($item) {
            return $item['id'] % 2 === 0;
        });
        
        $mapped = $largeCollection->map(function ($item) {
            return [
                'id' => $item['id'],
                'title_length' => strlen($item['title']),
                'content_words' => str_word_count($item['content']),
                'tag_count' => count($item['metadata']['tags'])
            ];
        });
        
        $grouped = $largeCollection->groupBy(function ($item) {
            return $item['id'] % 10;
        });
        
        $peakMemory = memory_get_usage(true);
        
        // Clean up
        unset($largeCollection, $filtered, $mapped, $grouped);
        gc_collect_cycles();
        
        $finalMemory = memory_get_usage(true);
        
        $memoryUsage = [
            'initial' => $initialMemory,
            'after_creation' => $afterCreationMemory,
            'peak' => $peakMemory,
            'final' => $finalMemory,
            'creation_cost' => $afterCreationMemory - $initialMemory,
            'operation_cost' => $peakMemory - $afterCreationMemory,
            'cleanup_effectiveness' => $peakMemory - $finalMemory
        ];
        
        $this->recordMemoryMetric('large_objects', $initialMemory, $finalMemory, $peakMemory - $initialMemory, $peakMemory);
        
        return $memoryUsage['creation_cost'];
    }

    /**
     * Test memory usage with string operations
     */
    #[Bench\Revs(30)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['memory', 'string-operations', 'concatenation'])]
    public function benchStringMemoryUsage()
    {
        $initialMemory = memory_get_usage(true);
        
        // Test string concatenation memory usage
        $largeString = '';
        for ($i = 1; $i <= 1000; $i++) {
            $largeString .= "String concatenation test iteration {$i}. This is testing memory usage during string operations. ";
        }
        
        $afterConcatenationMemory = memory_get_usage(true);
        
        // Test string manipulation
        $processedStrings = [];
        for ($i = 1; $i <= 500; $i++) {
            $base = "Processing string {$i}: " . str_repeat('data ', 50);
            $processedStrings[] = [
                'original' => $base,
                'uppercase' => strtoupper($base),
                'words' => explode(' ', $base),
                'reversed' => strrev($base),
                'encoded' => base64_encode($base)
            ];
        }
        
        $peakMemory = memory_get_usage(true);
        
        // Cleanup
        unset($largeString, $processedStrings);
        gc_collect_cycles();
        
        $finalMemory = memory_get_usage(true);
        
        return $peakMemory - $initialMemory;
    }

    /**
     * Record baseline memory usage
     */
    public function recordBaselineMemory()
    {
        $this->baselineMemory = memory_get_usage(true);
    }

    /**
     * Measure current memory usage against baseline
     */
    public function measureMemoryUsage()
    {
        $currentMemory = memory_get_usage(true);
        $memoryIncrease = $currentMemory - $this->baselineMemory;
        
        // Log memory usage (in a real implementation, this would go to a logging system)
        error_log("Memory increase: " . number_format($memoryIncrease / 1024 / 1024, 2) . " MB");
    }

    /**
     * Set up basic test data
     */
    public function seedBasicData()
    {
        // Create basic roles
        Role::firstOrCreate(['slug' => 'admin'], [
            'name' => 'Administrator',
            'description' => 'Full access',
            'capabilities' => ['manage_content', 'manage_users']
        ]);

        // Create test user
        User::firstOrCreate(['username' => 'memtest_admin'], [
            'email' => 'memtest@example.com',
            'password' => bcrypt('password123'),
            'first_name' => 'Memory',
            'last_name' => 'Test',
            'role_id' => 1
        ]);
    }

    /**
     * Set up large dataset for memory testing
     */
    public function seedLargeDataset()
    {
        $this->seedBasicData();
        
        // Create users
        for ($i = 1; $i <= 100; $i++) {
            User::firstOrCreate(['username' => "memuser{$i}"], [
                'email' => "memuser{$i}@example.com",
                'password' => bcrypt('password123'),
                'first_name' => "MemUser{$i}",
                'last_name' => 'Test',
                'role_id' => 1,
                'bio' => str_repeat('User bio for memory testing. ', 20)
            ]);
        }

        // Create content
        for ($i = 1; $i <= 200; $i++) {
            Content::firstOrCreate(['slug' => "memcontent-{$i}"], [
                'title' => "Memory Test Content {$i}",
                'content' => str_repeat('Memory testing content. ', 200),
                'excerpt' => "Memory test excerpt {$i}",
                'content_type' => 'post',
                'status' => 'published',
                'author_id' => (($i - 1) % 100) + 1,
                'published_at' => now()->subDays(rand(1, 100))
            ]);
        }
    }

    /**
     * Record memory metrics for analysis
     */
    private function recordMemoryMetric(string $operation, int $initialMemory, int $finalMemory, int $memoryGrowth, int $peakMemory = null)
    {
        $metrics = [
            'operation' => $operation,
            'initial_memory' => $initialMemory,
            'final_memory' => $finalMemory,
            'memory_growth' => $memoryGrowth,
            'peak_memory' => $peakMemory ?? $finalMemory,
            'timestamp' => microtime(true)
        ];
        
        // In a real implementation, store this in a database or file for trend analysis
        file_put_contents(
            storage_path('performance/memory_metrics.json'),
            json_encode($metrics) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Analyze memory leak patterns
     */
    private function analyzeMemoryLeakPattern(array $memoryReadings)
    {
        $leakDetected = false;
        $threshold = 1024 * 1024; // 1MB threshold
        
        foreach ($memoryReadings as $reading) {
            if ($reading['memory_diff'] > $threshold) {
                $leakDetected = true;
                break;
            }
        }
        
        $analysis = [
            'leak_detected' => $leakDetected,
            'readings' => $memoryReadings,
            'average_growth' => array_sum(array_column($memoryReadings, 'memory_diff')) / count($memoryReadings)
        ];
        
        // Store leak analysis
        file_put_contents(
            storage_path('performance/memory_leak_analysis.json'),
            json_encode($analysis) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Record garbage collection statistics
     */
    private function recordGcStats(array $gcStats)
    {
        // Store GC performance data
        file_put_contents(
            storage_path('performance/gc_stats.json'),
            json_encode($gcStats) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}