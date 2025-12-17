<?php

/**
 * Page Controller for the CMS Framework Pages Module.
 *
 * This controller handles CRUD operations for pages including listing,
 * creating, showing, updating, and deleting page records through API endpoints.
 * Also handles hierarchical operations like tree view, reordering, and moving pages.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Requests\PageRequest;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Resources\PageResource;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing pages.
 *
 * Provides RESTful API endpoints for page management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 2.0.0
 */
class PageController extends Controller
{
    use AuthorizesRequests;

    /**
     * The page manager instance.
     *
     * @since 2.0.0
     */
    protected PageManager $pageManager;

    /**
     * Create a new controller instance.
     *
     * @since 2.0.0
     */
    public function __construct(PageManager $pageManager)
    {
        $this->pageManager = $pageManager;
    }

    /**
     * Display a listing of pages.
     *
     * Retrieves a paginated list of pages and returns them as a JSON resource collection.
     *
     * @since 2.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of page resources.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Page::class);

        $filters = $request->only(['status', 'author', 'template', 'search']);
        $pages = $this->pageManager->getPageQuery($filters)->paginate(15);

        return PageResource::collection($pages);
    }

    /**
     * Get hierarchical page tree.
     *
     * Returns all pages in a hierarchical tree structure.
     *
     * @since 2.0.0
     *
     * @return JsonResponse The page tree as JSON.
     */
    public function tree(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        $filters = $request->only(['status', 'author', 'template']);
        $tree = $this->pageManager->getPageTree($filters);

        return response()->json(PageResource::collection($tree));
    }

    /**
     * Reorder pages.
     *
     * Updates the order values for multiple pages.
     *
     * @since 2.0.0
     *
     * @param  Request  $request  The HTTP request containing order data.
     * @return JsonResponse Success response.
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('update', Page::class);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'min:0'],
        ]);

        $this->pageManager->reorderPages($validated['order']);

        return response()->json(['message' => 'Pages reordered successfully']);
    }

    /**
     * Move a page to a new parent.
     *
     * Moves a page in the hierarchy by changing its parent.
     *
     * @since 2.0.0
     *
     * @param  Request  $request  The HTTP request containing parent data.
     * @param  int  $id  The ID of the page to move.
     * @return JsonResponse Success response.
     */
    public function move(Request $request, int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $this->authorize('update', $page);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:pages,id'],
        ]);

        try {
            $this->pageManager->movePage($id, $validated['parent_id'] ?? null);

            return response()->json(['message' => 'Page moved successfully']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Store a newly created page.
     *
     * Validates the incoming request data and creates a new page with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 2.0.0
     *
     * @param  PageRequest  $request  The HTTP request containing page data.
     * @return JsonResponse The JSON response containing the created page resource.
     */
    public function store(PageRequest $request): JsonResponse
    {
        $this->authorize('create', Page::class);

        $validated = $request->validated();
        $categories = $validated['categories'] ?? [];
        $tags = $validated['tags'] ?? [];

        unset($validated['categories'], $validated['tags']);

        $page = Page::create($validated);

        // Sync categories and tags
        if (! empty($categories)) {
            $page->categories()->sync($categories);
        }

        if (! empty($tags)) {
            $page->tags()->sync($tags);
        }

        $page->load(['author', 'categories', 'tags', 'parent', 'children']);

        return response()->json(new PageResource($page), 201);
    }

    /**
     * Display the specified page.
     *
     * Retrieves a single page by ID and returns it as a JSON resource.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the page to retrieve.
     * @return PageResource The page resource.
     */
    public function show(int $id): PageResource
    {
        $page = Page::with(['author', 'categories', 'tags', 'parent', 'children'])->findOrFail($id);
        $this->authorize('view', $page);

        return new PageResource($page);
    }

    /**
     * Update the specified page.
     *
     * Validates the incoming request data and updates the page with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 2.0.0
     *
     * @param  PageRequest  $request  The HTTP request containing updated page data.
     * @param  int  $id  The ID of the page to update.
     * @return PageResource The updated page resource.
     */
    public function update(PageRequest $request, int $id): PageResource
    {
        $page = Page::findOrFail($id);
        $this->authorize('update', $page);

        $validated = $request->validated();
        $categories = $validated['categories'] ?? null;
        $tags = $validated['tags'] ?? null;

        unset($validated['categories'], $validated['tags']);

        $page->update($validated);

        // Sync categories and tags if provided
        if ($categories !== null) {
            $page->categories()->sync($categories);
        }

        if ($tags !== null) {
            $page->tags()->sync($tags);
        }

        $page->load(['author', 'categories', 'tags', 'parent', 'children']);

        return new PageResource($page);
    }

    /**
     * Remove the specified page.
     *
     * Deletes a page from the database and returns a successful response
     * with no content.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the page to delete.
     * @return Response A response with 204 status code.
     */
    public function destroy(int $id): Response
    {
        $page = Page::findOrFail($id);
        $this->authorize('delete', $page);

        $page->delete();

        return response()->noContent();
    }
}
