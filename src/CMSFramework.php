<?php

namespace ArtisanPackUI\CMSFramework;

use ArtisanPackUI\CMSFramework\Settings\Settings;
use ArtisanPackUI\CMSFramework\Util\Interfaces\AdminModule;
use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;

class CMSFramework
{
	protected array $modules = [];

	protected array $adminModules = [];

	public function __construct()
	{
		$modules = $this->getModules();

		foreach ( $modules as $module ) {
			if ( $module instanceof Module ) {
				$this->modules[ $module->getSlug() ] = $module;
			}

			if ( $module instanceof AdminModule ) {
				$this->adminModules[ $module->getSlug() ] = $module;
			}
		}

		$this->init();
	}

	public function getModules(): array
	{
		return [
			new Settings(),
		];
	}

	public function init(): void
	{
		array_walk(
			$this->modules,
			function ( Module $component ) {
				$component->init();
			}
		);
	}
}
