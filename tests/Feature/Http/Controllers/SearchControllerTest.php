<?php

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Http\Controllers;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\SearchAnalytics;
use ArtisanPackUI\CMSFramework\Models\SearchIndex;
use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * SearchControllerTest.
 *
 * Feature tests for the SearchController API endpoints.
 *
 * @package ArtisanPackUI\CMSFramework\Tests\Feature\Http\Controllers
 * @since   1.2.0
 */
class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SearchService instance.
     *
     * @var SearchService
     */
    protected SearchService $searchService;

    /**
     * Test user instance.
     *
     * @var User
     */
    protected User $user;

    /**
     * Admin user instance.
     *
     * @var User
     */
    protected User $adminUser;

    /**
     * Set up the test case.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->searchService = app(SearchService::class);
        
        // Enable search functionality for tests
        Config::set('cms.search.enabled', true);
        Config::set('cms.search.analytics_enabled', true);
        Config::set('cms.search.facets.enabled', true);
        Config::set('cms.search.suggestions.enabled', true);
        
        // Create test users
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo('manage_search');
    }

    /**
     * Test main search endpoint returns successful response.
     */
    public function test_search_endpoint_returns_successful_response(): void
    {
        // Create and index test content
        $content = Content::factory()->create([
            'title' => 'Laravel Tutorial',
            'content' => 'Learn Laravel framework',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->searchService->indexModel($content);

        $response = $this->getJson('/api/cms/search?q=Laravel');

        $response->assertSuccessful()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'query',
                         'filters',
                         'results' => [
                             '*' => [
                                 'id',
                                 'type',
                                 'content_type',
                                 'title',
                                 'excerpt',
                                 'status',
                                 'published_at',
                                 'relevance_score',
                                 'search_score',
                             ]
                         ],
                         'pagination' => [
                             'current_page',
                             'per_page',
                             'total',
                             'total_pages',
                             'has_more',
                         ],
                         'meta' => [
                             'execution_time_ms',
                             'result_count',
                             'facets',
                         ],
                     ],
                 ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(1, $response->json('data.results'));
        $this->assertEquals('Laravel Tutorial', $response->json('data.results.0.title'));
    }

    /**
     * Test search endpoint with filters.
     */
    public function test_search_endpoint_with_filters(): void
    {
        // Create content with different types
        $post = Content::factory()->create([
            'title' => 'Blog Post',
            'type' => 'post',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $page = Content::factory()->create([
            'title' => 'Static Page',
            'type' => 'page',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->searchService->indexModel($post);
        $this->searchService->indexModel($page);

        // Search with type filter
        $response = $this->getJson('/api/cms/search?type=post');

        $response->assertSuccessful();
        $this->assertCount(1, $response->json('data.results'));
        $this->assertEquals('Blog Post', $response->json('data.results.0.title'));
    }

    /**
     * Test search endpoint validation.
     */
    public function test_search_endpoint_validation(): void
    {
        // Test with invalid per_page
        $response = $this->getJson('/api/cms/search?per_page=999');
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['per_page']);

        // Test with invalid sort option
        $response = $this->getJson('/api/cms/search?sort=invalid');
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['sort']);

        // Test with invalid date range
        $response = $this->getJson('/api/cms/search?date_from=2023-01-10&date_to=2023-01-05');
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['date_to']);
    }

    /**
     * Test search endpoint when search is disabled.
     */
    public function test_search_endpoint_when_disabled(): void
    {
        Config::set('cms.search.enabled', false);

        $response = $this->getJson('/api/cms/search?q=test');

        $response->assertStatus(503)
                 ->assertJson([
                     'error' => 'Search functionality is disabled',
                 ]);
    }

    /**
     * Test facets endpoint returns correct structure.
     */
    public function test_facets_endpoint_returns_correct_structure(): void
    {
        // Create content with different properties for facets
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $content1 = Content::factory()->create([
            'type' => 'post',
            'author_id' => $user1->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $content2 = Content::factory()->create([
            'type' => 'page',
            'author_id' => $user2->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->searchService->indexModel($content1);
        $this->searchService->indexModel($content2);

        $response = $this->getJson('/api/cms/search/facets');

        $response->assertSuccessful()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'query',
                         'filters',
                         'facets' => [
                             'types' => [
                                 '*' => [
                                     'value',
                                     'label',
                                     'count',
                                 ]
                             ],
                             'authors' => [
                                 '*' => [
                                     'value',
                                     'label',
                                     'count',
                                 ]
                             ],
                             'date_ranges',
                             'status',
                         ],
                     ],
                 ]);

        $facets = $response->json('data.facets');
        $this->assertCount(2, $facets['types']); // post and page
        $this->assertCount(2, $facets['authors']); // user1 and user2
    }

    /**
     * Test facets endpoint when disabled.
     */
    public function test_facets_endpoint_when_disabled(): void
    {
        Config::set('cms.search.facets.enabled', false);

        $response = $this->getJson('/api/cms/search/facets');

        $response->assertStatus(503)
                 ->assertJson([
                     'error' => 'Faceted search is disabled',
                 ]);
    }

    /**
     * Test suggestions endpoint returns suggestions.
     */
    public function test_suggestions_endpoint_returns_suggestions(): void
    {
        // Create content for suggestions
        $content1 = Content::factory()->create([
            'title' => 'Laravel Best Practices',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $content2 = Content::factory()->create([
            'title' => 'Laravel Testing Guide',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->searchService->indexModel($content1);
        $this->searchService->indexModel($content2);

        $response = $this->getJson('/api/cms/search/suggestions?q=Lara');

        $response->assertSuccessful()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'query',
                         'suggestions',
                     ],
                 ]);

        $suggestions = $response->json('data.suggestions');
        $this->assertIsArray($suggestions);
        $this->assertContains('Laravel Best Practices', $suggestions);
        $this->assertContains('Laravel Testing Guide', $suggestions);
    }

    /**
     * Test suggestions endpoint validation.
     */
    public function test_suggestions_endpoint_validation(): void
    {
        // Test with query too short
        $response = $this->getJson('/api/cms/search/suggestions?q=L');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['q']);

        // Test with invalid limit
        $response = $this->getJson('/api/cms/search/suggestions?q=Laravel&limit=999');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['limit']);

        // Test without query
        $response = $this->getJson('/api/cms/search/suggestions');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['q']);
    }

    /**
     * Test suggestions endpoint when disabled.
     */
    public function test_suggestions_endpoint_when_disabled(): void
    {
        Config::set('cms.search.suggestions.enabled', false);

        $response = $this->getJson('/api/cms/search/suggestions?q=test');

        $response->assertStatus(503)
                 ->assertJson([
                     'error' => 'Search suggestions are disabled',
                 ]);
    }

    /**
     * Test analytics endpoint requires admin access.
     */
    public function test_analytics_endpoint_requires_admin_access(): void
    {
        // Create some search analytics data
        SearchAnalytics::create([
            'query' => 'test search',
            'filters' => [],
            'result_count' => 5,
            'searched_at' => now(),
        ]);

        // Test without authentication
        $response = $this->getJson('/api/cms/search/analytics');
        $response->assertUnauthorized();

        // Test with regular user
        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/cms/search/analytics');
        $response->assertForbidden();

        // Test with admin user
        $response = $this->actingAs($this->adminUser, 'sanctum')
                         ->getJson('/api/cms/search/analytics');
        $response->assertSuccessful();
    }

    /**
     * Test analytics endpoint returns correct structure.
     */
    public function test_analytics_endpoint_returns_correct_structure(): void
    {
        // Create analytics data
        SearchAnalytics::create([
            'query' => 'Laravel',
            'filters' => ['type' => 'post'],
            'result_count' => 3,
            'execution_time_ms' => 150,
            'searched_at' => now()->subDays(5),
        ]);

        SearchAnalytics::create([
            'query' => 'PHP',
            'filters' => [],
            'result_count' => 0,
            'execution_time_ms' => 100,
            'searched_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
                         ->getJson('/api/cms/search/analytics');

        $response->assertSuccessful()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'date_range' => [
                             'from',
                             'to',
                         ],
                         'analytics' => [
                             'performance' => [
                                 'total_searches',
                                 'unique_queries',
                                 'avg_results_per_search',
                                 'avg_execution_time_ms',
                                 'failed_searches',
                                 'success_rate',
                             ],
                             'popular_queries',
                             'failed_queries',
                             'trends',
                         ],
                     ],
                 ]);

        $analytics = $response->json('data.analytics');
        $this->assertEquals(2, $analytics['performance']['total_searches']);
        $this->assertEquals(1, $analytics['performance']['failed_searches']);
        $this->assertEquals(50.0, $analytics['performance']['success_rate']);
    }

    /**
     * Test analytics endpoint when disabled.
     */
    public function test_analytics_endpoint_when_disabled(): void
    {
        Config::set('cms.search.analytics_enabled', false);

        $response = $this->actingAs($this->adminUser, 'sanctum')
                         ->getJson('/api/cms/search/analytics');

        $response->assertStatus(503)
                 ->assertJson([
                     'error' => 'Search analytics are disabled',
                 ]);
    }

    /**
     * Test status endpoint returns configuration info.
     */
    public function test_status_endpoint_returns_configuration_info(): void
    {
        $response = $this->getJson('/api/cms/search/status');

        $response->assertSuccessful()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'search_enabled',
                         'analytics_enabled',
                         'facets_enabled',
                         'suggestions_enabled',
                         'auto_indexing',
                         'cache_enabled',
                         'engine',
                         'limits' => [
                             'max_results',
                             'default_per_page',
                             'max_per_page',
                         ],
                         'indexable_models',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertTrue($data['search_enabled']);
        $this->assertTrue($data['analytics_enabled']);
        $this->assertEquals('mysql', $data['engine']);
    }

    /**
     * Test status endpoint includes index stats for admin users.
     */
    public function test_status_endpoint_includes_index_stats_for_admin(): void
    {
        // Create and index some content
        $content = Content::factory()->create(['status' => 'published']);
        $term = Term::factory()->create();
        
        $this->searchService->indexModel($content);
        $this->searchService->indexModel($term);

        // Test with admin user
        $response = $this->actingAs($this->adminUser, 'sanctum')
                         ->getJson('/api/cms/search/status');

        $response->assertSuccessful()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'index_stats' => [
                             'total_indexed',
                             'by_type',
                         ],
                     ],
                 ]);

        $indexStats = $response->json('data.index_stats');
        $this->assertEquals(2, $indexStats['total_indexed']);
        $this->assertArrayHasKey('post', $indexStats['by_type']); // Content type
        $this->assertArrayHasKey('taxonomy_term', $indexStats['by_type']); // Term type
    }

    /**
     * Test search analytics are logged correctly.
     */
    public function test_search_analytics_are_logged(): void
    {
        // Create and index content
        $content = Content::factory()->create([
            'title' => 'Test Content',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->searchService->indexModel($content);

        // Perform search as authenticated user
        $this->actingAs($this->user, 'sanctum')
             ->getJson('/api/cms/search?q=Test');

        // Check analytics were logged
        $this->assertDatabaseHas('search_analytics', [
            'query' => 'Test',
            'result_count' => 1,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test search with pagination works correctly.
     */
    public function test_search_with_pagination(): void
    {
        // Create multiple content items
        for ($i = 1; $i <= 15; $i++) {
            $content = Content::factory()->create([
                'title' => "Article {$i}",
                'content' => "Content for article {$i}",
                'status' => 'published',
                'published_at' => now(),
            ]);
            $this->searchService->indexModel($content);
        }

        // Test first page
        $response = $this->getJson('/api/cms/search?per_page=5&page=1');

        $response->assertSuccessful();
        $pagination = $response->json('data.pagination');
        
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(5, $pagination['per_page']);
        $this->assertEquals(15, $pagination['total']);
        $this->assertEquals(3, $pagination['total_pages']);
        $this->assertTrue($pagination['has_more']);
        $this->assertCount(5, $response->json('data.results'));

        // Test last page
        $response = $this->getJson('/api/cms/search?per_page=5&page=3');
        
        $response->assertSuccessful();
        $pagination = $response->json('data.pagination');
        
        $this->assertEquals(3, $pagination['current_page']);
        $this->assertFalse($pagination['has_more']);
        $this->assertCount(5, $response->json('data.results'));
    }
}