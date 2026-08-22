<?php

declare( strict_types=1 );

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
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $user->id is a type-safe model id, Eloquent parameter-bound in the existence check.
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
        // Only users with notification management capability can create. Guard
        // `hasCapability()` so the globally registered policy degrades to a plain
        // denial on host user models that do not compose the RBAC trait, rather
        // than fataling with "Call to undefined method".
        return method_exists( $user, 'hasCapability' ) && $user->hasCapability( 'notifications.manage' );
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
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $user->id is a type-safe model id, Eloquent parameter-bound in the existence check.
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
        // Only users with notification management capability can delete. Guard
        // `hasCapability()` so the globally registered policy degrades to a plain
        // denial on host user models that do not compose the RBAC trait, rather
        // than fataling with "Call to undefined method".
        return method_exists( $user, 'hasCapability' ) && $user->hasCapability( 'notifications.manage' );
    }
}
