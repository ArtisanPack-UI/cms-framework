<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\TermRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\TermResource;
use ArtisanPackUI\CMSFramework\Models\Term;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Term Controller.
 *
 * Handles CRUD operations for taxonomy terms in the CMS Framework.
 * Manages the creation, retrieval, updating, and deletion of terms.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.1.0
 */
class TermController
{
    use AuthorizesRequests;

    /**
     * Display a listing of all terms.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection Collection of term resources.
     */
    public function index()
    {
        $this->authorize( 'viewAny', Term::class );

        return TermResource::collection( Term::all() );
    }

    /**
     * Store a newly created term in storage.
     *
     * @since 1.1.0
     *
     * @param TermRequest $request The validated request data.
     * @return TermResource The newly created term resource.
     */
    public function store( TermRequest $request )
    {
        $this->authorize( 'create', Term::class );

        return new TermResource( Term::create( $request->validated() ) );
    }

    /**
     * Display the specified term.
     *
     * @since 1.1.0
     *
     * @param Term $term The term to display.
     * @return TermResource The term resource.
     */
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
