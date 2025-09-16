<?php

/**
 * Permission Manager for the CMS Framework Users Module.
 *
 * This class provides functionality for managing user permissions including
 * registration of new permissions within the system.
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Managers
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Managers;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Manages user permissions within the CMS Framework.
 *
 * Provides methods for registering new permissions and integrates with
 * the event system for permission-related hooks.
 *
 * @since 1.0.0
 */
class PermissionManager
{
	/**
	 * Register a new permission in the system.
	 *
	 * Creates a new permission with the given slug and name, or returns an existing
	 * permission if one with the same slug already exists. Triggers the 'ap_permission_registered'
	 * action hook after successful registration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The unique slug identifier for the permission.
	 * @param string $name The human-readable name for the permission.
	 *
	 * @return Permission The created or existing permission instance.
	 */
	public function register(string $slug, string $name): Permission
	{
		$permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
		Eventy::action('ap_permission_registered', $permission);
		return $permission;
	}
}