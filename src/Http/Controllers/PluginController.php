<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\PluginRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\PluginResource;
use ArtisanPackUI\CMSFramework\Models\Plugin;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PluginController
{
	use AuthorizesRequests;

	public function index()
	{
		$this->authorize( 'viewAny', Plugin::class );

		return PluginResource::collection( Plugin::all() );
	}

	public function store( PluginRequest $request )
	{
		$this->authorize( 'create', Plugin::class );

		return new PluginResource( Plugin::create( $request->validated() ) );
	}

	public function show( Plugin $plugin )
	{
		$this->authorize( 'view', $plugin );

		return new PluginResource( $plugin );
	}

	public function update( PluginRequest $request, Plugin $plugin )
	{
		$this->authorize( 'update', $plugin );

		$plugin->update( $request->validated() );

		return new PluginResource( $plugin );
	}

	public function destroy( Plugin $plugin )
	{
		$this->authorize( 'delete', $plugin );

		$plugin->delete();

		return response()->json();
	}
}
