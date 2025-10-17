<?php

/**
 * Role Manager for the CMS Framework Users Module.
 *
 * This class provides functionality for managing user roles including registration
 * of new roles and assignment of permissions to roles.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Managers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Managers;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Manages user roles within the CMS Framework.
 *
 * Provides methods for registering new roles and managing the relationship
 * between roles and permissions, including event hooks integration.
 *
 * @since 1.0.0
 */
class RoleManager
{
	/**
	 * Register a new role in the system.
	 *
	 * Creates a new role with the given slug and name, or returns an existing
	 * role if one with the same slug already exists. Triggers the 'ap_role_registered'
	 * action hook after successful registration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The unique slug identifier for the role.
	 * @param string $name The human-readable name for the role.
	 *
	 * @return Role The created or existing role instance.
	 */
	public function register( string $slug, string $name ): Role
	{
		$role = Role::firstOrCreate( [ 'slug' => $slug ], [ 'name' => $name ] );
		doAction( 'ap.roleRegistered', $role );
		return $role;
	}

	/**
	 * Add a permission to an existing role.
	 *
	 * Attaches a permission to a role without removing any existing permissions.
	 * Both the role and permission must exist in the system.
	 *
	 * @since 1.0.0
	 *
	 * @param string $roleSlug       The slug of the role to add permission to.
	 * @param string $permissionSlug The slug of the permission to add.
	 *
	 * @return void
	 */
	public function addPermissionToRole( string $roleSlug, string $permissionSlug ): void
	{
		$role       = Role::where( 'slug', $roleSlug )->firstOrFail();
		$permission = Permission::where( 'slug', $permissionSlug )->firstOrFail();
		$role->permissions()->syncWithoutDetaching( $permission->id );
	}
}