<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{
		return $user->can('viewAny_users');
	}

	public function view( User $user, User $model ): bool
	{
		return $user->can('view_users');
	}

	public function create( User $user ): bool
	{
		return $user->can('create_users');
	}

	public function update( User $user, User $model ): bool
	{
		return $user->can('update_users');
	}

	public function delete( User $user, User $model ): bool
	{
		return $user->can('delete_users');
	}

	public function restore( User $user, User $model ): bool
	{
		return $user->can('restore_users');
	}

	public function forceDelete( User $user, User $model ): bool
	{
		return $user->can('forceDelete_users');
	}
}
