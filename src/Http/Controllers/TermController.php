<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\TermRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\TermResource;
use ArtisanPackUI\CMSFramework\Models\Term;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TermController
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize( 'viewAny', Term::class );

        return TermResource::collection( Term::all() );
    }

    public function store( TermRequest $request )
    {
        $this->authorize( 'create', Term::class );

        return new TermResource( Term::create( $request->validated() ) );
    }

    public function show( Term $term )
    {
        $this->authorize( 'view', $term );

        return new TermResource( $term );
    }

    public function update( TermRequest $request, Term $term )
    {
        $this->authorize( 'update', $term );

        $term->update( $request->validated() );

        return new TermResource( $term );
    }

    public function destroy( Term $term )
    {
        $this->authorize( 'delete', $term );

        $term->delete();

        return response()->json();
    }
}
