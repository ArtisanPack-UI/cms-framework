<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\UserRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\UserResource;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController
{
	use AuthorizesRequests;

	public function index()
	{
		$this->authorize( 'viewAny', User::class );

		// Get all users and ensure we're returning them all
		$users = User::all();

		// Return the users as a JSON resource with a 'data' wrapper
		return response()->json([
			'data' => $users->map(function ($user) {
				return [
					'id' => $user->id,
					'username' => $user->username,
					'email' => $user->email,
					'email_verified_at' => $user->email_verified_at,
					'password' => $user->password,
					'role_id' => $user->role_id,
					'first_name' => $user->first_name,
					'last_name' => $user->last_name,
					'website' => $user->website,
					'bio' => $user->bio,
					'links' => $user->links,
					'settings' => $user->settings,
					'created_at' => $user->created_at,
					'updated_at' => $user->updated_at,
				];
			})
		]);
	}

	public function store( UserRequest $request )
	{
		$this->authorize( 'create', User::class );

		return new UserResource( User::create( $request->validated() ) );
	}

	public function show( $id )
	{
		// Find the user manually instead of relying on model binding
		$user = User::findOrFail($id);

		$this->authorize( 'view', $user );

		return new UserResource( $user );
	}

	public function update( UserRequest $request, $id )
	{
		// Find the user manually instead of relying on model binding
		$user = User::findOrFail($id);

		$this->authorize( 'update', $user );

		$user->update( $request->validated() );

		// Refresh the user to get the updated data
		$user->refresh();

		return new UserResource( $user );
	}

	public function destroy( $id )
	{
		// Find the user manually instead of relying on model binding
		$user = User::findOrFail($id);

		$this->authorize( 'delete', $user );

		$user->delete();

		return response()->json();
	}
}
