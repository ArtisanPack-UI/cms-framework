<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Orchestra\Testbench\TestCase;

/**
 * Application Update Manager Tests
 *
 * @since 2.0.0
 */
class ApplicationUpdateManagerTest extends TestCase
{
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
        $app['config']->set('cms.updates.backup_enabled', true);
        $app['config']->set('cms.updates.backup_path', 'backups/application');
        $app['config']->set('cms.updates.backup_retention_days', 30);
        $app['config']->set('cms.updates.verify_checksum', false); // Disable for tests
        $app['config']->set('cms.updates.composer_install_command', 'composer install --no-dev');
        $app['config']->set('cms.updates.composer_timeout', 600);
        $app['config']->set('cms.updates.exclude_from_update', ['.env', 'storage', 'vendor']);
    }

    /**
     * Test manager can check for updates.
     *
     * @since 2.0.0
     */
    public function test_can_check_for_update(): void
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

        $this->assertInstanceOf(UpdateInfo::class, $result);
        $this->assertTrue($result->hasUpdate);
        $this->assertEquals('2.0.0', $result->latestVersion);
    }

    /**
     * Test manager throws exception when no update URL configured.
     *
     * @since 2.0.0
     */
    public function test_throws_exception_when_no_update_url(): void
    {
        config(['cms.updates.update_source_url' => null]);

        $manager = new ApplicationUpdateManager;

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Update URL not configured');

        $manager->checkForUpdate();
    }

    /**
     * Test manager throws exception when no update available.
     *
     * @since 2.0.0
     */
    public function test_throws_exception_when_no_update_available(): void
    {
        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '1.0.0',
            hasUpdate: false,
            downloadUrl: 'https://example.com/update.zip'
        );

        $checker = $this->createMock(UpdateChecker::class);
        $checker->method('checkForUpdate')->willReturn($updateInfo);

        $manager->setUpdateChecker($checker);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('No update available');

        $manager->performUpdate();
    }

    /**
     * Test manager can clear cache.
     *
     * @since 2.0.0
     */
    public function test_can_clear_cache(): void
    {
        $manager = new ApplicationUpdateManager;

        $checker = $this->createMock(UpdateChecker::class);
        $checker->expects($this->once())->method('clearCache');

        $manager->setUpdateChecker($checker);
        $manager->clearCache();

        $this->assertTrue(true); // If we get here, the test passed
    }

    /**
     * Test path exclusion logic.
     *
     * @since 2.0.0
     */
    public function test_path_exclusion_logic(): void
    {
        $manager = new ApplicationUpdateManager;

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('isPathExcluded');
        $method->setAccessible(true);

        // Test exact match
        $this->assertTrue($method->invoke($manager, 'storage/logs/test.log', ['storage']));

        // Test non-match
        $this->assertFalse($method->invoke($manager, 'app/Models/User.php', ['storage']));

        // Test wildcard match
        $this->assertTrue($method->invoke($manager, 'bootstrap/cache/config.php', ['bootstrap/cache/*.php']));

        // Test no match with wildcard
        $this->assertFalse($method->invoke($manager, 'bootstrap/app.php', ['bootstrap/cache/*.php']));
    }

    /**
     * Test rollback throws exception when backup not found.
     *
     * @since 2.0.0
     */
    public function test_rollback_throws_exception_when_backup_not_found(): void
    {
        $manager = new ApplicationUpdateManager;

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Backup not found');

        $manager->rollback('/nonexistent/backup.zip');
    }

    /**
     * Test manager sets custom update checker.
     *
     * @since 2.0.0
     */
    public function test_can_set_custom_update_checker(): void
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

        $this->assertEquals('2.0.0', $result->latestVersion);
    }
}
