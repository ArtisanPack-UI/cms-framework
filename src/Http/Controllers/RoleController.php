<?php
/**
 * Class RoleController
 *
 * Controller for managing roles in the application.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\RoleRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\RoleResource;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class RoleController
 *
 * Handles HTTP requests related to roles management, including listing,
 * creating, viewing, updating, and deleting roles.
 *
 * @since 1.0.0
 */
class RoleController
{
	use AuthorizesRequests;

	/**
	 * Display a listing of all roles.
	 *
	 * @since 1.0.0
	 *
	 * @return JsonResponse A JSON response containing all roles.
	 */
	public function index(): JsonResponse
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

	/**
	 * Store a newly created role in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param RoleRequest $request The validated request containing role data.
	 * @return RoleResource The newly created role resource.
	 */
	public function store( RoleRequest $request ): RoleResource
	{
		$this->authorize( 'create', Role::class );

		return new RoleResource( Role::create( $request->validated() ) );
	}

	/**
	 * Display the specified role.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The ID of the role to display.
	 * @return RoleResource The specified role resource.
	 */
	public function show( int $id ): RoleResource
	{
		// Find the role manually instead of relying on model binding
		$role = Role::findOrFail($id);

		$this->authorize( 'view', $role );

		return new RoleResource( $role );
	}

	/**
	 * Update the specified role in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param RoleRequest $request The validated request containing updated role data.
	 * @param int         $id      The ID of the role to update.
	 * @return RoleResource The updated role resource.
	 */
	public function update( RoleRequest $request, int $id ): RoleResource
	{
		// Find the role manually instead of relying on model binding
		$role = Role::findOrFail($id);

		$this->authorize( 'update', $role );

		$role->update( $request->validated() );

		// Refresh the role to get the updated data
		$role->refresh();

		return new RoleResource( $role );
	}

	/**
	 * Remove the specified role from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The ID of the role to delete.
	 * @return JsonResponse A JSON response indicating success.
	 */
	public function destroy( int $id ): JsonResponse
	{
		// Find the role manually instead of relying on model binding
		$role = Role::findOrFail($id);

		$this->authorize( 'delete', $role );

		$role->delete();

		return response()->json();
	}
}
