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

        $validated = $request->validated();

        // Extract terms from the validated data
        $terms = $validated['terms'] ?? null;
        unset($validated['terms']);

        // Create the content
        $content = Content::create( $validated );

        // Sync terms if provided
        if ($terms !== null) {
            $content->terms()->sync($terms);
        }

        return new ContentResource( $content );
    }

    public function show( Content $content )
    {
        $this->authorize( 'view', $content );

        return new ContentResource( $content );
    }

    public function update( ContentRequest $request, Content $content )
    {
        $this->authorize( 'update', $content );

        $validated = $request->validated();

        // Extract terms from the validated data
        $terms = $validated['terms'] ?? null;
        unset($validated['terms']);

        // Update the content
        $content->update( $validated );

        // Sync terms if provided
        if ($terms !== null) {
            $content->terms()->sync($terms);
        }

        return new ContentResource( $content );
    }

    public function destroy( Content $content )
    {
        $this->authorize( 'delete', $content );

        $content->delete();

        return response()->json();
    }
}
