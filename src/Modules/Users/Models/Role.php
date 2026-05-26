<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models;

use ArtisanPackUI\Rbac\Models\Role as RbacRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * CMS Framework Role model.
 *
 * Subclasses {@see RbacRole} so the framework inherits the rbac base
 * (slug column, slug-aware helpers, parent/child hierarchy, observers,
 * Gate integration). CMS-specific behavior — the configurable
 * user-model relationship and the legacy `syncPermissions()` /
 * `givePermissionTo()` helpers used by the package's own managers and
 * tests — lives here on top of that.
 */
class Role extends RbacRole
{
    use HasFactory;

    /**
     * Override the user relationship to use the cms-framework
     * configurable `user_model` setting instead of the default
     * `auth.providers.users.model`.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config( 'artisanpack.cms-framework.user_model' ),
            'role_user',
            'role_id',
            'user_id',
        );
    }

    /**
     * Replace this role's permissions with the supplied set.
     *
     * Accepts Permission models, slugs, or names — resolved via the
     * configured rbac permission model.
     *
     * @param  array<int, Permission|string>|Collection  $permissions
     */
    public function syncPermissions( Collection|array $permissions ): self
    {
        $this->permissions()->sync( $this->resolvePermissionKeys( $permissions ) );

        return $this;
    }

    /**
     * Add the supplied permissions without detaching existing ones.
     *
     * @param  array<int, Permission|string>|Collection  $permissions
     */
    public function givePermissionTo( Collection|array $permissions ): self
    {
        $this->permissions()->syncWithoutDetaching( $this->resolvePermissionKeys( $permissions ) );

        return $this;
    }

    /**
     * Resolve a mixed collection of Permission models / slugs / names
     * into the primary keys the BelongsToMany helpers expect.
     *
     * @param  array<int, Permission|string>|Collection  $permissions
     *
     * @return array<int, int|string>
     */
    protected function resolvePermissionKeys( Collection|array $permissions ): array
    {
        $permissionModel = config( 'artisanpack.rbac.models.permission', Permission::class );

        return collect( $permissions )
            ->map( function ( $permission ) use ( $permissionModel ) {
                if ( $permission instanceof Permission ) {
                    return $permission->getKey();
                }

                // Name first, slug fallback — same lookup order as
                // rbac's HasRoles / HasPermissions helpers.
                $resolved = $permissionModel::query()->where( 'name', $permission )->first()
                    ?? $permissionModel::query()->where( 'slug', $permission )->first();

                return $resolved?->getKey();
            } )
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return \ArtisanPackUI\Database\Factories\RoleFactory
     */
    protected static function newFactory()
    {
        return \ArtisanPackUI\Database\Factories\RoleFactory::new();
    }
}
