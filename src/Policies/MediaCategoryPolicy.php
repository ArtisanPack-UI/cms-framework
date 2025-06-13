<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaCategoryPolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{
		return true;
	}

	public function view( User $user, MediaCategory $mediaCategory ): bool
	{
		return true;
	}

	public function create( User $user ): bool
	{
		return $user->can( 'manage_categories' );
	}

	public function update( User $user, MediaCategory $mediaCategory ): bool
	{
		return $user->can( 'manage_categories' );
	}

	public function delete( User $user, MediaCategory $mediaCategory ): bool
	{
		return $user->can( 'manage_categories' );
	}

	public function restore( User $user, MediaCategory $mediaCategory ): bool
	{
		return $user->can( 'manage_categories' );
	}

	public function forceDelete( User $user, MediaCategory $mediaCategory ): bool
	{
		return $user->can( 'manage_categories' );
	}
}
