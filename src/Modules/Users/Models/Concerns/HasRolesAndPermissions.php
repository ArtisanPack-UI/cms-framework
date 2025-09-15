<?php

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRolesAndPermissions
{
	public function roles(): BelongsToMany
	{
		return $this->belongsToMany(Role::class);
	}

	public function hasRole(string $roleSlug): bool
	{
		return $this->roles()->where('slug', $roleSlug)->exists();
	}

	public function hasPermissionTo(string $permissionSlug): bool
	{
		return $this->roles()
			->whereHas('permissions', function ($query) use ($permissionSlug) {
				$query->where('slug', $permissionSlug);
			})
			->exists();
	}
}