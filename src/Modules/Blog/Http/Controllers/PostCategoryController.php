<?php

/**
 * PostCategory Controller for the CMS Framework Blog Module.
 *
 * This controller handles CRUD operations for post categories including listing,
 * creating, showing, updating, and deleting category records through API endpoints.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests\PostCategoryRequest;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources\PostCategoryResource;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing post categories.
 *
 * Provides RESTful API endpoints for post category management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 2.0.0
 */
class PostCategoryController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of post categories.
     *
     * Retrieves a paginated list of post categories and returns them as a JSON resource collection.
     *
     * @since 2.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of category resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PostCategory::class);

        $categories = PostCategory::with(['parent', 'children'])->orderBy('order')->paginate(15);

        return PostCategoryResource::collection($categories);
    }

    /**
     * Store a newly created post category.
     *
     * Validates the incoming request data and creates a new category with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 2.0.0
     *
     * @param  PostCategoryRequest  $request  The HTTP request containing category data.
     * @return JsonResponse The JSON response containing the created category resource.
     */
    public function store(PostCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', PostCategory::class);

        $validated = $request->validated();
        $category = PostCategory::create($validated);
        $category->load(['parent', 'children']);

        return response()->json(new PostCategoryResource($category), 201);
    }

    /**
     * Display the specified post category.
     *
     * Retrieves a single post category by ID and returns it as a JSON resource.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the category to retrieve.
     * @return PostCategoryResource The category resource.
     */
    public function show(int $id): PostCategoryResource
    {
        $this->authorize('view', PostCategory::class);

        $category = PostCategory::with(['parent', 'children'])->findOrFail($id);

        return new PostCategoryResource($category);
    }

    /**
     * Update the specified post category.
     *
     * Validates the incoming request data and updates the category with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 2.0.0
     *
     * @param  PostCategoryRequest  $request  The HTTP request containing updated category data.
     * @param  int  $id  The ID of the category to update.
     * @return PostCategoryResource The updated category resource.
     */
    public function update(PostCategoryRequest $request, int $id): PostCategoryResource
    {
        $category = PostCategory::findOrFail($id);
        $this->authorize('update', $category);

        $validated = $request->validated();
        $category->update($validated);
        $category->load(['parent', 'children']);

        return new PostCategoryResource($category);
    }

    /**
     * Remove the specified post category.
     *
     * Deletes a category from the database and returns a successful response
     * with no content.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the category to delete.
     * @return Response A response with 204 status code.
     */
    public function destroy(int $id): Response
    {
        $category = PostCategory::findOrFail($id);
        $this->authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }
}
