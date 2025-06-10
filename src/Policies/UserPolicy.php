<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Models\User as UserModel;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{

	}

	public function view( User $user, UserModel $model ): bool
	{
	}

	public function create( User $user ): bool
	{
	}

	public function update( User $user, UserModel $model ): bool
	{
	}

	public function delete( User $user, UserModel $model ): bool
	{
	}

	public function restore( User $user, UserModel $model ): bool
	{
	}

	public function forceDelete( User $user, UserModel $model ): bool
	{
	}
}
