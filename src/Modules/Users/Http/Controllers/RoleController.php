<?php

declare( strict_types=1 );

/**
 * Role Controller for the CMS Framework Users Module.
 *
 * This controller handles CRUD operations for roles including listing,
 * creating, showing, updating, and deleting role records through API endpoints.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Controllers\Concerns\HasIncludableRelationships;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\RoleResource;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
#[Group( 'Roles', weight: 11 )]
class RoleController extends Controller
{
    use AuthorizesRequests;
    use HasIncludableRelationships;

    /**
     * The relationships that can be included via the include query parameter.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $includableRelationships = ['permissions'];

    /**
     * The default relationships to load when no include parameter is provided.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $defaultIncludes = ['permissions'];

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
    public function index( Request $request ): AnonymousResourceCollection
    {
        $this->authorize( 'viewAny', Role::class );

        $roles = Role::with( $this->getRequestedIncludes( $request ) )->paginate( 15 );

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
     * @param  Request  $request  The HTTP request containing role data.
     *
     * @return RoleResource The created role resource with loaded permissions.
     */
    public function store( Request $request ): JsonResponse
    {
        $this->authorize( 'create', Role::class );

        $validated = $request->validate( [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles',
        ] );

        $role = Role::create( $validated );
        $role->load( $this->getRequestedIncludes( $request ) );

        return (new RoleResource( $role ))->response()->setStatusCode( 201 );
    }

    /**
     * Display the specified role.
     *
     * Retrieves a single role by ID with their associated permissions
     * and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param  int|string  $id  The ID of the role to retrieve.
     *
     * @return RoleResource The role resource with loaded permissions.
     */
    public function show( Request $request, string|int $id ): RoleResource
    {
        $role = Role::findOrFail( $id );
        $this->authorize( 'view', $role );

        $role->load( $this->getRequestedIncludes( $request ) );

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
     * @param  Request  $request  The HTTP request containing updated role data.
     * @param  int|string  $id  The ID of the role to update.
     *
     * @return RoleResource The updated role resource with loaded permissions.
     */
    public function update( Request $request, string|int $id ): RoleResource
    {
        $role = Role::findOrFail( $id );
        $this->authorize( 'update', $role );

        $validated = $request->validate( [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $role->id,
        ] );

        $role->update( $validated );
        $role->load( $this->getRequestedIncludes( $request ) );

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
     * @param  int|string  $id  The ID of the role to delete.
     *
     * @return JsonResponse A JSON response with 204 status code.
     */
    public function destroy( string|int $id ): JsonResponse
    {
        $role = Role::findOrFail( $id );
        $this->authorize( 'delete', $role );

        $role->delete();

        return response()->json( [], 204 );
    }
}
