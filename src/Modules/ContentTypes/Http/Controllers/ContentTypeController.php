<?php

/**
 * ContentType Controller for the CMS Framework ContentTypes Module.
 *
 * This controller handles CRUD operations for content types including listing,
 * creating, showing, updating, and deleting content type records through API endpoints.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Controllers
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Requests\ContentTypeRequest;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Resources\ContentTypeResource;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing content types.
 *
 * Provides RESTful API endpoints for content type management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 2.0.0
 */
class ContentTypeController extends Controller
{
    use AuthorizesRequests;

    /**
     * The content type manager instance.
     *
     * @since 2.0.0
     */
    protected ContentTypeManager $contentTypeManager;

    /**
     * Create a new controller instance.
     *
     * @since 2.0.0
     */
    public function __construct(ContentTypeManager $contentTypeManager)
    {
        $this->contentTypeManager = $contentTypeManager;
    }

    /**
     * Display a listing of content types.
     *
     * Retrieves a paginated list of content types and returns them as a JSON resource collection.
     *
     * @since 2.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of content type resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContentType::class);

        $contentTypes = ContentType::select('content_types.*')
            ->selectSub(
                \ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField::selectRaw('count(*)')
                    ->whereRaw("JSON_CONTAINS(custom_fields.content_types, CONCAT('\"', content_types.slug, '\"'))"),
                'custom_fields_count'
            )
            ->paginate(15);

        return ContentTypeResource::collection($contentTypes);
    }

    /**
     * Store a newly created content type.
     *
     * Validates the incoming request data and creates a new content type with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 2.0.0
     *
     * @param  ContentTypeRequest  $request  The HTTP request containing content type data.
     * @return JsonResponse The JSON response containing the created content type resource.
     */
    public function store(ContentTypeRequest $request): JsonResponse
    {
        $this->authorize('create', ContentType::class);

        $validated = $request->validated();
        $contentType = $this->contentTypeManager->createContentType($validated);

        return response()->json(new ContentTypeResource($contentType), 201);
    }

    /**
     * Display the specified content type.
     *
     * Retrieves a single content type by slug and returns it as a JSON resource.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The slug of the content type to retrieve.
     * @return ContentTypeResource The content type resource.
     */
    public function show(string $slug): ContentTypeResource
    {
        $contentType = ContentType::select('content_types.*')
            ->selectSub(
                \ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField::selectRaw('count(*)')
                    ->whereRaw("JSON_CONTAINS(custom_fields.content_types, CONCAT('\"', content_types.slug, '\"'))"),
                'custom_fields_count'
            )
            ->where('slug', $slug)
            ->firstOrFail();
        $this->authorize('view', $contentType);

        return new ContentTypeResource($contentType);
    }

    /**
     * Update the specified content type.
     *
     * Validates the incoming request data and updates the content type with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 2.0.0
     *
     * @param  ContentTypeRequest  $request  The HTTP request containing updated content type data.
     * @param  string  $slug  The slug of the content type to update.
     * @return ContentTypeResource The updated content type resource.
     */
    public function update(ContentTypeRequest $request, string $slug): ContentTypeResource
    {
        $contentType = ContentType::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $contentType);

        $validated = $request->validated();
        $contentType = $this->contentTypeManager->updateContentType($slug, $validated);

        return new ContentTypeResource($contentType);
    }

    /**
     * Remove the specified content type.
     *
     * Deletes a content type from the database and returns a successful response
     * with no content.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The slug of the content type to delete.
     * @return Response A response with 204 status code.
     */
    public function destroy(string $slug): Response
    {
        $contentType = ContentType::where('slug', $slug)->firstOrFail();
        $this->authorize('delete', $contentType);

        $this->contentTypeManager->deleteContentType($slug);

        return response()->noContent();
    }

    /**
     * Get custom fields for a specific content type.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The slug of the content type.
     * @return JsonResponse The JSON response containing the custom fields.
     */
    public function customFields(string $slug): JsonResponse
    {
        $contentType = ContentType::where('slug', $slug)->firstOrFail();
        $this->authorize('view', $contentType);

        $customFields = $contentType->getCustomFields();

        return response()->json($customFields);
    }
}
