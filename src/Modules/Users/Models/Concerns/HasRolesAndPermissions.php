<?php

/**
 * Roles and Permissions Trait for User Models.
 *
 * This trait provides role and permission functionality that can be added
 * to user models to enable role-based access control within the CMS framework.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Provides role and permission functionality for user models.
 *
 * This trait adds methods for managing user roles and checking permissions,
 * enabling role-based access control throughout the application.
 *
 * @since 1.0.0
 */
trait HasRolesAndPermissions
{
    /**
     * Check if the user has a specific role.
     *
     * Determines whether the user is assigned to a role with the given slug.
     *
     * @since 1.0.0
     *
     * @param  string  $roleSlug  The slug of the role to check for.
     * @return bool True if the user has the role, false otherwise.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Get the roles that belong to the user.
     *
     * Defines a many-to-many relationship between users and roles using
     * the role_user pivot table.
     *
     * @since 1.0.0
     *
     * @return BelongsToMany The relationship instance.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    /**
     * Determine if the user has a given ability.
     *
     * If $arguments is empty and $ability is a permission slug string, this method
     * delegates to hasPermissionTo() for role/permission checks; otherwise it defers
     * to the parent implementation.
     *
     * @since 1.0.0
     *
     * @param  string  $ability  Ability name or permission slug.
     * @param  array  $arguments  Optional arguments forwarded to the parent gate check.
     * @return bool True if the ability is granted, false otherwise.
     */
    public function can($ability, $arguments = []): bool
    {
        if (empty($arguments) && is_string($ability) && $this->hasPermissionTo($ability)) {
            return true;
        }

        return parent::can($ability, $arguments);
    }

    /**
     * Check if the user has a specific permission.
     *
     * Determines whether the user has access to a permission through any of their assigned roles.
     *
     * @since 1.0.0
     *
     * @param  string  $permissionSlug  The slug of the permission to check for.
     * @return bool True if the user has the permission, false otherwise.
     */
    public function hasPermissionTo(string $permissionSlug): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionSlug) {
                $query->where('slug', $permissionSlug);
            })
            ->exists();
    }
}
