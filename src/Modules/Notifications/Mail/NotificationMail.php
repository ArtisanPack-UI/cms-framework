<?php
/**
 * Notification Mail
 *
 * Mailable for sending notification emails.
 *
 * @since 2.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Notifications\Mail
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Mail;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for notification emails.
 *
 * @since 2.0.0
 */
class NotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The notification instance.
     *
     * @since 2.0.0
     * @var Notification
     */
    public Notification $notification;

    /**
     * The user instance.
     *
     * @since 2.0.0
     * @var mixed
     */
    public $user;

    /**
     * Create a new message instance.
     *
     * @since 2.0.0
     *
     * @param Notification $notification The notification to send.
     * @param mixed $user The user receiving the email.
     */
    public function __construct(Notification $notification, $user)
    {
        $this->notification = $notification;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     *
     * @since 2.0.0
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    /**
     * Get the message content definition.
     *
     * @since 2.0.0
     *
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'notifications::emails.notification',
            with: [
                'notification' => $this->notification,
                'user' => $this->user,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @since 2.0.0
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
