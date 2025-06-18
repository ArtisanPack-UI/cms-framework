<?php
/**
 * Notification Manager
 *
 * Manages notification operations for the application.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Notifications
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Notifications;

use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Notifications\Notification;

/**
 * Class for managing application notifications
 *
 * Provides functionality to send notifications to users and other notifiable entities.
 *
 * @since 1.1.0
 */
class NotificationManager
{
    /**
     * Send a notification to a notifiable entity
     *
     * @since 1.1.0
     * @param mixed        $notifiable The entity to notify (user, etc.)
     * @param Notification $notification The notification instance to send
     * @return void
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        NotificationFacade::send($notifiable, $notification);
    }

    /**
     * Send a notification to multiple notifiable entities
     *
     * @since 1.1.0
     * @param array        $notifiables Array of entities to notify
     * @param Notification $notification The notification instance to send
     * @return void
     */
    public function sendToMany(array $notifiables, Notification $notification): void
    {
        NotificationFacade::send($notifiables, $notification);
    }

    /**
     * Send a notification later (queued)
     *
     * @since 1.1.0
     * @param mixed        $notifiable The entity to notify (user, etc.)
     * @param Notification $notification The notification instance to send
     * @param \DateTimeInterface|\DateInterval|int|null $delay Optional. When to send the notification
     * @return void
     */
    public function sendLater(mixed $notifiable, Notification $notification, $delay = null): void
    {
        NotificationFacade::sendNow($notifiable, $notification, $delay);
    }

    /**
     * Send a notification immediately (bypassing queue)
     *
     * @since 1.1.0
     * @param mixed        $notifiable The entity to notify (user, etc.)
     * @param Notification $notification The notification instance to send
     * @return void
     */
    public function sendNow(mixed $notifiable, Notification $notification): void
    {
        NotificationFacade::sendNow($notifiable, $notification);
    }
}
