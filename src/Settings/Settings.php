<?php

namespace ArtisanPackUI\CMSFramework\Settings;

use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;

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
		// TODO: Implement init() method.
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
}