<?php

declare( strict_types = 1 );

/**
 * Post Policy for the CMS Framework Blog Module.
 *
 * This policy handles authorization for post-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Policies;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing post permissions.
 *
 * Provides authorization methods for post-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 1.0.0
 */
class PostPolicy
{
    /**
     * Determine whether the user can view any posts.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can view posts, false otherwise.
     */
    public function viewAny( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any posts.
         *
         * @since 1.0.0
         *
         * @hook  posts.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'posts.viewAny', 'posts.view' ) );
    }

    /**
     * Determine whether the user can view the post.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Post  $post  The post instance to check permissions for.
     *
     * @return bool True if the user can view the post, false otherwise.
     */
    public function view( Authenticatable $user, Post $post ): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a post.
         *
         * @since 1.0.0
         *
         * @hook  posts.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  Post  $post  The post being checked.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'posts.view', 'posts.view', $post ) );
    }

    /**
     * Determine whether the user can create posts.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     *
     * @return bool True if the user can create posts, false otherwise.
     */
    public function create( Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can create posts.
         *
         * @since 1.0.0
         *
         * @hook  posts.create
         *
         * @param  string  $capability  Default capability slug to check.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'posts.create', 'posts.create' ) );
    }

    /**
     * Determine whether the user can update the post.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Post  $post  The post instance to check permissions for.
     *
     * @return bool True if the user can update the post, false otherwise.
     */
    public function update( Authenticatable $user, Post $post ): bool
    {
        // Check if user can edit any post
        $canEditAny = $user->can( applyFilters( 'posts.update', 'posts.edit', $post ) );

        if ( $canEditAny ) {
            return true;
        }

        // Check if user can edit their own posts
        $canEditOwn = $user->can( applyFilters( 'posts.updateOwn', 'posts.editOwn', $post ) );

        if ( $canEditOwn && $post->author_id === $user->id ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the post.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Post  $post  The post instance to check permissions for.
     *
     * @return bool True if the user can delete the post, false otherwise.
     */
    public function delete( Authenticatable $user, Post $post ): bool
    {
        // Check if user can delete any post
        $canDeleteAny = $user->can( applyFilters( 'posts.delete', 'posts.delete', $post ) );

        if ( $canDeleteAny ) {
            return true;
        }

        // Check if user can delete their own posts
        $canDeleteOwn = $user->can( applyFilters( 'posts.deleteOwn', 'posts.deleteOwn', $post ) );

        if ( $canDeleteOwn && $post->author_id === $user->id ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can publish posts.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Post  $post  The post instance to check permissions for.
     *
     * @return bool True if the user can publish the post, false otherwise.
     */
    public function publish( Authenticatable $user, Post $post ): bool
    {
        /**
         * Filters the capability used to determine whether a user can publish posts.
         *
         * @since 1.0.0
         *
         * @hook  posts.publish
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  Post  $post  The post being published.
         *
         * @return string Filtered capability slug.
         */
        return $user->can( applyFilters( 'posts.publish', 'posts.publish', $post ));
    }
}
