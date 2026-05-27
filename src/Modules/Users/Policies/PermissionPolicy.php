<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Users\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Authorization policy for permission-resource operations.
 *
 * All methods take Laravel's `Authorizable` contract — not `Authenticatable`,
 * which doesn't expose `can()` — so the static analyzer accepts the
 * `$user->can(...)` calls below. The Gate / `authorize()` helpers resolve
 * correctly because every User model in Laravel composes the `Authorizable`
 * trait alongside `Authenticatable`. Each default capability slug is
 * filterable through `applyFilters()` so consumers can rebrand or chain
 * checks without subclassing.
 */
class PermissionPolicy
{
    public function viewAny( Authorizable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.viewAny', 'permissions.viewAny' ) );
    }

    public function view( Authorizable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.view', 'permissions.view' ) );
    }

    public function create( Authorizable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.create', 'permissions.create' ) );
    }

    public function update( Authorizable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.update', 'permissions.update' ) );
    }

    public function delete( Authorizable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.delete', 'permissions.delete' ) );
    }

    public function restore( Authorizable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.restore', 'permissions.restore' ) );
    }

    public function forceDelete( Authorizable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.forceDelete', 'permissions.forceDelete' ) );
    }
}
