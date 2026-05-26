<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Users\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization policy for permission-resource operations.
 *
 * All methods take the standard Laravel `Authenticatable $user` so the
 * Gate / `authorize()` helpers in controllers resolve correctly. Each
 * default capability slug is filterable through the `applyFilters()`
 * hook so consumers can rebrand or chain checks without subclassing.
 */
class PermissionPolicy
{
    public function viewAny( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.viewAny', 'permissions.viewAny' ) );
    }

    public function view( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.view', 'permissions.view' ) );
    }

    public function create( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.create', 'permissions.create' ) );
    }

    public function update( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.update', 'permissions.update' ) );
    }

    public function delete( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.delete', 'permissions.delete' ) );
    }

    public function restore( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.restore', 'permissions.restore' ) );
    }

    public function forceDelete( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'permissions.forceDelete', 'permissions.forceDelete'));
    }
}
