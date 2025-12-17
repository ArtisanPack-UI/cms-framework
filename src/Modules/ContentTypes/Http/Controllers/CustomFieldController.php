<?php

/**
 * CustomField Controller for the CMS Framework ContentTypes Module.
 *
 * This controller handles CRUD operations for custom fields including listing,
 * creating, showing, updating, and deleting custom field records through API endpoints.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Requests\CustomFieldRequest;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Resources\CustomFieldResource;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing custom fields.
 *
 * Provides RESTful API endpoints for custom field management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 2.0.0
 */
class CustomFieldController extends Controller
{
    use AuthorizesRequests;

    /**
     * The custom field manager instance.
     *
     * @since 2.0.0
     */
    protected CustomFieldManager $customFieldManager;

    /**
     * Create a new controller instance.
     *
     * @since 2.0.0
     */
    public function __construct(CustomFieldManager $customFieldManager)
    {
        $this->customFieldManager = $customFieldManager;
    }

    /**
     * Display a listing of custom fields.
     *
     * Retrieves a paginated list of custom fields and returns them as a JSON resource collection.
     *
     * @since 2.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of custom field resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomField::class);

        $customFields = CustomField::orderBy('order')->paginate(15);

        return CustomFieldResource::collection($customFields);
    }

    /**
     * Store a newly created custom field.
     *
     * Validates the incoming request data and creates a new custom field with the
     * provided information. Also adds columns to the content type tables.
     * Returns the created resource with a 201 status code.
     *
     * @since 2.0.0
     *
     * @param  CustomFieldRequest  $request  The HTTP request containing custom field data.
     * @return JsonResponse The JSON response containing the created custom field resource.
     */
    public function store(CustomFieldRequest $request): JsonResponse
    {
        $this->authorize('create', CustomField::class);

        $validated = $request->validated();
        $customField = $this->customFieldManager->createField($validated);

        return response()->json(new CustomFieldResource($customField), 201);
    }

    /**
     * Display the specified custom field.
     *
     * Retrieves a single custom field by ID and returns it as a JSON resource.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the custom field to retrieve.
     * @return CustomFieldResource The custom field resource.
     */
    public function show(int $id): CustomFieldResource
    {
        $customField = CustomField::findOrFail($id);
        $this->authorize('view', $customField);

        return new CustomFieldResource($customField);
    }

    /**
     * Update the specified custom field.
     *
     * Validates the incoming request data and updates the custom field with the
     * provided information. Handles adding/removing columns from content type tables.
     * Only provided fields are updated (partial updates).
     *
     * @since 2.0.0
     *
     * @param  CustomFieldRequest  $request  The HTTP request containing updated custom field data.
     * @param  int  $id  The ID of the custom field to update.
     * @return CustomFieldResource The updated custom field resource.
     */
    public function update(CustomFieldRequest $request, int $id): CustomFieldResource
    {
        $customField = CustomField::findOrFail($id);
        $this->authorize('update', $customField);

        $validated = $request->validated();
        $customField = $this->customFieldManager->updateField($id, $validated);

        return new CustomFieldResource($customField);
    }

    /**
     * Remove the specified custom field.
     *
     * Deletes a custom field from the database and removes columns from content type tables.
     * Returns a successful response with no content.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The ID of the custom field to delete.
     * @return Response A response with 204 status code.
     */
    public function destroy(int $id): Response
    {
        $customField = CustomField::findOrFail($id);
        $this->authorize('delete', $customField);

        $this->customFieldManager->deleteField($id);

        return response()->noContent();
    }
}
