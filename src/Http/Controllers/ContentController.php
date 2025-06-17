<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\ContentRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\ContentResource;
use ArtisanPackUI\CMSFramework\Models\Content;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Content Controller.
 *
 * Handles CRUD operations for content items in the CMS Framework.
 * Manages content creation, retrieval, updating, and deletion with term associations.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.1.0
 */
class ContentController
{
    use AuthorizesRequests;

    /**
     * Display a listing of all content items.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection Collection of content resources.
     */
    public function index()
    {
        $this->authorize( 'viewAny', Content::class );

        return ContentResource::collection( Content::all() );
    }

    /**
     * Store a newly created content item in storage.
     *
     * @since 1.1.0
     *
     * @param ContentRequest $request The validated request data.
     * @return ContentResource The newly created content resource.
     */
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

    /**
     * Display the specified content item.
     *
     * @since 1.1.0
     *
     * @param Content $content The content item to display.
     * @return ContentResource The content resource.
     */
    public function show( Content $content )
    {
        $this->authorize( 'view', $content );

        return new ContentResource( $content );
    }

    /**
     * Update the specified content item in storage.
     *
     * @since 1.1.0
     *
     * @param ContentRequest $request The validated request data.
     * @param Content        $content The content item to update.
     * @return ContentResource The updated content resource.
     */
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

    /**
     * Remove the specified content item from storage.
     *
     * @since 1.1.0
     *
     * @param Content $content The content item to delete.
     * @return \Illuminate\Http\JsonResponse Empty JSON response.
     */
    public function destroy( Content $content )
    {
        $this->authorize( 'delete', $content );

        $content->delete();

        return response()->json();
    }
}
