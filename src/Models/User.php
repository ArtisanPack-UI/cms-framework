<?php
/**
 * User Model
 *
 * Represents a user in the application.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Models
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use phpDocumentor\Reflection\Types\Iterable_;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Class for the User model.
 *
 * Handles database interactions for users, including authentication, roles,
 * and user-specific settings and links stored as JSON columns.
 *
 * @since 1.0.0
 */
class User extends Authenticatable
{
	use HasFactory, Notifiable;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'users';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $fillable = [
		'username',
		'email',
		'password',
		'role_id',
		'first_name',
		'last_name',
		'website',
		'bio',
		'links',
		'settings',
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = [
		'email_verified_at' => 'datetime',
		'password'          => 'hashed',
		'links'             => 'array',
		'settings'          => 'array',
	];

	/**
	 * Get the role that the user belongs to.
	 *
	 * @since 1.0.0
	 * @return BelongsTo
	 */
	public function role(): BelongsTo
	{
		return $this->belongsTo( Role::class );
	}

	/**
	 * Checks if the user has a given capability through their assigned role.
	 *
	 * @since 1.0.0
	 * @param Iterable|string $abilities The capability to check for.
	 * @param array           $arguments Additional arguments for the check.
	 * @return bool True if the user has the capability, false otherwise.
	 */
	public function can( $abilities, $arguments = [] ): bool
	{
		/**
		 * Filters whether a user has a specific capability.
		 *
		 * This hook allows for custom logic to determine if a user has a capability,
		 * bypassing the default role-based check if necessary.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $hasCapability Whether the user has the capability. Default false.
		 * @param string $abilities     The capability being checked.
		 * @param User   $user          The user model instance.
		 */
		$hasCapability = Eventy::filter( 'ap.cms.users.user_can', false, $abilities, $this );

		if ( $hasCapability ) {
			return true;
		}

		if ( $this->role ) {
			return $this->role->hasCapability( $abilities );
		}

		return false;
	}

	/**
	 * Get a user-specific setting.
	 *
	 * @since 1.0.0
	 * @param mixed  $default Optional. The default value if the setting is not found. Default null.
	 * @param string $key     The setting key to retrieve.
	 * @return mixed The setting value.
	 */
	public function getSetting( string $key, mixed $default = null ): mixed
	{
		$settings = $this->settings ?? [];
		$value    = $settings[ $key ] ?? $default;

		/**
		 * Filters a user-specific setting value retrieved from the user's settings column.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed  $value The setting value.
		 * @param string $key   The setting key.
		 * @param User   $user  The user model instance.
		 */
		return Eventy::filter( 'ap.cms.users.user_setting.get', $value, $key, $this );
	}

	/**
	 * Set a user-specific setting.
	 *
	 * @since 1.0.0
	 * @param mixed  $value The value to store.
	 * @param string $key   The setting key to set.
	 * @return bool True if the setting was set and saved, false otherwise.
	 */
	public function setSetting( string $key, mixed $value ): bool
	{
		$settings         = $this->settings ?? [];
		$settings[ $key ] = $value;
		$this->settings   = $settings;
		$saved            = $this->save();

		if ( $saved ) {
			/**
			 * Fires after a user-specific setting has been set and saved.
			 *
			 * @since 1.0.0
			 *
			 * @param string $key   The setting key.
			 * @param mixed  $value The value that was set.
			 * @param User   $user  The user model instance.
			 */
			Eventy::action( 'ap.cms.users.user_setting.set', $key, $value, $this );
		}

		return $saved;
	}

	/**
	 * Delete a user-specific setting.
	 *
	 * @since 1.0.0
	 * @param string $key The setting key to delete.
	 * @return bool True if the setting was deleted and saved, false otherwise.
	 */
	public function deleteSetting( string $key ): bool
	{
		$settings = $this->settings ?? [];
		if ( isset( $settings[ $key ] ) ) {
			unset( $settings[ $key ] );
			$this->settings = $settings;
			$saved          = $this->save();

			if ( $saved ) {
				/**
				 * Fires after a user-specific setting has been deleted and saved.
				 *
				 * @since 1.0.0
				 *
				 * @param string $key  The setting key that was deleted.
				 * @param User   $user The user model instance.
				 */
				Eventy::action( 'ap.cms.users.user_setting.deleted', $key, $this );
			}

			return $saved;
		}
		return false;
	}
}