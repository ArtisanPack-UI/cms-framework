<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns;

use ArtisanPackUI\Rbac\Concerns\HasPermissions as RbacHasPermissions;
use ArtisanPackUI\Rbac\Concerns\HasRoles as RbacHasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Provides role and permission functionality for cms-framework user
 * models.
 *
 * Composes the rbac traits — {@see RbacHasRoles} +
 * {@see RbacHasPermissions} — so consumers continue to `use
 * HasRolesAndPermissions` while getting all of the rbac feature set
 * (slug-aware lookups, parent/child role hierarchy, permission
 * caching, Gate integration). The CMS-specific override is the
 * `roles()` relationship: it resolves through the configurable
 * `artisanpack.rbac.models.role` binding (which defaults to the
 * cms-framework Role subclass via the framework's service provider).
 */
trait HasRolesAndPermissions
{
    use RbacHasPermissions;
    use RbacHasRoles {
        RbacHasRoles::roles as protected rbacRoles;
    }

    public function roles(): BelongsToMany
    {
        $roleModel = config(
            'artisanpack.rbac.models.role',
            \ArtisanPackUI\CMSFramework\Modules\Users\Models\Role::class,
        );

        return $this->belongsToMany( $roleModel, 'role_user', 'user_id', 'role_id' );
    }
}
