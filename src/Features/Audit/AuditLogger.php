<?php
/**
 * Audit Logger
 *
 * Provides functionality for logging authentication-related events and user activities
 * within the CMS.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Audit
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Audit;

use ArtisanPackUI\CMSFramework\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Request;
use ArtisanPackUI\Security\Security;

/**
 * Handles the logging of various authentication and user activity events.
 *
 * This class provides methods to record detailed information about events
 * such as successful logins, failed attempts, password changes, and other
 * significant user actions for security monitoring and auditing purposes.
 *
 * @since 1.1.0
 */
class AuditLogger
{
	/**
	 * Logs a successful login event.
	 *
	 * @since 1.1.0
	 *
	 * @param Authenticatable $user The authenticated user.
	 * @return AuditLog The created audit log entry.
	 */
	public function logLogin( Authenticatable $user ): AuditLog
	{
		return $this->createLog(
			sanitizeText( 'login_success' ),
			sprintf( '%s (ID: %d) successfully logged in.', sanitizeText( $user->email ), sanitizeInt( $user->id ) ),
			sanitizeText( 'success' ),
			sanitizeInt( $user->id )
		);
	}

	/**
	 * Creates a new audit log entry in the database.
	 *
	 * @since  1.1.0
	 * @access private
	 *
	 * @param string   $action  The type of action.
	 * @param string   $message A descriptive message for the log.
	 * @param string   $status  The status of the action.
	 * @param int|null $userId  Optional. The ID of the user associated with the action. Default null.
	 * @return AuditLog The created audit log entry.
	 */
	private function createLog( string $action, string $message, string $status, ?int $userId = null ): AuditLog
	{
		$security = new Security(); // Instantiate the Security class for sanitization.

		return AuditLog::create( [
			'user_id'    => $userId,
			'action'     => $security->sanitizeText( $action ),
			'message'    => $security->sanitizeText( $message ),
			'ip_address' => $security->sanitizeText( Request::ip() ),
			'user_agent' => $security->sanitizeText( Request::header( 'User-Agent' ) ),
			'status'     => $security->sanitizeText( $status ),
		] );
	}

	/**
	 * Logs a failed login attempt.
	 *
	 * @since 1.1.0
	 *
	 * @param string $credentials The credentials used in the failed attempt (e.g., email).
	 * @return AuditLog The created audit log entry.
	 */
	public function logLoginFailed( string $credentials ): AuditLog
	{
		return $this->createLog(
			sanitizeText( 'login_failed' ),
			sprintf( 'Failed login attempt for email: %s.', sanitizeText( $credentials ) ),
			sanitizeText( 'failed' )
		);
	}

	/**
	 * Logs a user logout event.
	 *
	 * @since 1.1.0
	 *
	 * @param Authenticatable $user The user who logged out.
	 * @return AuditLog The created audit log entry.
	 */
	public function logLogout( Authenticatable $user ): AuditLog
	{
		return $this->createLog(
			sanitizeText( 'logout_success' ),
			sprintf( '%s (ID: %d) logged out.', sanitizeText( $user->email ), sanitizeInt( $user->id ) ),
			sanitizeText( 'success' ),
			sanitizeInt( $user->id )
		);
	}

	/**
	 * Logs a password change event.
	 *
	 * @since 1.1.0
	 *
	 * @param Authenticatable $user The user whose password was changed.
	 * @return AuditLog The created audit log entry.
	 */
	public function logPasswordChange( Authenticatable $user ): AuditLog
	{
		return $this->createLog(
			sanitizeText( 'password_changed' ),
			sprintf( 'Password for %s (ID: %d) was changed.', sanitizeText( $user->email ), sanitizeInt( $user->id ) ),
			sanitizeText( 'success' ),
			sanitizeInt( $user->id )
		);
	}

	/**
	 * Logs a generic user activity.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $action      The action performed (e.g., 'settings_updated', 'post_created').
	 * @param string   $description A detailed description of the activity.
	 * @param string   $status      The status of the action (e.g., 'success', 'failed', 'info').
	 * @param int|null $userId      Optional. The ID of the user performing the action. Default null.
	 * @return AuditLog The created audit log entry.
	 */
	public function logActivity( string $action, string $description, string $status = 'info', ?int $userId = null ): AuditLog
	{
		return $this->createLog(
			sanitizeText( $action ),
			sanitizeText( $description ),
			sanitizeText( $status ),
			$userId ? sanitizeInt( $userId ) : null
		);
	}
}