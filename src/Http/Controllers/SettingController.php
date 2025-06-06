<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\SettingRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\SettingResource;
use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SettingController
{
	use AuthorizesRequests;

	public function index()
	{
		$this->authorize( 'viewAny', Setting::class );

		return SettingResource::collection( Setting::all() );
	}

	public function store( SettingRequest $request )
	{
		$this->authorize( 'create', Setting::class );

		return new SettingResource( Setting::create( $request->validated() ) );
	}

	public function show( Setting $setting )
	{
		$this->authorize( 'view', $setting );

		return new SettingResource( $setting );
	}

	public function update( SettingRequest $request, Setting $setting )
	{
		$this->authorize( 'update', $setting );

		$setting->update( $request->validated() );

		return new SettingResource( $setting );
	}

	public function destroy( Setting $setting )
	{
		$this->authorize( 'delete', $setting );

		$setting->delete();

		return response()->json();
	}
}
