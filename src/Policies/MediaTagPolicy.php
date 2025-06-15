<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaTagPolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{
		return true;
	}

	public function view( User $user, MediaTag $mediaTag ): bool
	{
		return true;
	}

	public function create( User $user ): bool
	{
		return true;
	}

	public function update( User $user, MediaTag $mediaTag ): bool
	{
		return $user->can( 'manage_categories' );
	}

	public function delete( User $user, MediaTag $mediaTag ): bool
	{
		return $user->can( 'manage_categories' );
	}

	public function restore( User $user, MediaTag $mediaTag ): bool
	{
		return $user->can( 'manage_categories' );
	}

	public function forceDelete( User $user, MediaTag $mediaTag ): bool
	{
		return $user->can( 'manage_categories' );
	}
}
