<?php

/**
 * PageTag Policy for the CMS Framework Pages Module.
 *
 * This policy handles authorization for page tag-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Policies;

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageTag;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing page tag permissions.
 *
 * Provides authorization methods for page tag-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 2.0.0
 */
class PageTagPolicy
{
    /**
     * Determine whether the user can view any page tags.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view page tags, false otherwise.
     */
    public function viewAny(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any page tags.
         *
         * @since 2.0.0
         *
         * @hook  pageTags.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageTags.viewAny', 'pageTags.manage'));
    }

    /**
     * Determine whether the user can view the page tag.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PageTag  $tag  The tag instance to check permissions for.
     * @return bool True if the user can view the tag, false otherwise.
     */
    public function view(Authenticatable $user, PageTag $tag): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a page tag.
         *
         * @since 2.0.0
         *
         * @hook  pageTags.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PageTag  $tag  The tag being checked.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageTags.view', 'pageTags.manage', $tag));
    }

    /**
     * Determine whether the user can create page tags.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can create page tags, false otherwise.
     */
    public function create(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can create page tags.
         *
         * @since 2.0.0
         *
         * @hook  pageTags.create
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageTags.create', 'pageTags.manage'));
    }

    /**
     * Determine whether the user can update the page tag.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PageTag  $tag  The tag instance to check permissions for.
     * @return bool True if the user can update the tag, false otherwise.
     */
    public function update(Authenticatable $user, PageTag $tag): bool
    {
        /**
         * Filters the capability used to determine whether a user can update page tags.
         *
         * @since 2.0.0
         *
         * @hook  pageTags.update
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PageTag  $tag  The tag being updated.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageTags.update', 'pageTags.manage', $tag));
    }

    /**
     * Determine whether the user can delete the page tag.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PageTag  $tag  The tag instance to check permissions for.
     * @return bool True if the user can delete the tag, false otherwise.
     */
    public function delete(Authenticatable $user, PageTag $tag): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete page tags.
         *
         * @since 2.0.0
         *
         * @hook  pageTags.delete
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PageTag  $tag  The tag being deleted.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageTags.delete', 'pageTags.manage', $tag));
    }
}
