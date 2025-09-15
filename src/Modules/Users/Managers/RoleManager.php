<?php

namespace ArtisanPackUI\CMSFramework\Modules\Users\Managers;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use TorMorten\Eventy\Facades\Eventy;

class RoleManager
{
	public function register(string $slug, string $name)
	{
		$role = Role::firstOrCreate(['slug' => $slug], ['name' => $name]);
		Eventy::action('ap_role_registered', $role);
		return $role;
	}

	public function addPermissionToRole(string $roleSlug, string $permissionSlug)
	{
		$role = Role::where('slug', $roleSlug)->firstOrFail();
		$permission = Permission::where('slug', $permissionSlug)->firstOrFail();
		$role->permissions()->syncWithoutDetaching($permission->id);
	}
}