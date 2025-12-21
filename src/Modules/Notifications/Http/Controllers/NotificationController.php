<?php

declare( strict_types = 1 );

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Notification Controller
 *
 * @since 1.0.0
 */
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

        // phpcs:ignore ArtisanPackUIStandard.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above
        $limit      = $request->input( 'limit', 10 );
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
        // phpcs:ignore ArtisanPackUIStandard.Security.ValidatedSanitizedInput.MissingUnslash -- Authenticated user ID is type-safe
        $notification = Notification::with( ['users' => function ( $q ) use ( $request ): void {
            $q->where( 'user_id', $request->user()->id );
        }] )->find( $id );

        if ( ! $notification ) {
            return response()->json( ['message' => 'Notification not found'], 404 );
        }

        // Check if user has access to this notification
        if ( ! $notification->users->contains( 'id', $request->user()->id ) ) {
            return response()->json( ['message' => 'Unauthorized'], 403 );
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
            return response()->json( ['message' => 'Failed to mark notification as read'], 400 );
        }

        return response()->json( ['message' => 'Notification marked as read'], 200 );
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
            return response()->json( ['message' => 'Failed to dismiss notification'], 400 );
        }

        return response()->json( ['message' => 'Notification dismissed'], 200 );
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
            'message' => "Marked {$count} notifications as read",
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
            'message' => "Dismissed {$count} notifications",
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
        ], 200);
    }
}
