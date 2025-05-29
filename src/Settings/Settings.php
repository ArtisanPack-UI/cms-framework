<?php

namespace ArtisanPackUI\CMSFramework\Settings;

use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;
use TorMorten\Eventy\Facades\Eventy;

class Settings implements Module
{

	public function getSlug(): string
	{
		return 'settings';
	}

	public function functions(): array
	{
		return [];
	}

	public function init(): void
	{
		Eventy::addFilter( 'ap.migrations.directories', [ $this, 'settingsMigrations' ] );
	}

	public function registerSetting( $name, $value, $callback )
	{

	}

	public function getSettings( $args = [] )
	{

	}

	public function getSetting( $setting = '' )
	{

	}

	public function updateSetting( $setting, $value )
	{

	}

	public function deleteSetting( $setting )
	{

	}

	public function settingsMigrations( $directories )
	{
		$directories[] = __DIR__ . '/migrations';
		return $directories;
	}
}