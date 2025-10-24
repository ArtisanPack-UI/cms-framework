<?php
/**
 * Settings Manager
 *
 * Provides a simple API for registering, retrieving, and updating application settings
 * with sanitization callbacks and storage in the database. Exposes a filter hook to
 * allow third-parties to register settings.
 *
 * @since   2.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Settings\Managers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Managers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Manages registration, retrieval, and updates of settings.
 *
 * @since 2.0.0
 */
class SettingsManager
{
	/**
	 * Registers a setting definition via a filter so it can be discovered globally.
	 *
	 * The setting is exposed through the `ap.settings.registeredSettings` filter. Consumers
	 * should pass a sanitization callback which will be used when updating the setting value.
	 *
	 * @since 2.0.0
	 *
	 * @param string   $key          Unique key for the setting.
	 * @param mixed    $defaultValue Default value returned when no stored value exists.
	 * @param callable $callback     Sanitization callback used to clean values on update.
	 * @param string   $type         Data type of the setting (e.g., 'string', 'boolean', 'integer').
	 *
	 * @return void
	 */
	public function registerSetting( string $key, mixed $defaultValue, callable $callback, string $type = 'string' ): void
	{
		/**
		 * Filters the array of registered settings to add or modify items.
		 *
		 * This filter is used to register settings across the application. Each callback
		 * should return the augmented `$settings` array including its setting definition.
		 *
		 * @since 2.0.0
		 *
		 * @hook ap.settings.registeredSettings
		 *
		 * @param array $settings Associative array of registered settings keyed by setting key. Each item
		 *                        contains: 'default' (mixed), 'type' (string), and 'callback' (callable).
		 * @return array Filtered settings array.
		 */
		addFilter( 'ap.settings.registeredSettings', function ( $settings ) use ( $key, $defaultValue, $type, $callback ) {
			$settings[ $key ] = [
				'default'  => $defaultValue,
				'type'     => $type,
				'callback' => $callback,
			];

			return $settings;
		} );
	}

	/**
	 * Retrieves a setting value, falling back to a default when not stored.
	 *
	 * Checks the database first and if no record exists, falls back to the
	 * registered default value from the `ap.settings.registeredSettings` filter
	 * or the provided `$default` parameter.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key     Unique key for the setting.
	 * @param mixed  $default Optional default value to use if no stored or registered default exists.
	 *
	 * @return mixed The setting value.
	 */
	public function getSetting( string $key, mixed $default = null ): mixed
	{
		if ( Schema::hasTable( 'settings' ) ) {
			$setting = Setting::where( 'key', $key )->first();

			if ( $setting ) {
				return $setting->value;
			}
		}

		/**
		 * Filters the array of registered settings to allow discovery of defaults.
		 *
		 * @since 2.0.0
		 *
		 * @hook ap.settings.registeredSettings
		 *
		 * @param array $settings Associative array of registered settings keyed by setting key.
		 * @return array Filtered settings array.
		 */
		$settings          = applyFilters( 'ap.settings.registeredSettings', [] );
		$registeredDefault = $settings[ $key ]['default'] ?? null;

		return $default ?? $registeredDefault;
	}

	/**
	 * Updates a setting value after sanitizing it using the registered callback.
	 *
	 * Creates or updates the record in the database and stores the sanitized value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key   Unique key for the setting.
	 * @param mixed  $value New value to store for the setting (will be sanitized first).
	 *
	 * @return void
	 */
	public function updateSetting( string $key, mixed $value ): void
	{
		/**
		 * Filters the array of registered settings to retrieve type and sanitizer.
		 *
		 * @since 2.0.0
		 *
		 * @param array $settings Associative array of registered settings keyed by setting key.
		 * @return array Filtered settings array.
		 */
		$settings = applyFilters( 'ap.settings.registeredSettings', [] );
		$setting  = $settings[ $key ] ?? null;

		// Sanitize the new value using the registered callback.
		$value = $setting['callback']( $value );

		$currentSetting = Setting::where( 'key', $key )->first();

		if ( $currentSetting ) {
			$currentSetting->value = $value;
			$currentSetting->save();
		} else {
			Setting::create( [
				'key'   => $key,
				'value' => $value,
				'type'  => $setting['type'],
			] );
		}
	}
}