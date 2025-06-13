<?php
/**
 * Role Model
 *
 * Represents a role in the application.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Models
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\Database\factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class for the Role model.
 *
 * Handles database interactions for roles, their relationships with users,
 * and associated capabilities stored as a JSON column.
 *
 * @since 1.0.0
 */
class Role extends Model
{
	use HasFactory;

	/**
	 * The factory that should be used to instantiate the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected static $factory = RoleFactory::class;
	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'roles';
	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $fillable = [
		'name',
		'slug',
		'description',
		'capabilities',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = [
		'capabilities' => 'array',
	];

	/**
	 * Get the users that belong to this role.
	 *
	 * @since 1.0.0
	 * @return HasMany
	 */
	public function users(): HasMany
	{
		return $this->hasMany( User::class, 'role_id' ); // Specify foreign key for clarity.
	}

	/**
	 * Adds a capability to the role.
	 *
	 * @since 1.0.0
	 * @param string $capability The capability to add.
	 * @return bool True if the capability was added and saved, false otherwise.
	 */
	public function addCapability( string $capability ): bool
	{
		$capabilities = $this->capabilities ?? [];
		if ( ! $this->hasCapability( $capability ) ) {
			$capabilities[]     = $capability;
			$this->capabilities = $capabilities;
			return $this->save();
		}
		return false;
	}

	/**
	 * Checks if the role has a given capability.
	 *
	 * @since 1.0.0
	 * @param string $capability The capability to check for.
	 * @return bool True if the role has the capability, false otherwise.
	 */
	public function hasCapability( string $capability ): bool
	{
		$capabilities = $this->capabilities;

		// Handle both serialized and unserialized capabilities
		if ( is_string( $capabilities ) ) {
			$capabilities = unserialize( $capabilities );
		}

		return in_array( $capability, $capabilities ?? [], true );
	}

	/**
	 * Removes a capability from the role.
	 *
	 * @since 1.0.0
	 * @param string $capability The capability to remove.
	 * @return bool True if the capability was removed and saved, false otherwise.
	 */
	public function removeCapability( string $capability ): bool
	{
		$capabilities = $this->capabilities ?? [];
		if ( $this->hasCapability( $capability ) ) {
			$this->capabilities = array_values( array_diff( $capabilities, [ $capability ] ) );
			return $this->save();
		}
		return false;
	}
}
