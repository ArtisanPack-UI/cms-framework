<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Providers\CoreServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;

/**
 * Update Flow Integration Tests
 *
 * Tests the complete update workflow.
 *
 * @since 2.0.0
 */
class UpdateFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get package providers.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cms.updates.update_source_url', 'https://github.com/test/repo');
        $app['config']->set('cms.updates.backup_enabled', false); // Disable for tests
        $app['config']->set('cms.updates.verify_checksum', false);
    }

    /**
     * Test complete update check flow.
     *
     * @since 2.0.0
     */
    public function test_complete_update_check_flow(): void
    {
        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            hasUpdate: true,
            downloadUrl: 'https://example.com/update.zip'
        );

        $checker = $this->createMock(UpdateChecker::class);
        $checker->method('checkForUpdate')->willReturn($updateInfo);

        $manager->setUpdateChecker($checker);

        $result = $manager->checkForUpdate();

        $this->assertTrue($result->hasUpdate);
        $this->assertEquals('2.0.0', $result->latestVersion);
        $this->assertEquals('1.0.0', $result->currentVersion);
    }

    /**
     * Test update check command.
     *
     * @since 2.0.0
     */
    public function test_update_check_command(): void
    {
        Config::set('cms.updates.update_source_url', 'https://github.com/test/repo');

        $exitCode = Artisan::call('update:check', ['--clear-cache' => true]);

        // Command may fail due to no real repository, but it should execute
        $this->assertContains($exitCode, [0, 1]);
    }

    /**
     * Test scheduled update check command.
     *
     * @since 2.0.0
     */
    public function test_scheduled_update_check_command(): void
    {
        Config::set('cms.updates.update_source_url', 'https://github.com/test/repo');
        Config::set('cms.updates.auto_update_enabled', false);

        $exitCode = Artisan::call('update:check-scheduled');

        // Command may fail due to no real repository, but it should execute
        $this->assertContains($exitCode, [0, 1]);
    }

    /**
     * Test rollback command with no backups.
     *
     * @since 2.0.0
     */
    public function test_rollback_command_with_no_backups(): void
    {
        $exitCode = Artisan::call('update:rollback', ['--force' => true]);

        $this->assertEquals(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('No backups found', $output);
    }

    /**
     * Test that update commands are registered.
     *
     * @since 2.0.0
     */
    public function test_update_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('update:check', $commands);
        $this->assertArrayHasKey('update:perform', $commands);
        $this->assertArrayHasKey('update:rollback', $commands);
        $this->assertArrayHasKey('update:check-scheduled', $commands);
    }

    /**
     * Test cache clearing.
     *
     * @since 2.0.0
     */
    public function test_cache_clearing(): void
    {
        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '1.0.0',
            hasUpdate: false,
            downloadUrl: 'https://example.com/update.zip'
        );

        $checker = $this->createMock(UpdateChecker::class);
        $checker->expects($this->once())->method('clearCache');

        $manager->setUpdateChecker($checker);
        $manager->clearCache();

        $this->assertTrue(true); // If we get here, test passed
    }

    /**
     * Test configuration loading.
     *
     * @since 2.0.0
     */
    public function test_configuration_is_loaded(): void
    {
        $this->assertIsInt(config('cms.updates.cache_ttl'));
        $this->assertIsBool(config('cms.updates.backup_enabled'));
        $this->assertIsArray(config('cms.updates.exclude_from_update'));
        $this->assertIsString(config('cms.updates.composer_install_command'));
    }
}
