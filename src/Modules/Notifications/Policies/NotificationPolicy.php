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
        // Only users with notification management capability can create.
        return $this->userHasCapability( $user, 'notifications.manage' );
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
        // Only users with notification management capability can delete.
        return $this->userHasCapability( $user, 'notifications.manage' );
    }

    /**
     * Resolve the capability against whichever RBAC contract the host user
     * model exposes.
     *
     * The candidate methods are probed in priority order — the WordPress-style
     * `hasCapability()` first, then the rbac `hasPermissionTo()` and its
     * `hasPermission()` alias, which is what cms-framework's users actually
     * expose. Each candidate is guarded with `method_exists()` first, and the
     * first one the model defines decides the outcome, so a host model that
     * composes none of them degrades to a plain denial instead of fataling with
     * "Call to undefined method".
     *
     * @since 2.11.0
     *
     * @param  mixed  $user
     */
    protected function userHasCapability( $user, string $capability ): bool
    {
        foreach ( ['hasCapability', 'hasPermissionTo', 'hasPermission'] as $method ) {
            if ( method_exists( $user, $method ) ) {
                return (bool) $user->{$method}( $capability );
            }
        }

        return false;
    }
}
