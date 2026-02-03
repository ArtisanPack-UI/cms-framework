<?php

declare( strict_types = 1 );

/**
 * Notification Policy
 *
 * Handles authorization for notification operations.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Policies;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;

/**
 * Notification Policy
 *
 * @since 1.0.0
 */
class NotificationPolicy
{
    /**
     * Determine if the user can view any notifications.
     *
     * @since 1.0.0
     *
     * @param  mixed  $user
     */
    public function viewAny( $user ): bool
    {
        return true; // All authenticated users can view their notifications
    }

    /**
     * Determine if the user can view a specific notification.
     *
     * @since 1.0.0
     *
     * @param  mixed  $user
     */
    public function view( $user, Notification $notification ): bool
    {
        // User can only view notifications sent to them
        // phpcs:ignore ArtisanPackUIStandard.Security.ValidatedSanitizedInput.MissingUnslash -- Model ID is type-safe
        return $notification->users()->where( 'user_id', $user->id )->exists();
    }

    /**
     * Determine if the user can create notifications.
     *
     * @since 1.0.0
     *
     * @param  mixed  $user
     */
    public function create( $user ): bool
    {
        // Only users with notification management capability can create
        return $user->hasCapability( 'notifications.manage' );
    }

    /**
     * Determine if the user can update a notification.
     *
     * @since 1.0.0
     *
     * @param  mixed  $user
     */
    public function update( $user, Notification $notification ): bool
    {
        // User can update (mark as read/dismiss) their own notifications
        // phpcs:ignore ArtisanPackUIStandard.Security.ValidatedSanitizedInput.MissingUnslash -- Model ID is type-safe
        return $notification->users()->where( 'user_id', $user->id )->exists();
    }

    /**
     * Determine if the user can delete a notification.
     *
     * @since 1.0.0
     *
     * @param  mixed  $user
     */
    public function delete( $user, Notification $notification ): bool
    {
        // Only users with notification management capability can delete
        return $user->hasCapability( 'notifications.manage' );
    }
}
