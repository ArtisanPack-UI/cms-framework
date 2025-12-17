<?php

/**
 * PageCategory Policy for the CMS Framework Pages Module.
 *
 * This policy handles authorization for page category-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Policies;

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageCategory;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing page category permissions.
 *
 * Provides authorization methods for page category-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 2.0.0
 */
class PageCategoryPolicy
{
    /**
     * Determine whether the user can view any page categories.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view page categories, false otherwise.
     */
    public function viewAny(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any page categories.
         *
         * @since 2.0.0
         *
         * @hook  pageCategories.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageCategories.viewAny', 'pageCategories.manage'));
    }

    /**
     * Determine whether the user can view the page category.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PageCategory  $category  The category instance to check permissions for.
     * @return bool True if the user can view the category, false otherwise.
     */
    public function view(Authenticatable $user, PageCategory $category): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a page category.
         *
         * @since 2.0.0
         *
         * @hook  pageCategories.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PageCategory  $category  The category being checked.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageCategories.view', 'pageCategories.manage', $category));
    }

    /**
     * Determine whether the user can create page categories.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can create page categories, false otherwise.
     */
    public function create(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can create page categories.
         *
         * @since 2.0.0
         *
         * @hook  pageCategories.create
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageCategories.create', 'pageCategories.manage'));
    }

    /**
     * Determine whether the user can update the page category.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PageCategory  $category  The category instance to check permissions for.
     * @return bool True if the user can update the category, false otherwise.
     */
    public function update(Authenticatable $user, PageCategory $category): bool
    {
        /**
         * Filters the capability used to determine whether a user can update page categories.
         *
         * @since 2.0.0
         *
         * @hook  pageCategories.update
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PageCategory  $category  The category being updated.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageCategories.update', 'pageCategories.manage', $category));
    }

    /**
     * Determine whether the user can delete the page category.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PageCategory  $category  The category instance to check permissions for.
     * @return bool True if the user can delete the category, false otherwise.
     */
    public function delete(Authenticatable $user, PageCategory $category): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete page categories.
         *
         * @since 2.0.0
         *
         * @hook  pageCategories.delete
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PageCategory  $category  The category being deleted.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('pageCategories.delete', 'pageCategories.manage', $category));
    }
}
