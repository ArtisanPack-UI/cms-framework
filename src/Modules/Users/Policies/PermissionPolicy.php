<?php

/**
 * Permission Policy for the CMS Framework Users Module.
 *
 * This policy handles authorization for permission-related operations using
 * the Eventy filter system for extensible permission checking.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Policies
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Policies;

use TorMorten\Eventy\Facades\Eventy;

/**
 * Policy for managing permission permissions.
 *
 * Provides authorization methods for permission-related operations using
 * the configurable user model and Eventy filter system for extensibility.
 *
 * @since 1.0.0
 */
class PermissionPolicy
{
	/**
	 * Determine whether the user can view any permissions.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can view permissions, false otherwise.
	 */
	public function viewAny( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		/**
		 * Filters the capability used to determine whether a user can view any permissions.
		 *
		 * @since 1.0.0
		 *
		 * @hook permissions.viewAny
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'permissions.viewAny', 'permissions.viewAny' ) );
	}

	/**
	 * Determine whether the user can view the permission.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can view the permission, false otherwise.
	 */
	public function view( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		/**
		 * Filters the capability used to determine whether a user can view a permission.
		 *
		 * @since 1.0.0
		 *
		 * @hook permissions.view
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'permissions.view', 'permissions.view' ) );
	}

	/**
	 * Determine whether the user can create permissions.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can create permissions, false otherwise.
	 */
	public function create( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		/**
		 * Filters the capability used to determine whether a user can create permissions.
		 *
		 * @since 1.0.0
		 *
		 * @hook permissions.create
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'permissions.create', 'permissions.create' ) );
	}

	/**
	 * Determine whether the user can update the permission.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can update the permission, false otherwise.
	 */
	public function update( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		/**
		 * Filters the capability used to determine whether a user can update permissions.
		 *
		 * @since 1.0.0
		 *
		 * @hook permissions.update
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'permissions.update', 'permissions.update' ) );
	}

	/**
	 * Determine whether the user can delete the permission.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can delete the permission, false otherwise.
	 */
	public function delete( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		/**
		 * Filters the capability used to determine whether a user can delete permissions.
		 *
		 * @since 1.0.0
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'permissions.delete', 'permissions.delete' ) );
	}

	/**
	 * Determine whether the user can restore the permission.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can restore the permission, false otherwise.
	 */
	public function restore( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		/**
		 * Filters the capability used to determine whether a user can restore permissions.
		 *
		 * @since 1.0.0
		 *
		 * @hook permissions.restore
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'permissions.restore', 'permissions.restore' ) );
	}

	/**
	 * Determine whether the user can permanently delete the permission.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the user to check permissions for.
	 *
	 * @return bool True if the user can force delete the permission, false otherwise.
	 */
	public function forceDelete( string|int $id ): bool
	{
		$userModel = config( 'cms-framework.user_model' );
		$user      = $userModel::findOrFail( $id );

		/**
		 * Filters the capability used to determine whether a user can permanently delete permissions.
		 *
		 * @since 1.0.0
		 *
		 * @hook permissions.forceDelete
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'permissions.forceDelete', 'permissions.forceDelete' ) );
	}
}