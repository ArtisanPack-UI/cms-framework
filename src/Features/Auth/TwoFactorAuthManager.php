<?php
/**
 * Two-Factor Authentication Manager
 *
 * Manages the setup, verification, and recovery for email-based two-factor authentication (2FA)
 * within the CMS.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Auth
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Auth;

use ArtisanPackUI\Security\Security;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use ArtisanPackUI\CMSFramework\Features\Auth\Notifications\TwoFactorCodeNotification;

// Our new notification.

/**
 * Provides comprehensive functionality for Email-Based Two-Factor Authentication.
 *
 * This includes generating and sending numeric codes via email, verifying these codes,
 * and managing the 2FA status for users.
 *
 * @since 1.1.0
 */
class TwoFactorAuthManager
{
	/**
	 * Generates a new numeric 2FA code.
	 *
	 * @since 1.1.0
	 *
	 * @param int $length Optional. The length of the numeric code. Default 6.
	 * @return string The generated numeric code.
	 */
	public function generateNumericCode( int $length = 6 ): string
	{
		return str_pad( (string) random_int( 0, ( 10 ** $length ) - 1 ), $length, '0', STR_PAD_LEFT );
	}

	/**
	 * Sends the 2FA code to the user's email.
	 *
	 * @since 1.1.0
	 *
	 * @param Authenticatable $user The user receiving the code.
	 * @param string          $code The 2FA code to send.
	 * @return void
	 */
	public function sendTwoFactorCode( Authenticatable $user, string $code ): void
	{
		$user->notify( new TwoFactorCodeNotification( $code ) );
	}

	/**
	 * Stores the 2FA code and its expiration for the user.
	 *
	 * @since 1.1.0
	 *
	 * @param Authenticatable $user                                        The user for whom to store the code.
	 * @param string          $code                                        The 2FA code.
	 * @param int             $expiresInMinutes                            Optional. The number of minutes until the
	 *                                                                     code expires. Default 5.
	 * @return void
	 */
	public function storeTwoFactorCode( Authenticatable $user, string $code, int $expiresInMinutes = 5 ): void
	{
		$security = new Security(); // Instantiate the Security class for sanitization.

		$user->forceFill( [
			'two_factor_code'       => $security->sanitizeText( $code ),
			'two_factor_expires_at' => Carbon::now()->addMinutes( sanitizeInt( $expiresInMinutes ) ),
			'two_factor_enabled_at' => Carbon::now(), // Mark as enabled once code is sent for verification.
		] )->save();
	}

	/**
	 * Verifies the provided 2FA code against the stored one.
	 *
	 * @since 1.1.0
	 *
	 * @param Authenticatable $user The user whose code is being verified.
	 * @param string          $code The code entered by the user.
	 * @return bool True if the code is valid and not expired, false otherwise.
	 */
	public function verifyCode( Authenticatable $user, string $code ): bool
	{
		$security = new Security(); // Instantiate the Security class for sanitization.

		if (
			is_null( $user->two_factor_code ) ||
			is_null( $user->two_factor_expires_at ) ||
			Carbon::now()->isAfter( $user->two_factor_expires_at )
		) {
			return false; // Code not set or expired.
		}

		return $security->sanitizeText( $code ) === $user->two_factor_code;
	}

	/**
	 * Disables two-factor authentication for the user by clearing 2FA related fields.
	 *
	 * @since 1.1.0
	 *
	 * @param Authenticatable $user The user for whom to disable 2FA.
	 * @return void
	 */
	public function disableTwoFactor( Authenticatable $user ): void
	{
		$user->forceFill( [
			'two_factor_code'       => null,
			'two_factor_expires_at' => null,
			'two_factor_enabled_at' => null, // Mark as disabled.
		] )->save();
	}
}