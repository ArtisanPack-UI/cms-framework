<?php
/**
 * Base test case for the CMS Framework.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Tests
 */

namespace ArtisanPackUI\CMSFramework\Tests;

use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use TorMorten\Eventy\EventServiceProvider;

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

        // 1. Load the package's migrations for roles and permissions.
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        // 2. Load the temporary 'users' table migration for testing.
        include_once __DIR__ . '/Support/Migrations/2025_01_01_000000_create_users_table.php';
        ( new \CreateUsersTable() )->up();
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders( $app ): array
    {
        return [
            CMSFrameworkServiceProvider::class,
            EventServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp( $app ): void
    {
        // 1. Set the configurable user model to our test user model.
        $app['config']->set( 'cms-framework.user_model', TestUser::class );

        // 2. Set up database configuration
        $app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );
        $app['config']->set( 'database.default', 'testing' );
        $app['config']->set( 'database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ] );
    }
}
