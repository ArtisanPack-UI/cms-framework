<?php

declare( strict_types = 1 );

/**
 * Page Policy for the CMS Framework Pages Module.
 *
 * This policy handles authorization for page-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Policies;

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing page permissions.
 *
 * Provides authorization methods for page-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 1.0.0
 */
class PagePolicy
{
    /**
     * Determine whether the user can view any pages.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can view pages, false otherwise.
     */
    public function viewAny( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any pages.
         *
         * @since 1.0.0
         *
         * @hook  pages.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'pages.viewAny', 'pages.view' ) );
    }

    /**
     * Determine whether the user can view the page.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Page  $page  The page instance to check permissions for.
     *
     * @return bool True if the user can view the page, false otherwise.
     */
    public function view( Authenticatable $user, Page $page ): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a page.
         *
         * @since 1.0.0
         *
         * @hook  pages.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  Page  $page  The page being checked.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'pages.view', 'pages.view', $page ) );
    }

    /**
     * Determine whether the user can create pages.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can create pages, false otherwise.
     */
    public function create( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can create pages.
         *
         * @since 1.0.0
         *
         * @hook  pages.create
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'pages.create', 'pages.create' ) );
    }

    /**
     * Determine whether the user can update the page.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Page  $page  The page instance to check permissions for.
     *
     * @return bool True if the user can update the page, false otherwise.
     */
    public function update( Authenticatable $user, Page $page ): bool
    {
        // Check if user can edit any page
        $canEditAny = $user->can( applyFilters( 'pages.update', 'pages.edit', $page ) );

        if ( $canEditAny ) {
            return true;
        }

        // Check if user can edit their own pages
        $canEditOwn = $user->can( applyFilters( 'pages.updateOwn', 'pages.editOwn', $page ) );

        if ( $canEditOwn && $page->author_id === $user->id ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the page.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Page  $page  The page instance to check permissions for.
     *
     * @return bool True if the user can delete the page, false otherwise.
     */
    public function delete( Authenticatable $user, Page $page ): bool
    {
        // Check if user can delete any page
        $canDeleteAny = $user->can( applyFilters( 'pages.delete', 'pages.delete', $page ) );

        if ( $canDeleteAny ) {
            return true;
        }

        // Check if user can delete their own pages
        $canDeleteOwn = $user->can( applyFilters( 'pages.deleteOwn', 'pages.deleteOwn', $page ) );

        if ( $canDeleteOwn && $page->author_id === $user->id ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can publish pages.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Page  $page  The page instance to check permissions for.
     *
     * @return bool True if the user can publish the page, false otherwise.
     */
    public function publish( Authenticatable $user, Page $page ): bool
    {
        /**
         * Filters the capability used to determine whether a user can publish pages.
         *
         * @since 1.0.0
         *
         * @hook  pages.publish
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  Page  $page  The page being published.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'pages.publish', 'pages.publish', $page ));
    }
}
