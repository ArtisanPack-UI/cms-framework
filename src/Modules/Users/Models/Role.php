<?php

/**
 * Role Model for the CMS Framework Users Module.
 *
 * This model represents user roles within the CMS framework and manages
 * relationships between roles, permissions, and users.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Models
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role model for managing user roles.
 *
 * Represents user roles within the system and defines relationships
 * with permissions and users through many-to-many relationships.
 *
 * @since 1.0.0
 */
class Role extends Model
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'name',
		'slug',
	];

	/**
	 * Get the users that belong to the role.
	 *
	 * Defines a many-to-many relationship between roles and users using
	 * the configurable user model from the cms-framework configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsToMany The relationship instance.
	 */
	public function users(): BelongsToMany
	{
		return $this->belongsToMany( config( 'cms-framework.user_model' ), 'role_user', 'role_id', 'user_id' );
	}

	public function syncPermissions( Collection|array $permissions ): self
	{
		// If the items are Permission models, pluck their names.
		// If they are already strings, this won't change them.
		$permissionNames = collect( $permissions )->map( function ( $permission ) {
			return $permission instanceof Permission ? $permission->name : $permission;
		} );

		// Find all the permission models for the given names.
		$permissionsToSync = Permission::whereIn( 'name', $permissionNames )->get();

		// Use Laravel's built-in sync() method on the relationship.
		$this->permissions()->sync( $permissionsToSync );

		return $this;
	}

	/**
	 * Get the permissions that belong to the role.
	 *
	 * Defines a many-to-many relationship between roles and permissions.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsToMany The relationship instance.
	 */
	public function permissions(): BelongsToMany
	{
		return $this->belongsToMany( Permission::class );
	}

	/**
	 * Gives one or more permissions to the role.
	 *
	 * This will not remove any existing permissions.
	 *
	 * @since  2.0.0
	 * @param \Illuminate\Support\Collection|array $permissions A collection of Permission models or an array of
	 *                                                          permission names.
	 * @return $this
	 */
	public function givePermissionTo( Collection|array $permissions ): self
	{
		// If the items are Permission models, pluck their names.
		// If they are already strings, this won't change them.
		$permissionNames = collect( $permissions )->map( function ( $permission ) {
			return $permission instanceof Permission ? $permission->name : $permission;
		} );

		// Find all the permission models for the given names.
		$permissionsToGive = Permission::whereIn( 'name', $permissionNames )->get();

		// Use syncWithoutDetaching() to add the new permissions without removing existing ones.
		$this->permissions()->syncWithoutDetaching( $permissionsToGive );

		return $this;
	}
}
