<?php

namespace Tests;

use ArtisanPackUI\Accessibility\A11yServiceProvider;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
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
            SanctumServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Register routes directly to match routes/api.php
        $this->app['router']->middleware('api')->prefix('api/cms')->group(function ($router) {
            $router->apiResource('users', \ArtisanPackUI\CMSFramework\Http\Controllers\UserController::class);
            $router->apiResource('roles', \ArtisanPackUI\CMSFramework\Http\Controllers\RoleController::class);
            $router->apiResource('settings', \ArtisanPackUI\CMSFramework\Http\Controllers\SettingController::class);
        });

        // Configure Sanctum for testing
        $this->app['config']->set('sanctum.stateful', ['testing']);
        $this->app['config']->set('sanctum.middleware.verify_csrf_token', false);
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../vendor/laravel/sanctum/database/migrations');
    }
}
