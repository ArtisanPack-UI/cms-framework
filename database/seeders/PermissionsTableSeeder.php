<?php

declare( strict_types=1 );

/**
 * Seeder for default permissions.
 *
 * This seeder creates the default permissions for the CMS and associates them with roles.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\Database\Seeders;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCapability;
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
     * settings, system administration, and the self-updater.
     *
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

            // Self-updater permissions. The slugs are the `UpdateCapability`
            // ability names verbatim, because rbac's `Gate::before` matches an
            // ability against permission names and slugs — seeding these is
            // what makes `Gate::authorize( UpdateCapability::PERFORM )` resolve
            // through RBAC rather than falling through to the framework's
            // deny-by-default definition.
            [
                'name'        => 'Perform Application Updates',
                'slug'        => UpdateCapability::PERFORM,
                'description' => 'Download and install application updates',
            ],
            [
                'name'        => 'Roll Back Application Updates',
                'slug'        => UpdateCapability::ROLLBACK,
                'description' => 'Restore the application from a pre-update backup',
            ],
            [
                'name'        => 'View Application Updates',
                'slug'        => UpdateCapability::VIEW,
                'description' => 'View available application updates and update status',
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
     *
     * @since 1.0.0
     */
    protected function assignPermissionsToRoles(): void
    {
        // Use syncWithoutDetaching everywhere so re-running this opt-in
        // seeder against an environment that's already added consumer
        // permissions doesn't strip them off the framework's default
        // roles. The seeder is now non-destructive: it adds the default
        // assignments without removing anything an operator added.

        // Admin gets all permissions.
        $admin = Role::where( 'slug', 'admin' )->first();
        if ( $admin ) {
            $admin->permissions()->syncWithoutDetaching(
                Permission::pluck( 'id' )->all(),
            );
        }

        // Editor gets content and access permissions.
        $editor = Role::where( 'slug', 'editor' )->first();
        if ( $editor ) {
            $editor->permissions()->syncWithoutDetaching(
                Permission::whereIn( 'slug', [
                    'manage-content',
                    'publish-content',
                    'access-admin',
                ] )->pluck( 'id' )->all(),
            );
        }

        // User gets only access permission.
        $user = Role::where( 'slug', 'user' )->first();
        if ( $user ) {
            $user->permissions()->syncWithoutDetaching(
                Permission::where( 'slug', 'access-admin' )->pluck( 'id' )->all(),
            );
        }
    }
}
