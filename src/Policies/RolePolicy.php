<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Guests cannot view role listings for security reasons
            return Eventy::filter('ap.cms.role.can_view_any', false, null);
        }

        // Allow users with role management permissions to view all roles
        if ($user->can('edit_roles')) {
            return true;
        }

        // Allow users to view basic role information if they can read roles
        $canViewRoles = $user->can('read_roles');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.role.can_view_any', $canViewRoles, $user);
    }

    public function view(?User $user, Role $role): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Guests cannot view role details for security reasons
            return Eventy::filter('ap.cms.role.can_view', false, null, $role);
        }

        // Allow users with role management permissions to view any role
        if ($user->can('edit_roles')) {
            return true;
        }

        // Allow viewing of basic role information if user can read roles
        $canViewRole = $user->can('read_roles');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.role.can_view', $canViewRole, $user, $role);
    }

    public function create(User $user): bool
    {
        return $user->can('edit_roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('edit_roles');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('edit_roles');
    }

    public function restore(User $user, Role $role): bool
    {
        return $user->can('edit_roles');
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $user->can('edit_roles');
    }
}
