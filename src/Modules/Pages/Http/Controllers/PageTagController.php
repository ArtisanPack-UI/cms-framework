<?php

/**
 * PageTag Controller for the CMS Framework Pages Module.
 *
 * This controller handles CRUD operations for page tags including listing,
 * creating, showing, updating, and deleting tag records through API endpoints.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Requests\PageTagRequest;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Resources\PageTagResource;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing page tags.
 *
 * Provides RESTful API endpoints for page tag management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 2.0.0
 */
class PageTagController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of page tags.
     *
     * Retrieves a paginated list of page tags and returns them as a JSON resource collection.
     *
     * @since 2.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of tag resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PageTag::class);

        $tags = PageTag::orderBy('order')->paginate(15);

        return PageTagResource::collection($tags);
    }

    /**
     * Store a newly created page tag.
     *
     * Validates the incoming request data and creates a new tag with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 2.0.0
     *
     * @param  PageTagRequest  $request  The HTTP request containing tag data.
     * @return JsonResponse The JSON response containing the created tag resource.
     */
    public function store(PageTagRequest $request): JsonResponse
    {
        $this->authorize('create', PageTag::class);

        $validated = $request->validated();
        $tag = PageTag::create($validated);

        return response()->json(new PageTagResource($tag), 201);
    }

    /**
     * Display the specified page tag.
     *
     * Retrieves a single page tag by ID and returns it as a JSON resource.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the tag to retrieve.
     * @return PageTagResource The tag resource.
     */
    public function show(int $id): PageTagResource
    {
        $this->authorize('view', PageTag::class);

        $tag = PageTag::findOrFail($id);

        return new PageTagResource($tag);
    }

    /**
     * Update the specified page tag.
     *
     * Validates the incoming request data and updates the tag with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 2.0.0
     *
     * @param  PageTagRequest  $request  The HTTP request containing updated tag data.
     * @param  int  $id  The ID of the tag to update.
     * @return PageTagResource The updated tag resource.
     */
    public function update(PageTagRequest $request, int $id): PageTagResource
    {
        $tag = PageTag::findOrFail($id);
        $this->authorize('update', $tag);

        $validated = $request->validated();
        $tag->update($validated);

        return new PageTagResource($tag);
    }

    /**
     * Remove the specified page tag.
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
        $tag = PageTag::findOrFail($id);
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }
}
