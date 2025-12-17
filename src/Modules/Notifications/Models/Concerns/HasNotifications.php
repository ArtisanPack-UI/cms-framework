<?php

/**
 * HasNotifications Trait
 *
 * Provides notification-related methods for User models.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Concerns;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HasNotifications Trait
 *
 * @since 2.0.0
 */
trait HasNotifications
{
    /**
     * Get all system notifications for the user.
     *
     * @since 2.0.0
     */
    public function systemNotifications(): BelongsToMany
    {
        return $this->belongsToMany(
            Notification::class,
            'notification_user',
            'user_id',
            'notification_id'
        )
            ->withPivot(['is_read', 'read_at', 'is_dismissed', 'dismissed_at'])
            ->withTimestamps()
            ->orderByDesc('created_at');
    }

    /**
     * Get unread system notifications for the user.
     *
     * @since 2.0.0
     */
    public function unreadSystemNotifications(): BelongsToMany
    {
        return $this->systemNotifications()
            ->wherePivot('is_read', false)
            ->wherePivot('is_dismissed', false);
    }

    /**
     * Get notification preferences for the user.
     *
     * @since 2.0.0
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class, 'user_id');
    }

    /**
     * Check if the user has a preference for a notification type.
     *
     * @since 2.0.0
     *
     * @param  string  $notificationType  The notification type key.
     */
    public function getNotificationPreference(string $notificationType): ?NotificationPreference
    {
        return $this->notificationPreferences()
            ->where('notification_type', $notificationType)
            ->first();
    }

    /**
     * Check if the user should receive a notification type.
     *
     * @since 2.0.0
     *
     * @param  string  $notificationType  The notification type key.
     */
    public function shouldReceiveNotification(string $notificationType): bool
    {
        $preference = $this->getNotificationPreference($notificationType);

        // If no preference exists, default to enabled
        if (! $preference) {
            return true;
        }

        return $preference->is_enabled;
    }

    /**
     * Check if the user should receive email for a notification type.
     *
     * @since 2.0.0
     *
     * @param  string  $notificationType  The notification type key.
     */
    public function shouldReceiveNotificationEmail(string $notificationType): bool
    {
        $preference = $this->getNotificationPreference($notificationType);

        // If no preference exists, default to enabled
        if (! $preference) {
            return true;
        }

        return $preference->email_enabled;
    }

    /**
     * Mark a notification as read for this user.
     *
     * @since 2.0.0
     *
     * @param  int  $notificationId  The notification ID.
     */
    public function markNotificationAsRead(int $notificationId): bool
    {
        return $this->systemNotifications()->updateExistingPivot($notificationId, [
            'is_read' => true,
            'read_at' => now(),
        ]) > 0;
    }

    /**
     * Dismiss a notification for this user.
     *
     * @since 2.0.0
     *
     * @param  int  $notificationId  The notification ID.
     */
    public function dismissNotification(int $notificationId): bool
    {
        return $this->systemNotifications()->updateExistingPivot($notificationId, [
            'is_dismissed' => true,
            'dismissed_at' => now(),
        ]) > 0;
    }

    /**
     * Mark all system notifications as read for this user.
     *
     * @since 2.0.0
     *
     * @return int The number of notifications marked as read.
     */
    public function markAllNotificationsAsRead(): int
    {
        return \Illuminate\Support\Facades\DB::table('notification_user')
            ->where('user_id', $this->id)
            ->where('is_read', false)
            ->where('is_dismissed', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Dismiss all notifications for this user.
     *
     * @since 2.0.0
     *
     * @return int The number of notifications dismissed.
     */
    public function dismissAllNotifications(): int
    {
        return \Illuminate\Support\Facades\DB::table('notification_user')
            ->where('user_id', $this->id)
            ->where('is_dismissed', false)
            ->update([
                'is_dismissed' => true,
                'dismissed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Get the count of unread system notifications for this user.
     *
     * @since 2.0.0
     */
    public function unreadSystemNotificationsCount(): int
    {
        return $this->unreadSystemNotifications()->count();
    }
}
