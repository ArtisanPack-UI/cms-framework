<?php

declare( strict_types=1 );

/**
 * Comment Policy for the CMS Framework Blog Module.
 *
 * Mirrors `PostPolicy` — gates Comment CRUD + moderation through
 * the artisanpack-ui/hooks filter system so host apps can override
 * the capability slugs per operation.
 *
 * @since 2.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Policies;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @since 2.1.0
 */
class CommentPolicy
{
    /**
     * @since 2.1.0
     */
    public function viewAny( ?Authenticatable $user ): bool
    {
        /**
         * Filters the capability used to determine whether a user can list comments.
         * @hook  comments.viewAny
         */
        $capability = applyFilters( 'comments.viewAny', 'comments.view' );

        // Allow anonymous reads of the approved set so guest visitors
        // can see comments on the front-end. Host apps that want to
        // gate even the approved view can return `false` from a
        // hooks filter on `comments.viewAny.public`.
        if ( applyFilters( 'comments.viewAny.public', true ) ) {
            return true;
        }

        return null !== $user && $user->can( $capability );
    }

    /**
     * @since 2.1.0
     */
    public function view( ?Authenticatable $user, Comment $comment ): bool
    {
        if ( Comment::STATUS_APPROVED === $comment->status
            && applyFilters( 'comments.view.public', true, $comment ) ) {
            return true;
        }

        if ( null === $user ) {
            return false;
        }

        return $user->can( applyFilters( 'comments.view', 'comments.view', $comment ) );
    }

    /**
     * @since 2.1.0
     */
    public function create( ?Authenticatable $user ): bool
    {
        // Guest commenting is allowed by default; host apps can opt
        // out by returning `false` from `comments.create.public`.
        if ( null === $user ) {
            return ( bool ) applyFilters( 'comments.create.public', true );
        }

        return $user->can( applyFilters( 'comments.create', 'comments.create' ) );
    }

    /**
     * @since 2.1.0
     */
    public function update( Authenticatable $user, Comment $comment ): bool
    {
        $canEditAny = $user->can( applyFilters( 'comments.update', 'comments.edit', $comment ) );

        if ( $canEditAny ) {
            return true;
        }

        $canEditOwn = $user->can( applyFilters( 'comments.updateOwn', 'comments.editOwn', $comment ) );

        if ( $canEditOwn && null !== $comment->user_id && $comment->user_id === $user->getAuthIdentifier() ) {
            return true;
        }

        return false;
    }

    /**
     * @since 2.1.0
     */
    public function delete( Authenticatable $user, Comment $comment ): bool
    {
        $canDeleteAny = $user->can( applyFilters( 'comments.delete', 'comments.delete', $comment ) );

        if ( $canDeleteAny ) {
            return true;
        }

        $canDeleteOwn = $user->can( applyFilters( 'comments.deleteOwn', 'comments.deleteOwn', $comment ) );

        if ( $canDeleteOwn && null !== $comment->user_id && $comment->user_id === $user->getAuthIdentifier() ) {
            return true;
        }

        return false;
    }

    /**
     * @since 2.1.0
     */
    public function moderate( Authenticatable $user, Comment $comment ): bool
    {
        return $user->can( applyFilters( 'comments.moderate', 'comments.moderate', $comment ) );
    }
}
