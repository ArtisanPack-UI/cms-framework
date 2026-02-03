<?php

declare( strict_types = 1 );

/**
 * Seeder for default permissions.
 *
 * This seeder creates the default permissions for the CMS and associates them with roles.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\Database\Seeders;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds default permissions into the database.
 *
 * @since 1.0.0
 */
class PermissionsTableSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * Creates default permissions for content management, user management,
	 * settings, and system administration.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function run(): void
	{
		$permissions = [
			// Content permissions
			[
				'name'        => 'Manage Content',
				'slug'        => 'manage-content',
				'description' => 'Create, edit, and delete content',
			],
			[
				'name'        => 'Publish Content',
				'slug'        => 'publish-content',
				'description' => 'Publish and unpublish content',
			],
			[
				'name'        => 'Delete Content',
				'slug'        => 'delete-content',
				'description' => 'Delete content permanently',
			],

			// User permissions
			[
				'name'        => 'Manage Users',
				'slug'        => 'manage-users',
				'description' => 'Create, edit, and delete users',
			],
			[
				'name'        => 'Manage Roles',
				'slug'        => 'manage-roles',
				'description' => 'Create, edit, and delete roles',
			],
			[
				'name'        => 'Manage Permissions',
				'slug'        => 'manage-permissions',
				'description' => 'Assign permissions to roles',
			],

			// Settings permissions
			[
				'name'        => 'Manage Settings',
				'slug'        => 'manage-settings',
				'description' => 'Modify system settings',
			],

			// Plugin & Theme permissions
			[
				'name'        => 'Manage Plugins',
				'slug'        => 'manage-plugins',
				'description' => 'Install, activate, and configure plugins',
			],
			[
				'name'        => 'Manage Themes',
				'slug'        => 'manage-themes',
				'description' => 'Install and activate themes',
			],

			// System permissions
			[
				'name'        => 'Access Admin',
				'slug'        => 'access-admin',
				'description' => 'Access the admin dashboard',
			],
		];

		foreach ( $permissions as $permission ) {
			Permission::firstOrCreate(
				['slug' => $permission['slug']],
				$permission,
			);
		}

		// Assign permissions to roles
		$this->assignPermissionsToRoles();
	}

	/**
	 * Assign permissions to default roles.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function assignPermissionsToRoles(): void
	{
		// Admin gets all permissions
		$admin = Role::where( 'slug', 'admin' )->first();
		if ( $admin ) {
			$admin->permissions()->sync( Permission::all() );
		}

		// Editor gets content and access permissions
		$editor = Role::where( 'slug', 'editor' )->first();
		if ( $editor ) {
			$editor->permissions()->sync(
				Permission::whereIn( 'slug', [
					'manage-content',
					'publish-content',
					'access-admin',
				] )->get(),
			);
		}

		// User gets only access permission
		$user = Role::where( 'slug', 'user' )->first();
		if ( $user ) {
			$user->permissions()->sync(
				Permission::where( 'slug', 'access-admin' )->get(),
			);
		}
	}
}
