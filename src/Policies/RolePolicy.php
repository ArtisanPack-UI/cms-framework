<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{
		return $user->can('viewAny_roles');
	}

	public function view( User $user, Role $role ): bool
	{
		return $user->can('view_roles');
	}

	public function create( User $user ): bool
	{
		return $user->can('create_roles');
	}

	public function update( User $user, Role $role ): bool
	{
		return $user->can('update_roles');
	}

	public function delete( User $user, Role $role ): bool
	{
		return $user->can('delete_roles');
	}

	public function restore( User $user, Role $role ): bool
	{
		return $user->can('restore_roles');
	}

	public function forceDelete( User $user, Role $role ): bool
	{
		return $user->can('forceDelete_roles');
	}
}
