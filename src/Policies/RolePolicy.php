<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{

	}

	public function view( User $user, Role $role ): bool
	{
	}

	public function create( User $user ): bool
	{
	}

	public function update( User $user, Role $role ): bool
	{
	}

	public function delete( User $user, Role $role ): bool
	{
	}

	public function restore( User $user, Role $role ): bool
	{
	}

	public function forceDelete( User $user, Role $role ): bool
	{
	}
}
