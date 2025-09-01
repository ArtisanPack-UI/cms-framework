<?php

namespace ArtisanPackUI\CMSFramework\Services;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\SearchAnalytics;
use ArtisanPackUI\CMSFramework\Models\SearchIndex;
use ArtisanPackUI\CMSFramework\Models\Term;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use TorMorten\Eventy\Facades\Eventy;

/**
 * SearchService.
 *
 * Handles all search-related functionality for the ArtisanPack UI CMS Framework,
 * including indexing content, performing searches, and managing search analytics.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Services
 * @since   1.2.0
 */
class SearchService
{
    /**
     * Cache service instance.
     *
     * @var CacheService
     */
    protected CacheService $cacheService;

    /**
     * Create a new SearchService instance.
     *
     * @since 1.2.0
     *
     * @param CacheService $cacheService
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Perform a search with the given query and filters.
     *
     * @since 1.2.0
     *
     * @param string $query Search query
     * @param array $filters Search filters
     * @param int $page Page number
     * @param int $perPage Results per page
     * @param Request|null $request HTTP request for analytics
     * @return array Search results with pagination and metadata
     */
    public function search(
        string $query,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        ?Request $request = null
    ): array {
        $startTime = microtime(true);

        // Apply search query and filters
        $searchQuery = $this->buildSearchQuery($query, $filters);

        // Get total count for pagination
        $total = $searchQuery->count();

        // Apply pagination and get results
        $results = $searchQuery
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Calculate execution time
        $executionTime = (int) round((microtime(true) - $startTime) * 1000);

        // Log search analytics if enabled
        if (config('cms.search.analytics_enabled', true)) {
            $this->logSearchAnalytics($query, $filters, $total, $executionTime, $request);
        }

        // Transform results for API response
        $transformedResults = $this->transformSearchResults($results);

        return [
            'query' => $query,
            'filters' => $filters,
            'results' => $transformedResults,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => $page * $perPage < $total,
            ],
            'meta' => [
                'execution_time_ms' => $executionTime,
                'result_count' => $total,
                'facets' => $this->getFacets($query, $filters),
            ],
        ];
    }

    /**
     * Build the search query with filters and ranking.
     *
     * @since 1.2.0
     *
     * @param string $query Search query
     * @param array $filters Search filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildSearchQuery(string $query, array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $searchQuery = SearchIndex::query();

        // Apply full-text search if query is provided
        if (!empty(trim($query))) {
            $searchMode = $filters['search_mode'] ?? 'natural';
            $searchQuery = $searchQuery->search($query, $searchMode);
        }

        // Apply content access filters (published content only for non-admin users)
        $searchQuery = $searchQuery->published();

        // Apply type filter
        if (isset($filters['type']) && !empty($filters['type'])) {
            if (is_array($filters['type'])) {
                $searchQuery = $searchQuery->whereIn('type', $filters['type']);
            } else {
                $searchQuery = $searchQuery->ofType($filters['type']);
            }
        }

        // Apply author filter
        if (isset($filters['author']) && !empty($filters['author'])) {
            if (is_array($filters['author'])) {
                $searchQuery = $searchQuery->whereIn('author_id', $filters['author']);
            } else {
                $searchQuery = $searchQuery->byAuthor($filters['author']);
            }
        }

        // Apply date range filter
        if (isset($filters['date_from']) || isset($filters['date_to'])) {
            $searchQuery = $searchQuery->dateRange(
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null
            );
        }

        // Apply custom meta filters
        if (isset($filters['meta']) && is_array($filters['meta'])) {
            foreach ($filters['meta'] as $key => $value) {
                $searchQuery = $searchQuery->whereJsonContains('meta_data->' . $key, $value);
            }
        }

        // Apply ranking and sorting
        $sort = $filters['sort'] ?? 'relevance';
        $direction = $filters['direction'] ?? 'desc';

        switch ($sort) {
            case 'date':
                $searchQuery = $searchQuery->orderBy('published_at', $direction);
                break;
            case 'title':
                $searchQuery = $searchQuery->orderBy('title', $direction);
                break;
            case 'type':
                $searchQuery = $searchQuery->orderBy('type', $direction);
                break;
            case 'relevance':
            default:
                if (!empty(trim($query))) {
                    // Order by calculated search score
                    $searchQuery = $searchQuery->orderByRaw('
                        (relevance_score * 0.4 + 
                         relevance_boost * 0.6) DESC
                    ');
                } else {
                    // Default to date for non-search queries
                    $searchQuery = $searchQuery->orderBy('published_at', 'desc');
                }
                break;
        }

        // Allow filtering through Eventy hooks
        return Eventy::filter('ap.cms.search.query', $searchQuery, $query, $filters);
    }

    /**
     * Transform search results for API response.
     *
     * @since 1.2.0
     *
     * @param Collection $results
     * @return array
     */
    protected function transformSearchResults(Collection $results): array
    {
        return $results->map(function (SearchIndex $index) {
            $data = [
                'id' => $index->searchable_id,
                'type' => $index->searchable_type,
                'content_type' => $index->type,
                'title' => $index->title,
                'excerpt' => $index->excerpt,
                'status' => $index->status,
                'published_at' => $index->published_at?->toISOString(),
                'author' => $index->author ? [
                    'id' => $index->author->id,
                    'name' => $index->author->name,
                ] : null,
                'relevance_score' => $index->relevance_score ?? 0,
                'search_score' => isset($index->relevance_score) 
                    ? $index->calculateSearchScore($index->relevance_score) 
                    : 0,
                'meta_data' => $index->meta_data,
            ];

            return Eventy::filter('ap.cms.search.result', $data, $index);
        })->toArray();
    }

    /**
     * Get search facets for filtering.
     *
     * @since 1.2.0
     *
     * @param string $query Current search query
     * @param array $currentFilters Currently applied filters
     * @return array
     */
    public function getFacets(string $query = '', array $currentFilters = []): array
    {
        $cacheKey = 'search_facets_' . md5($query . serialize($currentFilters));

        return $this->cacheService->remember(
            'search',
            $cacheKey,
            function () use ($query, $currentFilters) {
                $baseQuery = $this->buildBaseFacetQuery($query, $currentFilters);

                return [
                    'types' => $this->getTypeFacets($baseQuery),
                    'authors' => $this->getAuthorFacets($baseQuery),
                    'date_ranges' => $this->getDateFacets($baseQuery),
                    'status' => $this->getStatusFacets($baseQuery),
                ];
            },
            config('cms.search.cache_ttl', 3600)
        );
    }

    /**
     * Build base query for facet calculations.
     *
     * @since 1.2.0
     *
     * @param string $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildBaseFacetQuery(string $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $facetQuery = SearchIndex::query()->published();

        // Apply search query but not other filters for facet calculation
        if (!empty(trim($query))) {
            $searchMode = $filters['search_mode'] ?? 'natural';
            $facetQuery = $facetQuery->search($query, $searchMode);
        }

        return $facetQuery;
    }

    /**
     * Get type facets with counts.
     *
     * @since 1.2.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @return array
     */
    protected function getTypeFacets($baseQuery): array
    {
        return $baseQuery
            ->selectRaw('type, COUNT(*) as count')
            ->whereNotNull('type')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->type,
                    'label' => ucfirst(str_replace('_', ' ', $item->type)),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get author facets with counts.
     *
     * @since 1.2.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @return array
     */
    protected function getAuthorFacets($baseQuery): array
    {
        return $baseQuery
            ->selectRaw('author_id, COUNT(*) as count')
            ->whereNotNull('author_id')
            ->with('author:id,name')
            ->groupBy('author_id')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->author_id,
                    'label' => $item->author->name ?? 'Unknown',
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get date range facets.
     *
     * @since 1.2.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @return array
     */
    protected function getDateFacets($baseQuery): array
    {
        $now = now();
        
        return [
            [
                'value' => 'last_week',
                'label' => 'Last Week',
                'count' => $baseQuery->clone()->dateRange($now->copy()->subWeek())->count(),
            ],
            [
                'value' => 'last_month',
                'label' => 'Last Month',
                'count' => $baseQuery->clone()->dateRange($now->copy()->subMonth())->count(),
            ],
            [
                'value' => 'last_year',
                'label' => 'Last Year',
                'count' => $baseQuery->clone()->dateRange($now->copy()->subYear())->count(),
            ],
        ];
    }

    /**
     * Get status facets with counts.
     *
     * @since 1.2.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @return array
     */
    protected function getStatusFacets($baseQuery): array
    {
        return $baseQuery
            ->selectRaw('status, COUNT(*) as count')
            ->whereNotNull('status')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->status,
                    'label' => ucfirst($item->status),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get search suggestions based on partial query.
     *
     * @since 1.2.0
     *
     * @param string $query Partial search query
     * @param int $limit Number of suggestions to return
     * @return array
     */
    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'search_suggestions_' . md5($query) . '_' . $limit;

        return $this->cacheService->remember(
            'search',
            $cacheKey,
            function () use ($query, $limit) {
                // Get suggestions from indexed content
                $contentSuggestions = SearchIndex::query()
                    ->published()
                    ->where(function ($q) use ($query) {
                        $q->where('title', 'LIKE', "%{$query}%")
                          ->orWhere('keywords', 'LIKE', "%{$query}%");
                    })
                    ->select('title')
                    ->distinct()
                    ->limit($limit)
                    ->get()
                    ->pluck('title')
                    ->toArray();

                // Get suggestions from popular search queries
                $popularSuggestions = SearchAnalytics::query()
                    ->where('query', 'LIKE', "%{$query}%")
                    ->where('result_count', '>', 0)
                    ->selectRaw('query, COUNT(*) as search_count')
                    ->groupBy('query')
                    ->orderByDesc('search_count')
                    ->limit($limit)
                    ->get()
                    ->pluck('query')
                    ->toArray();

                // Merge and deduplicate suggestions
                $suggestions = array_unique(array_merge($contentSuggestions, $popularSuggestions));

                return array_slice($suggestions, 0, $limit);
            },
            config('cms.search.cache_ttl', 3600)
        );
    }

    /**
     * Index a model for search.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return void
     */
    public function indexModel(Model $model): void
    {
        $indexData = $this->extractIndexData($model);

        if ($indexData) {
            SearchIndex::updateOrCreate(
                [
                    'searchable_type' => get_class($model),
                    'searchable_id' => $model->id,
                ],
                $indexData
            );

            Eventy::action('ap.cms.search.model_indexed', $model, $indexData);
        }
    }

    /**
     * Remove a model from search index.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return void
     */
    public function removeFromIndex(Model $model): void
    {
        SearchIndex::where('searchable_type', get_class($model))
            ->where('searchable_id', $model->id)
            ->delete();

        Eventy::action('ap.cms.search.model_removed', $model);
    }

    /**
     * Extract indexable data from a model.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return array|null
     */
    protected function extractIndexData(Model $model): ?array
    {
        if ($model instanceof Content) {
            return $this->extractContentIndexData($model);
        }

        if ($model instanceof Term) {
            return $this->extractTermIndexData($model);
        }

        // Allow other models to be indexed via Eventy filter
        return Eventy::filter('ap.cms.search.extract_index_data', null, $model);
    }

    /**
     * Extract index data from Content model.
     *
     * @since 1.2.0
     *
     * @param Content $content
     * @return array
     */
    protected function extractContentIndexData(Content $content): array
    {
        $excerpt = $this->generateExcerpt($content->content ?? '', 500);
        $keywords = $this->generateKeywords($content);

        return [
            'title' => $content->title,
            'content' => strip_tags($content->content ?? ''),
            'excerpt' => $excerpt,
            'keywords' => $keywords,
            'type' => $content->type,
            'status' => $content->status,
            'author_id' => $content->author_id,
            'published_at' => $content->published_at,
            'relevance_boost' => 1.0,
            'meta_data' => array_merge($content->meta ?? [], [
                'slug' => $content->slug,
                'parent_id' => $content->parent_id,
            ]),
        ];
    }

    /**
     * Extract index data from Term model.
     *
     * @since 1.2.0
     *
     * @param Term $term
     * @return array
     */
    protected function extractTermIndexData(Term $term): array
    {
        return [
            'title' => $term->name,
            'content' => $term->name . ' ' . ($term->taxonomy->name ?? ''),
            'excerpt' => $term->name,
            'keywords' => $term->slug . ',' . $term->name,
            'type' => 'taxonomy_term',
            'status' => 'published',
            'author_id' => null,
            'published_at' => $term->created_at,
            'relevance_boost' => 0.8, // Terms are less relevant than content
            'meta_data' => [
                'slug' => $term->slug,
                'taxonomy_id' => $term->taxonomy_id,
                'taxonomy_name' => $term->taxonomy->name ?? null,
                'parent_id' => $term->parent_id,
            ],
        ];
    }

    /**
     * Generate excerpt from content.
     *
     * @since 1.2.0
     *
     * @param string $content
     * @param int $maxLength
     * @return string
     */
    protected function generateExcerpt(string $content, int $maxLength = 500): string
    {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        $excerpt = substr($content, 0, $maxLength);
        $lastSpace = strrpos($excerpt, ' ');
        
        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }

        return $excerpt . '...';
    }

    /**
     * Generate keywords from content.
     *
     * @since 1.2.0
     *
     * @param Content $content
     * @return string
     */
    protected function generateKeywords(Content $content): string
    {
        $keywords = [$content->slug];

        // Add terms as keywords
        if ($content->relationLoaded('terms')) {
            $termKeywords = $content->terms->pluck('name')->toArray();
            $keywords = array_merge($keywords, $termKeywords);
        }

        // Extract keywords from title and content
        $text = $content->title . ' ' . strip_tags($content->content ?? '');
        $words = str_word_count(strtolower($text), 1);
        $commonWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must'];
        
        $filteredWords = array_filter($words, function ($word) use ($commonWords) {
            return strlen($word) > 3 && !in_array($word, $commonWords);
        });

        $wordCounts = array_count_values($filteredWords);
        arsort($wordCounts);
        $topWords = array_slice(array_keys($wordCounts), 0, 10);

        $keywords = array_merge($keywords, $topWords);

        return implode(',', array_unique($keywords));
    }

    /**
     * Reindex all searchable content.
     *
     * @since 1.2.0
     *
     * @param callable|null $progressCallback
     * @return int Number of items reindexed
     */
    public function reindexAll(?callable $progressCallback = null): int
    {
        $indexed = 0;
        $batchSize = config('cms.search.index_batch_size', 100);

        // Clear existing index
        SearchIndex::truncate();

        // Index all content
        Content::chunk($batchSize, function ($contents) use (&$indexed, $progressCallback) {
            foreach ($contents as $content) {
                $this->indexModel($content);
                $indexed++;

                if ($progressCallback) {
                    $progressCallback('content', $indexed);
                }
            }
        });

        // Index all terms
        Term::with('taxonomy')->chunk($batchSize, function ($terms) use (&$indexed, $progressCallback) {
            foreach ($terms as $term) {
                $this->indexModel($term);
                $indexed++;

                if ($progressCallback) {
                    $progressCallback('term', $indexed);
                }
            }
        });

        // Allow other models to be indexed
        Eventy::action('ap.cms.search.reindex_additional', $indexed, $progressCallback);

        return $indexed;
    }

    /**
     * Log search analytics.
     *
     * @since 1.2.0
     *
     * @param string $query
     * @param array $filters
     * @param int $resultCount
     * @param int $executionTime
     * @param Request|null $request
     * @return void
     */
    protected function logSearchAnalytics(
        string $query,
        array $filters,
        int $resultCount,
        int $executionTime,
        ?Request $request
    ): void {
        if (!$request) {
            return;
        }

        SearchAnalytics::logSearch(
            $query,
            $filters,
            $resultCount,
            $executionTime,
            $request->user()?->id,
            $request->ip(),
            $request->userAgent()
        );
    }
}