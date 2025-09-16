<?php

/**
 * Role Policy for the CMS Framework Users Module.
 *
 * This policy handles authorization for role-related operations using
 * the Eventy filter system for extensible permission checking.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Policies
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Policies;

use TorMorten\Eventy\Facades\Eventy;

/**
 * Policy for managing role permissions.
 *
 * Provides authorization methods for role-related operations using
 * the configurable user model and Eventy filter system for extensibility.
 *
 * @since 1.0.0
 */
class RolePolicy
{
	/**
	 * Determine whether the user can view any roles.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can view roles, false otherwise.
	 */
	public function viewAny( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		return $user->can( Eventy::filter( 'role.viewAny', 'role.viewAny' ) );
	}

	/**
	 * Determine whether the user can view the role.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can view the role, false otherwise.
	 */
	public function view( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		return $user->can( Eventy::filter( 'role.view', 'role.view' ) );
	}

	/**
	 * Determine whether the user can create roles.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can create roles, false otherwise.
	 */
	public function create( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		return $user->can( Eventy::filter( 'role.create', 'role.create' ) );
	}

	/**
	 * Determine whether the user can update the role.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can update the role, false otherwise.
	 */
	public function update( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		return $user->can( Eventy::filter( 'role.update', 'role.update' ) );
	}

	/**
	 * Determine whether the user can delete the role.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can delete the role, false otherwise.
	 */
	public function delete( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		return $user->can( Eventy::filter( 'role.delete', 'role.delete' ) );
	}

	/**
	 * Determine whether the user can restore the role.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can restore the role, false otherwise.
	 */
	public function restore( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		return $user->can( Eventy::filter( 'role.restore', 'role.restore' ) );
	}

	/**
	 * Determine whether the user can permanently delete the role.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can force delete the role, false otherwise.
	 */
	public function forceDelete( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		return $user->can( Eventy::filter( 'role.forceDelete', 'role.forceDelete' ) );
	}
}