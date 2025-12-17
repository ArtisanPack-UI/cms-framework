<?php

/**
 * Permission Controller for the CMS Framework Users Module.
 *
 * This controller handles CRUD operations for permissions including listing,
 * creating, showing, updating, and deleting permission records through API endpoints.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\PermissionResource;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * API controller for managing permissions.
 *
 * Provides RESTful API endpoints for permission management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     *
     * Retrieves a paginated list of permissions with their associated roles
     * and returns them as a JSON resource collection.
     *
     * @since 1.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of permission resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::with('roles')->paginate(15);

        return PermissionResource::collection($permissions);
    }

    /**
     * Store a newly created permission.
     *
     * Validates the incoming request data and creates a new permission with the
     * provided information.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request containing permission data.
     * @return PermissionResource The created permission resource with loaded roles.
     */
    public function store(Request $request): PermissionResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions',
        ]);

        $permission = Permission::create($validated);
        $permission->load('roles');

        return new PermissionResource($permission);
    }

    /**
     * Display the specified permission.
     *
     * Retrieves a single permission by ID with their associated roles
     * and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param  string|int  $id  The ID of the permission to retrieve.
     * @return PermissionResource The permission resource with loaded roles.
     */
    public function show(string|int $id): PermissionResource
    {
        $permission = Permission::with('roles')->findOrFail($id);

        return new PermissionResource($permission);
    }

    /**
     * Update the specified permission.
     *
     * Validates the incoming request data and updates the permission with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request containing updated permission data.
     * @param  string|int  $id  The ID of the permission to update.
     * @return PermissionResource The updated permission resource with loaded roles.
     */
    public function update(Request $request, string|int $id): PermissionResource
    {
        $permission = Permission::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:permissions,slug,'.$permission->id,
        ]);

        $permission->update($validated);
        $permission->load('roles');

        return new PermissionResource($permission);
    }

    /**
     * Remove the specified permission.
     *
     * Deletes a permission from the database and returns a successful response
     * with no content.
     *
     * @since 1.0.0
     *
     * @param  string|int  $id  The ID of the permission to delete.
     * @return JsonResponse A JSON response with 204 status code.
     */
    public function destroy(string|int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json([], 204);
    }
}
