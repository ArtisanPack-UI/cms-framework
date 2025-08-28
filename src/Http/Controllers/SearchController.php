<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Models\SearchAnalytics;
use ArtisanPackUI\CMSFramework\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use TorMorten\Eventy\Facades\Eventy;

/**
 * SearchController.
 *
 * Handles all search-related API endpoints for the ArtisanPack UI CMS Framework.
 * Provides full-text search, faceted search, suggestions, and analytics functionality.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Http\Controllers
 * @since   1.2.0
 */
class SearchController extends Controller
{
    /**
     * SearchService instance.
     *
     * @var SearchService
     */
    protected SearchService $searchService;

    /**
     * Create a new SearchController instance.
     *
     * @since 1.2.0
     *
     * @param SearchService $searchService
     */
    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Perform a search with the given query and filters.
     *
     * @since 1.2.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        // Check if search is enabled
        if (!config('cms.search.enabled', true)) {
            return response()->json([
                'error' => 'Search functionality is disabled',
                'message' => 'Search has been disabled in the configuration',
            ], 503);
        }

        // Validate request parameters
        $validated = $request->validate([
            'q' => 'nullable|string|max:500',
            'type' => 'nullable|string|max:100',
            'types' => 'nullable|array',
            'types.*' => 'string|max:100',
            'author' => 'nullable|integer|exists:users,id',
            'authors' => 'nullable|array',
            'authors.*' => 'integer|exists:users,id',
            'status' => 'nullable|string|in:published,draft,archived',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'meta' => 'nullable|array',
            'sort' => 'nullable|string|in:relevance,date,title,type',
            'direction' => 'nullable|string|in:asc,desc',
            'search_mode' => 'nullable|string|in:natural,boolean,query_expansion',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:' . config('cms.search.results.max_per_page', 100),
            'include_facets' => 'nullable|boolean',
        ]);

        // Set defaults
        $query = $validated['q'] ?? '';
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? config('cms.search.results.default_per_page', 20);

        // Combine type filters
        $typeFilters = [];
        if (!empty($validated['type'])) {
            $typeFilters[] = $validated['type'];
        }
        if (!empty($validated['types'])) {
            $typeFilters = array_merge($typeFilters, $validated['types']);
        }

        // Combine author filters
        $authorFilters = [];
        if (!empty($validated['author'])) {
            $authorFilters[] = $validated['author'];
        }
        if (!empty($validated['authors'])) {
            $authorFilters = array_merge($authorFilters, $validated['authors']);
        }

        // Build filters array
        $filters = array_filter([
            'type' => !empty($typeFilters) ? array_unique($typeFilters) : null,
            'author' => !empty($authorFilters) ? array_unique($authorFilters) : null,
            'status' => $validated['status'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'meta' => $validated['meta'] ?? null,
            'sort' => $validated['sort'] ?? 'relevance',
            'direction' => $validated['direction'] ?? 'desc',
            'search_mode' => $validated['search_mode'] ?? 'natural',
        ], fn($value) => $value !== null);

        try {
            // Perform search
            $results = $this->searchService->search($query, $filters, $page, $perPage, $request);

            // Remove facets if not requested to reduce response size
            if (!($validated['include_facets'] ?? true)) {
                unset($results['meta']['facets']);
            }

            // Allow filtering of search results through Eventy hooks
            $results = Eventy::filter('ap.cms.search.api_results', $results, $query, $filters, $request);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            // Log error but don't expose internal details to API consumers
            \Log::error('Search API error', [
                'query' => $query,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Search failed',
                'message' => 'An error occurred while performing the search. Please try again.',
            ], 500);
        }
    }

    /**
     * Get search facets for filtering.
     *
     * @since 1.2.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function facets(Request $request): JsonResponse
    {
        // Check if faceted search is enabled
        if (!config('cms.search.facets.enabled', true)) {
            return response()->json([
                'error' => 'Faceted search is disabled',
                'message' => 'Faceted search functionality has been disabled in the configuration',
            ], 503);
        }

        // Validate request parameters
        $validated = $request->validate([
            'q' => 'nullable|string|max:500',
            'type' => 'nullable|string|max:100',
            'author' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|string|in:published,draft,archived',
            'search_mode' => 'nullable|string|in:natural,boolean,query_expansion',
        ]);

        $query = $validated['q'] ?? '';
        $filters = array_filter([
            'type' => $validated['type'] ?? null,
            'author' => $validated['author'] ?? null,
            'status' => $validated['status'] ?? null,
            'search_mode' => $validated['search_mode'] ?? 'natural',
        ], fn($value) => $value !== null);

        try {
            $facets = $this->searchService->getFacets($query, $filters);

            // Allow filtering of facets through Eventy hooks
            $facets = Eventy::filter('ap.cms.search.api_facets', $facets, $query, $filters, $request);

            return response()->json([
                'success' => true,
                'data' => [
                    'query' => $query,
                    'filters' => $filters,
                    'facets' => $facets,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Search facets API error', [
                'query' => $query,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load search facets',
                'message' => 'An error occurred while loading search facets. Please try again.',
            ], 500);
        }
    }

    /**
     * Get search suggestions based on partial query.
     *
     * @since 1.2.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function suggestions(Request $request): JsonResponse
    {
        // Check if suggestions are enabled
        if (!config('cms.search.suggestions.enabled', true)) {
            return response()->json([
                'error' => 'Search suggestions are disabled',
                'message' => 'Search suggestions functionality has been disabled in the configuration',
            ], 503);
        }

        // Validate request parameters
        $validated = $request->validate([
            'q' => 'required|string|min:' . config('cms.search.suggestions.min_query_length', 2) . '|max:500',
            'limit' => 'nullable|integer|min:1|max:' . config('cms.search.suggestions.max_suggestions', 10),
        ]);

        $query = $validated['q'];
        $limit = $validated['limit'] ?? config('cms.search.suggestions.max_suggestions', 10);

        try {
            $suggestions = $this->searchService->getSearchSuggestions($query, $limit);

            // Allow filtering of suggestions through Eventy hooks
            $suggestions = Eventy::filter('ap.cms.search.api_suggestions', $suggestions, $query, $limit, $request);

            return response()->json([
                'success' => true,
                'data' => [
                    'query' => $query,
                    'suggestions' => $suggestions,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Search suggestions API error', [
                'query' => $query,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load search suggestions',
                'message' => 'An error occurred while loading search suggestions. Please try again.',
            ], 500);
        }
    }

    /**
     * Get search analytics data (admin only).
     *
     * @since 1.2.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function analytics(Request $request): JsonResponse
    {
        // Check if analytics are enabled
        if (!config('cms.search.analytics_enabled', true)) {
            return response()->json([
                'error' => 'Search analytics are disabled',
                'message' => 'Search analytics functionality has been disabled in the configuration',
            ], 503);
        }

        // Validate request parameters
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'top_queries' => 'nullable|integer|min:1|max:100',
            'failed_queries' => 'nullable|integer|min:1|max:100',
            'trends_days' => 'nullable|integer|min:1|max:90',
            'include_performance' => 'nullable|boolean',
            'include_trends' => 'nullable|boolean',
            'include_users' => 'nullable|boolean',
        ]);

        // Set date range defaults (last 30 days)
        $dateTo = $validated['date_to'] ? Carbon::parse($validated['date_to']) : now();
        $dateFrom = $validated['date_from'] ? Carbon::parse($validated['date_from']) : $dateTo->copy()->subDays(30);

        try {
            $analytics = [];

            // Get performance statistics
            if ($validated['include_performance'] ?? true) {
                $analytics['performance'] = SearchAnalytics::getPerformanceStats($dateFrom, $dateTo);
            }

            // Get popular queries
            $topQueriesLimit = $validated['top_queries'] ?? 10;
            $analytics['popular_queries'] = SearchAnalytics::getPopularQueries($topQueriesLimit, $dateFrom, $dateTo);

            // Get failed queries
            $failedQueriesLimit = $validated['failed_queries'] ?? 10;
            $analytics['failed_queries'] = SearchAnalytics::getFailedQueries($failedQueriesLimit, $dateFrom, $dateTo);

            // Get search trends
            if ($validated['include_trends'] ?? true) {
                $trendsDays = $validated['trends_days'] ?? 30;
                $analytics['trends'] = SearchAnalytics::getSearchTrends($trendsDays, $dateTo);
            }

            // Get top search users
            if ($validated['include_users'] ?? false) {
                $analytics['top_users'] = SearchAnalytics::getTopSearchUsers(10, $dateFrom, $dateTo);
            }

            // Allow filtering of analytics through Eventy hooks
            $analytics = Eventy::filter('ap.cms.search.api_analytics', $analytics, $dateFrom, $dateTo, $request);

            return response()->json([
                'success' => true,
                'data' => [
                    'date_range' => [
                        'from' => $dateFrom->toISOString(),
                        'to' => $dateTo->toISOString(),
                    ],
                    'analytics' => $analytics,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Search analytics API error', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load search analytics',
                'message' => 'An error occurred while loading search analytics. Please try again.',
            ], 500);
        }
    }

    /**
     * Get search status and configuration info.
     *
     * @since 1.2.0
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $status = [
                'search_enabled' => config('cms.search.enabled', true),
                'analytics_enabled' => config('cms.search.analytics_enabled', true),
                'facets_enabled' => config('cms.search.facets.enabled', true),
                'suggestions_enabled' => config('cms.search.suggestions.enabled', true),
                'auto_indexing' => config('cms.search.indexing.auto_index', true),
                'cache_enabled' => config('cms.search.cache.enabled', true),
                'engine' => config('cms.search.engine.driver', 'mysql'),
                'limits' => [
                    'max_results' => config('cms.search.results.max_results', 1000),
                    'default_per_page' => config('cms.search.results.default_per_page', 20),
                    'max_per_page' => config('cms.search.results.max_per_page', 100),
                ],
                'indexable_models' => config('cms.search.indexing.indexable_models', []),
            ];

            // Add index statistics if user has appropriate permissions
            if ($request->user() && $request->user()->can('manage_search')) {
                $status['index_stats'] = [
                    'total_indexed' => \ArtisanPackUI\CMSFramework\Models\SearchIndex::count(),
                    'by_type' => \ArtisanPackUI\CMSFramework\Models\SearchIndex::query()
                        ->selectRaw('type, COUNT(*) as count')
                        ->groupBy('type')
                        ->get()
                        ->pluck('count', 'type')
                        ->toArray(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            \Log::error('Search status API error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load search status',
                'message' => 'An error occurred while loading search status. Please try again.',
            ], 500);
        }
    }
}