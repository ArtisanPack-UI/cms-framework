<?php

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;

if ( ! function_exists( 'apGetSetting' ) ) {
	function apGetSetting( string $key, mixed $default = null ): mixed
	{
		app( SettingsManager::class )->getSetting( $key, $default );
	}
}

if ( ! function_exists( 'apRegisterSetting' ) ) {
	function apRegisterSetting( string $key, mixed $defaulValue, string $type = 'string', callable $callback ): void
	{
		app( SettingsManager::class )->registerSetting( $key, $defaulValue, $type, $callback );
	}
}

if ( ! function_exists( 'apUpdateSetting' ) ) {
	function apUpdateSetting( string $key, mixed $value ): void
	{
		app( SettingsManager::class )->updateSetting( $key, $value );
	}
}