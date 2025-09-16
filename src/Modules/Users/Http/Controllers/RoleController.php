<?php

/**
 * Role Controller for the CMS Framework Users Module.
 *
 * This controller handles CRUD operations for roles including listing,
 * creating, showing, updating, and deleting role records through API endpoints.
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * API controller for managing roles.
 *
 * Provides RESTful API endpoints for role management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     *
     * Retrieves a paginated list of roles with their associated permissions
     * and returns them as a JSON resource collection.
     *
     * @since 1.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of role resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::with( 'permissions' )->paginate( 15 );

        return RoleResource::collection( $roles );
    }

    /**
     * Store a newly created role.
     *
     * Validates the incoming request data and creates a new role with the
     * provided information.
     *
     * @since 1.0.0
     *
     * @param Request $request The HTTP request containing role data.
     *
     * @return RoleResource The created role resource with loaded permissions.
     */
    public function store( Request $request ): RoleResource
    {
        $validated = $request->validate( [
                                             'name' => 'required|string|max:255',
                                             'slug' => 'required|string|max:255|unique:roles',
                                         ] );

        $role = Role::create( $validated );
        $role->load( 'permissions' );

        return new RoleResource( $role );
    }

    /**
     * Display the specified role.
     *
     * Retrieves a single role by ID with their associated permissions
     * and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param string|int $id The ID of the role to retrieve.
     *
     * @return RoleResource The role resource with loaded permissions.
     */
    public function show( string | int $id ): RoleResource
    {
        $role = Role::with( 'permissions' )->findOrFail( $id );

        return new RoleResource( $role );
    }

    /**
     * Update the specified role.
     *
     * Validates the incoming request data and updates the role with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 1.0.0
     *
     * @param Request    $request The HTTP request containing updated role data.
     * @param string|int $id      The ID of the role to update.
     *
     * @return RoleResource The updated role resource with loaded permissions.
     */
    public function update( Request $request, string | int $id ): RoleResource
    {
        $role = Role::findOrFail( $id );
        $validated = $request->validate( [
                                             'name' => 'sometimes|required|string|max:255',
                                             'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $role->id,
                                         ] );

        $role->update( $validated );
        $role->load( 'permissions' );

        return new RoleResource( $role );
    }

    /**
     * Remove the specified role.
     *
     * Deletes a role from the database and returns a successful response
     * with no content.
     *
     * @since 1.0.0
     *
     * @param string|int $id The ID of the role to delete.
     *
     * @return JsonResponse A JSON response with 204 status code.
     */
    public function destroy( string | int $id ): JsonResponse
    {
        $role = Role::findOrFail( $id );
        $role->delete();

        return response()->json( [], 204 );
    }
}