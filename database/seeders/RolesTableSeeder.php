<?php

declare( strict_types = 1 );

/**
 * Seeder for default user roles.
 *
 * This seeder creates the default roles for the CMS: Admin, Editor, and User.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\Database\Seeders;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds default user roles into the database.
 *
 * @since 1.0.0
 */
class RolesTableSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * Creates three default roles:
	 * - Admin: Full system access
	 * - Editor: Content management access
	 * - User: Basic user access
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function run(): void
	{
		$roles = [
			[
				'name'        => 'Admin',
				'slug'        => 'admin',
				'description' => 'Full system access with all permissions',
			],
			[
				'name'        => 'Editor',
				'slug'        => 'editor',
				'description' => 'Content management access for creating and editing content',
			],
			[
				'name'        => 'User',
				'slug'        => 'user',
				'description' => 'Basic user access with limited permissions',
			],
		];

		foreach ( $roles as $role ) {
			Role::firstOrCreate(
				['slug' => $role['slug']],
				$role,
			);
		}
	}
}
