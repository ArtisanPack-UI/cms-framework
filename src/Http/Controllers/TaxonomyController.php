<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\TaxonomyRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\TaxonomyResource;
use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Taxonomy Controller.
 *
 * Handles CRUD operations for taxonomies in the CMS Framework.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.1.0
 */
class TaxonomyController
{
    use AuthorizesRequests;

    /**
     * Display a listing of all taxonomies.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $this->authorize( 'viewAny', Taxonomy::class );

        return TaxonomyResource::collection( Taxonomy::all() );
    }

    /**
     * Store a newly created taxonomy in storage.
     *
     * @since 1.1.0
     *
     * @param TaxonomyRequest $request The validated request data.
     * @return TaxonomyResource The newly created taxonomy resource.
     */
    public function store( TaxonomyRequest $request )
    {
        // Allow all authenticated users to create taxonomies
        // $this->authorize( 'create', Taxonomy::class );

        return new TaxonomyResource( Taxonomy::create( $request->validated() ) );
    }

    /**
     * Display the specified taxonomy.
     *
     * @since 1.1.0
     *
     * @param Taxonomy $taxonomy The taxonomy to display.
     * @return TaxonomyResource The taxonomy resource.
     */
    public function show( Taxonomy $taxonomy )
    {
        $this->authorize( 'view', $taxonomy );

        return new TaxonomyResource( $taxonomy );
    }

    /**
     * Update the specified taxonomy in storage.
     *
     * @since 1.1.0
     *
     * @param TaxonomyRequest $request  The validated request data.
     * @param Taxonomy        $taxonomy The taxonomy to update.
     * @return TaxonomyResource The updated taxonomy resource.
     */
    public function update( TaxonomyRequest $request, Taxonomy $taxonomy )
    {
        // Allow all authenticated users to update taxonomies
        // $this->authorize( 'update', $taxonomy );

        $taxonomy->update( $request->validated() );

        return new TaxonomyResource( $taxonomy );
    }

    /**
     * Remove the specified taxonomy from storage.
     *
     * @since 1.1.0
     *
     * @param Taxonomy $taxonomy The taxonomy to delete.
     * @return \Illuminate\Http\JsonResponse Empty JSON response.
     */
    public function destroy( Taxonomy $taxonomy )
    {
        $this->authorize( 'delete', $taxonomy );

        $taxonomy->delete();

        return response()->json();
    }
}
