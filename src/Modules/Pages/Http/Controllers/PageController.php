<?php

declare(strict_types=1);

/**
 * Page Controller for the CMS Framework Pages Module.
 *
 * This controller handles CRUD operations for pages including listing,
 * creating, showing, updating, and deleting page records through API endpoints.
 * Also handles hierarchical operations like tree view, reordering, and moving pages.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Controllers\Concerns\HasIncludableRelationships;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Requests\BulkPageRequest;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Requests\PageRequest;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Resources\PageResource;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Throwable;

/**
 * API controller for managing pages.
 *
 * Provides RESTful API endpoints for page management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
#[Group('Pages', weight: 4)]
class PageController extends Controller
{
    use AuthorizesRequests;
    use HasIncludableRelationships;

    /**
     * The relationships that can be included via the include query parameter.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $includableRelationships = ['author', 'categories', 'tags', 'parent', 'children'];

    /**
     * The default relationships to load when no include parameter is provided.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $defaultIncludes = ['author', 'categories', 'tags', 'parent', 'children'];

    /**
     * The page manager instance.
     *
     * @since 1.0.0
     */
    protected PageManager $pageManager;

    /**
     * Create a new controller instance.
     *
     * @since 1.0.0
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
     * @since 1.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of page resources.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Page::class);

        $filters  = $request->only(['status', 'author', 'template', 'search']);
        $includes = $this->getRequestedIncludes($request);
        $pages    = $this->pageManager->getPageQuery($filters)->with($includes)->paginate(15);

        return PageResource::collection($pages);
    }

    /**
     * Get hierarchical page tree.
     *
     * Returns all pages in a hierarchical tree structure.
     *
     * @since 1.0.0
     *
     * @return JsonResponse The page tree as JSON.
     */
    public function tree(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        $filters = $request->only(['status', 'author', 'template']);
        $tree    = $this->pageManager->getPageTree($filters);

        return response()->json(PageResource::collection($tree));
    }

    /**
     * Reorder pages.
     *
     * Updates the order values for multiple pages.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request containing order data.
     *
     * @return JsonResponse Success response.
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('update', Page::class);

        $validated = $request->validate([
            'order'   => ['required', 'array'],
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
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request containing parent data.
     * @param  int  $id  The ID of the page to move.
     *
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
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Store a newly created page.
     *
     * Validates the incoming request data and creates a new page with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 1.0.0
     *
     * @param  PageRequest  $request  The HTTP request containing page data.
     *
     * @return JsonResponse The JSON response containing the created page resource.
     */
    public function store(PageRequest $request): JsonResponse
    {
        $this->authorize('create', Page::class);

        $validated  = $request->validated();
        $categories = $validated['categories'] ?? [];
        $tags       = $validated['tags'] ?? [];

        unset($validated['categories'], $validated['tags']);

        $page = Page::create($validated);

        // Sync categories and tags
        if (! empty($categories)) {
            $page->categories()->sync($categories);
        }

        if (! empty($tags)) {
            $page->tags()->sync($tags);
        }

        $page->load($this->getRequestedIncludes($request));

        return response()->json(new PageResource($page), 201);
    }

    /**
     * Display the specified page.
     *
     * Retrieves a single page by ID and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The ID of the page to retrieve.
     *
     * @return PageResource The page resource.
     */
    public function show(Request $request, int $id): PageResource
    {
        $page = Page::findOrFail($id);
        $this->authorize('view', $page);

        $page->load($this->getRequestedIncludes($request));

        return new PageResource($page);
    }

    /**
     * Update the specified page.
     *
     * Validates the incoming request data and updates the page with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 1.0.0
     *
     * @param  PageRequest  $request  The HTTP request containing updated page data.
     * @param  int  $id  The ID of the page to update.
     *
     * @return PageResource The updated page resource.
     */
    public function update(PageRequest $request, int $id): PageResource
    {
        $page = Page::findOrFail($id);
        $this->authorize('update', $page);

        $validated  = $request->validated();
        $categories = $validated['categories'] ?? null;
        $tags       = $validated['tags'] ?? null;

        unset($validated['categories'], $validated['tags']);

        $page->update($validated);

        // Sync categories and tags if provided
        if (null !== $categories) {
            $page->categories()->sync($categories);
        }

        if (null !== $tags) {
            $page->tags()->sync($tags);
        }

        $page->load($this->getRequestedIncludes($request));

        return new PageResource($page);
    }

    /**
     * Remove the specified page.
     *
     * Deletes a page from the database and returns a successful response
     * with no content.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The ID of the page to delete.
     *
     * @return Response A response with 204 status code.
     */
    public function destroy(int $id): Response
    {
        $page = Page::findOrFail($id);
        $this->authorize('delete', $page);

        $page->delete();

        return response()->noContent();
    }

    /**
     * Perform a bulk action on multiple pages.
     *
     * Processes the requested action on each page individually, respecting
     * authorization policies. Returns a summary of successes and failures.
     *
     * @since 1.1.0
     *
     * @param  BulkPageRequest  $request  The validated bulk action request.
     *
     * @return JsonResponse Summary with processed count, failed count, and error details.
     */
    public function bulk(BulkPageRequest $request): JsonResponse
    {
        $action    = $request->validated('action');
        $ids       = $request->validated('ids');
        $processed = 0;
        $errors    = [];

        $pages = Page::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $page = $pages->get($id);

            if (null === $page) {
                $errors[$id] = __('Page not found.');

                continue;
            }

            $policyMethod = $this->getBulkPolicyMethod($action);

            if (! $request->user()->can($policyMethod, $page)) {
                $errors[$id] = __('You do not have permission to :action this page.', ['action' => $action]);

                continue;
            }

            try {
                $this->executeBulkAction($action, $page);
                $processed++;
            } catch (Throwable $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        return response()->json([
            'processed' => $processed,
            'failed'    => count($errors),
            'errors'    => $errors,
        ]);
    }

    /**
     * Get the policy method for a bulk action.
     *
     * @since 1.1.0
     *
     * @param  string  $action  The bulk action name.
     *
     * @return string The policy method name.
     */
    protected function getBulkPolicyMethod(string $action): string
    {
        return match ($action) {
            'delete'  => 'delete',
            'publish' => 'publish',
            'draft'   => 'update',
            default   => throw new InvalidArgumentException(__('Unsupported bulk action: :action', ['action' => $action])),
        };
    }

    /**
     * Execute a bulk action on a single page.
     *
     * @since 1.1.0
     *
     * @param  string  $action  The bulk action to perform.
     * @param  Page  $page  The page to perform the action on.
     */
    protected function executeBulkAction(string $action, Page $page): void
    {
        match ($action) {
            'delete'  => $page->delete(),
            'publish' => $page->update([
                'status'       => ContentStatus::Published->value,
                'published_at' => $page->published_at ?? now(),
            ]),
            'draft'   => $page->update([
                'status'       => ContentStatus::Draft->value,
                'published_at' => null,
            ]),
            default   => throw new InvalidArgumentException(__('Unsupported bulk action: :action', ['action' => $action])),
        };
    }
}
