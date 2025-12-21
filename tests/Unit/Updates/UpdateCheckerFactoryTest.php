<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCheckerFactory;
use Orchestra\Testbench\TestCase;

/**
 * Update Checker Factory Tests
 *
 * @since 2.0.0
 */
class UpdateCheckerFactoryTest extends TestCase
{
    /**
     * Test factory detects GitHub source.
     *
     * @since 2.0.0
     */
    public function test_detects_github_source(): void
    {
        $checker = UpdateCheckerFactory::buildUpdateChecker(
            'https://github.com/username/repo',
            'application',
            'test-app',
            '1.0.0',
        );

        $this->assertInstanceOf( UpdateChecker::class, $checker );
        $this->assertEquals( 'GitHub', $checker->getSourceName() );
    }

    /**
     * Test factory detects GitLab source.
     *
     * @since 2.0.0
     */
    public function test_detects_gitlab_source(): void
    {
        $checker = UpdateCheckerFactory::buildUpdateChecker(
            'https://gitlab.com/username/repo',
            'application',
            'test-app',
            '1.0.0',
        );

        $this->assertInstanceOf( UpdateChecker::class, $checker );
        $this->assertEquals( 'GitLab', $checker->getSourceName() );
    }

    /**
     * Test factory falls back to custom JSON source.
     *
     * @since 2.0.0
     */
    public function test_falls_back_to_custom_json_source(): void
    {
        $checker = UpdateCheckerFactory::buildUpdateChecker(
            'https://example.com/updates.json',
            'application',
            'test-app',
            '1.0.0',
        );

        $this->assertInstanceOf( UpdateChecker::class, $checker );
        $this->assertEquals( 'Custom JSON', $checker->getSourceName() );
    }

    /**
     * Test factory auto-detects application version.
     *
     * @since 2.0.0
     */
    public function test_auto_detects_application_version(): void
    {
        $checker = UpdateCheckerFactory::buildUpdateChecker(
            'https://github.com/username/repo',
            'application',
            'test-app',
        );

        $this->assertInstanceOf( UpdateChecker::class, $checker );
    }

    /**
     * Test factory returns correct update type.
     *
     * @since 2.0.0
     */
    public function test_returns_correct_update_type(): void
    {
        $checker = UpdateCheckerFactory::buildUpdateChecker(
            'https://github.com/username/repo',
            'plugin',
            'test-plugin',
            '1.0.0',
        );

        $this->assertEquals( 'plugin', $checker->getType() );
        $this->assertEquals( 'test-plugin', $checker->getSlug() );
    }

    /**
     * Define environment setup.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment( $app ): void
    {
        // Set app version for testing
        $app['config']->set( 'app.version', '1.0.0' );
    }
}
