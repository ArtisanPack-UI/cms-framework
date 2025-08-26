<?php

namespace Tests\Performance;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Media;
use PhpBench\Attributes as Bench;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Load Testing Benchmarks for CMS Framework
 * 
 * This class simulates high-load scenarios including:
 * - Concurrent user simulation
 * - High-volume content creation
 * - Media upload stress testing
 * - Authentication load testing
 * - Database connection stress testing
 * 
 * @package Tests\Performance
 */
class LoadTestingBench
{
    private $app;
    private $stopwatch;
    private $users = [];
    private $tokens = [];

    public function __construct()
    {
        $this->app = $GLOBALS['laravel_app'];
        $this->stopwatch = new Stopwatch();
    }

    /**
     * Simulate concurrent user authentication load
     */
    #[Bench\BeforeMethods('seedBasicData')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['load', 'auth', 'concurrent'])]
    public function benchConcurrentAuthentication()
    {
        $this->stopwatch->start('concurrent_auth');
        
        // Simulate 50 concurrent authentication attempts
        for ($i = 1; $i <= 50; $i++) {
            $email = "loadtest{$i}@example.com";
            $password = 'password123';
            
            // Simulate authentication request
            $request = \Illuminate\Http\Request::create('/api/cms/auth/login', 'POST', [
                'email' => $email,
                'password' => $password,
                'device_name' => "LoadTest{$i}"
            ]);
            
            $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
        }
        
        $this->stopwatch->stop('concurrent_auth');
    }

    /**
     * Simulate high-volume content creation load
     */
    #[Bench\BeforeMethods('setupAuthenticatedUsers')]
    #[Bench\Revs(5)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['load', 'content', 'bulk-creation'])]
    public function benchBulkContentCreation()
    {
        $this->stopwatch->start('bulk_content');
        
        // Create 100 content items rapidly
        for ($i = 1; $i <= 100; $i++) {
            $contentData = [
                'title' => "Load Test Content {$i}",
                'content' => "Load testing content {$i}. " . str_repeat('Bulk content creation stress test. ', 20),
                'excerpt' => "Load test excerpt {$i}",
                'content_type' => 'post',
                'status' => 'published',
                'slug' => "load-test-content-{$i}-" . uniqid(),
                'author_id' => (($i - 1) % count($this->users)) + 1,
                'published_at' => now()
            ];
            
            Content::create($contentData);
        }
        
        $this->stopwatch->stop('bulk_content');
    }

    /**
     * Simulate concurrent API requests from multiple users
     */
    #[Bench\BeforeMethods('setupAuthenticatedUsers')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['load', 'api', 'concurrent-requests'])]
    public function benchConcurrentApiRequests()
    {
        $this->stopwatch->start('concurrent_api');
        
        $endpoints = [
            '/api/cms/content',
            '/api/cms/users',
            '/api/cms/roles',
            '/api/cms/settings'
        ];
        
        // Simulate 25 users each making 4 requests (100 total requests)
        for ($userIndex = 0; $userIndex < 25; $userIndex++) {
            $token = $this->tokens[$userIndex % count($this->tokens)];
            
            foreach ($endpoints as $endpoint) {
                $request = \Illuminate\Http\Request::create($endpoint, 'GET');
                $request->headers->set('Authorization', 'Bearer ' . $token);
                
                $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
            }
        }
        
        $this->stopwatch->stop('concurrent_api');
    }

    /**
     * Simulate database connection pool stress
     */
    #[Bench\BeforeMethods('seedBasicData')]
    #[Bench\Revs(5)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['load', 'database', 'connection-stress'])]
    public function benchDatabaseConnectionStress()
    {
        $this->stopwatch->start('db_connections');
        
        // Simulate multiple simultaneous database operations
        for ($i = 1; $i <= 50; $i++) {
            // Mix of different query types to stress connection pool
            User::where('id', '>', 0)->count();
            Content::where('status', 'published')->limit(10)->get();
            Role::with('users')->get();
            
            // Create and delete operations
            $tempUser = User::create([
                'username' => "temp_stress_user_{$i}",
                'email' => "temp_stress_{$i}@example.com",
                'password' => bcrypt('temp123'),
                'first_name' => 'Temp',
                'last_name' => 'Stress'
            ]);
            
            $tempUser->delete();
        }
        
        $this->stopwatch->stop('db_connections');
    }

    /**
     * Simulate high-frequency read operations
     */
    #[Bench\BeforeMethods('setupLargeDataset')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['load', 'database', 'read-intensive'])]
    public function benchReadIntensiveLoad()
    {
        $this->stopwatch->start('read_intensive');
        
        // Simulate 200 rapid read operations
        for ($i = 1; $i <= 200; $i++) {
            $randomId = rand(1, 50);
            
            // Mix of different read patterns
            switch ($i % 4) {
                case 0:
                    User::find($randomId);
                    break;
                case 1:
                    Content::where('status', 'published')->limit(5)->get();
                    break;
                case 2:
                    Content::with('author')->find($randomId);
                    break;
                case 3:
                    User::withCount('content')->limit(10)->get();
                    break;
            }
        }
        
        $this->stopwatch->stop('read_intensive');
    }

    /**
     * Simulate memory-intensive operations
     */
    #[Bench\BeforeMethods('setupLargeDataset')]
    #[Bench\Revs(5)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['load', 'memory', 'intensive-operations'])]
    public function benchMemoryIntensiveOperations()
    {
        $this->stopwatch->start('memory_intensive');
        
        // Load large datasets into memory
        $allContent = Content::with(['author', 'terms'])->limit(500)->get();
        $allUsers = User::with(['role', 'content'])->limit(100)->get();
        
        // Process data to simulate real-world operations
        $processedData = [];
        foreach ($allContent as $content) {
            $processedData[] = [
                'id' => $content->id,
                'title' => $content->title,
                'author' => $content->author?->username ?? 'unknown',
                'word_count' => str_word_count($content->content ?? ''),
                'excerpt' => substr($content->content ?? '', 0, 100)
            ];
        }
        
        // Simulate complex data operations
        $groupedByAuthor = collect($processedData)->groupBy('author');
        $wordCounts = collect($processedData)->pluck('word_count')->sort();
        
        unset($allContent, $allUsers, $processedData, $groupedByAuthor, $wordCounts);
        
        $this->stopwatch->stop('memory_intensive');
    }

    /**
     * Simulate burst traffic patterns
     */
    #[Bench\BeforeMethods('setupAuthenticatedUsers')]
    #[Bench\Revs(5)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['load', 'traffic', 'burst-patterns'])]
    public function benchBurstTrafficPattern()
    {
        $this->stopwatch->start('burst_traffic');
        
        // Simulate traffic burst: rapid requests followed by quieter period
        
        // Burst phase: 100 rapid requests
        for ($i = 1; $i <= 100; $i++) {
            $token = $this->tokens[($i - 1) % count($this->tokens)];
            $endpoint = ['/api/cms/content', '/api/cms/users'][$i % 2];
            
            $request = \Illuminate\Http\Request::create($endpoint, 'GET');
            $request->headers->set('Authorization', 'Bearer ' . $token);
            
            $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
        }
        
        // Simulate brief quiet period (represented by fewer operations)
        for ($i = 1; $i <= 10; $i++) {
            User::find(1);
        }
        
        // Another burst phase
        for ($i = 1; $i <= 50; $i++) {
            $contentData = [
                'title' => "Burst Content {$i}",
                'content' => "Burst traffic content {$i}",
                'content_type' => 'post',
                'status' => 'draft',
                'slug' => "burst-content-{$i}-" . uniqid(),
                'author_id' => 1
            ];
            
            Content::create($contentData);
        }
        
        $this->stopwatch->stop('burst_traffic');
    }

    /**
     * Simulate authentication token validation load
     */
    #[Bench\BeforeMethods('setupAuthenticatedUsers')]
    #[Bench\Revs(20)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['load', 'auth', 'token-validation'])]
    public function benchAuthTokenValidationLoad()
    {
        $this->stopwatch->start('token_validation');
        
        // Simulate 100 requests that require token validation
        for ($i = 1; $i <= 100; $i++) {
            $token = $this->tokens[($i - 1) % count($this->tokens)];
            
            $request = \Illuminate\Http\Request::create('/api/cms/auth/user', 'GET');
            $request->headers->set('Authorization', 'Bearer ' . $token);
            
            $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
        }
        
        $this->stopwatch->stop('token_validation');
    }

    /**
     * Set up basic test data
     */
    public function seedBasicData()
    {
        // Create admin role
        $adminRole = Role::firstOrCreate([
            'slug' => 'admin'
        ], [
            'name' => 'Administrator',
            'description' => 'Full system access',
            'capabilities' => ['manage_content', 'manage_users']
        ]);

        // Create editor role
        $editorRole = Role::firstOrCreate([
            'slug' => 'editor'
        ], [
            'name' => 'Editor',
            'description' => 'Content management',
            'capabilities' => ['manage_content']
        ]);

        // Create load test users
        for ($i = 1; $i <= 50; $i++) {
            User::firstOrCreate([
                'username' => "loadtest{$i}"
            ], [
                'email' => "loadtest{$i}@example.com",
                'password' => bcrypt('password123'),
                'first_name' => "LoadTest{$i}",
                'last_name' => 'User',
                'role_id' => $i <= 5 ? $adminRole->id : $editorRole->id
            ]);
        }
    }

    /**
     * Set up authenticated users with tokens
     */
    public function setupAuthenticatedUsers()
    {
        $this->seedBasicData();
        
        // Create tokens for first 10 users
        for ($i = 1; $i <= 10; $i++) {
            $user = User::where('username', "loadtest{$i}")->first();
            if ($user) {
                $this->users[] = $user;
                $token = $user->createToken("LoadTest{$i}Token");
                $this->tokens[] = $token->plainTextToken;
            }
        }
    }

    /**
     * Set up large dataset for intensive testing
     */
    public function setupLargeDataset()
    {
        $this->setupAuthenticatedUsers();
        
        // Create large content dataset
        for ($i = 1; $i <= 500; $i++) {
            Content::firstOrCreate([
                'slug' => "large-dataset-{$i}"
            ], [
                'title' => "Large Dataset Content {$i}",
                'content' => "Large dataset content {$i}. " . str_repeat('Load testing large dataset content. ', 50),
                'excerpt' => "Large dataset excerpt {$i}",
                'content_type' => 'post',
                'status' => 'published',
                'author_id' => (($i - 1) % count($this->users)) + 1,
                'published_at' => now()->subDays(rand(1, 100))
            ]);
        }
    }
}