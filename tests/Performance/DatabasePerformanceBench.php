<?php

namespace Tests\Performance;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use ArtisanPackUI\CMSFramework\Models\Term;
use PhpBench\Attributes as Bench;

/**
 * Database Performance Benchmarks for CMS Framework
 * 
 * This class contains benchmarks for core database operations including:
 * - CRUD operations on primary models
 * - Relationship loading performance
 * - Query optimization validation
 * - Large dataset handling
 * 
 * @package Tests\Performance
 */
class DatabasePerformanceBench
{
    private $app;
    private $users = [];
    private $content = [];
    private $roles = [];

    public function __construct()
    {
        $this->app = $GLOBALS['laravel_app'];
    }

    /**
     * Set up test data before benchmarks
     */
    #[Bench\BeforeMethods('seedTestData')]
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'user', 'query'])]
    public function benchUserQuery()
    {
        // Test single user query performance
        User::find(1);
    }

    /**
     * Benchmark user creation performance
     */
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'user', 'create'])]
    public function benchUserCreation()
    {
        $userData = [
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'first_name' => 'Test',
            'last_name' => 'User'
        ];
        
        User::create($userData);
    }

    /**
     * Benchmark bulk user queries
     */
    #[Bench\BeforeMethods('seedTestData')]
    #[Bench\Revs(20)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'user', 'bulk'])]
    public function benchBulkUserQuery()
    {
        // Test querying multiple users at once
        User::whereIn('id', [1, 2, 3, 4, 5])->get();
    }

    /**
     * Benchmark user with role relationship loading
     */
    #[Bench\BeforeMethods('seedTestData')]
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'user', 'relationship'])]
    public function benchUserWithRole()
    {
        // Test eager loading user with role
        User::with('role')->find(1);
    }

    /**
     * Benchmark content creation performance
     */
    #[Bench\Revs(30)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'content', 'create'])]
    public function benchContentCreation()
    {
        $contentData = [
            'title' => 'Test Content ' . uniqid(),
            'content' => 'This is test content for performance benchmarking. ' . str_repeat('Lorem ipsum dolor sit amet. ', 100),
            'excerpt' => 'Test excerpt for performance testing',
            'content_type' => 'post',
            'status' => 'published',
            'slug' => 'test-content-' . uniqid(),
            'author_id' => 1,
            'published_at' => now()
        ];
        
        Content::create($contentData);
    }

    /**
     * Benchmark content queries with filtering
     */
    #[Bench\BeforeMethods('seedTestData')]
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'content', 'query'])]
    public function benchContentQuery()
    {
        // Test content queries with common filters
        Content::where('status', 'published')
               ->where('content_type', 'post')
               ->orderBy('published_at', 'desc')
               ->limit(10)
               ->get();
    }

    /**
     * Benchmark content with relationships
     */
    #[Bench\BeforeMethods('seedTestData')]
    #[Bench\Revs(30)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'content', 'relationship'])]
    public function benchContentWithRelationships()
    {
        // Test loading content with author and terms
        Content::with(['author', 'terms'])
               ->where('status', 'published')
               ->limit(5)
               ->get();
    }

    /**
     * Benchmark large dataset queries (N+1 problem prevention)
     */
    #[Bench\BeforeMethods('seedLargeDataset')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(3)]
    #[Bench\Groups(['database', 'content', 'large-dataset'])]
    public function benchLargeDatasetQuery()
    {
        // Test querying large number of content items with relationships
        Content::with(['author', 'terms'])
               ->where('status', 'published')
               ->limit(100)
               ->get();
    }

    /**
     * Benchmark taxonomy and term queries
     */
    #[Bench\BeforeMethods('seedTestData')]
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'taxonomy', 'query'])]
    public function benchTaxonomyQuery()
    {
        // Test taxonomy queries with terms
        Taxonomy::with('terms')->get();
    }

    /**
     * Benchmark complex joins and aggregations
     */
    #[Bench\BeforeMethods('seedTestData')]
    #[Bench\Revs(20)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['database', 'complex', 'aggregation'])]
    public function benchComplexAggregation()
    {
        // Test complex query with counts and joins
        User::withCount(['content' => function ($query) {
                $query->where('status', 'published');
            }])
            ->having('content_count', '>', 0)
            ->get();
    }

    /**
     * Set up basic test data
     */
    public function seedTestData()
    {
        // Create roles
        $adminRole = Role::firstOrCreate([
            'slug' => 'admin'
        ], [
            'name' => 'Administrator',
            'description' => 'Full system access',
            'capabilities' => ['manage_content', 'manage_users']
        ]);
        
        $editorRole = Role::firstOrCreate([
            'slug' => 'editor'
        ], [
            'name' => 'Editor',
            'description' => 'Content management access',
            'capabilities' => ['manage_content']
        ]);

        // Create test users
        for ($i = 1; $i <= 10; $i++) {
            User::firstOrCreate([
                'username' => "testuser{$i}"
            ], [
                'email' => "test{$i}@example.com",
                'password' => bcrypt('password123'),
                'first_name' => "Test{$i}",
                'last_name' => 'User',
                'role_id' => $i <= 2 ? $adminRole->id : $editorRole->id
            ]);
        }

        // Create taxonomies and terms
        $category = Taxonomy::firstOrCreate([
            'slug' => 'category'
        ], [
            'name' => 'Categories',
            'description' => 'Content categories'
        ]);

        $tag = Taxonomy::firstOrCreate([
            'slug' => 'tags'
        ], [
            'name' => 'Tags',
            'description' => 'Content tags'
        ]);

        // Create some terms
        Term::firstOrCreate([
            'slug' => 'technology',
            'taxonomy_id' => $category->id
        ], [
            'name' => 'Technology',
            'description' => 'Technology related content'
        ]);

        Term::firstOrCreate([
            'slug' => 'php',
            'taxonomy_id' => $tag->id
        ], [
            'name' => 'PHP',
            'description' => 'PHP programming language'
        ]);

        // Create test content
        for ($i = 1; $i <= 20; $i++) {
            Content::firstOrCreate([
                'slug' => "test-content-{$i}"
            ], [
                'title' => "Test Content {$i}",
                'content' => "This is test content {$i} for benchmarking. " . str_repeat('Lorem ipsum dolor sit amet. ', 50),
                'excerpt' => "Excerpt for test content {$i}",
                'content_type' => 'post',
                'status' => $i % 3 === 0 ? 'draft' : 'published',
                'author_id' => (($i - 1) % 10) + 1,
                'published_at' => now()->subDays(rand(1, 30))
            ]);
        }
    }

    /**
     * Set up large dataset for performance testing
     */
    public function seedLargeDataset()
    {
        $this->seedTestData();
        
        // Create additional content for large dataset testing
        for ($i = 21; $i <= 200; $i++) {
            Content::firstOrCreate([
                'slug' => "large-content-{$i}"
            ], [
                'title' => "Large Dataset Content {$i}",
                'content' => "Large dataset content {$i}. " . str_repeat('Content for large dataset testing. ', 100),
                'excerpt' => "Large dataset excerpt {$i}",
                'content_type' => 'post',
                'status' => 'published',
                'author_id' => (($i - 1) % 10) + 1,
                'published_at' => now()->subDays(rand(1, 60))
            ]);
        }
    }
}