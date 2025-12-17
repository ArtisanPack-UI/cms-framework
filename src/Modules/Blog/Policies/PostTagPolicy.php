<?php

/**
 * PostTag Policy for the CMS Framework Blog Module.
 *
 * This policy handles authorization for post tag-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Policies;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing post tag permissions.
 *
 * Provides authorization methods for post tag-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 2.0.0
 */
class PostTagPolicy
{
    /**
     * Determine whether the user can view any post tags.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view post tags, false otherwise.
     */
    public function viewAny(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any post tags.
         *
         * @since 2.0.0
         *
         * @hook  postTags.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postTags.viewAny', 'postTags.manage'));
    }

    /**
     * Determine whether the user can view the post tag.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PostTag  $tag  The tag instance to check permissions for.
     * @return bool True if the user can view the tag, false otherwise.
     */
    public function view(Authenticatable $user, PostTag $tag): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a post tag.
         *
         * @since 2.0.0
         *
         * @hook  postTags.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PostTag  $tag  The tag being checked.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postTags.view', 'postTags.manage', $tag));
    }

    /**
     * Determine whether the user can create post tags.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can create post tags, false otherwise.
     */
    public function create(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can create post tags.
         *
         * @since 2.0.0
         *
         * @hook  postTags.create
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postTags.create', 'postTags.manage'));
    }

    /**
     * Determine whether the user can update the post tag.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PostTag  $tag  The tag instance to check permissions for.
     * @return bool True if the user can update the tag, false otherwise.
     */
    public function update(Authenticatable $user, PostTag $tag): bool
    {
        /**
         * Filters the capability used to determine whether a user can update post tags.
         *
         * @since 2.0.0
         *
         * @hook  postTags.update
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PostTag  $tag  The tag being updated.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postTags.update', 'postTags.manage', $tag));
    }

    /**
     * Determine whether the user can delete the post tag.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  PostTag  $tag  The tag instance to check permissions for.
     * @return bool True if the user can delete the tag, false otherwise.
     */
    public function delete(Authenticatable $user, PostTag $tag): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete post tags.
         *
         * @since 2.0.0
         *
         * @hook  postTags.delete
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  PostTag  $tag  The tag being deleted.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('postTags.delete', 'postTags.manage', $tag));
    }
}
