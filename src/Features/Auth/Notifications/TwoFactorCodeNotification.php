<?php
/**
 * Two-Factor Code Notification
 *
 * Notifies the user with a two-factor authentication code via email.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Auth\Notifications
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification to send a two-factor authentication code to a user's email.
 *
 * @since 1.1.0
 */
class TwoFactorCodeNotification extends Notification
{
	use Queueable;

	/**
	 * The 2FA code to be sent.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public string $code;

	/**
	 * Create a new notification instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string $code The 2FA code to send.
	 */
	public function __construct( string $code )
	{
		$this->code = $code;
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $notifiable The notifiable entity (user).
	 * @return array<int, string>
	 */
	public function via( mixed $notifiable ): array
	{
		return [ 'mail' ];
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $notifiable The notifiable entity (user).
	 * @return MailMessage
	 */
	public function toMail( mixed $notifiable ): MailMessage
	{
		return ( new MailMessage() )
			->subject( 'Your Two-Factor Authentication Code' )
			->line( 'Please use the following code to complete your login:' )
			->line( 'Code: ' . $this->code )
			->line( 'This code will expire in 5 minutes.' )
			->line( 'If you did not request this code, no further action is required.' );
	}
}