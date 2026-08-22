<?php

declare( strict_types=1 );

/**
 * User Policy for the CMS Framework Users Module.
 *
 * This policy handles authorization for user-related operations using
 * the Eventy filter system for extensible permission checking.
 *
 * @since   2.9.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing user permissions.
 *
 * Provides authorization methods for user-related operations using
 * the configurable user model and Eventy filter system for extensibility.
 * The default capability slugs mirror those already enforced by the bulk
 * user actions ( `users.manage` / `users.delete` ) so a single grant covers
 * both the resource endpoints and the bulk endpoint.
 *
 * @since 2.9.0
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     *
     * @since 2.9.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can view users, false otherwise.
     */
    public function viewAny( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any users.
         *
         * @since 2.9.0
         *
         * @hook user.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.user.viewAny', 'users.manage' ) );
    }

    /**
     * Determine whether the user can view the user.
     *
     * @since 2.9.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can view the user, false otherwise.
     */
    public function view( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can view users.
         *
         * @since 2.9.0
         *
         * @hook user.view
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.user.view', 'users.manage' ) );
    }

    /**
     * Determine whether the user can create users.
     *
     * @since 2.9.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can create users, false otherwise.
     */
    public function create( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can create users.
         *
         * @since 2.9.0
         *
         * @hook user.create
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.user.create', 'users.manage' ) );
    }

    /**
     * Determine whether the user can update the user.
     *
     * @since 2.9.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can update the user, false otherwise.
     */
    public function update( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can update users.
         *
         * @since 2.9.0
         *
         * @hook user.update
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.user.update', 'users.manage' ) );
    }

    /**
     * Determine whether the user can delete the user.
     *
     * @since 2.9.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can delete the user, false otherwise.
     */
    public function delete( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete users.
         *
         * @since 2.9.0
         *
         * @hook user.delete
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.user.delete', 'users.delete' ) );
    }
}
