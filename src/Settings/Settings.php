<?php
/**
 * Represents the settings module for the ArtisanPackUI CMS Framework.
 *
 * This class handles registration, retrieval, updating, and deletion of settings.
 * Additionally, it adds custom migration directories for settings in the system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Settings\Settings
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Settings;

use ArtisanPackUI\CMSFramework\Settings\Models\Setting;
use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Represents the settings module for the ArtisanPackUI CMS Framework.
 *
 * Implements the behavior and functionality required for managing settings
 * within the application. This includes functionality for registering,
 * retrieving, updating, and deleting settings, as well as managing related
 * migrations.
 *
 * @since 1.0.0
 */
class Settings implements Module
{

	/**
	 * Returns the slug for the module.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @return string The slug for the module.
	 */
	public function getSlug(): string
	{
		return 'settings';
	}

	/**
	 * Returns an array of functions to be registered with the CMSFramework.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @return array List of functions to register.
	 */
	public function functions(): array
	{
		return [
			'registerSetting' => [ $this, 'registerSetting' ],
			'getSetting'      => [ $this, 'getSetting' ],
			'updateSetting'   => [ $this, 'updateSetting' ],
			'getSettings'     => [ $this, 'getSettings' ],
			'deleteSetting'   => [ $this, 'deleteSetting' ],
			'addSetting'      => [ $this, 'addSetting' ],
		];
	}

	/**
	 * Initializes the module.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 */
	public function init(): void
	{
		Eventy::addFilter( 'ap.migrations.directories', [ $this, 'settingsMigrations' ] );
	}

	/**
	 * Registers a setting with the CMSFramework.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param string   $name     The name of the setting.
	 * @param string   $value    The value of the setting.
	 * @param callable $callback The callback to use for the setting.
	 */
	public function registerSetting( string $name, string $value, callable $callback ): void
	{
		if ( !Setting::where( 'key', $name )->exists() ) {
			$this->addSetting( $name, $value );
		}

		Eventy::addFilter( 'ap.settings.settings', function ( array $settings ) use ( $name, $callback ) {
			$settings[ $name ] = $callback( $settings );
			return $settings;
		} );
	}

	/**
	 * Adds a setting to the database.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param string $setting The name of the setting to add.
	 * @param string $value   The value of the setting to add.
	 */
	public function addSetting( string $setting, string $value ): void
	{
		Setting::create( [
			'key'   => $setting,
			'value' => $value,
		] );
	}

	public function getSettings( array $args = [] )
	{

	}

	/**
	 * Retrieves a setting from the database.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param string $setting The name of the setting to retrieve.
	 * @param string $default The default value to return if the setting is not found.
	 * @return string The value of the setting, or the default value if not found.
	 */
	public function getSetting( string $setting, string $default = '' ): string
	{
		$setting = Setting::where( 'key', $setting )->first();
		if ( $setting ) {
			return $setting->value;
		}
		return $default;
	}

	public function updateSetting( string $setting, string $value )
	{

	}

	public function deleteSetting( string $setting )
	{

	}

	/**
	 * Adds custom migration directories for settings.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param array $directories The array of migration directories.
	 * @return array The array of migration directories.
	 */
	public function settingsMigrations( array $directories ): array
	{
		$directories[] = __DIR__ . '/Migrations';
		return $directories;
	}
}
