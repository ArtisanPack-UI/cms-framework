<?php

declare( strict_types=1 );

/**
 * Notification API Controller
 *
 * Handles API requests for notifications.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Http\Resources\NotificationResource;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Managers\NotificationManager;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Notification Controller
 *
 * @since 1.0.0
 */
#[Group( 'Notifications', weight: 14 )]
class NotificationController extends Controller
{
    /**
     * The notification manager instance.
     *
     * @since 1.0.0
     */
    protected NotificationManager $notificationManager;

    /**
     * Create a new controller instance.
     *
     * @since 1.0.0
     */
    public function __construct( NotificationManager $notificationManager )
    {
        $this->notificationManager = $notificationManager;
    }

    /**
     * Get all notifications for the authenticated user.
     *
     * @since 1.0.0
     */
    public function index( Request $request ): AnonymousResourceCollection
    {
        $request->validate( [
            'limit'       => 'sometimes|integer|min:1|max:100',
            'unread_only' => 'sometimes|boolean',
        ] );

        $limit      = $request->integer( 'limit', 10 );
        $unreadOnly = $request->boolean( 'unread_only', false );

        $notifications = $this->notificationManager->getUserNotifications(
            $request->user()->id,
            $limit,
            $unreadOnly,
        );

        return NotificationResource::collection( $notifications );
    }

    /**
     * Get a single notification.
     *
     * @since 1.0.0
     */
    public function show( Request $request, int $id ): NotificationResource|JsonResponse
    {
        $notification = Notification::with( ['users' => function ( $q ) use ( $request ): void {
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $request->user()->id is an authenticated, type-safe user id and is Eloquent parameter-bound in the where().
            $q->where( 'user_id', $request->user()->id );
        }] )->find( $id );

        // Return 404 for both "does not exist" and "exists but not the caller's"
        // so the response cannot be used to enumerate notification ids. The
        // ownership decision runs through the registered `view` policy so it is
        // not dead code.
        if ( ! $notification || Gate::denies( 'view', $notification ) ) {
            return response()->json( ['message' => __( 'Notification not found' )], 404 );
        }

        return new NotificationResource( $notification );
    }

    /**
     * Mark a notification as read.
     *
     * @since 1.0.0
     */
    public function markAsRead( Request $request, int $id ): JsonResponse
    {
        $success = $this->notificationManager->markAsRead( $id, $request->user()->id );

        if ( ! $success ) {
            return response()->json( ['message' => __( 'Failed to mark notification as read' )], 400 );
        }

        return response()->json( ['message' => __( 'Notification marked as read' )], 200 );
    }

    /**
     * Dismiss a notification.
     *
     * @since 1.0.0
     */
    public function dismiss( Request $request, int $id ): JsonResponse
    {
        $success = $this->notificationManager->dismissNotification( $id, $request->user()->id );

        if ( ! $success ) {
            return response()->json( ['message' => __( 'Failed to dismiss notification' )], 400 );
        }

        return response()->json( ['message' => __( 'Notification dismissed' )], 200 );
    }

    /**
     * Mark all notifications as read.
     *
     * @since 1.0.0
     */
    public function markAllAsRead( Request $request ): JsonResponse
    {
        $count = $this->notificationManager->markAllAsRead( $request->user()->id );

        return response()->json( [
            'message' => trans_choice( ':count notification marked as read.|:count notifications marked as read.', $count, ['count' => $count] ),
            'count'   => $count,
        ], 200 );
    }

    /**
     * Dismiss all notifications.
     *
     * @since 1.0.0
     */
    public function dismissAll( Request $request ): JsonResponse
    {
        $count = $this->notificationManager->dismissAll( $request->user()->id );

        return response()->json( [
            'message' => trans_choice( ':count notification dismissed.|:count notifications dismissed.', $count, ['count' => $count] ),
            'count'   => $count,
        ], 200 );
    }

    /**
     * Get unread notification count.
     *
     * @since 1.0.0
     */
    public function unreadCount( Request $request ): JsonResponse
    {
        $count = $this->notificationManager->getUnreadCount( $request->user()->id );

        return response()->json( [
            'count' => $count,
        ], 200 );
    }
}
