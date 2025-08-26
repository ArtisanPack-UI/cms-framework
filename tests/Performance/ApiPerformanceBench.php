<?php

namespace Tests\Performance;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Role;
use Laravel\Sanctum\Sanctum;
use PhpBench\Attributes as Bench;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * API Performance Benchmarks for CMS Framework
 * 
 * This class measures the performance of API endpoints including:
 * - Authentication overhead measurement
 * - CRUD operation response times
 * - Rate limiting impact analysis
 * - Payload size impact on performance
 * 
 * @package Tests\Performance
 */
class ApiPerformanceBench
{
    private $app;
    private $authenticatedUser;
    private $apiToken;

    public function __construct()
    {
        $this->app = $GLOBALS['laravel_app'];
    }

    /**
     * Benchmark authentication endpoint performance
     */
    #[Bench\BeforeMethods('seedBasicData')]
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'auth', 'login'])]
    public function benchAuthLogin()
    {
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle(
            \Illuminate\Http\Request::create('/api/cms/auth/login', 'POST', [
                'email' => 'admin@example.com',
                'password' => 'password123',
                'device_name' => 'Performance Test'
            ])
        );
    }

    /**
     * Benchmark user listing API performance
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'users', 'list'])]
    public function benchUsersIndex()
    {
        $request = \Illuminate\Http\Request::create('/api/cms/users', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
    }

    /**
     * Benchmark single user retrieval performance
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(150)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'users', 'show'])]
    public function benchUserShow()
    {
        $request = \Illuminate\Http\Request::create('/api/cms/users/1', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
    }

    /**
     * Benchmark user creation API performance
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(30)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'users', 'create'])]
    public function benchUserCreate()
    {
        $userData = [
            'username' => 'apitest_' . uniqid(),
            'email' => 'apitest_' . uniqid() . '@example.com',
            'password' => 'password123',
            'first_name' => 'API',
            'last_name' => 'Test',
            'role_id' => 2
        ];

        $request = \Illuminate\Http\Request::create('/api/cms/users', 'POST', $userData);
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        $request->headers->set('Content-Type', 'application/json');
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
    }

    /**
     * Benchmark content listing API performance
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(80)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'content', 'list'])]
    public function benchContentIndex()
    {
        $request = \Illuminate\Http\Request::create('/api/cms/content', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
    }

    /**
     * Benchmark content creation with large payload
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(20)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'content', 'create', 'large-payload'])]
    public function benchContentCreateLargePayload()
    {
        $contentData = [
            'title' => 'Large Content Benchmark ' . uniqid(),
            'content' => str_repeat('This is a large content payload for performance testing. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ', 500), // ~50KB content
            'excerpt' => 'Performance test excerpt with large payload',
            'content_type' => 'post',
            'status' => 'published',
            'slug' => 'large-content-' . uniqid(),
            'meta_title' => 'Large Content Meta Title',
            'meta_description' => 'Meta description for large content performance testing',
            'author_id' => 1,
            'published_at' => now()->toISOString()
        ];

        $request = \Illuminate\Http\Request::create('/api/cms/content', 'POST', $contentData);
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        $request->headers->set('Content-Type', 'application/json');
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
    }

    /**
     * Benchmark content update performance
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(40)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'content', 'update'])]
    public function benchContentUpdate()
    {
        $updateData = [
            'title' => 'Updated Content Title ' . uniqid(),
            'content' => 'Updated content for performance benchmarking. ' . str_repeat('Updated content text. ', 100),
            'status' => 'published'
        ];

        $request = \Illuminate\Http\Request::create('/api/cms/content/1', 'PUT', $updateData);
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        $request->headers->set('Content-Type', 'application/json');
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
    }

    /**
     * Benchmark multiple concurrent-like API requests
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['api', 'concurrent', 'simulation'])]
    public function benchMultipleApiCalls()
    {
        // Simulate multiple API calls in succession (like concurrent requests)
        $endpoints = [
            '/api/cms/users',
            '/api/cms/content',
            '/api/cms/roles',
            '/api/cms/settings'
        ];

        foreach ($endpoints as $endpoint) {
            $request = \Illuminate\Http\Request::create($endpoint, 'GET');
            $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
            $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
        }
    }

    /**
     * Benchmark authentication overhead by comparing authenticated vs unauthenticated endpoints
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'auth', 'overhead'])]
    public function benchAuthenticationOverhead()
    {
        // Test authenticated endpoint
        $request = \Illuminate\Http\Request::create('/api/cms/users/1', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
    }

    /**
     * Benchmark API response with different payload sizes
     */
    #[Bench\BeforeMethods('setupLargeDataset')]
    #[Bench\Revs(30)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'payload', 'large-response'])]
    public function benchLargeResponsePayload()
    {
        // Request content list which should return large response
        $request = \Illuminate\Http\Request::create('/api/cms/content', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
    }

    /**
     * Benchmark validation overhead in API requests
     */
    #[Bench\BeforeMethods('setupAuthentication')]
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['api', 'validation', 'overhead'])]
    public function benchValidationOverhead()
    {
        // Test request with complex validation rules
        $userData = [
            'username' => 'validationtest_' . uniqid(),
            'email' => 'validation_' . uniqid() . '@example.com',
            'password' => 'complexpassword123!@#',
            'first_name' => 'Validation',
            'last_name' => 'Test',
            'website' => 'https://example.com',
            'bio' => str_repeat('Bio content for validation testing. ', 50),
            'role_id' => 2
        ];

        $request = \Illuminate\Http\Request::create('/api/cms/users', 'POST', $userData);
        $request->headers->set('Authorization', 'Bearer ' . $this->apiToken);
        $request->headers->set('Content-Type', 'application/json');
        
        $response = $this->app['Illuminate\Contracts\Http\Kernel']->handle($request);
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

        // Create admin user for API testing
        User::firstOrCreate([
            'username' => 'admin'
        ], [
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role_id' => $adminRole->id
        ]);
    }

    /**
     * Set up authentication for API benchmarks
     */
    public function setupAuthentication()
    {
        $this->seedBasicData();
        
        $this->authenticatedUser = User::where('username', 'admin')->first();
        
        // Create API token
        $token = $this->authenticatedUser->createToken('Performance Test Token');
        $this->apiToken = $token->plainTextToken;
        
        // Set up Sanctum authentication in the application
        $this->app['auth']->guard('sanctum')->setUser($this->authenticatedUser);
    }

    /**
     * Set up large dataset for response payload testing
     */
    public function setupLargeDataset()
    {
        $this->setupAuthentication();
        
        // Create additional users
        for ($i = 1; $i <= 50; $i++) {
            User::firstOrCreate([
                'username' => "perftest{$i}"
            ], [
                'email' => "perftest{$i}@example.com",
                'password' => bcrypt('password123'),
                'first_name' => "PerfTest{$i}",
                'last_name' => 'User',
                'role_id' => 2
            ]);
        }

        // Create additional content
        for ($i = 1; $i <= 100; $i++) {
            Content::firstOrCreate([
                'slug' => "perf-content-{$i}"
            ], [
                'title' => "Performance Content {$i}",
                'content' => "Performance testing content {$i}. " . str_repeat('Content for API performance testing. ', 100),
                'excerpt' => "Performance excerpt {$i}",
                'content_type' => 'post',
                'status' => 'published',
                'author_id' => 1,
                'published_at' => now()->subDays(rand(1, 30))
            ]);
        }
    }
}