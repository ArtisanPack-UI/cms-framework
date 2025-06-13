<?php
/**
 * Role Seeder
 *
 * Populates the roles table with default roles and their capabilities.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Database\Seeders
 * @since      1.0.0
 */

namespace ArtisanPackUI\Database\seeders;

use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Class for seeding default roles.
 *
 * This seeder is responsible for creating initial roles and assigning their
 * predefined capabilities in the application's database.
 *
 * @since 1.0.0
 */
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @since 1.0.0
     * @return void
     */
    public function run(): void
    {
        $roles = [
            'author'        => [
                'name'         => 'Author',
                'slug'         => 'author',
                'description'  => 'Users who can publish and manage their own posts.',
                'capabilities' => [
                    'delete_posts',
                    'delete_published_posts',
                    'edit_posts',
                    'edit_published_posts',
                    'publish_posts',
                    'read',
                    'upload_files',
                ],
            ],
            'editor'        => [
                'name'         => 'Editor',
                'slug'         => 'editor',
                'description'  => 'Users who can publish and manage posts including those of other users, and manage categories, links, and comments.',
                'capabilities' => [
                    'delete_others_pages',
                    'delete_others_posts',
                    'delete_pages',
                    'delete_posts',
                    'delete_private_pages',
                    'delete_private_posts',
                    'delete_published_pages',
                    'delete_published_posts',
                    'edit_others_pages',
                    'edit_others_posts',
                    'edit_pages',
                    'edit_posts',
                    'edit_private_pages',
                    'edit_private_posts',
                    'edit_published_pages',
                    'edit_published_posts',
                    'manage_categories',
                    'manage_links',
                    'moderate_comments',
                    'publish_pages',
                    'publish_posts',
                    'read',
                    'read_private_pages',
                    'read_private_posts',
                    'unfiltered_html',
                    'upload_files',
                ],
            ],
            'administrator' => [
                'name'         => 'Administrator',
                'slug'         => 'administrator',
                'description'  => 'Users who can do everything on the website.',
                'capabilities' => [
                    'activate_plugins',
                    'delete_others_pages',
                    'delete_others_posts',
                    'delete_pages',
                    'delete_posts',
                    'delete_private_pages',
                    'delete_private_posts',
                    'delete_published_pages',
                    'delete_published_posts',
                    'edit_dashboard',
                    'edit_others_pages',
                    'edit_others_posts',
                    'edit_pages',
                    'edit_posts',
                    'edit_private_pages',
                    'edit_private_posts',
                    'edit_published_pages',
                    'edit_published_posts',
                    'edit_theme_options',
                    'export',
                    'import',
                    'list_users',
                    'manage_categories',
                    'manage_links',
                    'manage_options',
                    'moderate_comments',
                    'promote_users',
                    'publish_pages',
                    'publish_posts',
                    'read_private_pages',
                    'read_private_posts',
                    'read',
                    'remove_users',
                    'switch_themes',
                    'upload_files',
                    'customize',
                    'delete_site',
                    'update_core',
                    'update_plugins',
                    'update_themes',
                    'install_plugins',
                    'install_themes',
                    'delete_themes',
                    'delete_plugins',
                    'edit_plugins',
                    'edit_themes',
                    'edit_files',
                    'edit_users',
                    'add_users',
                    'create_users',
                    'delete_users',
                    'unfiltered_html',
                    'edit_roles',
                    'manage_plugins',
                    'manage_audit_logs',
                ],
            ],
        ];

        foreach ( $roles as $roleData ) {
            Role::firstOrCreate(
                [
                    'slug' => $roleData['slug'],
                ], // Use slug for unique identification
                [
                    'name'         => $roleData['name'],
                    'description'  => $roleData['description'],
                    'capabilities' => serialize( $roleData['capabilities'] ),
                ]
            );
        }
    }
}
