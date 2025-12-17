<?php

/**
 * Role Policy for the CMS Framework Users Module.
 *
 * This policy handles authorization for role-related operations using
 * the Eventy filter system for extensible permission checking.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Policy for managing role permissions.
 *
 * Provides authorization methods for role-related operations using
 * the configurable user model and Eventy filter system for extensibility.
 *
 * @since 1.0.0
 */
class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view roles, false otherwise.
     */
    public function viewAny(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any roles.
         *
         * @since 1.0.0
         *
         * @hook role.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('role.viewAny', 'roles.manage'));
    }

    /**
     * Determine whether the user can view the role.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view the role, false otherwise.
     */
    public function view(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view roles.
         *
         * @since 1.0.0
         *
         * @hook role.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('role.view', 'roles.manage'));
    }

    /**
     * Determine whether the user can create roles.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can create roles, false otherwise.
     */
    public function create(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can create roles.
         *
         * @since 1.0.0
         *
         * @hook role.create
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('role.create', 'roles.manage'));
    }

    /**
     * Determine whether the user can update the role.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can update the role, false otherwise.
     */
    public function update(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can update roles.
         *
         * @since 1.0.0
         *
         * @hook role.update
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('role.update', 'roles.manage'));
    }

    /**
     * Determine whether the user can delete the role.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can delete the role, false otherwise.
     */
    public function delete(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete roles.
         *
         * @since 1.0.0
         *
         * @hook role.delete
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('role.delete', 'roles.delete'));
    }

    /**
     * Determine whether the user can restore the role.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can restore the role, false otherwise.
     */
    public function restore(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can restore roles.
         *
         * @since 1.0.0
         *
         * @hook role.restore
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('role.restore', 'roles.manage'));
    }

    /**
     * Determine whether the user can permanently delete the role.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can force delete the role, false otherwise.
     */
    public function forceDelete(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can permanently delete roles.
         *
         * @since 1.0.0
         *
         * @hook role.forceDelete
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('role.forceDelete', 'roles.delete'));
    }
}
