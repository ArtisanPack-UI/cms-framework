<?php

namespace ArtisanPackUI\CMSFramework\Modules\Users\Managers;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use TorMorten\Eventy\Facades\Eventy;

class PermissionManager
{
	public function register(string $slug, string $name)
	{
		$permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
		Eventy::action('ap_permission_registered', $permission);
		return $permission;
	}
}