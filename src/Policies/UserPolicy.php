<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny( User $user ): bool
    {
        return true;
    }

    public function view( User $user, User $model ): bool
    {
        return true;
    }

    public function create( User $user ): bool
    {
        return $user->can( 'manage_users' );
    }

    public function update( User $user, User $model ): bool
    {
        return $user === $model || $user->can( 'manage_users' );
    }

    public function delete( User $user, User $model ): bool
    {
        return $user->can( 'manage_users' );
    }

    public function restore( User $user, User $model ): bool
    {
        return $user->can( 'manage_users' );
    }

    public function forceDelete( User $user, User $model ): bool
    {
        return $user->can( 'manage_users' );
    }
}
