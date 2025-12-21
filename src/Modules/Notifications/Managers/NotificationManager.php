<?php

declare( strict_types = 1 );

/**
 * Notification Manager
 *
 * Manages notification registration, sending, and user interactions.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Managers;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Jobs\SendNotificationEmail;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use Illuminate\Support\Collection;

/**
 * Manages notification registration and delivery.
 *
 * @since 1.0.0
 */
// phpcs:disable ArtisanPackUIStandard.Classes.ClassStructure.TraitUseNotAtTop -- False positives for closure use
class NotificationManager
{
    /**
     * Register a notification type with defaults.
     *
     * Notifications are registered via the `ap.notifications.registeredNotifications` filter.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Unique key for the notification type.
     * @param  string  $title  Default title for this notification type.
     * @param  string  $content  Default content for this notification type.
     * @param  NotificationType  $type  The notification type (error, warning, success, info).
     * @param  bool  $sendEmail  Whether to send email notifications by default.
     * @param  array  $metadata  Additional metadata for the notification.
     */
    public function registerNotification(
        string $key,
        string $title,
        string $content,
        NotificationType $type     = NotificationType::Info,
        bool $sendEmail            = false,
        array $metadata            = [],
    ): void {
        /**
         * Filters the array of registered notifications.
         *
         * @since 1.0.0
         *
         * @hook ap.notifications.registeredNotifications
         *
         * @param  array  $notifications  Associative array of registered notifications keyed by notification key.
         *
         * @return array Filtered notifications array.
         */
        addFilter(
            'ap.notifications.registeredNotifications',
            function ( $notifications ) use ( $key, $title, $content, $type, $sendEmail, $metadata ) {
                $notifications[ $key ] = [
                    'title'      => $title,
                    'content'    => $content,
                    'type'       => $type,
                    'send_email' => $sendEmail,
                    'metadata'   => $metadata,
                ];

                return $notifications;
            },
        );
    }

    /**
     * Get all registered notifications.
     *
     * @since 1.0.0
     */
    public function getRegisteredNotifications(): array
    {
        /**
         * Filters the array of registered notifications.
         *
         * @since 1.0.0
         *
         * @hook ap.notifications.registeredNotifications
         *
         * @param  array  $notifications  Associative array of registered notifications.
         *
         * @return array Filtered notifications array.
         */
        return applyFilters( 'ap.notifications.registeredNotifications', [] );
    }

    /**
     * Send a notification to specified users.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The registered notification key (or custom title if not registered).
     * @param  array  $userIds  Array of user IDs to send notification to.
     * @param  array  $overrides  Array to override default values: title, content, type, send_email, metadata.
     *
     * @return Notification|null The created notification instance.
     */
    public function sendNotification( string $key, array $userIds, array $overrides = [] ): ?Notification
    {
        $registered = $this->getRegisteredNotifications();
        $defaults   = $registered[ $key ] ?? [];

        // Merge defaults with overrides
        $title      = $overrides['title'] ?? $defaults['title'] ?? $key;
        $content    = $overrides['content'] ?? $defaults['content'] ?? '';
        $type       = $overrides['type'] ?? $defaults['type'] ?? NotificationType::Info;
        $sendEmail  = $overrides['send_email'] ?? $defaults['send_email'] ?? false;
        $metadata   = array_merge( $defaults['metadata'] ?? [], $overrides['metadata'] ?? [] );

        // Filter users based on their notification preferences
        $userIds = $this->filterUsersByPreferences( $userIds, $key );

        if ( empty( $userIds ) ) {
            return null;
        }

        // Create the notification
        $notification = Notification::create( [
            'type'       => $type,
            'title'      => $title,
            'content'    => $content,
            'metadata'   => $metadata,
            'send_email' => $sendEmail,
        ] );

        // Attach users to the notification
        $notification->users()->attach( $userIds );

        /**
         * Fires after a notification has been sent.
         *
         * @since 1.0.0
         *
         * @hook ap.notifications.sendNotification
         *
         * @param  Notification  $notification  The created notification instance.
         * @param  array  $userIds  Array of user IDs the notification was sent to.
         * @param  string  $key  The notification key.
         */
        doAction( 'ap.notifications.sendNotification', $notification, $userIds, $key );

        // Queue email sending if enabled
        if ( $sendEmail ) {
            $emailUserIds = $this->filterUsersForEmail( $userIds, $key );
            if ( ! empty( $emailUserIds ) ) {
                SendNotificationEmail::dispatch( $notification, $emailUserIds );
            }
        }

        return $notification;
    }

    /**
     * Send notification to users by role.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The registered notification key.
     * @param  string  $role  The role name to send to.
     * @param  array  $overrides  Array to override default values.
     */
    public function sendNotificationByRole( string $key, string $role, array $overrides = [] ): ?Notification
    {
        $userModel = config( 'auth.providers.users.model' );
        $userIds   = $userModel::whereHas( 'roles', function ( $query ) use ( $role ): void {
            $query->where( 'name', sanitizeText( $role ) );
        } )->pluck( 'id' )->toArray();

        return $this->sendNotification( $key, $userIds, $overrides );
    }

    /**
     * Send notification to the current authenticated user.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The registered notification key.
     * @param  array  $overrides  Array to override default values.
     */
    public function sendNotificationToCurrentUser( string $key, array $overrides = [] ): ?Notification
    {
        if ( ! auth()->check() ) {
            return null;
        }

        return $this->sendNotification( $key, [auth()->id()], $overrides );
    }

    /**
     * Get notifications for a specific user.
     *
     * @since 1.0.0
     *
     * @param  int  $userId  The user ID.
     * @param  int  $limit  Maximum number of notifications to retrieve.
     * @param  bool  $unreadOnly  Whether to retrieve only unread notifications.
     */
    public function getUserNotifications( int $userId, int $limit = 10, bool $unreadOnly = false ): Collection
    {
        $query = Notification::whereHas( 'users', function ( $q ) use ( $userId ): void {
            $q->where( 'user_id', sanitizeInt( $userId ) )
                ->where( 'is_dismissed', false );
        } )
            ->with( ['users' => function ( $q ) use ( $userId ): void {
                $q->where( 'user_id', sanitizeInt( $userId ) );
            }] )
            ->orderByDesc( 'created_at' )
            ->limit( $limit );

        if ( $unreadOnly ) {
            $query->whereHas( 'users', function ( $q ) use ( $userId ): void {
                $q->where( 'user_id', sanitizeInt( $userId ) )
                    ->where( 'is_read', false );
            } );
        }

        return $query->get();
    }

    /**
     * Mark a notification as read for a user.
     *
     * @since 1.0.0
     *
     * @param  int  $notificationId  The notification ID.
     * @param  int  $userId  The user ID.
     */
    public function markAsRead( int $notificationId, int $userId ): bool
    {
        $updated = Notification::find( $notificationId )?->users()
            ->updateExistingPivot( $userId, [
                'is_read' => true,
                'read_at' => now(),
            ] );

        if ( $updated ) {
            /**
             * Fires after a notification has been marked as read.
             *
             * @since 1.0.0
             *
             * @hook ap.notifications.readNotification
             *
             * @param  int  $notificationId  The notification ID.
             * @param  int  $userId  The user ID.
             */
            doAction( 'ap.notifications.readNotification', $notificationId, $userId );
        }

        return $updated > 0;
    }

    /**
     * Dismiss a notification for a user.
     *
     * @since 1.0.0
     *
     * @param  int  $notificationId  The notification ID.
     * @param  int  $userId  The user ID.
     */
    public function dismissNotification( int $notificationId, int $userId ): bool
    {
        $updated = Notification::find( $notificationId )?->users()
            ->updateExistingPivot( $userId, [
                'is_dismissed' => true,
                'dismissed_at' => now(),
            ] );

        if ( $updated ) {
            /**
             * Fires after a notification has been dismissed.
             *
             * @since 1.0.0
             *
             * @hook ap.notifications.dismissNotification
             *
             * @param  int  $notificationId  The notification ID.
             * @param  int  $userId  The user ID.
             */
            doAction( 'ap.notifications.dismissNotification', $notificationId, $userId );
        }

        return $updated > 0;
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @since 1.0.0
     *
     * @param  int  $userId  The user ID.
     *
     * @return int The number of notifications marked as read.
     */
    public function markAllAsRead( int $userId ): int
    {
        return Notification::whereHas( 'users', function ( $q ) use ( $userId ): void {
            $q->where( 'user_id', sanitizeInt( $userId ) )
                ->where( 'is_read', false )
                ->where( 'is_dismissed', false );
        } )->get()->sum( function ( $notification ) use ( $userId ) {
            return $this->markAsRead( $notification->id, $userId ) ? 1 : 0;
        } );
    }

    /**
     * Dismiss all notifications for a user.
     *
     * @since 1.0.0
     *
     * @param  int  $userId  The user ID.
     *
     * @return int The number of notifications dismissed.
     */
    public function dismissAll( int $userId ): int
    {
        return Notification::whereHas( 'users', function ( $q ) use ( $userId ): void {
            $q->where( 'user_id', sanitizeInt( $userId ) )
                ->where( 'is_dismissed', false );
        } )->get()->sum( function ( $notification ) use ( $userId ) {
            return $this->dismissNotification( $notification->id, $userId ) ? 1 : 0;
        } );
    }

    /**
     * Get the count of unread notifications for a user.
     *
     * @since 1.0.0
     *
     * @param  int  $userId  The user ID.
     */
    public function getUnreadCount( int $userId ): int
    {
        return Notification::unreadForUser( $userId )->count();
    }

    /**
     * Filter users based on their notification preferences.
     *
     * @since 1.0.0
     *
     * @param  array  $userIds  Array of user IDs.
     * @param  string  $notificationKey  The notification key.
     *
     * @return array Filtered user IDs.
     */
    protected function filterUsersByPreferences( array $userIds, string $notificationKey ): array
    {
        $userModel = config( 'auth.providers.users.model' );

        return $userModel::whereIn( 'id', $userIds )
            ->whereDoesntHave( 'notificationPreferences', function ( $query ) use ( $notificationKey ): void {
                $query->where( 'notification_type', sanitizeText( $notificationKey ) )
                    ->where( 'is_enabled', false );
            } )
            ->pluck( 'id' )
            ->toArray();
    }

    /**
     * Filter users who should receive email notifications.
     *
     * @since 1.0.0
     *
     * @param  array  $userIds  Array of user IDs.
     * @param  string  $notificationKey  The notification key.
     *
     * @return array Filtered user IDs.
     */
    protected function filterUsersForEmail( array $userIds, string $notificationKey ): array
    {
        $userModel = config( 'auth.providers.users.model' );

        return $userModel::whereIn( 'id', $userIds )
            ->whereDoesntHave( 'notificationPreferences', function ( $query ) use ( $notificationKey ): void {
                $query->where( 'notification_type', sanitizeText( $notificationKey))
                    ->where( 'email_enabled', false);
            })
            ->pluck( 'id')
            ->toArray();
    }
}
