<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Plugin;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PluginPolicy
{
    use HandlesAuthorization;

    public function viewAny( User $user ): bool
    {
        return true;
    }

    public function view( User $user, Plugin $plugin ): bool
    {
        return true;
    }

    public function create( User $user ): bool
    {
        return $user->can( 'manage_plugins' );
    }

    public function update( User $user, Plugin $plugin ): bool
    {
        return $user->can( 'manage_plugins' );
    }

    public function delete( User $user, Plugin $plugin ): bool
    {
        return $user->can( 'manage_plugins' );
    }

    public function restore( User $user, Plugin $plugin ): bool
    {
        return $user->can( 'manage_plugins' );
    }

    public function forceDelete( User $user, Plugin $plugin ): bool
    {
        return $user->can( 'manage_plugins' );
    }
}
