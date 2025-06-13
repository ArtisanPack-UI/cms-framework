<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\MediaRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\MediaResource;
use ArtisanPackUI\CMSFramework\Models\Media;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MediaController
{
	use AuthorizesRequests;

	public function index()
	{
		$this->authorize( 'viewAny', Media::class );

		return MediaResource::collection( Media::all() );
	}

	public function store( MediaRequest $request )
	{
		$this->authorize( 'create', Media::class );

		return new MediaResource( Media::create( $request->validated() ) );
	}

	public function show( Media $media )
	{
		$this->authorize( 'view', $media );

		return new MediaResource( $media );
	}

	public function update( MediaRequest $request, Media $media )
	{
		$this->authorize( 'update', $media );

		$media->update( $request->validated() );

		return new MediaResource( $media );
	}

	public function destroy( Media $media )
	{
		$this->authorize( 'delete', $media );

		$media->delete();

		return response()->json();
	}
}
