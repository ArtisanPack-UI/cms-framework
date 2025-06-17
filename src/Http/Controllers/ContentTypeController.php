<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\ContentTypeRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\ContentTypeResource;
use ArtisanPackUI\CMSFramework\Models\ContentType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ContentTypeController
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize( 'viewAny', ContentType::class );

        return ContentTypeResource::collection( ContentType::all() );
    }

    public function store( ContentTypeRequest $request )
    {
        $this->authorize( 'create', ContentType::class );

        return new ContentTypeResource( ContentType::create( $request->validated() ) );
    }

    public function show( ContentType $contentType )
    {
        $this->authorize( 'view', $contentType );

        return new ContentTypeResource( $contentType );
    }

    public function update( ContentTypeRequest $request, ContentType $contentType )
    {
        $this->authorize( 'update', $contentType );

        $contentType->update( $request->validated() );

        return new ContentTypeResource( $contentType );
    }

    public function destroy( ContentType $contentType )
    {
        $this->authorize( 'delete', $contentType );

        $contentType->delete();

        return response()->json();
    }
}
