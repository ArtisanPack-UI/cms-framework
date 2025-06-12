<?php
/**
 * Audit Log Service Provider
 *
 * Provides the service registration and bootstrapping for the audit logging feature
 * of the CMS framework. This service provider is responsible for defining
 * the registration and bootstrapping process related to audit logging functionality.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Audit
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Audit;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;

/**
 * Class for providing audit logging services.
 *
 * This service provider registers the AuditLogger as a singleton and
 * attaches listeners for authentication events to record them in the audit log.
 *
 * @since 1.1.0
 * @see   ServiceProvider
 * @see   AuditLogger
 */
class AuditLogServiceProvider extends ServiceProvider
{
	/**
	 * Register audit logging services.
	 *
	 * Registers the AuditLogger as a singleton service in the application container.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register(): void
	{
		$this->app->singleton( AuditLogger::class, function ( $app ) {
			return new AuditLogger();
		} );
	}

	/**
	 * Boot audit logging services.
	 *
	 * Attaches event listeners for various authentication events to the AuditLogger.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void
	{
		Event::listen( Login::class, function ( Login $event ) {
			app( AuditLogger::class )->logLogin( $event->user );
		} );

		Event::listen( Failed::class, function ( Failed $event ) {
			app( AuditLogger::class )->logLoginFailed( $event->credentials['email'] ?? 'unknown' );
		} );

		Event::listen( Logout::class, function ( Logout $event ) {
			if ( $event->user ) {
				app( AuditLogger::class )->logLogout( $event->user );
			}
		} );
	}
}