<?php

/**
 * Setting Policy for the CMS Framework Settings Module.
 *
 * This policy handles authorization for setting-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Settings\Policies
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing setting permissions.
 *
 * Provides authorization methods for settings-related operations using
 * the configurable setting model and artisanpack-ui/hooks system for extensibility.
 *
 * @since 1.0.0
 */
class SettingPolicy
{
	/**
	 * Determine whether the user can view any settings.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user to check capabilities for.
	 *
	 * @return bool True if the user can view settings, false otherwise.
	 */
	public function viewAny( Authenticatable $user ): bool
	{
		/**
		 * Filters the capability used to determine whether a user can view any settings.
		 *
		 * @since 1.0.0
		 *
		 * @hook settings.viewAny
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'settings.viewAny', 'settings.manage' ) );
	}

	/**
	 * Determine whether the user can view the setting.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user to check capabilities for.
	 *
	 * @return bool True if the user can view the setting, false otherwise.
	 */
	public function view( Authenticatable $user ): bool
	{
		/**
		 * Filters the capability used to determine whether a user can view a setting.
		 *
		 * @since 1.0.0
		 *
		 * @hook settings.view
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'settings.view', 'settings.manage' ) );
	}

	/**
	 * Determine whether the user can create settings.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user to check capabilities for.
	 *
	 * @return bool True if the user can create settings, false otherwise.
	 */
	public function create( Authenticatable $user ): bool
	{
		/**
		 * Filters the capability used to determine whether a user can create settings.
		 *
		 * @since 1.0.0
		 *
		 * @hook settings.create
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'settings.create', 'settings.manage' ) );
	}

	/**
	 * Determine whether the user can update the setting.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user to check capabilities for.
	 *
	 * @return bool True if the user can update the setting, false otherwise.
	 */
	public function update( Authenticatable $user ): bool
	{
		/**
		 * Filters the capability used to determine whether a user can update settings.
		 *
		 * @since 1.0.0
		 *
		 * @hook settings.update
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'settings.update', 'settings.manage' ) );
	}

	/**
	 * Determine whether the user can delete the setting.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user to check capabilities for.
	 *
	 * @return bool True if the user can delete the setting, false otherwise.
	 */
	public function delete( Authenticatable $user ): bool
	{
		/**
		 * Filters the capability used to determine whether a user can delete settings.
		 *
		 * @since 1.0.0
		 *
		 * @hook settings.delete
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'settings.delete', 'settings.delete' ) );
	}

	/**
	 * Determine whether the user can restore the setting.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user to check capabilities for.
	 *
	 * @return bool True if the user can restore the setting, false otherwise.
	 */
	public function restore( Authenticatable $user ): bool
	{
		/**
		 * Filters the capability used to determine whether a user can restore settings.
		 *
		 * @since 1.0.0
		 *
		 * @hook settings.restore
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'settings.restore', 'settings.manage' ) );
	}

	/**
	 * Determine whether the user can permanently delete the setting.
	 *
	 * @since 1.0.0
	 *
	 * @param Authenticatable $user The authenticated user to check capabilities for.
	 *
	 * @return bool True if the user can force delete the setting, false otherwise.
	 */
	public function forceDelete( Authenticatable $user ): bool
	{
		/**
		 * Filters the capability used to determine whether a user can permanently delete settings.
		 *
		 * @since 1.0.0
		 *
		 * @hook settings.forceDelete
		 *
		 * @param string $capability Default capability slug to check.
		 * @return string Filtered capability slug.
		 */
		return $user->can( applyFilters( 'settings.forceDelete', 'settings.delete' ) );
	}
}