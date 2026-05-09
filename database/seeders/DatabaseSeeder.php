<?php

declare(strict_types=1);

/**
 * Main database seeder for the CMS Framework.
 *
 * This seeder orchestrates all other seeders to populate the database with default data.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Main database seeder class.
 *
 * @since 1.0.0
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds the database in the following order:
     * 1. Roles
     * 2. Permissions (with role assignments)
     * 3. Settings
     *
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        // SettingsTableSeeder is required to seed the framework's
        // default site settings. RolesTableSeeder + PermissionsTableSeeder
        // are now **opt-in** — consumers that want the package's
        // default role / permission set can call them explicitly:
        //
        //     $seeder->call(\ArtisanPackUI\Database\Seeders\RolesTableSeeder::class);
        //     $seeder->call(\ArtisanPackUI\Database\Seeders\PermissionsTableSeeder::class);
        //
        // Most consumers (Keystone, application repos) maintain their
        // own role / permission inventories and don't want
        // cms-framework's defaults forced on them.
        $this->call(SettingsTableSeeder::class);
    }
}
