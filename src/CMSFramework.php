<?php

namespace ArtisanPackUI\CMSFramework;

use ArtisanPackUI\CMSFramework\Settings\Settings;
use ArtisanPackUI\CMSFramework\Util\Functions;
use ArtisanPackUI\CMSFramework\Util\Interfaces\AdminModule;
use ArtisanPackUI\CMSFramework\Util\Interfaces\AuthModule;
use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;
use ArtisanPackUI\CMSFramework\Util\Interfaces\PublicModule;

class CMSFramework
{
	protected array $modules = [];

	protected array $adminModules = [];

    protected array $publicModules = [];

    protected array $authModules = [];

    protected Functions $functions;

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

            if ( $module instanceof PublicModule ) {
                $this->publicModules[ $module->getSlug() ] = $module;
            }

            if ( $module instanceof AuthModule ) {
                $this->authModules[ $module->getSlug() ] = $module;
            }
		}

        $this->functions = new Functions( $modules );

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
			function ( Module $module ) {
				$module->init();
			}
		);
	}

    public function adminInit(): void
    {
        array_walk(
            $this->adminModules,
            function ( AdminModule $module ) {
                $module->adminInit();
            }
        );
    }

    public function publicInit(): void {
        array_walk(
            $this->publicModules,
            function ( PublicModule $module ) {
                $module->publicInit();
            }
        );
    }

    public function authInit(): void {
        array_walk(
            $this->authModules,
            function ( AuthModule $module ) {
                $module->authInit();
            }
        );
    }

    public function functions(): Functions
    {
        return $this->functions;
    }
}
