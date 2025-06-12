<?php
/**
 * Provides Email-Based Two-Factor Authenticatable functionality to Eloquent models.
 *
 * This trait should be used on the User model in a consuming application
 * to add the necessary fields and methods for email-based 2FA support.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Auth
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Auth;

use Illuminate\Support\Carbon;

trait TwoFactorAuthenticatable
{
	/**
	 * Get the current two-factor authentication code for the user.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null The current 2FA code, or null if not set.
	 */
	public function getTwoFactorCodeAttribute(): ?string
	{
		return $this->two_factor_code;
	}

	/**
	 * Get the expiration timestamp for the two-factor authentication code.
	 *
	 * @since 1.1.0
	 *
	 * @return Carbon|null The expiration timestamp, or null if not set.
	 */
	public function getTwoFactorExpiresAtAttribute(): ?Carbon
	{
		return $this->two_factor_expires_at ? Carbon::parse( $this->two_factor_expires_at ) : null;
	}

	/**
	 * Determine if the two-factor authentication code has expired.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if the code has expired, false otherwise.
	 */
	public function twoFactorCodeHasExpired(): bool
	{
		return $this->hasTwoFactorEnabled() && Carbon::now()->isAfter( $this->two_factor_expires_at );
	}

	/**
	 * Determine if email-based two-factor authentication is currently enabled for this user.
	 *
	 * This checks if the two_factor_code and two_factor_expires_at fields are set
	 * and if the enabled_at timestamp is set, indicating a 2FA session is active.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if 2FA is active, false otherwise.
	 */
	public function hasTwoFactorEnabled(): bool
	{
		return ! is_null( $this->two_factor_code ) &&
			! is_null( $this->two_factor_expires_at ) &&
			! is_null( $this->two_factor_enabled_at );
	}

	/**
	 * Store the two-factor authentication code for the user.
	 *
	 * @since 1.1.0
	 *
	 * @param string $code             The 2FA code to store.
	 * @param int    $expiresInMinutes Optional. Minutes until code expires. Default 5.
	 * @return void
	 */
	public function setTwoFactorCode( string $code, int $expiresInMinutes = 5 ): void
	{
		$this->forceFill( [
			'two_factor_code'       => $code,
			'two_factor_expires_at' => Carbon::now()->addMinutes( $expiresInMinutes ),
			'two_factor_enabled_at' => Carbon::now(), // Mark as enabled.
		] )->save();
	}

	/**
	 * Clear two-factor authentication data for the user.
	 *
	 * This effectively disables 2FA for the user.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function clearTwoFactorData(): void
	{
		$this->forceFill( [
			'two_factor_code'       => null,
			'two_factor_expires_at' => null,
			'two_factor_enabled_at' => null,
		] )->save();
	}
}