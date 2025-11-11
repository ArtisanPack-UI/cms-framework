<?php

/**
 * Taxonomy Controller for the CMS Framework ContentTypes Module.
 *
 * This controller handles CRUD operations for taxonomies including listing,
 * creating, showing, updating, and deleting taxonomy records through API endpoints.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Controllers
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Requests\TaxonomyRequest;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Resources\TaxonomyResource;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\TaxonomyManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Taxonomy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing taxonomies.
 *
 * Provides RESTful API endpoints for taxonomy management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 2.0.0
 */
class TaxonomyController extends Controller
{
    use AuthorizesRequests;

    /**
     * The taxonomy manager instance.
     *
     * @since 2.0.0
     */
    protected TaxonomyManager $taxonomyManager;

    /**
     * Create a new controller instance.
     *
     * @since 2.0.0
     */
    public function __construct(TaxonomyManager $taxonomyManager)
    {
        $this->taxonomyManager = $taxonomyManager;
    }

    /**
     * Display a listing of taxonomies.
     *
     * Retrieves a paginated list of taxonomies and returns them as a JSON resource collection.
     *
     * @since 2.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of taxonomy resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Taxonomy::class);

        $taxonomies = Taxonomy::paginate(15);

        return TaxonomyResource::collection($taxonomies);
    }

    /**
     * Store a newly created taxonomy.
     *
     * Validates the incoming request data and creates a new taxonomy with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 2.0.0
     *
     * @param  TaxonomyRequest  $request  The HTTP request containing taxonomy data.
     * @return JsonResponse The JSON response containing the created taxonomy resource.
     */
    public function store(TaxonomyRequest $request): JsonResponse
    {
        $this->authorize('create', Taxonomy::class);

        $validated = $request->validated();
        $taxonomy = $this->taxonomyManager->createTaxonomy($validated);

        return response()->json(new TaxonomyResource($taxonomy), 201);
    }

    /**
     * Display the specified taxonomy.
     *
     * Retrieves a single taxonomy by slug and returns it as a JSON resource.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The slug of the taxonomy to retrieve.
     * @return TaxonomyResource The taxonomy resource.
     */
    public function show(string $slug): TaxonomyResource
    {
        $this->authorize('view', Taxonomy::class);

        $taxonomy = Taxonomy::where('slug', $slug)->firstOrFail();

        return new TaxonomyResource($taxonomy);
    }

    /**
     * Update the specified taxonomy.
     *
     * Validates the incoming request data and updates the taxonomy with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 2.0.0
     *
     * @param  TaxonomyRequest  $request  The HTTP request containing updated taxonomy data.
     * @param  string  $slug  The slug of the taxonomy to update.
     * @return TaxonomyResource The updated taxonomy resource.
     */
    public function update(TaxonomyRequest $request, string $slug): TaxonomyResource
    {
        $taxonomy = Taxonomy::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $taxonomy);

        $validated = $request->validated();
        $taxonomy = $this->taxonomyManager->updateTaxonomy($slug, $validated);

        return new TaxonomyResource($taxonomy);
    }

    /**
     * Remove the specified taxonomy.
     *
     * Deletes a taxonomy from the database and returns a successful response
     * with no content.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The slug of the taxonomy to delete.
     * @return Response A response with 204 status code.
     */
    public function destroy(string $slug): Response
    {
        $taxonomy = Taxonomy::where('slug', $slug)->firstOrFail();
        $this->authorize('delete', $taxonomy);

        $this->taxonomyManager->deleteTaxonomy($slug);

        return response()->noContent();
    }

    /**
     * Get taxonomies for a specific content type.
     *
     * @since 2.0.0
     *
     * @param  string  $contentTypeSlug  The slug of the content type.
     * @return AnonymousResourceCollection The collection of taxonomy resources.
     */
    public function byContentType(string $contentTypeSlug): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Taxonomy::class);

        $taxonomies = $this->taxonomyManager->getTaxonomiesForContentType($contentTypeSlug);

        return TaxonomyResource::collection($taxonomies);
    }
}
