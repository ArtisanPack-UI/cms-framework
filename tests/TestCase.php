<?php

namespace Tests;

use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
	protected function getPackageProviders( $app )
	{
		return [
			CMSFrameworkServiceProvider::class,
		];
	}
}
