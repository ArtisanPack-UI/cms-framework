<?php
/**
 * Class SettingsManager
 *
 * Manages CRUD operations and event filters for application settings.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Settings
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Settings;

use ArtisanPackUI\CMSFramework\Models\Setting;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Class SettingsManager
 *
 * The SettingsManager class provides functionality to manage application settings, including
 * registering, adding, updating, retrieving, and deleting settings.
 *
 * @since 1.0.0
 */
class SettingsManager
{
	/**
	 * Registers a setting with the application.
	 *
	 * Registers a setting with the application. The setting will be added to the database if it does not already exist.
	 * The callback function will be used to retrieve the setting's value.
	 *
	 * @since  1.0.0
	 *
	 * @link   https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param string   $name     The name of the setting.
	 * @param string   $value    The default value of the setting.
	 * @param callable $callback The callback function to use when retrieving the setting.
	 * @param string   $category The category of the setting.
	 */
	public function registerSetting( string $name, string $value, callable $callback, string $category = '' ): void
	{
		if ( !Setting::where( 'name', $name )->exists() ) {
			$this->addSetting( $name, $value, $category );
		}

		Eventy::addFilter( 'ap.cms.settings.settingsList', function ( array $settings ) use ( $name, $callback ) {
			$settings[ $name ] = $callback;
			return $settings;
		} );

	}

	/**
	 * Adds a setting to the database.
	 *
	 * @since   1.0.0
	 *
	 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param string $name     The name of the setting.
	 * @param string $value    The default value of the setting.
	 * @param string $category The category of the setting.
	 */
	public function addSetting( string $name, string $value, string $category = '' ): void
	{
		Setting::create( [
			'name'     => $name,
			'value'    => $value,
			'category' => $category,
		] );
	}

	/**
	 * Retrieves the value of a specified setting.
	 *
	 * Fetches the value of the specified setting from the database. If the setting is not found, the provided default
	 * value is returned.
	 *
	 * @since 1.0.0
	 *
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param string $setting The name of the setting to retrieve.
	 * @param string $default The default value to return if the setting is not found.
	 * @return string The value of the setting, or the default value if the setting does not exist.
	 */
	public function getSetting( string $setting, string $default = '' ): string
	{
		$setting = Setting::where( 'name', $setting )->first();
		if ( $setting ) {
			return $setting->value;
		}
		return $default;
	}

	/**
	 * Retrieves settings based on the provided arguments or returns all settings if no arguments are specified.
	 *
	 * @since 1.0.0
	 *
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param array $args Options for filtering settings. Recognized key:
	 *                    - 'category' (string): Filters settings by category.
	 * @return array The retrieved settings as an array.
	 */
	public function getSettings( array $args = [] ): array
	{
		if ( !empty( $args['category'] ) ) {
			return Setting::where( 'category', $args['category'] )->get()->toArray();
		}
		return Setting::all()->toArray();
	}

	/**
	 * Updates a specific setting with a new value.
	 *
	 * @since 1.0.0
	 *
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param string $setting The name of the setting to update.
	 * @param string $value   The new value to assign to the setting.
	 * @return Setting|bool The updated setting on success, or false if the setting does not exist.
	 */
	public function updateSetting( string $setting, string $value ): Setting|bool
	{
		$settings = Eventy::filter( 'ap.cms.settings.settingsList', [] );
		if ( !isset( $settings[ $setting ] ) ) {
			return false;
		}
		$updatedValue = call_user_func_array( $settings[ $setting ], [ $value ] );
		return Setting::where( 'name', $setting )->update( [ 'value' => $updatedValue ] );
	}

	/**
	 * Deletes a specific setting based on its name.
	 *
	 * @since 1.0.0
	 *
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @param string $setting The name of the setting to delete.
	 * @return bool|int False if the setting does not exist, or the number of rows affected by the delete operation.
	 */
	public function deleteSetting( string $setting ): bool|int
	{
		if ( !Setting::where( 'name', $setting )->exists() ) {
			return false;
		}

		return Setting::destroy( Setting::where( 'name', $setting )->first()->id );
	}
}