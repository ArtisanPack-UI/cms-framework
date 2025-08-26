<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Allow guest access to public user listings
            return Eventy::filter('ap.cms.user.can_view_any', true, null);
        }

        // Allow users with manage_users permission to view all users
        if ($user->can('manage_users')) {
            return true;
        }

        // Allow users to view basic user listings for public profiles
        $canViewUsers = $user->can('read_users');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.user.can_view_any', $canViewUsers, $user);
    }

    public function view(?User $user, User $model): bool
    {
        // Handle guest/unauthenticated users - allow viewing public profiles
        if (! $user) {
            // Guests can view public user profiles by default
            return Eventy::filter('ap.cms.user.can_view', true, null, $model);
        }

        // Users can always view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Allow users with manage_users permission to view any user
        if ($user->can('manage_users')) {
            return true;
        }

        // Allow viewing of public user profiles
        $canViewUser = $user->can('read_users');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.user.can_view', $canViewUser, $user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function update(User $user, User $model): bool
    {
        return $user === $model || $user->can('manage_users');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('manage_users');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('manage_users');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->can('manage_users');
    }
}
