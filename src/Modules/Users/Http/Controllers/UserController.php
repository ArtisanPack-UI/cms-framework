<?php

/**
 * User Controller for the CMS Framework Users Module.
 *
 * This controller handles CRUD operations for users including listing,
 * creating, showing, updating, and deleting user records through API endpoints.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * API controller for managing users.
 *
 * Provides RESTful API endpoints for user management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
class UserController extends Controller
{
    /**
     * Display a listing of users.
     *
     * Retrieves a paginated list of users with their associated roles
     * and returns them as a JSON resource collection.
     *
     * @since 1.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of user resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $userModel = config('cms-framework.user_model');
        $users = $userModel::with('roles')->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     *
     * Validates the incoming request data and creates a new user with the
     * provided information. The password is automatically hashed before storage.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request containing user data.
     * @return UserResource The created user resource with loaded roles.
     */
    public function store(Request $request): UserResource
    {
        $userModel = config('cms-framework.user_model');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $validated['password'] = bcrypt($validated['password']);

        $user = $userModel::create($validated);
        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Display the specified user.
     *
     * Retrieves a single user by ID with their associated roles
     * and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param  string|int  $id  The ID of the user to retrieve.
     * @return UserResource The user resource with loaded roles.
     */
    public function show(string|int $id): UserResource
    {
        $userModel = config('cms-framework.user_model');
        $user = $userModel::with('roles')->findOrFail($id);

        return new UserResource($user);
    }

    /**
     * Update the specified user.
     *
     * Validates the incoming request data and updates the user with the
     * provided information. Only provided fields are updated (partial updates).
     * Passwords are automatically hashed if provided.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request containing updated user data.
     * @param  string|int  $id  The ID of the user to update.
     * @return UserResource The updated user resource with loaded roles.
     */
    public function update(Request $request, string|int $id): UserResource
    {
        $userModel = config('cms-framework.user_model');
        $user = $userModel::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$user->id,
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
     * Remove the specified user.
     *
     * Deletes a user from the database and returns a successful response
     * with no content.
     *
     * @since 1.0.0
     *
     * @param  string|int  $id  The ID of the user to delete.
     * @return JsonResponse A JSON response with 204 status code.
     */
    public function destroy(string|int $id): JsonResponse
    {
        $userModel = config('cms-framework.user_model');
        $user = $userModel::findOrFail($id);
        $user->delete();

        return response()->json([], 204);
    }
}
