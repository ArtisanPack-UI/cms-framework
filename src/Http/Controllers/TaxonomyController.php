<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\TaxonomyRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\TaxonomyResource;
use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaxonomyController
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize( 'viewAny', Taxonomy::class );

        return TaxonomyResource::collection( Taxonomy::all() );
    }

    public function store( TaxonomyRequest $request )
    {
        // Allow all authenticated users to create taxonomies
        // $this->authorize( 'create', Taxonomy::class );

        return new TaxonomyResource( Taxonomy::create( $request->validated() ) );
    }

    public function show( Taxonomy $taxonomy )
    {
        $this->authorize( 'view', $taxonomy );

        return new TaxonomyResource( $taxonomy );
    }

    public function update( TaxonomyRequest $request, Taxonomy $taxonomy )
    {
        // Allow all authenticated users to update taxonomies
        // $this->authorize( 'update', $taxonomy );

        $taxonomy->update( $request->validated() );

        return new TaxonomyResource( $taxonomy );
    }

    public function destroy( Taxonomy $taxonomy )
    {
        $this->authorize( 'delete', $taxonomy );

        $taxonomy->delete();

        return response()->json();
    }
}
