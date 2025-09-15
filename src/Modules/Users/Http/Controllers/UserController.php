<?php

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\UserResource;

class UserController extends Controller
{
	/**
	 * Display a listing of the resource.
	 */
	public function index(): AnonymousResourceCollection
	{
		$users = User::with('roles')->paginate(15);
		
		return UserResource::collection($users);
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request): UserResource
	{
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|string|email|max:255|unique:users',
			'password' => 'required|string|min:8',
		]);

		$validated['password'] = bcrypt($validated['password']);

		$user = User::create($validated);
		$user->load('roles');

		return new UserResource($user);
	}

	/**
	 * Display the specified resource.
	 */
	public function show(User $user): UserResource
	{
		$user->load('roles');
		
		return new UserResource($user);
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, User $user): UserResource
	{
		$validated = $request->validate([
			'name' => 'sometimes|required|string|max:255',
			'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
			'password' => 'sometimes|required|string|min:8',
		]);

		if (isset($validated['password'])) {
			$validated['password'] = bcrypt($validated['password']);
		}

		$user->update($validated);
		$user->load('roles');

		return new UserResource($user);
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(User $user): JsonResponse
	{
		$user->delete();

		return response()->json(['message' => 'User deleted successfully'], 204);
	}
}