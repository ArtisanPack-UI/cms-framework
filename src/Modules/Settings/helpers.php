<?php

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;

if ( ! function_exists( 'apGetSetting' ) ) {
	function apGetSetting( string $key, mixed $default = null ): mixed
	{
		return app( SettingsManager::class )->getSetting( $key, $default );
	}
}

if ( ! function_exists( 'apRegisterSetting' ) ) {
	function apRegisterSetting( string $key, mixed $defaultValue, callable $callback, string $type = 'string' ): void
	{
		app( SettingsManager::class )->registerSetting( $key, $defaultValue, $callback, $type );
	}
}

if ( ! function_exists( 'apUpdateSetting' ) ) {
	function apUpdateSetting( string $key, mixed $value ): void
	{
		app( SettingsManager::class )->updateSetting( $key, $value );
	}
}