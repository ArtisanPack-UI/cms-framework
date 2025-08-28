<?php

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Services;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\SearchIndex;
use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\CMSFramework\Services\CacheService;
use ArtisanPackUI\CMSFramework\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * SearchServiceTest.
 *
 * Unit tests for the SearchService class.
 *
 * @package ArtisanPackUI\CMSFramework\Tests\Unit\Services
 * @since   1.2.0
 */
class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SearchService instance.
     *
     * @var SearchService
     */
    protected SearchService $searchService;

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
    }

    /**
     * Test search with empty query returns all published content.
     */
    public function test_search_with_empty_query_returns_published_content(): void
    {
        // Create test content
        $publishedContent = Content::factory()->create([
            'title' => 'Published Article',
            'content' => 'This is published content',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $draftContent = Content::factory()->create([
            'title' => 'Draft Article',
            'content' => 'This is draft content',
            'status' => 'draft',
        ]);

        // Index the content
        $this->searchService->indexModel($publishedContent);
        $this->searchService->indexModel($draftContent);

        // Perform search with empty query
        $results = $this->searchService->search('', [], 1, 10);

        $this->assertArrayHasKey('results', $results);
        $this->assertArrayHasKey('pagination', $results);
        $this->assertCount(1, $results['results']); // Only published content
        $this->assertEquals('Published Article', $results['results'][0]['title']);
    }

    /**
     * Test search with query filters results correctly.
     */
    public function test_search_with_query_filters_results(): void
    {
        // Create test content
        $matchingContent = Content::factory()->create([
            'title' => 'Laravel Tutorial',
            'content' => 'Learn Laravel framework basics',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $nonMatchingContent = Content::factory()->create([
            'title' => 'PHP Basics',
            'content' => 'Introduction to PHP programming',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Index the content
        $this->searchService->indexModel($matchingContent);
        $this->searchService->indexModel($nonMatchingContent);

        // Perform search with query
        $results = $this->searchService->search('Laravel', [], 1, 10);

        $this->assertArrayHasKey('results', $results);
        $this->assertCount(1, $results['results']); // Only matching content
        $this->assertEquals('Laravel Tutorial', $results['results'][0]['title']);
        $this->assertGreaterThan(0, $results['results'][0]['relevance_score']);
    }

    /**
     * Test indexing content model.
     */
    public function test_index_content_model(): void
    {
        $content = Content::factory()->create([
            'title' => 'Test Article',
            'content' => 'This is test content for indexing',
            'status' => 'published',
            'type' => 'post',
        ]);

        $this->searchService->indexModel($content);

        $this->assertDatabaseHas('search_indices', [
            'searchable_type' => Content::class,
            'searchable_id' => $content->id,
            'title' => 'Test Article',
            'type' => 'post',
            'status' => 'published',
        ]);
    }

    /**
     * Test indexing term model.
     */
    public function test_index_term_model(): void
    {
        $term = Term::factory()->create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $this->searchService->indexModel($term);

        $this->assertDatabaseHas('search_indices', [
            'searchable_type' => Term::class,
            'searchable_id' => $term->id,
            'title' => 'Technology',
            'type' => 'taxonomy_term',
            'status' => 'published',
        ]);
    }

    /**
     * Test removing model from index.
     */
    public function test_remove_model_from_index(): void
    {
        $content = Content::factory()->create();
        
        // First index the content
        $this->searchService->indexModel($content);
        
        $this->assertDatabaseHas('search_indices', [
            'searchable_type' => Content::class,
            'searchable_id' => $content->id,
        ]);

        // Remove from index
        $this->searchService->removeFromIndex($content);

        $this->assertDatabaseMissing('search_indices', [
            'searchable_type' => Content::class,
            'searchable_id' => $content->id,
        ]);
    }

    /**
     * Test search suggestions.
     */
    public function test_get_search_suggestions(): void
    {
        // Create content with similar titles
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

        // Index content
        $this->searchService->indexModel($content1);
        $this->searchService->indexModel($content2);

        // Get suggestions
        $suggestions = $this->searchService->getSearchSuggestions('Lara', 5);

        $this->assertIsArray($suggestions);
        $this->assertGreaterThan(0, count($suggestions));
        $this->assertContains('Laravel Best Practices', $suggestions);
        $this->assertContains('Laravel Testing Guide', $suggestions);
    }

    /**
     * Test facets generation.
     */
    public function test_get_facets(): void
    {
        // Create content with different types and authors
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();

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

        // Index content
        $this->searchService->indexModel($content1);
        $this->searchService->indexModel($content2);

        // Get facets
        $facets = $this->searchService->getFacets('', []);

        $this->assertArrayHasKey('types', $facets);
        $this->assertArrayHasKey('authors', $facets);
        $this->assertArrayHasKey('date_ranges', $facets);
        $this->assertArrayHasKey('status', $facets);

        // Check type facets
        $this->assertCount(2, $facets['types']); // post and page
        $this->assertEquals('post', $facets['types'][0]['value']);
        $this->assertEquals('page', $facets['types'][1]['value']);
    }

    /**
     * Test search with type filter.
     */
    public function test_search_with_type_filter(): void
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

        // Index content
        $this->searchService->indexModel($post);
        $this->searchService->indexModel($page);

        // Search with type filter
        $results = $this->searchService->search('', ['type' => 'post'], 1, 10);

        $this->assertCount(1, $results['results']);
        $this->assertEquals('Blog Post', $results['results'][0]['title']);
        $this->assertEquals('post', $results['results'][0]['content_type']);
    }

    /**
     * Test search with date range filter.
     */
    public function test_search_with_date_range_filter(): void
    {
        $oldDate = now()->subDays(10);
        $newDate = now()->subDays(2);

        // Create content with different dates
        $oldContent = Content::factory()->create([
            'title' => 'Old Article',
            'status' => 'published',
            'published_at' => $oldDate,
        ]);

        $newContent = Content::factory()->create([
            'title' => 'New Article',
            'status' => 'published',
            'published_at' => $newDate,
        ]);

        // Index content
        $this->searchService->indexModel($oldContent);
        $this->searchService->indexModel($newContent);

        // Search with date filter (last 5 days)
        $results = $this->searchService->search('', [
            'date_from' => now()->subDays(5)->toDateString(),
        ], 1, 10);

        $this->assertCount(1, $results['results']);
        $this->assertEquals('New Article', $results['results'][0]['title']);
    }

    /**
     * Test reindex all functionality.
     */
    public function test_reindex_all(): void
    {
        // Create test content
        $content1 = Content::factory()->create(['status' => 'published']);
        $content2 = Content::factory()->create(['status' => 'published']);
        $term = Term::factory()->create();

        // Ensure no existing index entries
        SearchIndex::truncate();

        // Perform reindex
        $indexedCount = $this->searchService->reindexAll();

        $this->assertGreaterThanOrEqual(3, $indexedCount); // At least 2 content + 1 term
        $this->assertDatabaseHas('search_indices', [
            'searchable_type' => Content::class,
            'searchable_id' => $content1->id,
        ]);
        $this->assertDatabaseHas('search_indices', [
            'searchable_type' => Content::class,
            'searchable_id' => $content2->id,
        ]);
        $this->assertDatabaseHas('search_indices', [
            'searchable_type' => Term::class,
            'searchable_id' => $term->id,
        ]);
    }

    /**
     * Test excerpt generation.
     */
    public function test_excerpt_generation(): void
    {
        $longContent = Content::factory()->create([
            'content' => str_repeat('This is a long piece of content that should be truncated. ', 50),
            'status' => 'published',
        ]);

        $this->searchService->indexModel($longContent);

        $searchIndex = SearchIndex::where('searchable_id', $longContent->id)->first();
        
        $this->assertNotNull($searchIndex->excerpt);
        $this->assertLessThanOrEqual(503, strlen($searchIndex->excerpt)); // 500 chars + '...'
        $this->assertStringEndsWith('...', $searchIndex->excerpt);
    }

    /**
     * Test search analytics logging.
     */
    public function test_search_analytics_logging(): void
    {
        // Create a mock request
        $request = Request::create('/api/search', 'GET', ['q' => 'test']);
        $request->setUserResolver(function () {
            return \App\Models\User::factory()->create();
        });

        // Perform search
        $results = $this->searchService->search('test', [], 1, 10, $request);

        // Check that analytics were logged
        $this->assertDatabaseHas('search_analytics', [
            'query' => 'test',
            'result_count' => $results['pagination']['total'],
        ]);
    }
}