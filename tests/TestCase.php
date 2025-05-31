<?php

namespace Tests;

use ArtisanPackUI\Accessibility\A11yServiceProvider;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use TorMorten\Eventy\EventServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders( $app )
    {
        return [
            CMSFrameworkServiceProvider::class,
            EventServiceProvider::class,
            A11yServiceProvider::class,
            SecurityServiceProvider::class,
        ];
    }
}
