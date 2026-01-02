<?php

declare( strict_types = 1 );

/**
 * Seeder for default settings.
 *
 * This seeder creates the default settings for the CMS.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\Database\Seeders;

use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds default settings into the database.
 *
 * @since 1.0.0
 */
class SettingsTableSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * Creates default settings for site configuration, including:
	 * - Site name and tagline
	 * - Email and timezone settings
	 * - Pagination and upload limits
	 * - Maintenance mode settings
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function run(): void
	{
		$settings = [
			// General settings
			[
				'key'         => 'site_name',
				'value'       => 'ArtisanPack CMS',
				'description' => 'The name of the website',
				'type'        => 'string',
			],
			[
				'key'         => 'site_tagline',
				'value'       => 'A modern Laravel CMS',
				'description' => 'A brief description of the website',
				'type'        => 'string',
			],
			[
				'key'         => 'admin_email',
				'value'       => 'admin@example.com',
				'description' => 'The administrator email address',
				'type'        => 'string',
			],
			[
				'key'         => 'timezone',
				'value'       => 'UTC',
				'description' => 'The default timezone for the site',
				'type'        => 'string',
			],
			[
				'key'         => 'date_format',
				'value'       => 'Y-m-d',
				'description' => 'The date format for displaying dates',
				'type'        => 'string',
			],
			[
				'key'         => 'time_format',
				'value'       => 'H:i:s',
				'description' => 'The time format for displaying times',
				'type'        => 'string',
			],

			// Content settings
			[
				'key'         => 'posts_per_page',
				'value'       => '10',
				'description' => 'Number of posts to show per page',
				'type'        => 'integer',
			],
			[
				'key'         => 'default_post_status',
				'value'       => 'draft',
				'description' => 'Default status for new posts',
				'type'        => 'string',
			],

			// Media settings
			[
				'key'         => 'max_upload_size',
				'value'       => '10240',
				'description' => 'Maximum upload file size in KB',
				'type'        => 'integer',
			],
			[
				'key'         => 'allowed_file_types',
				'value'       => 'jpg,jpeg,png,gif,pdf,doc,docx',
				'description' => 'Comma-separated list of allowed file extensions',
				'type'        => 'string',
			],

			// System settings
			[
				'key'         => 'maintenance_mode',
				'value'       => '0',
				'description' => 'Enable or disable maintenance mode',
				'type'        => 'boolean',
			],
			[
				'key'         => 'maintenance_message',
				'value'       => 'The site is currently undergoing maintenance. Please check back soon.',
				'description' => 'Message to display when in maintenance mode',
				'type'        => 'string',
			],
			[
				'key'         => 'enable_registration',
				'value'       => '1',
				'description' => 'Allow new user registrations',
				'type'        => 'boolean',
			],
			[
				'key'         => 'default_user_role',
				'value'       => 'user',
				'description' => 'Default role for new user registrations',
				'type'        => 'string',
			],
		];

		foreach ( $settings as $setting ) {
			Setting::firstOrCreate(
				['key' => $setting['key']],
				$setting,
			);
		}
	}
}
