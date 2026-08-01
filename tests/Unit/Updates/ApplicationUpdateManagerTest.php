<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Error;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Orchestra\Testbench\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;
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
     * Test maybeVerifyChecksum logs a warning when the source omits a checksum
     * and the host has explicitly opted in to accepting unverified updates.
     *
     * @since 2.0.0
     */
    public function test_maybe_verify_checksum_logs_warning_when_sha256_missing(): void
    {
        config( [
            'cms.updates.verify_checksum'          => true,
            'cms.updates.allow_unverified_updates' => true,
        ] );

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
     * Test maybeVerifyChecksum fails closed by default when the source omits a
     * checksum. The updater must refuse to proceed rather than installing
     * arbitrary remote code without integrity verification.
     *
     * @since 2.5.4
     */
    public function test_maybe_verify_checksum_throws_when_sha256_missing_and_not_opted_in(): void
    {
        config( [
            'cms.updates.verify_checksum'          => true,
            'cms.updates.allow_unverified_updates' => false,
        ] );

        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
            sha256: null,
            metadata: ['source' => 'gitlab'],
        );

        $reflection = new ReflectionClass( $manager );
        $method     = $reflection->getMethod( 'maybeVerifyChecksum' );
        $method->setAccessible( true );

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'did not advertise a SHA-256 checksum' );

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
     * Regression for #234: `extractUpdate` must reject ZIP entries that resolve
     * outside the extraction root (Zip Slip). Because extraction bypasses
     * `ZipArchive::extractTo()` and streams entries manually, PHP's own
     * traversal mitigations don't apply — the guard has to live in the manager.
     *
     * @since 2.5.4
     */
    public function test_extract_update_rejects_zip_slip_entries(): void
    {
        $tempRoot = sys_get_temp_dir() . '/cmsfw-zipslip-' . bin2hex( random_bytes( 6 ) );
        $target   = $tempRoot . '/target';
        $outside  = $tempRoot . '/outside';
        mkdir( $target, 0755, true );
        mkdir( $outside, 0755, true );

        $zipPath = $tempRoot . '/update.zip';

        $zip = new ZipArchive;
        $this->assertTrue( true === $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
        // Benign entry under the common prefix — should extract normally.
        $zip->addFromString( 'release-root/app/Model.php', "<?php\n" );
        // Malicious entry: after the common prefix is stripped, the remaining
        // path traverses out of the extract root.
        $zip->addFromString( 'release-root/../../../outside/pwn.php', "<?php // pwned\n" );
        // Malicious entry with absolute path.
        $zip->addFromString( '/absolute/etc/cron.d/x', "pwn\n" );
        $zip->close();

        $manager = new class extends ApplicationUpdateManager {
            public function extractInto( string $zipPath ): void
            {
                $this->extractUpdate( $zipPath );
            }
        };

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager->extractInto( $zipPath );

            $this->assertFileExists( $target . '/app/Model.php' );
            $this->assertFileDoesNotExist( $outside . '/pwn.php' );
            $this->assertFileDoesNotExist( $tempRoot . '/outside/pwn.php' );
            $this->assertFileDoesNotExist( '/absolute/etc/cron.d/x' );
        } finally {
            app()->setBasePath( $originalBase );
            @unlink( $zipPath );
            $this->removeDirectory( $target );
            $this->removeDirectory( $outside );
            @rmdir( $tempRoot );
        }
    }

    /**
     * Regression for #236: `extractUpdate` must surface a per-entry write
     * failure (fopen/fread on the target file) as an `UpdateException` so
     * `performUpdate()`'s catch block can roll back to the pre-update snapshot
     * instead of leaving a partial install on disk.
     *
     * @since 2.5.4
     */
    public function test_extract_update_throws_when_entry_target_cannot_be_opened(): void
    {
        if ( 0 === posix_geteuid() ) {
            $this->markTestSkipped( 'Cannot exercise fopen failure as root — the read-only parent guard is bypassed.' );
        }

        $tempRoot = sys_get_temp_dir() . '/cmsfw-fopen-fail-' . bin2hex( random_bytes( 6 ) );
        $target   = $tempRoot . '/target';
        mkdir( $target, 0755, true );

        // Pre-create the entry's parent directory as read-only so the manager
        // reuses it (skipping the makeDirectory branch) and the subsequent
        // fopen('wb') on a new file inside it fails.
        $lockedDir = $target . '/locked';
        mkdir( $lockedDir, 0555, true );

        $zipPath = $tempRoot . '/update.zip';

        $zip = new ZipArchive;
        $this->assertTrue( true === $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
        $zip->addFromString( 'release-root/locked/file.php', "<?php\n// payload\n" );
        $zip->close();

        $manager = new class extends ApplicationUpdateManager {
            public function extractInto( string $zipPath ): void
            {
                $this->extractUpdate( $zipPath );
            }
        };

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $threw = false;

            try {
                $manager->extractInto( $zipPath );
            } catch ( UpdateException $e ) {
                $threw = true;
                $this->assertStringContainsString( 'release-root/locked/file.php', $e->getMessage() );
                $this->assertStringContainsString( 'could not open target for writing', $e->getMessage() );
            }

            $this->assertTrue( $threw, 'extractUpdate must throw UpdateException when a target file cannot be opened for writing.' );
            $this->assertFileDoesNotExist( $lockedDir . '/file.php' );
        } finally {
            app()->setBasePath( $originalBase );
            @chmod( $lockedDir, 0755 );
            @unlink( $zipPath );
            $this->removeDirectory( $target );
            @rmdir( $tempRoot );
        }
    }

    /**
     * Regression for #225: `COMPOSER_BINARY` env var wins over both config and
     * discovery and is invoked via `PHP_BINARY` so the PHP-FPM pool's `PATH`
     * never has to resolve composer's shebang.
     *
     * @since 2.5.3
     */
    public function test_resolve_composer_command_prefers_env_binary(): void
    {
        config( ['cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND] );

        $original = getenv( 'COMPOSER_BINARY' );
        putenv( 'COMPOSER_BINARY=/opt/homebrew/bin/composer' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'resolveComposerCommand' );
            $method->setAccessible( true );

            $command = $method->invoke( $manager );

            $this->assertStringStartsWith( escapeshellarg( PHP_BINARY ) . ' ', $command );
            $this->assertStringContainsString( escapeshellarg( '/opt/homebrew/bin/composer' ), $command );
            $this->assertStringEndsWith( ' ' . ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_ARGS, $command );
        } finally {
            putenv( false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY=' . $original );
        }
    }

    /**
     * Regression for #232: the shipped `cms.updates.composer_binary` config
     * key (populated from `env('COMPOSER_BINARY')`) is honored as priority-1
     * so operators who set `COMPOSER_BINARY` in Laravel `.env` — the natural
     * first move — actually get picked up under HTTP-request context, where
     * `getenv()` would otherwise return `false` under Laravel 11+.
     *
     * @since 2.5.4
     */
    public function test_resolve_composer_command_prefers_config_composer_binary(): void
    {
        config( [
            'cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND,
            'cms.updates.composer_binary'          => '/opt/homebrew/bin/composer',
        ] );

        $original = getenv( 'COMPOSER_BINARY' );
        putenv( 'COMPOSER_BINARY' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'resolveComposerCommand' );
            $method->setAccessible( true );

            $command = $method->invoke( $manager );

            $this->assertStringStartsWith( escapeshellarg( PHP_BINARY ) . ' ', $command );
            $this->assertStringContainsString( escapeshellarg( '/opt/homebrew/bin/composer' ), $command );
            $this->assertStringEndsWith( ' ' . ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_ARGS, $command );
        } finally {
            putenv( false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY=' . $original );
        }
    }

    /**
     * Regression for #232: `cms.updates.composer_binary` wins over
     * `getenv('COMPOSER_BINARY')` when both are set — the `.env`-populated
     * config value is the documented priority-1 source.
     *
     * @since 2.5.4
     */
    public function test_resolve_composer_command_config_binary_wins_over_getenv(): void
    {
        config( [
            'cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND,
            'cms.updates.composer_binary'          => '/from/config/composer',
        ] );

        $original = getenv( 'COMPOSER_BINARY' );
        putenv( 'COMPOSER_BINARY=/from/getenv/composer' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'resolveComposerCommand' );
            $method->setAccessible( true );

            $command = $method->invoke( $manager );

            $this->assertStringContainsString( escapeshellarg( '/from/config/composer' ), $command );
            $this->assertStringNotContainsString( '/from/getenv/composer', $command );
        } finally {
            putenv( false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY=' . $original );
        }
    }

    /**
     * Regression for #232: when the config key is unset (empty string, null),
     * fall back to `getenv('COMPOSER_BINARY')` so OS-level exports (PHP-FPM
     * pool env, container ENV) keep working exactly as before.
     *
     * @since 2.5.4
     */
    public function test_resolve_composer_command_falls_back_to_getenv_when_config_empty(): void
    {
        config( [
            'cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND,
            'cms.updates.composer_binary'          => null,
        ] );

        $original = getenv( 'COMPOSER_BINARY' );
        putenv( 'COMPOSER_BINARY=/opt/homebrew/bin/composer' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'resolveComposerCommand' );
            $method->setAccessible( true );

            $command = $method->invoke( $manager );

            $this->assertStringContainsString( escapeshellarg( '/opt/homebrew/bin/composer' ), $command );
        } finally {
            putenv( false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY=' . $original );
        }
    }

    /**
     * Regression for #225: when the config value differs from the shipped
     * default, treat it as an explicit operator override and leave it alone —
     * no PHP_BINARY wrapping, no shell escaping.
     *
     * @since 2.5.3
     */
    public function test_resolve_composer_command_respects_non_default_config_override(): void
    {
        $override = 'PATH=/opt/homebrew/bin:/usr/bin /opt/homebrew/bin/composer install --no-dev --no-interaction --optimize-autoloader';
        config( ['cms.updates.composer_install_command' => $override] );

        $original = getenv( 'COMPOSER_BINARY' );
        putenv( 'COMPOSER_BINARY' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'resolveComposerCommand' );
            $method->setAccessible( true );

            $this->assertSame( $override, $method->invoke( $manager ) );
        } finally {
            putenv( false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY=' . $original );
        }
    }

    /**
     * Regression for #225: with no env override and default config, use the
     * discovered binary and invoke it via `PHP_BINARY`.
     *
     * @since 2.5.3
     */
    public function test_resolve_composer_command_uses_discovered_binary(): void
    {
        config( ['cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND] );

        $original = getenv( 'COMPOSER_BINARY' );
        putenv( 'COMPOSER_BINARY' );

        try {
            $manager = new class extends ApplicationUpdateManager {
                protected function discoverComposerBinary(): ?string
                {
                    return '/discovered/path/composer';
                }
            };

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'resolveComposerCommand' );
            $method->setAccessible( true );

            $command = $method->invoke( $manager );

            $this->assertStringStartsWith( escapeshellarg( PHP_BINARY ) . ' ', $command );
            $this->assertStringContainsString( escapeshellarg( '/discovered/path/composer' ), $command );
            $this->assertStringEndsWith( ' ' . ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_ARGS, $command );
        } finally {
            putenv( false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY=' . $original );
        }
    }

    /**
     * Regression for #225: when nothing is set and discovery finds nothing,
     * fall back to the default (bare `composer`) — the pre-2.5.3 behavior.
     *
     * @since 2.5.3
     */
    public function test_resolve_composer_command_falls_back_to_default(): void
    {
        config( ['cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND] );

        $original = getenv( 'COMPOSER_BINARY' );
        putenv( 'COMPOSER_BINARY' );

        try {
            $manager = new class extends ApplicationUpdateManager {
                protected function discoverComposerBinary(): ?string
                {
                    return null;
                }
            };

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'resolveComposerCommand' );
            $method->setAccessible( true );

            $this->assertSame(
                ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND,
                $method->invoke( $manager ),
            );
        } finally {
            putenv( false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY=' . $original );
        }
    }

    /**
     * Regression for #225: the discovery candidate list must include the
     * documented common install paths in preference order so the diagnostic
     * message stays honest and hosts see the same list the framework tried.
     *
     * Regression for #254: Herd's bundled composer leads the list, mirroring
     * `phpCandidatePaths()`. A Herd-only macOS host — no Homebrew composer, no
     * global install — matched none of the other five paths, so discovery
     * returned null and the updater fell through to bare `composer`, which
     * PHP-FPM's stripped `PATH` cannot resolve.
     *
     * @since 2.5.3
     */
    public function test_composer_candidate_paths_covers_documented_locations(): void
    {
        $original = getenv( 'HOME' );
        putenv( 'HOME=/home/tester' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'composerCandidatePaths' );
            $method->setAccessible( true );

            $candidates = $method->invoke( $manager );

            $this->assertSame(
                [
                    '/home/tester/Library/Application Support/Herd/bin/composer',
                    '/usr/local/bin/composer',
                    '/opt/homebrew/bin/composer',
                    '/home/tester/.composer/vendor/bin/composer',
                    '/home/tester/.config/composer/vendor/bin/composer',
                    '/usr/bin/composer',
                ],
                $candidates,
            );
        } finally {
            putenv( false === $original ? 'HOME' : 'HOME=' . $original );
        }
    }

    /**
     * Regression for #254: with no `HOME` there is no Herd directory to derive,
     * so the Herd entry is omitted rather than rendered against an empty
     * string — which would stat `/Library/Application Support/Herd/bin/composer`
     * on every candidate walk.
     *
     * @since 2.7.1
     */
    public function test_composer_candidate_paths_omits_herd_entry_without_home(): void
    {
        $original = getenv( 'HOME' );
        putenv( 'HOME' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'composerCandidatePaths' );
            $method->setAccessible( true );

            $this->assertSame(
                [
                    '/usr/local/bin/composer',
                    '/opt/homebrew/bin/composer',
                    '/usr/bin/composer',
                ],
                $method->invoke( $manager ),
            );
        } finally {
            putenv( false === $original ? 'HOME' : 'HOME=' . $original );
        }
    }

    /**
     * Regression for #254: both candidate lists derive Herd's `bin/` directory
     * from the same helper, so they cannot drift apart again — the drift that
     * left the composer list without Herd awareness after #225 taught the PHP
     * list about it.
     *
     * @since 2.7.1
     */
    public function test_herd_bin_path_is_shared_by_both_candidate_lists(): void
    {
        $original = getenv( 'HOME' );
        putenv( 'HOME=/home/tester' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );

            $herdBin = $reflection->getMethod( 'herdBinPath' );
            $herdBin->setAccessible( true );

            $composerPaths = $reflection->getMethod( 'composerCandidatePaths' );
            $composerPaths->setAccessible( true );

            $phpPaths = $reflection->getMethod( 'phpCandidatePaths' );
            $phpPaths->setAccessible( true );

            $resolved = $herdBin->invoke( $manager );

            $this->assertSame( '/home/tester/Library/Application Support/Herd/bin', $resolved );
            $this->assertSame( $resolved . '/composer', $composerPaths->invoke( $manager )[0] );
            $this->assertSame( $resolved . '/php', $phpPaths->invoke( $manager )[0] );
        } finally {
            putenv( false === $original ? 'HOME' : 'HOME=' . $original );
        }
    }

    /**
     * Regression for #254: `herdBinPath()` returns null when `HOME` is unset or
     * empty, so callers omit the Herd candidate entirely.
     *
     * @since 2.7.1
     */
    public function test_herd_bin_path_returns_null_without_usable_home(): void
    {
        $original = getenv( 'HOME' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'herdBinPath' );
            $method->setAccessible( true );

            putenv( 'HOME' );
            $this->assertNull( $method->invoke( $manager ) );

            putenv( 'HOME=' );
            $this->assertNull( $method->invoke( $manager ) );
        } finally {
            putenv( false === $original ? 'HOME' : 'HOME=' . $original );
        }
    }

    /**
     * Regression for #254: Herd's bundled composer wins discovery on a host
     * that also carries a Homebrew composer, keeping a Herd machine on a single
     * toolchain — the same rationale `phpCandidatePaths()` applies.
     *
     * @since 2.7.1
     */
    public function test_discover_composer_binary_prefers_herd_over_homebrew(): void
    {
        $tempHome = sys_get_temp_dir() . '/cmsfw-herd-' . bin2hex( random_bytes( 6 ) );
        $herdBin  = $tempHome . '/Library/Application Support/Herd/bin';
        mkdir( $herdBin, 0755, true );

        $herdComposer = $herdBin . '/composer';
        file_put_contents( $herdComposer, "#!/usr/bin/env php\n" );
        chmod( $herdComposer, 0755 );

        $original = getenv( 'HOME' );
        putenv( 'HOME=' . $tempHome );

        try {
            $manager = new class extends ApplicationUpdateManager {
                public function callDiscover(): ?string
                {
                    return $this->discoverComposerBinary();
                }
            };

            Log::shouldReceive( 'warning' )->never();

            $this->assertSame( $herdComposer, $manager->callDiscover() );
        } finally {
            putenv( false === $original ? 'HOME' : 'HOME=' . $original );
            @unlink( $herdComposer );
            @rmdir( $herdBin );
            @rmdir( $tempHome . '/Library/Application Support/Herd' );
            @rmdir( $tempHome . '/Library/Application Support' );
            @rmdir( $tempHome . '/Library' );
            @rmdir( $tempHome );
        }
    }

    /**
     * Regression for #225: `discoverComposerBinary()` returns the first
     * candidate that is both a file and executable. Prevents a silent
     * regression where the `is_executable()` gate is dropped or the list gets
     * reordered.
     *
     * @since 2.5.3
     */
    public function test_discover_composer_binary_returns_first_executable_hit(): void
    {
        $tempDir = sys_get_temp_dir() . '/cmsfw-composer-' . bin2hex( random_bytes( 6 ) );
        mkdir( $tempDir, 0755, true );

        $missing        = $tempDir . '/missing-composer';
        $nonExecutable  = $tempDir . '/non-executable-composer';
        $executableHit  = $tempDir . '/executable-composer';
        $laterCandidate = $tempDir . '/never-reached-composer';

        file_put_contents( $nonExecutable, "#!/bin/sh\n" );
        chmod( $nonExecutable, 0644 );

        file_put_contents( $executableHit, "#!/bin/sh\n" );
        chmod( $executableHit, 0755 );

        file_put_contents( $laterCandidate, "#!/bin/sh\n" );
        chmod( $laterCandidate, 0755 );

        try {
            $manager = new class( [$missing, $nonExecutable, $executableHit, $laterCandidate] ) extends ApplicationUpdateManager {
                public function __construct( protected array $paths )
                {
                }

                protected function composerCandidatePaths(): array
                {
                    return $this->paths;
                }

                public function callDiscover(): ?string
                {
                    return $this->discoverComposerBinary();
                }
            };

            Log::shouldReceive( 'warning' )->never();

            $this->assertSame( $executableHit, $manager->callDiscover() );
        } finally {
            @unlink( $missing );
            @unlink( $nonExecutable );
            @unlink( $executableHit );
            @unlink( $laterCandidate );
            @rmdir( $tempDir );
        }
    }

    /**
     * Regression for #233: when discovery finds nothing, emit a structured
     * `Log::warning` that reports per-candidate `is_file()` / `is_executable()`
     * results so operators can distinguish a wrong-path failure from a
     * PHP-FPM sandboxed-stat failure (macOS Herd Pro, chrooted FPM pools,
     * restrictive `open_basedir`) without having to reflect into the
     * framework.
     *
     * @since 2.5.4
     */
    public function test_discover_composer_binary_logs_per_candidate_diagnostics_on_failure(): void
    {
        $tempDir = sys_get_temp_dir() . '/cmsfw-composer-diag-' . bin2hex( random_bytes( 6 ) );
        mkdir( $tempDir, 0755, true );

        $missing       = $tempDir . '/missing-composer';
        $nonExecutable = $tempDir . '/non-executable-composer';

        file_put_contents( $nonExecutable, "#!/bin/sh\n" );
        chmod( $nonExecutable, 0644 );

        $manager = new class( [$missing, $nonExecutable] ) extends ApplicationUpdateManager {
            public function __construct( protected array $paths )
            {
            }

            protected function composerCandidatePaths(): array
            {
                return $this->paths;
            }

            public function callDiscover(): ?string
            {
                return $this->discoverComposerBinary();
            }
        };

        $captured = [];
        Log::shouldReceive( 'warning' )
            ->once()
            ->andReturnUsing( function ( string $message, array $context ) use ( &$captured ): void {
                $captured = [
                    'message' => $message,
                    'context' => $context,
                ];
            } );

        try {
            $this->assertNull( $manager->callDiscover() );

            $this->assertStringContainsString( 'composer binary discovery failed', $captured['message'] );
            $this->assertArrayHasKey( 'candidates', $captured['context'] );
            $this->assertArrayHasKey( 'php_sapi', $captured['context'] );
            $this->assertArrayHasKey( 'hint', $captured['context'] );

            $this->assertSame(
                [
                    ['path' => $missing, 'is_file' => false, 'is_executable' => false],
                    ['path' => $nonExecutable, 'is_file' => true, 'is_executable' => false],
                ],
                $captured['context']['candidates'],
            );
        } finally {
            @unlink( $missing );
            @unlink( $nonExecutable );
            @rmdir( $tempDir );
        }
    }

    /**
     * Regression for #225: rollback pre-checks the resolved composer binary
     * with `--version` before invoking install, and surfaces a specific
     * error rather than the generic "Manual intervention required" when the
     * binary can't be reached.
     *
     * As of 2.5.4 the pre-check throws `composerVerificationFailed` (which
     * captures exit code + output) rather than `composerBinaryNotFound` —
     * the binary *was* located, so the misleading "not found" wording was
     * masking real interpreter mismatches on PHP-FPM hosts.
     *
     * As of 2.7.1 this branch requires the binary to be visible to PHP on
     * disk; a failed probe against a path PHP cannot see takes the
     * `configuredComposerBinaryMissing` branch instead (see #254), hence the
     * real temp file below.
     *
     * @since 2.5.3
     */
    public function test_rollback_throws_composer_verification_failed_when_version_check_fails(): void
    {
        Process::fake( [
            '*--version*' => Process::result( '', 'command not found', 127 ),
        ] );

        $composerBinary = tempnam( sys_get_temp_dir(), 'cmsfw-composer-' );
        file_put_contents( $composerBinary, "#!/usr/bin/env php\n" );

        $manager = new class( $composerBinary ) extends ApplicationUpdateManager {
            public function __construct( protected string $binary )
            {
            }

            protected function resolveComposerBinaryForVerification(): ?string
            {
                return $this->binary;
            }
        };

        $backupPath = tempnam( sys_get_temp_dir(), 'cmsfw-backup-' ) . '.zip';
        $zip        = new ZipArchive;
        $this->assertTrue( true === $zip->open( $backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
        $zip->addFromString( 'placeholder.txt', 'ok' );
        $zip->close();

        try {
            $this->expectException( UpdateException::class );
            $this->expectExceptionMessage( 'could not be executed' );

            $manager->rollback( $backupPath );
        } finally {
            @unlink( $backupPath );
            @unlink( $composerBinary );
        }
    }

    /**
     * Regression for #254: when the probe fails against a path PHP cannot see
     * either, the error names that path as the fault instead of reporting
     * "Composer binary was located but could not be executed" with a trailing
     * `CMS_PHP_BINARY` hint — which blames the PHP interpreter that had
     * resolved correctly and buries the real cause mid-sentence.
     *
     * @since 2.7.1
     */
    public function test_rollback_throws_configured_binary_missing_when_path_does_not_exist(): void
    {
        Process::fake( [
            '*--version*' => Process::result( '', 'Could not open input file', 1 ),
        ] );

        $missing = sys_get_temp_dir() . '/cmsfw-nonexistent-composer-' . bin2hex( random_bytes( 6 ) );

        $manager = new class( $missing ) extends ApplicationUpdateManager {
            public function __construct( protected string $binary )
            {
            }

            protected function resolveComposerBinaryForVerification(): ?string
            {
                return $this->binary;
            }
        };

        $backupPath = tempnam( sys_get_temp_dir(), 'cmsfw-backup-' ) . '.zip';
        $zip        = new ZipArchive;
        $this->assertTrue( true === $zip->open( $backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
        $zip->addFromString( 'placeholder.txt', 'ok' );
        $zip->close();

        try {
            $manager->rollback( $backupPath );
            $this->fail( 'Expected UpdateException for the missing configured composer binary.' );
        } catch ( UpdateException $e ) {
            $this->assertStringContainsString( 'could not be found at', $e->getMessage() );
            $this->assertStringContainsString( $missing, $e->getMessage() );
            $this->assertStringContainsString( 'COMPOSER_BINARY', $e->getMessage() );
            $this->assertStringNotContainsString( 'CMS_PHP_BINARY', $e->getMessage() );
        } finally {
            @unlink( $backupPath );
        }
    }

    /**
     * Regression for #254 guarding #233: a `COMPOSER_BINARY` whose `--version`
     * probe *succeeds* must be accepted even when `is_file()` reports false for
     * it. PHP-FPM sandboxing (macOS Herd Pro, chrooted pools, restrictive
     * `open_basedir`) hides real files from `stat()` while the shelled-out
     * child reaches them fine — the exact case `COMPOSER_BINARY` exists to work
     * around. Gating the probe on `is_file()` would have closed that escape
     * hatch, so the stat may only select the message after a failed probe.
     *
     * @since 2.7.1
     */
    public function test_rollback_accepts_unstattable_binary_whose_version_probe_succeeds(): void
    {
        Process::fake( [
            '*--version*' => Process::result( 'Composer version 2.10.1', '', 0 ),
            '*'           => Process::result( '', '', 0 ),
        ] );

        $sandboxed = '/opt/homebrew/bin/composer-visible-only-to-the-shell';
        $this->assertFalse( is_file( $sandboxed ), 'Fixture path must be unstattable for this test to mean anything.' );

        $manager = new class( $sandboxed ) extends ApplicationUpdateManager {
            public function __construct( protected string $binary )
            {
            }

            protected function resolveComposerBinaryForVerification(): ?string
            {
                return $this->binary;
            }

            protected function clearCaches(): void
            {
                // No-op; nothing to clear in this unit context.
            }
        };

        $backupPath = tempnam( sys_get_temp_dir(), 'cmsfw-backup-' ) . '.zip';
        $zip        = new ZipArchive;
        $this->assertTrue( true === $zip->open( $backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
        $zip->addFromString( 'placeholder.txt', 'ok' );
        $zip->close();

        try {
            $manager->rollback( $backupPath );
        } finally {
            @unlink( $backupPath );
            @unlink( base_path( 'placeholder.txt' ) );
        }
    }

    /**
     * Regression for #225: when rollback fails, the resulting exception must
     * preserve the original update-failure message so operators see *why* the
     * update failed alongside the rollback failure.
     *
     * @since 2.5.3
     */
    public function test_rollback_failure_preserves_original_error(): void
    {
        $manager = new class extends ApplicationUpdateManager {
            public function __construct()
            {
                $stub = tempnam( sys_get_temp_dir(), 'cmsfw-backup-' );
                @unlink( $stub );
                $this->backupPath = $stub . '.zip';
                file_put_contents( $this->backupPath, 'not-a-zip' );
            }

            protected function disableMaintenanceMode(): void
            {
                // No-op; nothing to disable in this unit context.
            }

            public function callHandleFailure( Throwable $e ): void
            {
                $this->handleUpdateFailure( $e );
            }
        };

        try {
            $original = new RuntimeException( 'composer install failed because it ran out of memory' );

            try {
                $manager->callHandleFailure( $original );
                $this->fail( 'Expected UpdateException from rollback.' );
            } catch ( UpdateException $e ) {
                $this->assertStringContainsString( 'Rollback failed', $e->getMessage() );
                $this->assertStringContainsString( 'ran out of memory', $e->getMessage() );
                $this->assertStringContainsString( 'Manual intervention required', $e->getMessage() );
            }
        } finally {
            $reflection = new ReflectionClass( $manager );
            $property   = $reflection->getProperty( 'backupPath' );
            $property->setAccessible( true );
            $path = $property->getValue( $manager );
            if ( is_string( $path ) ) {
                @unlink( $path );
            }
        }
    }

    /**
     * The PHP interpreter used to run composer must be a *CLI* SAPI binary.
     * Under PHP-FPM `PHP_BINARY` points at the FPM daemon, which prints usage
     * and exits 64 when handed a PHAR — the root cause of the "Composer
     * install failed. Output: ." symptom observed on Herd hosts. Regression
     * guard: an FPM-shaped candidate must be rejected in favor of a CLI one.
     *
     * @since 2.5.4
     */
    public function test_resolve_php_binary_skips_fpm_candidates_and_prefers_cli(): void
    {
        $tempDir = sys_get_temp_dir() . '/cmsfw-php-' . bin2hex( random_bytes( 6 ) );
        mkdir( $tempDir, 0755, true );

        $fpm = $tempDir . '/php84-fpm';
        $cli = $tempDir . '/php';

        file_put_contents( $fpm, "#!/bin/sh\n" );
        chmod( $fpm, 0755 );
        file_put_contents( $cli, "#!/bin/sh\n" );
        chmod( $cli, 0755 );

        $original = getenv( 'CMS_PHP_BINARY' );
        putenv( 'CMS_PHP_BINARY' );

        try {
            $manager = new class( [$fpm, $cli], 'fpm-fcgi' ) extends ApplicationUpdateManager {
                public function __construct( protected array $paths, protected string $sapi )
                {
                }

                protected function phpCandidatePaths(): array
                {
                    return $this->paths;
                }

                public function callResolvePhpBinary(): string
                {
                    // Bypass the PHP_SAPI short-circuit by shadowing the
                    // condition — we can't mutate the runtime SAPI constant,
                    // so we run the discovery path directly.
                    foreach ( $this->phpCandidatePaths() as $candidate ) {
                        if ( $this->isCliPhpBinary( $candidate ) ) {
                            return $candidate;
                        }
                    }

                    return PHP_BINARY;
                }
            };

            $this->assertSame( $cli, $manager->callResolvePhpBinary() );
        } finally {
            @unlink( $fpm );
            @unlink( $cli );
            @rmdir( $tempDir );
            putenv( false === $original ? 'CMS_PHP_BINARY' : 'CMS_PHP_BINARY=' . $original );
        }
    }

    /**
     * `CMS_PHP_BINARY` is the operator escape hatch — when set it wins over
     * SAPI detection and candidate discovery so hosts with an unusual layout
     * can point the updater at a known-good CLI PHP.
     *
     * @since 2.5.4
     */
    public function test_resolve_php_binary_respects_env_override(): void
    {
        $original = getenv( 'CMS_PHP_BINARY' );
        putenv( 'CMS_PHP_BINARY=/custom/path/to/php' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'resolvePhpBinary' );
            $method->setAccessible( true );

            $this->assertSame( '/custom/path/to/php', $method->invoke( $manager ) );
        } finally {
            putenv( false === $original ? 'CMS_PHP_BINARY' : 'CMS_PHP_BINARY=' . $original );
        }
    }

    /**
     * The candidate list must include Herd's macOS install path and the
     * common Homebrew/system locations in preference order. Cross-checked
     * with the diagnostic in `composerVerificationFailed` so operators see
     * the same list the framework tried.
     *
     * @since 2.5.4
     */
    public function test_php_candidate_paths_covers_documented_locations(): void
    {
        $original = getenv( 'HOME' );
        putenv( 'HOME=/home/tester' );

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'phpCandidatePaths' );
            $method->setAccessible( true );

            $this->assertSame(
                [
                    '/home/tester/Library/Application Support/Herd/bin/php',
                    '/opt/homebrew/bin/php',
                    '/usr/local/bin/php',
                    '/usr/bin/php',
                ],
                $method->invoke( $manager ),
            );
        } finally {
            putenv( false === $original ? 'HOME' : 'HOME=' . $original );
        }
    }

    /**
     * `composerVerificationFailed` must surface the actual exit code and
     * captured output so operators can distinguish an interpreter-mismatch
     * (usage/64) failure from a genuine unreachable-binary case. Without
     * this the previous "not found" wording sent operators chasing PATH
     * issues that weren't there.
     *
     * @since 2.5.4
     */
    public function test_composer_verification_failed_message_includes_diagnostic_context(): void
    {
        $exception = UpdateException::composerVerificationFailed(
            composerBinary: '/opt/homebrew/bin/composer',
            phpBinary: '/Users/tester/Herd/bin/php84-fpm',
            exitCode: 64,
            detail: 'Usage: php84-fpm ...',
        );

        $message = $exception->getMessage();

        $this->assertStringContainsString( '/opt/homebrew/bin/composer', $message );
        $this->assertStringContainsString( '/Users/tester/Herd/bin/php84-fpm', $message );
        $this->assertStringContainsString( 'Exit code: 64', $message );
        $this->assertStringContainsString( 'Usage: php84-fpm', $message );
        $this->assertStringContainsString( 'CMS_PHP_BINARY', $message );
    }

    /**
     * Regression for #255: `composer.lock` must not appear in the shipped
     * `exclude_from_update` default.
     *
     * `composer install` only ever *reads* a lock file — it never writes one —
     * so excluding the lock while letting `composer.json` be overwritten leaves
     * the pair out of sync and aborts step 6 on every release that changes a
     * dependency constraint. The `vendor` entry stays, because that directory
     * genuinely is rebuilt by `composer install`.
     *
     * @since 2.7.1
     */
    public function test_composer_lock_is_not_excluded_from_updates_by_default(): void
    {
        $shipped = require __DIR__ . '/../../../src/Modules/Core/Updates/config/updates.php';

        $this->assertIsArray( $shipped['exclude_from_update'] );
        $this->assertNotContains(
            'composer.lock',
            $shipped['exclude_from_update'],
            'composer.lock must land from the release so it stays in sync with the release composer.json.',
        );
        $this->assertContains(
            'vendor',
            $shipped['exclude_from_update'],
            'vendor is genuinely rebuilt by composer install and must stay excluded.',
        );
    }

    /**
     * Regression for #255: extraction must overwrite the installed
     * `composer.lock` with the release's, rather than skipping it and leaving
     * the old lock beside the new `composer.json`.
     *
     * @since 2.7.1
     */
    public function test_extract_update_overwrites_composer_lock_from_release(): void
    {
        $shipped = require __DIR__ . '/../../../src/Modules/Core/Updates/config/updates.php';
        config( ['cms.updates.exclude_from_update' => $shipped['exclude_from_update']] );

        $tempRoot = sys_get_temp_dir() . '/cmsfw-lock-' . bin2hex( random_bytes( 6 ) );
        $target   = $tempRoot . '/target';
        mkdir( $target, 0755, true );

        // The installed site: an old constraint and the lock that matches it.
        file_put_contents( $target . '/composer.json', '{"require":{"artisanpack-ui/cms-framework":"^2.5.3"}}' );
        file_put_contents( $target . '/composer.lock', '{"content-hash":"old","packages":[]}' );

        $zipPath = $tempRoot . '/update.zip';

        $zip = new ZipArchive;
        $this->assertTrue( true === $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
        $zip->addFromString( 'release-root/composer.json', '{"require":{"artisanpack-ui/cms-framework":"^2.7.0"}}' );
        $zip->addFromString( 'release-root/composer.lock', '{"content-hash":"new","packages":[]}' );
        $zip->close();

        $manager = new class extends ApplicationUpdateManager {
            public function extractInto( string $zipPath ): void
            {
                $this->extractUpdate( $zipPath );
            }
        };

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager->extractInto( $zipPath );

            $this->assertStringContainsString(
                '^2.7.0',
                (string) file_get_contents( $target . '/composer.json' ),
                'composer.json should carry the release constraint.',
            );
            $this->assertStringContainsString(
                'new',
                (string) file_get_contents( $target . '/composer.lock' ),
                'composer.lock must be overwritten by the release lock, not preserved.',
            );
        } finally {
            app()->setBasePath( $originalBase );
            @unlink( $zipPath );
            $this->removeDirectory( $target );
            @rmdir( $tempRoot );
        }
    }

    /**
     * The framework's reimplementation of `Locker::getContentHash()` must agree
     * with the hash composer itself wrote, so the test fails loudly if a future
     * composer release changes the algorithm.
     *
     * Verified against a committed fixture pair in
     * `tests/Fixtures/ComposerLockSync/`, generated by running real
     * `composer update` over a manifest that exercises every key the algorithm
     * consumes — `name`, `version`, `require`, `conflict`, `repositories`,
     * `extra`, `minimum-stability`, `prefer-stable`, and the special-cased
     * `config.platform`.
     *
     * Deliberately *not* verified against this package's own
     * `composer.json`/`composer.lock`: `version` participates in the hash, and
     * a release that bumps it without regenerating the lock would fail this
     * test for a reason having nothing to do with the algorithm. That has
     * happened before — the 2.5.4 → 2.6.0 bump left the lock's `content-hash`
     * untouched.
     *
     * @since 2.7.1
     */
    public function test_composer_content_hash_matches_composer_generated_lock(): void
    {
        $fixtureDir = __DIR__ . '/../../Fixtures/ComposerLockSync';

        $lock = json_decode( (string) file_get_contents( $fixtureDir . '/composer.lock' ), true );

        $manager    = new ApplicationUpdateManager;
        $reflection = new ReflectionClass( $manager );
        $method     = $reflection->getMethod( 'composerContentHash' );
        $method->setAccessible( true );

        $this->assertSame(
            $lock['content-hash'],
            $method->invoke( $manager, $fixtureDir . '/composer.json' ),
            'The framework must compute the same content-hash composer writes into the lock file.',
        );
    }

    /**
     * The sync check must accept the composer-generated fixture pair as-is —
     * an end-to-end pass over `verifyComposerFilesInSync()` against files real
     * composer produced, rather than hand-built JSON.
     *
     * @since 2.7.1
     */
    public function test_verify_composer_files_in_sync_accepts_a_real_composer_pair(): void
    {
        $fixtureDir = __DIR__ . '/../../Fixtures/ComposerLockSync';

        $target = $this->makeComposerPair(
            (string) file_get_contents( $fixtureDir . '/composer.json' ),
            (string) file_get_contents( $fixtureDir . '/composer.lock' ),
        );

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager    = new ApplicationUpdateManager;
            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'verifyComposerFilesInSync' );
            $method->setAccessible( true );

            $method->invoke( $manager, 'composer install --no-dev' );

            $this->assertTrue( true ); // No exception means the real pair was accepted.
        } finally {
            app()->setBasePath( $originalBase );
            $this->removeDirectory( $target );
        }
    }

    /**
     * Regression for #255 (Fix C): a positively-detected desync must abort with
     * a message naming the real cause, rather than letting composer emit its
     * "incorrectly merged or manually edited" guess.
     *
     * @since 2.7.1
     */
    public function test_verify_composer_files_in_sync_throws_when_lock_is_stale(): void
    {
        Process::fake();

        $target = $this->makeComposerPair(
            '{"require":{"artisanpack-ui/cms-framework":"^2.7.0"}}',
            '{"content-hash":"0000000000000000stalehash0000000","packages":[]}',
        );

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager = new class extends ApplicationUpdateManager {
                public function callRunComposerInstall(): void
                {
                    $this->runComposerInstall();
                }
            };

            $threw = false;

            try {
                $manager->callRunComposerInstall();
            } catch ( UpdateException $e ) {
                $threw = true;
                $this->assertStringContainsString( 'out of sync', $e->getMessage() );
                $this->assertStringContainsString( 'never writes one', $e->getMessage() );
                $this->assertStringContainsString( 'not a merge conflict', $e->getMessage() );
            }

            $this->assertTrue( $threw, 'A stale lock must abort the update before composer runs.' );

            Process::assertNothingRan();
        } finally {
            app()->setBasePath( $originalBase );
            $this->removeDirectory( $target );
        }
    }

    /**
     * The happy path: an in-sync pair passes the check and composer is invoked.
     *
     * @since 2.7.1
     */
    public function test_verify_composer_files_in_sync_passes_when_hashes_match(): void
    {
        $json   = '{"require":{"artisanpack-ui/cms-framework":"^2.7.0"}}';
        $target = $this->makeComposerPair( $json, null );

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager    = new ApplicationUpdateManager;
            $reflection = new ReflectionClass( $manager );

            $hashMethod = $reflection->getMethod( 'composerContentHash' );
            $hashMethod->setAccessible( true );
            $hash = $hashMethod->invoke( $manager, $target . '/composer.json' );

            file_put_contents(
                $target . '/composer.lock',
                json_encode( [
                    'content-hash' => $hash,
                    'packages'     => [],
                ] ),
            );

            $method = $reflection->getMethod( 'verifyComposerFilesInSync' );
            $method->setAccessible( true );
            $method->invoke( $manager, 'composer install --no-dev' );

            $this->assertTrue( true ); // No exception means the pair was accepted.
        } finally {
            app()->setBasePath( $originalBase );
            $this->removeDirectory( $target );
        }
    }

    /**
     * The check fails *open*. A missing lock, an unparseable lock, or a lock
     * with no `content-hash` is left for composer to adjudicate — a false alarm
     * must never block an update that would otherwise install cleanly.
     *
     * @since 2.7.1
     *
     * @dataProvider inconclusiveComposerLockProvider
     *
     * @param  string|null  $lockContents  Lock file contents, or null to omit the file.
     */
    public function test_verify_composer_files_in_sync_fails_open_when_inconclusive( ?string $lockContents ): void
    {
        $target = $this->makeComposerPair(
            '{"require":{"artisanpack-ui/cms-framework":"^2.7.0"}}',
            $lockContents,
        );

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager    = new ApplicationUpdateManager;
            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'verifyComposerFilesInSync' );
            $method->setAccessible( true );

            $method->invoke( $manager, 'composer install --no-dev' );

            $this->assertTrue( true ); // No exception means the check failed open.
        } finally {
            app()->setBasePath( $originalBase );
            $this->removeDirectory( $target );
        }
    }

    /**
     * Inconclusive lock states that must not abort the update.
     *
     * @since 2.7.1
     *
     * @return array<string, array{0: string|null}>
     */
    public static function inconclusiveComposerLockProvider(): array
    {
        return [
            'no lock file at all'         => [null],
            'unparseable lock'            => ['{ this is not json'],
            'lock without a hash key'     => ['{"packages":[]}'],
            'lock with a non-string hash' => ['{"content-hash":123,"packages":[]}'],
        ];
    }

    /**
     * The check can be switched off entirely, for hosts stuck behind a composer
     * release that changed the content-hash algorithm.
     *
     * @since 2.7.1
     */
    public function test_verify_composer_files_in_sync_can_be_disabled(): void
    {
        config( ['cms.updates.verify_composer_lock_sync' => false] );

        $target = $this->makeComposerPair(
            '{"require":{"artisanpack-ui/cms-framework":"^2.7.0"}}',
            '{"content-hash":"definitely-not-the-right-hash","packages":[]}',
        );

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager    = new ApplicationUpdateManager;
            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'verifyComposerFilesInSync' );
            $method->setAccessible( true );

            $method->invoke( $manager, 'composer install --no-dev' );

            $this->assertTrue( true ); // No exception means the check was skipped.
        } finally {
            app()->setBasePath( $originalBase );
            $this->removeDirectory( $target );
        }
    }

    /**
     * A host that has overridden `composer_install_command` to run `composer
     * update` has deliberately opted into re-resolving the tree, and a lock
     * that disagrees with `composer.json` is exactly what `update` reconciles.
     * The guard must not abort those hosts.
     *
     * @since 2.7.1
     */
    public function test_verify_composer_files_in_sync_skips_non_install_commands(): void
    {
        $target = $this->makeComposerPair(
            '{"require":{"artisanpack-ui/cms-framework":"^2.7.0"}}',
            '{"content-hash":"definitely-not-the-right-hash","packages":[]}',
        );

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager    = new ApplicationUpdateManager;
            $reflection = new ReflectionClass( $manager );
            $method     = $reflection->getMethod( 'verifyComposerFilesInSync' );
            $method->setAccessible( true );

            $method->invoke( $manager, 'composer update --no-dev --no-interaction' );

            $this->assertTrue( true ); // No exception means the guard stood down.

            // ...but the same stale pair must still abort an `install`.
            $this->expectException( UpdateException::class );
            $method->invoke( $manager, 'composer install --no-dev' );
        } finally {
            app()->setBasePath( $originalBase );
            $this->removeDirectory( $target );
        }
    }

    /**
     * Write a scratch base path holding a `composer.json` and, optionally, a
     * `composer.lock`. Returns the directory, which the caller must remove.
     *
     * @since 2.7.1
     *
     * @param  string  $json  Contents for `composer.json`.
     * @param  string|null  $lock  Contents for `composer.lock`, or null to omit it.
     *
     * @return string Absolute path to the scratch directory.
     */
    protected function makeComposerPair( string $json, ?string $lock ): string
    {
        $target = sys_get_temp_dir() . '/cmsfw-sync-' . bin2hex( random_bytes( 6 ) );
        mkdir( $target, 0755, true );

        file_put_contents( $target . '/composer.json', $json );

        if ( null !== $lock ) {
            file_put_contents( $target . '/composer.lock', $lock );
        }

        return $target;
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
        $app['config']->set( 'cms.updates.exclude_from_update', ['.env', 'storage', 'vendor']);
    }
}
