<?php

use ArtisanPackUI\CMSFramework\Modules\Users\Managers\RoleManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;

if (! function_exists('ap_register_role')) {
	function ap_register_role(string $slug, string $name) {
		return app(RoleManager::class)->register($slug, $name);
	}
}

if (! function_exists('ap_register_permission')) {
	function ap_register_permission(string $slug, string $name) {
		return app(PermissionManager::class)->register($slug, $name);
	}
}

if (! function_exists('ap_add_permission_to_role')) {
	function ap_add_permission_to_role(string $roleSlug, string $permissionSlug) {
		return app(RoleManager::class)->addPermissionToRole($roleSlug, $permissionSlug);
	}
}