<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Models\Plugin;
use Illuminate\Auth\Access\HandlesAuthorization;

class PluginPolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{

	}

	public function view( User $user, Plugin $plugin ): bool
	{
	}

	public function create( User $user ): bool
	{
	}

	public function update( User $user, Plugin $plugin ): bool
	{
	}

	public function delete( User $user, Plugin $plugin ): bool
	{
	}

	public function restore( User $user, Plugin $plugin ): bool
	{
	}

	public function forceDelete( User $user, Plugin $plugin ): bool
	{
	}
}
