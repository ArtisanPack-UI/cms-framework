<?php

/**
 * Notification helper functions.
 *
 * These helpers proxy calls to the NotificationManager for easy access throughout
 * the application. All helpers follow WordPress-style inline documentation.
 *
 * @since 2.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Managers\NotificationManager;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use Illuminate\Support\Collection;

if (! function_exists('apRegisterNotification')) {
    /**
     * Register a notification type with defaults.
     *
     * @since 2.0.0
     *
     * @param  string  $key  Unique key for the notification type.
     * @param  string  $title  Default title for this notification type.
     * @param  string  $content  Default content for this notification type.
     * @param  NotificationType  $type  The notification type (error, warning, success, info).
     * @param  bool  $sendEmail  Whether to send email notifications by default.
     * @param  array  $metadata  Additional metadata for the notification.
     */
    function apRegisterNotification(
        string $key,
        string $title,
        string $content,
        NotificationType $type = NotificationType::Info,
        bool $sendEmail = false,
        array $metadata = []
    ): void {
        app(NotificationManager::class)->registerNotification(
            $key,
            $title,
            $content,
            $type,
            $sendEmail,
            $metadata
        );
    }
}

if (! function_exists('apSendNotification')) {
    /**
     * Send a notification to specified users.
     *
     * @since 2.0.0
     *
     * @param  string  $key  The registered notification key.
     * @param  array  $userIds  Array of user IDs to send notification to.
     * @param  array  $overrides  Array to override default values.
     * @return Notification|null The created notification instance.
     */
    function apSendNotification(string $key, array $userIds, array $overrides = []): ?Notification
    {
        return app(NotificationManager::class)->sendNotification($key, $userIds, $overrides);
    }
}

if (! function_exists('apSendNotificationByRole')) {
    /**
     * Send notification to users by role.
     *
     * @since 2.0.0
     *
     * @param  string  $key  The registered notification key.
     * @param  string  $role  The role name to send to.
     * @param  array  $overrides  Array to override default values.
     */
    function apSendNotificationByRole(string $key, string $role, array $overrides = []): ?Notification
    {
        return app(NotificationManager::class)->sendNotificationByRole($key, $role, $overrides);
    }
}

if (! function_exists('apSendNotificationToCurrentUser')) {
    /**
     * Send notification to the current authenticated user.
     *
     * @since 2.0.0
     *
     * @param  string  $key  The registered notification key.
     * @param  array  $overrides  Array to override default values.
     */
    function apSendNotificationToCurrentUser(string $key, array $overrides = []): ?Notification
    {
        return app(NotificationManager::class)->sendNotificationToCurrentUser($key, $overrides);
    }
}

if (! function_exists('apGetNotifications')) {
    /**
     * Get notifications for a specific user.
     *
     * @since 2.0.0
     *
     * @param  int  $userId  The user ID.
     * @param  int  $limit  Maximum number of notifications to retrieve.
     * @param  bool  $unreadOnly  Whether to retrieve only unread notifications.
     */
    function apGetNotifications(int $userId, int $limit = 10, bool $unreadOnly = false): Collection
    {
        return app(NotificationManager::class)->getUserNotifications($userId, $limit, $unreadOnly);
    }
}

if (! function_exists('apMarkNotificationAsRead')) {
    /**
     * Mark a notification as read for a user.
     *
     * @since 2.0.0
     *
     * @param  int  $notificationId  The notification ID.
     * @param  int  $userId  The user ID.
     */
    function apMarkNotificationAsRead(int $notificationId, int $userId): bool
    {
        return app(NotificationManager::class)->markAsRead($notificationId, $userId);
    }
}

if (! function_exists('apDismissNotification')) {
    /**
     * Dismiss a notification for a user.
     *
     * @since 2.0.0
     *
     * @param  int  $notificationId  The notification ID.
     * @param  int  $userId  The user ID.
     */
    function apDismissNotification(int $notificationId, int $userId): bool
    {
        return app(NotificationManager::class)->dismissNotification($notificationId, $userId);
    }
}

if (! function_exists('apMarkAllNotificationsAsRead')) {
    /**
     * Mark all notifications as read for a user.
     *
     * @since 2.0.0
     *
     * @param  int  $userId  The user ID.
     * @return int The number of notifications marked as read.
     */
    function apMarkAllNotificationsAsRead(int $userId): int
    {
        return app(NotificationManager::class)->markAllAsRead($userId);
    }
}

if (! function_exists('apDismissAllNotifications')) {
    /**
     * Dismiss all notifications for a user.
     *
     * @since 2.0.0
     *
     * @param  int  $userId  The user ID.
     * @return int The number of notifications dismissed.
     */
    function apDismissAllNotifications(int $userId): int
    {
        return app(NotificationManager::class)->dismissAll($userId);
    }
}

if (! function_exists('apGetUnreadNotificationCount')) {
    /**
     * Get the count of unread notifications for a user.
     *
     * @since 2.0.0
     *
     * @param  int  $userId  The user ID.
     */
    function apGetUnreadNotificationCount(int $userId): int
    {
        return app(NotificationManager::class)->getUnreadCount($userId);
    }
}

if (! function_exists('apGetRegisteredNotifications')) {
    /**
     * Get all registered notifications.
     *
     * @since 2.0.0
     */
    function apGetRegisteredNotifications(): array
    {
        return app(NotificationManager::class)->getRegisteredNotifications();
    }
}
