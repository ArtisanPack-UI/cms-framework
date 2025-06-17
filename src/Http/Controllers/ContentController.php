<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\ContentRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\ContentResource;
use ArtisanPackUI\CMSFramework\Models\Content;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ContentController
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize( 'viewAny', Content::class );

        return ContentResource::collection( Content::all() );
    }

    public function store( ContentRequest $request )
    {
        $this->authorize( 'create', Content::class );

        return new ContentResource( Content::create( $request->validated() ) );
    }

    public function show( Content $content )
    {
        $this->authorize( 'view', $content );

        return new ContentResource( $content );
    }

    public function update( ContentRequest $request, Content $content )
    {
        $this->authorize( 'update', $content );

        $content->update( $request->validated() );

        return new ContentResource( $content );
    }

    public function destroy( Content $content )
    {
        $this->authorize( 'delete', $content );

        $content->delete();

        return response()->json();
    }
}
