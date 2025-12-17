<?php

/**
 * Base test case for the CMS Framework.
 *
 * @since      2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Tests;

use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use ArtisanPackUI\Hooks\Providers\HooksServiceProvider;
use Illuminate\Foundation\Application;

/**
 * Provides the base application for all package tests.
 *
 * @since 2.0.0
 */
class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load the package's migrations (includes users, roles, permissions, settings, etc.)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CMSFrameworkServiceProvider::class,
            HooksServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // 1. Set the configurable user model to our test user model.
        $app['config']->set('cms-framework.user_model', TestUser::class);
        $app['config']->set('auth.providers.users.model', TestUser::class);

        // 2. Set up database configuration
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
