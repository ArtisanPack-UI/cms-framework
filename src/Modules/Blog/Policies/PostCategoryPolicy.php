<?php

/**
 * PostCategory Policy for the CMS Framework Blog Module.
 *
 * This policy handles authorization for post category-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Blog\Policies
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Policies;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing post category permissions.
 *
 * Provides authorization methods for post category-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 2.0.0
 */
class PostCategoryPolicy
{
    /**
     * Determine whether the user can view any post categories.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view post categories, false otherwise.
     */
    public function viewAny(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any post categories.
         *
         * @since 2.0.0
         *
         * @hook  postCategories.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postCategories.viewAny', 'postCategories.manage'));
    }

    /**
     * Determine whether the user can view the post category.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PostCategory  $category  The category instance to check permissions for.
     * @return bool True if the user can view the category, false otherwise.
     */
    public function view(Authenticatable $user, PostCategory $category): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a post category.
         *
         * @since 2.0.0
         *
         * @hook  postCategories.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PostCategory  $category  The category being checked.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postCategories.view', 'postCategories.manage', $category));
    }

    /**
     * Determine whether the user can create post categories.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can create post categories, false otherwise.
     */
    public function create(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can create post categories.
         *
         * @since 2.0.0
         *
         * @hook  postCategories.create
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postCategories.create', 'postCategories.manage'));
    }

    /**
     * Determine whether the user can update the post category.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PostCategory  $category  The category instance to check permissions for.
     * @return bool True if the user can update the category, false otherwise.
     */
    public function update(Authenticatable $user, PostCategory $category): bool
    {
        /**
         * Filters the capability used to determine whether a user can update post categories.
         *
         * @since 2.0.0
         *
         * @hook  postCategories.update
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PostCategory  $category  The category being updated.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postCategories.update', 'postCategories.manage', $category));
    }

    /**
     * Determine whether the user can delete the post category.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PostCategory  $category  The category instance to check permissions for.
     * @return bool True if the user can delete the category, false otherwise.
     */
    public function delete(Authenticatable $user, PostCategory $category): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete post categories.
         *
         * @since 2.0.0
         *
         * @hook  postCategories.delete
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PostCategory  $category  The category being deleted.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postCategories.delete', 'postCategories.manage', $category));
    }
}
