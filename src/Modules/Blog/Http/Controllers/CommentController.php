<?php

declare( strict_types=1 );

/**
 * Comment Controller for the CMS Framework Blog Module.
 *
 * Provides RESTful API endpoints for comment management. Read endpoints
 * default to the approved set (public-facing); moderation surfaces can
 * request other statuses with the `?status=...` filter and the right
 * capability.
 *
 * @since 2.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests\CommentRequest;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources\CommentResource;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * @since 2.1.0
 */
#[Group( 'Comments', weight: 2 )]
class CommentController extends Controller
{
    use AuthorizesRequests;

    /**
     * List comments. Defaults to approved + top-level set; pass
     * `post_id`, `parent_id`, `status` to filter. Replies are
     * eager-loaded one level deep so the visual-editor renderer can
     * draw the immediate thread without a follow-up request.
     *
     * @since 2.1.0
     */
    public function index( Request $request ): AnonymousResourceCollection
    {
        $this->authorize( 'viewAny', Comment::class );

        // `replies` defaults to the approved set on the relation,
        // so eager-loading is safe for public callers.
        $query = Comment::query()->with( 'replies' );

        if ( $request->filled( 'post_id' ) ) {
            $query->where( 'post_id', ( int ) $request->input( 'post_id' ) );
        }

        if ( $request->has( 'parent_id' ) ) {
            $parent = $request->input( 'parent_id' );
            $query->where( 'parent_id', '' === $parent || null === $parent ? null : ( int ) $parent );
        } else {
            // Default to top-level comments when no parent filter is
            // supplied; consumers iterating manually can pass
            // `?parent_id=` (empty) or `?parent_id=null` explicitly.
            $query->whereNull( 'parent_id' );
        }

        // Status filter is moderation-gated: only callers with the
        // `comments.moderate` capability can request non-approved
        // sets. Public callers always see the approved view,
        // regardless of what they pass on the query string.
        $requestedStatus = $request->input( 'status' );
        $user            = $request->user();
        $canModerate     = null !== $user && $user->can( applyFilters( 'ap.cmsFramework.abilities.comments.moderate', 'comments.moderate' ) );
        $status          = ( $canModerate && null !== $requestedStatus && '' !== $requestedStatus )
            ? $requestedStatus
            : Comment::STATUS_APPROVED;

        $query->where( 'status', $status );

        $perPage = ( int ) $request->input( 'per_page', 15 );
        $perPage = max( 1, min( 100, $perPage ) );

        return CommentResource::collection(
            $query->latest()->paginate( $perPage ),
        );
    }

    /**
     * @since 2.1.0
     */
    public function show( Comment $comment ): CommentResource
    {
        $this->authorize( 'view', $comment );

        return new CommentResource( $comment->load( 'replies' ) );
    }

    /**
     * @since 2.1.0
     */
    public function store( CommentRequest $request ): JsonResponse
    {
        $this->authorize( 'create', Comment::class );

        $data = $request->validated();
        $user = $request->user();

        // `user_id` is server-controlled — stripped from the request
        // body in `CommentRequest::prepareForValidation()` to prevent
        // impersonation. Wire it in from the authenticated session
        // here.
        if ( null !== $user ) {
            $data['user_id'] = $user->getAuthIdentifier();
        }

        // Default status: approved when the requester is an
        // authenticated user with moderation capability; pending
        // otherwise. `CommentRequest::prepareForValidation()` strips
        // a client-supplied `status` unless the caller can moderate,
        // so this branch fires for everyone else.
        if ( ! isset( $data['status'] ) ) {
            $autoApprove     = null !== $user && $user->can( applyFilters( 'ap.cmsFramework.abilities.comments.moderate', 'comments.moderate' ) );
            $defaultStatus   = $autoApprove ? Comment::STATUS_APPROVED : Comment::STATUS_PENDING;
            $data['status']  = applyFilters( 'ap.cmsFramework.comments.store.defaultStatus', $defaultStatus, $request );
        }

        if ( Comment::STATUS_APPROVED === $data['status'] && empty( $data['approved_at'] ) ) {
            $data['approved_at'] = now();
        }

        $comment = Comment::create( $data );

        return ( new CommentResource( $comment ) )
            ->response()
            ->setStatusCode( Response::HTTP_CREATED );
    }

    /**
     * @since 2.1.0
     */
    public function update( CommentRequest $request, Comment $comment ): CommentResource
    {
        $this->authorize( 'update', $comment );

        $data = $request->validated();

        // Re-stamp approved_at when moving into the approved status.
        if ( isset( $data['status'] )
            && Comment::STATUS_APPROVED === $data['status']
            && Comment::STATUS_APPROVED !== $comment->status ) {
            $data['approved_at'] = now();
        }

        $comment->update( $data );

        return new CommentResource( $comment->fresh() );
    }

    /**
     * @since 2.1.0
     */
    public function destroy( Comment $comment ): Response
    {
        $this->authorize( 'delete', $comment );

        $comment->delete();

        return response()->noContent();
    }
}
