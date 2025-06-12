<?php
/**
 * Two-Factor Authentication Service Provider
 *
 * Provides the service registration for the email-based two-factor authentication feature
 * of the CMS framework. This service provider is responsible for binding the
 * TwoFactorAuthManager to the container.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Auth
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Auth;

use Illuminate\Support\ServiceProvider;

/**
 * Class for providing email-based 2FA services.
 *
 * This service provider registers the TwoFactorAuthManager as a singleton
 * for dependency injection throughout the application.
 *
 * @since 1.1.0
 * @see   ServiceProvider
 * @see   TwoFactorAuthManager
 */
class TwoFactorAuthServiceProvider extends ServiceProvider
{
	/**
	 * Register two-factor authentication services.
	 *
	 * Registers the TwoFactorAuthManager as a singleton service in the application container.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( TwoFactorAuthManager::class, function ( $app ) {
			return new TwoFactorAuthManager();
		} );
	}

	/**
	 * Boot two-factor authentication services.
	 *
	 * No specific booting logic is required for this service provider beyond
	 * registering the manager.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void
	{
		// No specific booting logic for email-based 2FA in the service provider itself,
		// as the actual sending/verification is triggered by the manager/middleware.
	}
}