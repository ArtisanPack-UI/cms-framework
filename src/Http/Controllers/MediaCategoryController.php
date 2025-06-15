<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\MediaCategoryRequest;
use ArtisanPackUI\CMSFramework\Http\Requests\MediaTagRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\MediaCategoryResource;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class MediaCategoryController
{
	use AuthorizesRequests;

	public function index()
	{
		$this->authorize( 'viewAny', MediaCategory::class );

		return MediaCategoryResource::collection( MediaCategory::all() );
	}

	public function store( MediaCategoryRequest $request )
	{
		$this->authorize( 'create', MediaCategory::class );

		return new MediaCategoryResource( MediaCategory::create( $request->validated() ) );
	}

	public function show( MediaCategory $mediaCategory )
	{
		$this->authorize( 'view', $mediaCategory );

		return new MediaCategoryResource( $mediaCategory );
	}

	/**
	 * Update the specified media category in storage.
	 *
	 * @since 1.0.0
	 * @param MediaCategory        $mediaCategory The MediaCategory instance resolved by route model binding.
	 * @param MediaCategoryRequest $request       The validated form request.
	 * @return JsonResponse
	 */
	public function update( MediaCategoryRequest $request, MediaCategory $mediaCategory ): JsonResponse
	{
		// REMOVE: $request->setResolvedMediaCategory( $mediaCategory ); // THIS CALL IS NO LONGER VALID

		$validatedData = $request->validated();
		$mediaCategory->update( $validatedData );

		return response()->json( [ 'message' => 'Media category updated successfully.', 'data' => $mediaCategory ] );
	}

	/**
	 * Remove the specified media category from storage.
	 *
	 * @since 1.0.0
	 * @param MediaCategory        $mediaCategory The MediaCategory instance resolved by route model binding.
	 * @param MediaCategoryRequest $request       The validated form request.
	 * @return JsonResponse
	 */
	public function destroy( MediaCategoryRequest $request, MediaCategory $mediaCategory ): JsonResponse
	{
		// REMOVE: $request->setResolvedMediaCategory( $mediaCategory ); // THIS CALL IS NO LONGER VALID

		if ( $mediaCategory->delete() ) {
			return response()->json( [ 'message' => 'Media category deleted successfully.' ], 204 );
		}

		return response()->json( [ 'message' => 'Media category deletion failed.' ], 500 );
	}
}
