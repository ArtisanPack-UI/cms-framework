<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\ContentTypeRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\ContentTypeResource;
use ArtisanPackUI\CMSFramework\Models\ContentType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Content Type Controller.
 *
 * Handles CRUD operations for content types in the CMS Framework.
 * Manages the definition and configuration of different content types.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.1.0
 */
class ContentTypeController
{
    use AuthorizesRequests;

    /**
     * Display a listing of all content types.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection Collection of content type resources.
     */
    public function index()
    {
        $this->authorize( 'viewAny', ContentType::class );

        return ContentTypeResource::collection( ContentType::all() );
    }

    /**
     * Store a newly created content type in storage.
     *
     * @since 1.1.0
     *
     * @param ContentTypeRequest $request The validated request data.
     * @return ContentTypeResource The newly created content type resource.
     */
    public function store( ContentTypeRequest $request )
    {
        // Allow all authenticated users to create content types
        // $this->authorize( 'create', ContentType::class );

        return new ContentTypeResource( ContentType::create( $request->validated() ) );
    }

    /**
     * Display the specified content type.
     *
     * @since 1.1.0
     *
     * @param ContentType $contentType The content type to display.
     * @return ContentTypeResource The content type resource.
     */
    public function show( ContentType $contentType )
    {
        $this->authorize( 'view', $contentType );

        return new ContentTypeResource( $contentType );
    }

    /**
     * Update the specified content type in storage.
     *
     * @since 1.1.0
     *
     * @param ContentTypeRequest $request     The validated request data.
     * @param ContentType        $contentType The content type to update.
     * @return ContentTypeResource The updated content type resource.
     */
    public function update( ContentTypeRequest $request, ContentType $contentType )
    {
        // Allow all authenticated users to update content types
        // $this->authorize( 'update', $contentType );

        $contentType->update( $request->validated() );

        return new ContentTypeResource( $contentType );
    }

    /**
     * Remove the specified content type from storage.
     *
     * @since 1.1.0
     *
     * @param ContentType $contentType The content type to delete.
     * @return \Illuminate\Http\JsonResponse Empty JSON response.
     */
    public function destroy( ContentType $contentType )
    {
        $this->authorize( 'delete', $contentType );

        $contentType->delete();

        return response()->json();
    }
}
