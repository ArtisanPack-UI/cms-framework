<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\MediaTagRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\MediaTagResource;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MediaTagController
{
	use AuthorizesRequests;

	public function index()
	{
		$this->authorize( 'viewAny', MediaTag::class );

		return MediaTagResource::collection( MediaTag::all() );
	}

	public function store( MediaTagRequest $request )
	{
		$this->authorize( 'create', MediaTag::class );

		return new MediaTagResource( MediaTag::create( $request->validated() ) );
	}

	public function show( MediaTag $mediaTag )
	{
		$this->authorize( 'view', $mediaTag );

		return new MediaTagResource( $mediaTag );
	}

	public function update( MediaTagRequest $request, MediaTag $mediaTag )
	{
		$this->authorize( 'update', $mediaTag );

		$mediaTag->update( $request->validated() );

		return new MediaTagResource( $mediaTag );
	}

	public function destroy( MediaTag $mediaTag )
	{
		$this->authorize( 'delete', $mediaTag );

		$mediaTag->delete();

		return response()->json();
	}
}
