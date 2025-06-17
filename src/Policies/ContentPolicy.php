<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentPolicy
{
    use HandlesAuthorization;

    public function viewAny( User $user ): bool
    {
        return true;
    }

    public function view( User $user, Content $content ): bool
    {
        return true;
    }

    public function create( User $user ): bool
    {
        return $user->can( 'create_content' );
    }

    public function update( User $user, Content $content ): bool
    {
        return $user->id === $content->author_id || $user->can( 'edit_content' );
    }

    public function delete( User $user, Content $content ): bool
    {
        return $user->id === $content->author_id || $user->can( 'delete_content' );
    }

    public function restore( User $user, Content $content ): bool
    {
        return $user->can( 'edit_content' );
    }

    public function forceDelete( User $user, Content $content ): bool
    {
        return $user->can( 'delete_content' );
    }
}
