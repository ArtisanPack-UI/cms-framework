<?php
/**
 * Audit Log Model
 *
 * Represents an entry in the audit log, storing details of user activities and system events.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Models
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for the audit_logs table.
 *
 * @since 1.1.0
 *
 * @property int    $id
 * @property int    $user_id      The ID of the user who performed the action, if applicable.
 * @property string $action       The type of action logged (e.g., 'login_success', 'password_changed').
 * @property string $message      A descriptive message for the log entry.
 * @property string $ip_address   The IP address from which the action originated.
 * @property string $user_agent   The user agent string of the client.
 * @property string $status       The status of the action (e.g., 'success', 'failed', 'info').
 * @property string $created_at   The timestamp when the log entry was created.
 * @property string $updated_at   The timestamp when the log entry was last updated.
 */
class AuditLog extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected $table = 'audit_logs';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.1.0
	 * @var array<int, string>
	 */
	protected $fillable = [
		'user_id',
		'action',
		'message',
		'ip_address',
		'user_agent',
		'status',
	];

	/**
	 * Get the user that owns the audit log.
	 *
	 * @since 1.1.0
	 * @return BelongsTo
	 */
	public function user(): BelongsTo
	{
		// Assuming your consuming application's User model is `App\Models\User`.
		// You might need to make this configurable or provide a default.
		return $this->belongsTo( User::class );
	}
}