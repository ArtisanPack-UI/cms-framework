<?php

/**
 * PostTag Controller for the CMS Framework Blog Module.
 *
 * This controller handles CRUD operations for post tags including listing,
 * creating, showing, updating, and deleting tag records through API endpoints.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests\PostTagRequest;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources\PostTagResource;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing post tags.
 *
 * Provides RESTful API endpoints for post tag management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 2.0.0
 */
class PostTagController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of post tags.
     *
     * Retrieves a paginated list of post tags and returns them as a JSON resource collection.
     *
     * @since 2.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of tag resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PostTag::class);

        $tags = PostTag::orderBy('order')->paginate(15);

        return PostTagResource::collection($tags);
    }

    /**
     * Store a newly created post tag.
     *
     * Validates the incoming request data and creates a new tag with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 2.0.0
     *
     * @param  PostTagRequest  $request  The HTTP request containing tag data.
     * @return JsonResponse The JSON response containing the created tag resource.
     */
    public function store(PostTagRequest $request): JsonResponse
    {
        $this->authorize('create', PostTag::class);

        $validated = $request->validated();
        $tag = PostTag::create($validated);

        return response()->json(new PostTagResource($tag), 201);
    }

    /**
     * Display the specified post tag.
     *
     * Retrieves a single post tag by ID and returns it as a JSON resource.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the tag to retrieve.
     * @return PostTagResource The tag resource.
     */
    public function show(int $id): PostTagResource
    {
        $this->authorize('view', PostTag::class);

        $tag = PostTag::findOrFail($id);

        return new PostTagResource($tag);
    }

    /**
     * Update the specified post tag.
     *
     * Validates the incoming request data and updates the tag with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 2.0.0
     *
     * @param  PostTagRequest  $request  The HTTP request containing updated tag data.
     * @param  int  $id  The ID of the tag to update.
     * @return PostTagResource The updated tag resource.
     */
    public function update(PostTagRequest $request, int $id): PostTagResource
    {
        $tag = PostTag::findOrFail($id);
        $this->authorize('update', $tag);

        $validated = $request->validated();
        $tag->update($validated);

        return new PostTagResource($tag);
    }

    /**
     * Remove the specified post tag.
     *
     * Deletes a tag from the database and returns a successful response
     * with no content.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the tag to delete.
     * @return Response A response with 204 status code.
     */
    public function destroy(int $id): Response
    {
        $tag = PostTag::findOrFail($id);
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }
}
