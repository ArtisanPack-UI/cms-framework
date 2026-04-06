<?php

declare(strict_types=1);

/**
 * PageCategory Controller for the CMS Framework Pages Module.
 *
 * This controller handles CRUD operations for page categories including listing,
 * creating, showing, updating, and deleting category records through API endpoints.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Controllers\Concerns\HasIncludableRelationships;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Requests\PageCategoryRequest;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Resources\PageCategoryResource;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing page categories.
 *
 * Provides RESTful API endpoints for page category management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
class PageCategoryController extends Controller
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
    protected array $includableRelationships = ['parent', 'children'];

    /**
     * The default relationships to load when no include parameter is provided.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $defaultIncludes = ['parent', 'children'];

    /**
     * Display a listing of page categories.
     *
     * Retrieves a paginated list of page categories and returns them as a JSON resource collection.
     *
     * @since 1.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of category resources.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PageCategory::class);

        $categories = PageCategory::with($this->getRequestedIncludes($request))->orderBy('order')->paginate(15);

        return PageCategoryResource::collection($categories);
    }

    /**
     * Store a newly created page category.
     *
     * Validates the incoming request data and creates a new category with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 1.0.0
     *
     * @param  PageCategoryRequest  $request  The HTTP request containing category data.
     *
     * @return JsonResponse The JSON response containing the created category resource.
     */
    public function store(PageCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', PageCategory::class);

        $validated = $request->validated();
        $category  = PageCategory::create($validated);
        $category->load($this->getRequestedIncludes($request));

        return response()->json(new PageCategoryResource($category), 201);
    }

    /**
     * Display the specified page category.
     *
     * Retrieves a single page category by ID and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The ID of the category to retrieve.
     *
     * @return PageCategoryResource The category resource.
     */
    public function show(Request $request, int $id): PageCategoryResource
    {
        $category = PageCategory::with($this->getRequestedIncludes($request))->findOrFail($id);
        $this->authorize('view', $category);

        return new PageCategoryResource($category);
    }

    /**
     * Update the specified page category.
     *
     * Validates the incoming request data and updates the category with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 1.0.0
     *
     * @param  PageCategoryRequest  $request  The HTTP request containing updated category data.
     * @param  int  $id  The ID of the category to update.
     *
     * @return PageCategoryResource The updated category resource.
     */
    public function update(PageCategoryRequest $request, int $id): PageCategoryResource
    {
        $category = PageCategory::findOrFail($id);
        $this->authorize('update', $category);

        $validated = $request->validated();
        $category->update($validated);
        $category->load($this->getRequestedIncludes($request));

        return new PageCategoryResource($category);
    }

    /**
     * Remove the specified page category.
     *
     * Deletes a category from the database and returns a successful response
     * with no content.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The ID of the category to delete.
     *
     * @return Response A response with 204 status code.
     */
    public function destroy(int $id): Response
    {
        $category = PageCategory::findOrFail($id);
        $this->authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }
}
