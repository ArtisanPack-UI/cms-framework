<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Error;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use ReflectionClass;
use ZipArchive;

/**
 * Application Update Manager Tests
 *
 * @since 1.0.0
 */
class ApplicationUpdateManagerTest extends TestCase
{
    /**
     * Test manager can check for updates.
     *
     * @since 1.0.0
     */
    public function test_can_check_for_update(): void
    {
        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        );

        $checker = $this->createMock( UpdateChecker::class );
        $checker->method( 'checkForUpdate' )->willReturn( $updateInfo );

        $manager->setUpdateChecker( $checker );

        $result = $manager->checkForUpdate();

        $this->assertInstanceOf( UpdateInfo::class, $result );
        $this->assertTrue( $result->hasUpdate() );
        $this->assertEquals( '2.0.0', $result->latestVersion );
    }

    /**
     * Test manager throws exception when no update URL configured.
     *
     * @since 1.0.0
     */
    public function test_throws_exception_when_no_update_url(): void
    {
        config( ['cms.updates.update_source_url' => null] );

        $manager = new ApplicationUpdateManager;

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'Update URL not configured' );

        $manager->checkForUpdate();
    }

    /**
     * Test manager throws exception when no update available.
     *
     * @since 1.0.0
     */
    public function test_throws_exception_when_no_update_available(): void
    {
        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '1.0.0',
            downloadUrl: 'https://example.com/update.zip',
        );

        $checker = $this->createMock( UpdateChecker::class );
        $checker->method( 'checkForUpdate' )->willReturn( $updateInfo );

        $manager->setUpdateChecker( $checker );

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'No update available' );

        $manager->performUpdate();
    }

    /**
     * Test manager can clear cache.
     *
     * @since 1.0.0
     */
    public function test_can_clear_cache(): void
    {
        $manager = new ApplicationUpdateManager;

        $checker = $this->createMock( UpdateChecker::class );
        $checker->expects( $this->once() )->method( 'clearCache' );

        $manager->setUpdateChecker( $checker );
        $manager->clearCache();

        $this->assertTrue( true ); // If we get here, the test passed
    }

    /**
     * Test path exclusion logic.
     *
     * @since 1.0.0
     */
    public function test_path_exclusion_logic(): void
    {
        $manager = new ApplicationUpdateManager;

        $reflection = new ReflectionClass( $manager );
        $method     = $reflection->getMethod( 'isPathExcluded' );
        $method->setAccessible( true );

        // Test exact match
        $this->assertTrue( $method->invoke( $manager, 'storage/logs/test.log', ['storage'] ) );

        // Test non-match
        $this->assertFalse( $method->invoke( $manager, 'app/Models/User.php', ['storage'] ) );

        // Test wildcard match
        $this->assertTrue( $method->invoke( $manager, 'bootstrap/cache/config.php', ['bootstrap/cache/*.php'] ) );

        // Test no match with wildcard
        $this->assertFalse( $method->invoke( $manager, 'bootstrap/app.php', ['bootstrap/cache/*.php'] ) );
    }

    /**
     * Test rollback throws exception when backup not found.
     *
     * @since 1.0.0
     */
    public function test_rollback_throws_exception_when_backup_not_found(): void
    {
        $manager = new ApplicationUpdateManager;

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'Backup not found' );

        $manager->rollback( '/nonexistent/backup.zip' );
    }

    /**
     * Test manager sets custom update checker.
     *
     * @since 1.0.0
     */
    public function test_can_set_custom_update_checker(): void
    {
        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        );

        $checker = $this->createMock( UpdateChecker::class );
        $checker->method( 'checkForUpdate' )->willReturn( $updateInfo );

        $manager->setUpdateChecker( $checker );

        $result = $manager->checkForUpdate();

        $this->assertEquals( '2.0.0', $result->latestVersion );
    }

    /**
     * Test verifyChecksum throws on mismatch.
     *
     * @since 2.0.0
     */
    public function test_verify_checksum_throws_on_mismatch(): void
    {
        $manager = new ApplicationUpdateManager;

        $zipPath = tempnam( sys_get_temp_dir(), 'cmsfw-update-' );
        file_put_contents( $zipPath, 'fake zip contents' );

        try {
            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'verifyChecksum' );
            $method->setAccessible( true );

            $this->expectException( UpdateException::class );
            $this->expectExceptionMessage( 'Checksum mismatch' );

            $method->invoke( $manager, $zipPath, str_repeat( '0', 64 ) );
        } finally {
            @unlink( $zipPath );
        }
    }

    /**
     * Test verifyChecksum passes when the digest matches.
     *
     * @since 2.0.0
     */
    public function test_verify_checksum_passes_when_digest_matches(): void
    {
        $manager = new ApplicationUpdateManager;

        $zipPath = tempnam( sys_get_temp_dir(), 'cmsfw-update-' );
        file_put_contents( $zipPath, 'matching zip contents' );

        try {
            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'verifyChecksum' );
            $method->setAccessible( true );

            $method->invoke( $manager, $zipPath, hash_file( 'sha256', $zipPath ) );

            $this->assertTrue( true ); // No exception means success.
        } finally {
            @unlink( $zipPath );
        }
    }

    /**
     * Test maybeVerifyChecksum logs a warning when the source omits a checksum.
     *
     * @since 2.0.0
     */
    public function test_maybe_verify_checksum_logs_warning_when_sha256_missing(): void
    {
        config( ['cms.updates.verify_checksum' => true] );

        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
            sha256: null,
            metadata: ['source' => 'gitlab'],
        );

        Log::shouldReceive( 'warning' )
            ->once()
            ->withArgs( function ( string $message, array $context ): bool {
                return str_contains( $message, 'Skipping update integrity verification' )
                    && '2.0.0' === ( $context['target_version'] ?? null )
                    && 'gitlab' === ( $context['source'] ?? null );
            } );

        $reflection = new ReflectionClass( $manager );
        $method     = $reflection->getMethod( 'maybeVerifyChecksum' );
        $method->setAccessible( true );

        $method->invoke( $manager, '/does/not/matter.zip', $updateInfo, '2.0.0' );
    }

    /**
     * Test maybeVerifyChecksum does not warn when verification is disabled.
     *
     * @since 2.0.0
     */
    public function test_maybe_verify_checksum_is_silent_when_disabled(): void
    {
        config( ['cms.updates.verify_checksum' => false] );

        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
            sha256: null,
        );

        Log::shouldReceive( 'warning' )->never();

        $reflection = new ReflectionClass( $manager );
        $method     = $reflection->getMethod( 'maybeVerifyChecksum' );
        $method->setAccessible( true );

        $method->invoke( $manager, '/does/not/matter.zip', $updateInfo, '2.0.0' );
    }

    /**
     * Test that a fatal `\Error` during the update disables maintenance mode
     * and rethrows the error, rather than leaving the host stranded.
     *
     * @since 2.5.1
     */
    public function test_perform_update_disables_maintenance_mode_on_fatal_error(): void
    {
        config( ['cms.updates.backup_enabled' => false] );

        $manager = new class extends ApplicationUpdateManager {
            public array $modeCalls = [];

            protected function enableMaintenanceMode(): void
            {
                $this->modeCalls[] = 'enable';
            }

            protected function disableMaintenanceMode(): void
            {
                $this->modeCalls[] = 'disable';
            }
        };

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        );

        $checker = $this->createMock( UpdateChecker::class );
        $checker->method( 'checkForUpdate' )->willReturn( $updateInfo );
        $checker->method( 'downloadUpdate' )->willThrowException( new Error( 'Simulated fatal error' ) );

        $manager->setUpdateChecker( $checker );

        try {
            $manager->performUpdate();
            $this->fail( 'Expected Error to be thrown.' );
        } catch ( Error $e ) {
            $this->assertSame( 'Simulated fatal error', $e->getMessage() );
        }

        $this->assertSame( ['enable', 'disable'], $manager->modeCalls );
    }

    /**
     * Regression for #219: `extractUpdate` must stream each ZIP entry to disk
     * via `getStream()` rather than loading it into memory with
     * `getFromIndex()`, otherwise a single large file inside the release
     * archive OOMs on 128M hosts mid-extraction.
     *
     * @since 2.5.2
     */
    public function test_extract_update_streams_entries_to_disk(): void
    {
        $tempRoot = sys_get_temp_dir() . '/cmsfw-extract-' . bin2hex( random_bytes( 6 ) );
        $target   = $tempRoot . '/target';
        mkdir( $target, 0755, true );

        $zipPath  = $tempRoot . '/update.zip';
        $largeBig = str_repeat( 'x', 65536 );

        $zip = new ZipArchive;
        $this->assertTrue( true === $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
        $zip->addFromString( 'release-root/app/Model.php', "<?php\n" . $largeBig );
        $zip->addFromString( 'release-root/routes/web.php', "<?php\nRoute::get('/', fn () => 'ok');\n" );
        $zip->close();

        $manager = new class( $target ) extends ApplicationUpdateManager {
            public function __construct( protected string $extractRoot )
            {
            }

            public function extractInto( string $zipPath ): void
            {
                $this->extractUpdate( $zipPath );
            }
        };

        // Point the application base path to our temp target so `base_path()`
        // inside extractUpdate resolves to a scratch directory instead of the
        // testbench root.
        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager->extractInto( $zipPath );

            $this->assertFileExists( $target . '/app/Model.php' );
            $this->assertFileExists( $target . '/routes/web.php' );
            $this->assertSame(
                strlen( "<?php\n" . $largeBig ),
                filesize( $target . '/app/Model.php' ),
                'Streamed extraction should produce a byte-for-byte copy of the ZIP entry.',
            );
        } finally {
            app()->setBasePath( $originalBase );
            @unlink( $zipPath );
            $this->removeDirectory( $target );
            @rmdir( $tempRoot );
        }
    }

    /**
     * Recursively remove a directory used by extraction tests.
     *
     * @since 2.5.2
     */
    protected function removeDirectory( string $path ): void
    {
        if ( ! is_dir( $path ) ) {
            return;
        }

        $items = scandir( $path );

        if ( false === $items ) {
            return;
        }

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;

            if ( is_dir( $full ) ) {
                $this->removeDirectory( $full );
            } else {
                @unlink( $full );
            }
        }

        @rmdir( $path );
    }

    /**
     * Define environment setup.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment( $app ): void
    {
        $app['config']->set( 'cms.updates.update_source_url', 'https://github.com/test/repo' );
        $app['config']->set( 'cms.updates.backup_enabled', true );
        $app['config']->set( 'cms.updates.backup_path', 'backups/application' );
        $app['config']->set( 'cms.updates.backup_retention_days', 30 );
        $app['config']->set( 'cms.updates.verify_checksum', false ); // Disable for tests
        $app['config']->set( 'cms.updates.composer_install_command', 'composer install --no-dev' );
        $app['config']->set( 'cms.updates.composer_timeout', 600 );
        $app['config']->set( 'cms.updates.exclude_from_update', ['.env', 'storage', 'vendor'] );
    }
}
