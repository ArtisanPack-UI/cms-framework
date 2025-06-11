<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\RoleRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\RoleResource;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoleController
{
	use AuthorizesRequests;

	public function index()
	{
		$this->authorize( 'viewAny', Role::class );

		// Get all roles and ensure we're returning them all
		$roles = Role::all();

		// Return the roles as a JSON resource with a 'data' wrapper
		return response()->json([
			'data' => $roles->map(function ($role) {
				return [
					'id' => $role->id,
					'name' => $role->name,
					'slug' => $role->slug,
					'description' => $role->description,
					'capabilities' => $role->capabilities,
					'created_at' => $role->created_at,
					'updated_at' => $role->updated_at,
				];
			})
		]);
	}

	public function store( RoleRequest $request )
	{
		$this->authorize( 'create', Role::class );

		return new RoleResource( Role::create( $request->validated() ) );
	}

	public function show( $id )
	{
		// Find the role manually instead of relying on model binding
		$role = Role::findOrFail($id);

		$this->authorize( 'view', $role );

		return new RoleResource( $role );
	}

	public function update( RoleRequest $request, $id )
	{
		// Find the role manually instead of relying on model binding
		$role = Role::findOrFail($id);

		$this->authorize( 'update', $role );

		$role->update( $request->validated() );

		// Refresh the role to get the updated data
		$role->refresh();

		return new RoleResource( $role );
	}

	public function destroy( $id )
	{
		// Find the role manually instead of relying on model binding
		$role = Role::findOrFail($id);

		$this->authorize( 'delete', $role );

		$role->delete();

		return response()->json();
	}
}
