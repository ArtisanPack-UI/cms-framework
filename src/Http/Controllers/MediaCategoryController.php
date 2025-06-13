<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\MediaCategoryRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\MediaCategoryResource;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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

	public function update( MediaCategoryRequest $request, MediaCategory $mediaCategory )
	{
		$this->authorize( 'update', $mediaCategory );

		$mediaCategory->update( $request->validated() );

		return new MediaCategoryResource( $mediaCategory );
	}

	public function destroy( MediaCategory $mediaCategory )
	{
		$this->authorize( 'delete', $mediaCategory );

		$mediaCategory->delete();

		return response()->json();
	}
}
