<?php

declare( strict_types=1 );

/**
 * Base test case for the CMS Framework.
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Tests;

use ArtisanPackUI\Ai\AiServiceProvider;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;
use ArtisanPackUI\CMSFramework\Tests\Support\Composer\RecordingWebOpcacheResetter;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use ArtisanPackUI\Hooks\Providers\HooksServiceProvider;
use ArtisanPackUI\Rbac\RbacServiceProvider;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\ParallelTesting;
use Laravel\Sanctum\SanctumServiceProvider;

/**
 * Provides the base application for all package tests.
 *
 * @since 1.0.0
 */
class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Plugin and theme installs, activations and updates reset PHP-FPM's
        // OPcache from the CLI (#438, #450, #453). Never reach for a real FPM
        // socket or the loopback route from a test.
        $this->app->instance( WebOpcacheResetter::class, new RecordingWebOpcacheResetter );

        // Load the package's migrations (includes users, roles, permissions, settings, etc.)
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        // Sanctum ships its own personal-access-tokens table; load it so
        // `auth:sanctum` HTTP tests can mint bearer tokens. Resolve the path
        // defensively so a missing vendor install doesn't crash bootstrap.
        $sanctumMigrations = realpath( __DIR__ . '/../vendor/laravel/sanctum/database/migrations' );

        if ( false !== $sanctumMigrations ) {
            $this->loadMigrationsFrom( $sanctumMigrations );
        }
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders( $app ): array
    {
        return [
            SecurityServiceProvider::class,
            ScrambleServiceProvider::class,
            RbacServiceProvider::class,
            SanctumServiceProvider::class,
            CMSFrameworkServiceProvider::class,
            HooksServiceProvider::class,
            AiServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp( $app ): void
    {
        // 1. Set the configurable user model and enable OpenAPI for testing.
        $app['config']->set( 'artisanpack.cms-framework.user_model', TestUser::class );
        $app['config']->set( 'artisanpack.cms-framework.openapi.enabled', true );
        $app['config']->set( 'auth.providers.users.model', TestUser::class );

        // 2. Set up database configuration
        $app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );
        $app['config']->set( 'database.default', 'testing' );
        $app['config']->set( 'database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ] );

        // 3. Under `pest --parallel`, give each process its own plugins and
        //    themes directories. They live under Testbench's shared skeleton,
        //    so otherwise one process's cleanup deletes the fixtures another
        //    is using (#458). Tests build paths with testPluginsDirectory()
        //    and testThemesDirectory().
        $token = ParallelTesting::token();

        if ( is_string( $token ) && '' !== $token ) {
            $app['config']->set( 'cms.plugins.directory', "plugins-test-{$token}" );
            $app['config']->set( 'cms.themes.directory', "themes-test-{$token}" );
        }
    }
}
