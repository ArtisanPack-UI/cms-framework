<?php

/**
 * Helper Functions for the CMS Framework Users Module.
 *
 * This file contains global helper functions for managing roles and permissions
 * within the CMS framework, providing a convenient API for common operations.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Users
 */

use ArtisanPackUI\CMSFramework\Modules\Users\Managers\RoleManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use TorMorten\Eventy\Facades\Eventy;

if ( ! function_exists( 'ap_register_role' ) ) {
	/**
	 * Register a new role in the system.
	 *
	 * Creates a new role with the given slug and name, or returns an existing
	 * role if one with the same slug already exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The unique slug identifier for the role.
	 * @param string $name The human-readable name for the role.
	 *
	 * @return Role The created or existing role instance.
	 */
	function ap_register_role( string $slug, string $name ): Role
	{
		return app( RoleManager::class )->register( $slug, $name );
	}
}

if ( ! function_exists( 'ap_register_permission' ) ) {
	/**
	 * Register a new permission in the system.
	 *
	 * Creates a new permission with the given slug and name, or returns an existing
	 * permission if one with the same slug already exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The unique slug identifier for the permission.
	 * @param string $name The human-readable name for the permission.
	 *
	 * @return Permission The created or existing permission instance.
	 */
	function ap_register_permission( string $slug, string $name ): Permission
	{
		return app( PermissionManager::class )->register( $slug, $name );
	}
}

if ( ! function_exists( 'ap_add_permission_to_role' ) ) {
	/**
	 * Add a permission to an existing role.
	 *
	 * Attaches a permission to a role without removing any existing permissions.
	 * Both the role and permission must exist in the system.
	 *
	 * @since 1.0.0
	 *
	 * @param string $roleSlug       The slug of the role to add permission to.
	 * @param string $permissionSlug The slug of the permission to add.
	 *
	 * @return void
	 */
	function ap_add_permission_to_role( string $roleSlug, string $permissionSlug ): void
	{
		app( RoleManager::class )->addPermissionToRole( $roleSlug, $permissionSlug );
	}
}

if ( ! function_exists( 'apRegisterUserSettingsSection' ) ) {
	/**
	 * Register a new section (tab) on the User Edit page.
	 *
	 * Adds a keyed section with a label and order that consumers can use to
	 * render additional settings on user profile screens.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key   A unique machine-readable key (e.g., 'business_hours').
	 * @param string $label The human-readable label for the tab (e.g., 'Business Hours').
	 * @param int    $order The display order for the tab.
	 *
	 * @return void
	 */
	function apRegisterUserSettingsSection( string $key, string $label, int $order = 50 ): void
	{
		/**
		 * Filters the available sections on the User Settings page.
		 *
		 * Allows plugins and modules to add, remove, or reorder sections (tabs)
		 * that appear on user profile edit screens.
		 *
		 * @since 2.0.0
		 *
		 * @hook ap.users.settings.sections
		 *
		 * @param array<string,array{label:string,order:int}> $sections Associative array of section definitions keyed by section key.
		 * @return array<string,array{label:string,order:int}> Modified sections array.
		 */
		addFilter( 'ap.users.settings.sections', function ( array $sections ) use ( $key, $label, $order ) {
			$sections[ $key ] = compact( 'label', 'order' );
			return $sections;
		} );
	}
}