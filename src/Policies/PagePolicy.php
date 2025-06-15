<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Page;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PagePolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{
		return true;
	}

	public function view( User $user, Page $page ): bool
	{
		return true;
	}

	public function create( User $user ): bool
	{
		return $user->can( 'edit_pages' );
	}

	public function update( User $user, Page $page ): bool
	{
		return $user->id === $page->user->id || $user->can( 'edit_others_pages' );
	}

	public function delete( User $user, Page $page ): bool
	{
		return $user->id === $page->user->id || $user->can( 'delete_others_pages' );
	}

	public function restore( User $user, Page $page ): bool
	{
		return $user->id === $page->user->id || $user->can( 'edit_others_pages' );
	}

	public function forceDelete( User $user, Page $page ): bool
	{
		return $user->id === $page->user->id || $user->can( 'delete_others_pages' );
	}
}
