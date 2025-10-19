<?php

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Managers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;

class SettingsManager
{
	public function registerSetting( string $key, mixed $defaultValue, callable $callback, string $type = 'string' ): void
	{
		addFilter( 'ap.settings.registeredSettings', function ( $settings ) use ( $key, $defaultValue, $type, $callback ) {
			$settings[ $key ] = [
				'default'  => $defaultValue,
				'type'     => $type,
				'callback' => $callback,
			];

			return $settings;
		} );
	}

	public function getSetting( string $key, mixed $default = null ): mixed
	{
		$setting = Setting::where( 'key', $key )->first();

		if ( $setting ) {
			return $setting->value;
		}

		$settings          = applyFilters( 'ap.settings.registeredSettings', [] );
		$registeredDefault = $settings[ $key ]['default'] ?? null;

		return $default ?? $registeredDefault;
	}

	public function updateSetting( string $key, mixed $value ): void
	{
		$settings = applyFilters( 'ap.settings.registeredSettings', [] );
		$setting  = $settings[ $key ] ?? null;

		// Sanitize the new value
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