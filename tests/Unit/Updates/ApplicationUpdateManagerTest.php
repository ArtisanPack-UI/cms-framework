<?php

declare(strict_types=1);

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

        $checker = $this->createMock(UpdateChecker::class);
        $checker->method('checkForUpdate')->willReturn($updateInfo);

        $manager->setUpdateChecker($checker);

        $result = $manager->checkForUpdate();

        $this->assertInstanceOf(UpdateInfo::class, $result);
        $this->assertTrue($result->hasUpdate());
        $this->assertEquals('2.0.0', $result->latestVersion);
    }

    /**
     * Test manager throws exception when no update URL configured.
     *
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
     */
    public function test_path_exclusion_logic(): void
    {
        $manager = new ApplicationUpdateManager;

        $reflection = new ReflectionClass($manager);
        $method     = $reflection->getMethod('isPathExcluded');
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
     * @since 1.0.0
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

        $checker = $this->createMock(UpdateChecker::class);
        $checker->method('checkForUpdate')->willReturn($updateInfo);

        $manager->setUpdateChecker($checker);

        $result = $manager->checkForUpdate();

        $this->assertEquals('2.0.0', $result->latestVersion);
    }

    /**
     * Test verifyChecksum throws on mismatch.
     *
     * @since 2.0.0
     */
    public function test_verify_checksum_throws_on_mismatch(): void
    {
        $manager = new ApplicationUpdateManager;

        $zipPath = tempnam(sys_get_temp_dir(), 'cmsfw-update-');
        file_put_contents($zipPath, 'fake zip contents');

        try {
            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('verifyChecksum');
            $method->setAccessible(true);

            $this->expectException(UpdateException::class);
            $this->expectExceptionMessage('Checksum mismatch');

            $method->invoke($manager, $zipPath, str_repeat('0', 64));
        } finally {
            @unlink($zipPath);
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

        $zipPath = tempnam(sys_get_temp_dir(), 'cmsfw-update-');
        file_put_contents($zipPath, 'matching zip contents');

        try {
            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('verifyChecksum');
            $method->setAccessible(true);

            $method->invoke($manager, $zipPath, hash_file('sha256', $zipPath));

            $this->assertTrue(true); // No exception means success.
        } finally {
            @unlink($zipPath);
        }
    }

    /**
     * Test maybeVerifyChecksum logs a warning when the source omits a checksum.
     *
     * @since 2.0.0
     */
    public function test_maybe_verify_checksum_logs_warning_when_sha256_missing(): void
    {
        config(['cms.updates.verify_checksum' => true]);

        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
            sha256: null,
            metadata: ['source' => 'gitlab'],
        );

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Skipping update integrity verification')
                    && '2.0.0' === ($context['target_version'] ?? null)
                    && 'gitlab' === ($context['source'] ?? null);
            });

        $reflection = new ReflectionClass($manager);
        $method     = $reflection->getMethod('maybeVerifyChecksum');
        $method->setAccessible(true);

        $method->invoke($manager, '/does/not/matter.zip', $updateInfo, '2.0.0');
    }

    /**
     * Test maybeVerifyChecksum does not warn when verification is disabled.
     *
     * @since 2.0.0
     */
    public function test_maybe_verify_checksum_is_silent_when_disabled(): void
    {
        config(['cms.updates.verify_checksum' => false]);

        $manager = new ApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
            sha256: null,
        );

        Log::shouldReceive('warning')->never();

        $reflection = new ReflectionClass($manager);
        $method     = $reflection->getMethod('maybeVerifyChecksum');
        $method->setAccessible(true);

        $method->invoke($manager, '/does/not/matter.zip', $updateInfo, '2.0.0');
    }

    /**
     * Test that a fatal `\Error` during the update disables maintenance mode
     * and rethrows the error, rather than leaving the host stranded.
     *
     * @since 2.5.1
     */
    public function test_perform_update_disables_maintenance_mode_on_fatal_error(): void
    {
        config(['cms.updates.backup_enabled' => false]);

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

        $checker = $this->createMock(UpdateChecker::class);
        $checker->method('checkForUpdate')->willReturn($updateInfo);
        $checker->method('downloadUpdate')->willThrowException(new Error('Simulated fatal error'));

        $manager->setUpdateChecker($checker);

        try {
            $manager->performUpdate();
            $this->fail('Expected Error to be thrown.');
        } catch (Error $e) {
            $this->assertSame('Simulated fatal error', $e->getMessage());
        }

        $this->assertSame(['enable', 'disable'], $manager->modeCalls);
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
        $tempRoot = sys_get_temp_dir().'/cmsfw-extract-'.bin2hex(random_bytes(6));
        $target   = $tempRoot.'/target';
        mkdir($target, 0755, true);

        $zipPath  = $tempRoot.'/update.zip';
        $largeBig = str_repeat('x', 65536);

        $zip = new ZipArchive;
        $this->assertTrue(true === $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('release-root/app/Model.php', "<?php\n".$largeBig);
        $zip->addFromString('release-root/routes/web.php', "<?php\nRoute::get('/', fn () => 'ok');\n");
        $zip->close();

        $manager = new class($target) extends ApplicationUpdateManager {
            public function __construct(protected string $extractRoot) {}

            public function extractInto(string $zipPath): void
            {
                $this->extractUpdate($zipPath);
            }
        };

        // Point the application base path to our temp target so `base_path()`
        // inside extractUpdate resolves to a scratch directory instead of the
        // testbench root.
        $originalBase = base_path();
        app()->setBasePath($target);

        try {
            $manager->extractInto($zipPath);

            $this->assertFileExists($target.'/app/Model.php');
            $this->assertFileExists($target.'/routes/web.php');
            $this->assertSame(
                strlen("<?php\n".$largeBig),
                filesize($target.'/app/Model.php'),
                'Streamed extraction should produce a byte-for-byte copy of the ZIP entry.',
            );
        } finally {
            app()->setBasePath($originalBase);
            @unlink($zipPath);
            $this->removeDirectory($target);
            @rmdir($tempRoot);
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
        config(['cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND]);

        $original = getenv('COMPOSER_BINARY');
        putenv('COMPOSER_BINARY=/opt/homebrew/bin/composer');

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('resolveComposerCommand');
            $method->setAccessible(true);

            $command = $method->invoke($manager);

            $this->assertStringStartsWith(escapeshellarg(PHP_BINARY).' ', $command);
            $this->assertStringContainsString(escapeshellarg('/opt/homebrew/bin/composer'), $command);
            $this->assertStringEndsWith(' '.ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_ARGS, $command);
        } finally {
            putenv(false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY='.$original);
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
        config(['cms.updates.composer_install_command' => $override]);

        $original = getenv('COMPOSER_BINARY');
        putenv('COMPOSER_BINARY');

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('resolveComposerCommand');
            $method->setAccessible(true);

            $this->assertSame($override, $method->invoke($manager));
        } finally {
            putenv(false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY='.$original);
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
        config(['cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND]);

        $original = getenv('COMPOSER_BINARY');
        putenv('COMPOSER_BINARY');

        try {
            $manager = new class extends ApplicationUpdateManager {
                protected function discoverComposerBinary(): ?string
                {
                    return '/discovered/path/composer';
                }
            };

            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('resolveComposerCommand');
            $method->setAccessible(true);

            $command = $method->invoke($manager);

            $this->assertStringStartsWith(escapeshellarg(PHP_BINARY).' ', $command);
            $this->assertStringContainsString(escapeshellarg('/discovered/path/composer'), $command);
            $this->assertStringEndsWith(' '.ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_ARGS, $command);
        } finally {
            putenv(false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY='.$original);
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
        config(['cms.updates.composer_install_command' => ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND]);

        $original = getenv('COMPOSER_BINARY');
        putenv('COMPOSER_BINARY');

        try {
            $manager = new class extends ApplicationUpdateManager {
                protected function discoverComposerBinary(): ?string
                {
                    return null;
                }
            };

            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('resolveComposerCommand');
            $method->setAccessible(true);

            $this->assertSame(
                ApplicationUpdateManager::DEFAULT_COMPOSER_INSTALL_COMMAND,
                $method->invoke($manager),
            );
        } finally {
            putenv(false === $original ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY='.$original);
        }
    }

    /**
     * Regression for #225: the discovery candidate list must include the
     * documented common install paths in preference order so the diagnostic
     * message stays honest and hosts see the same list the framework tried.
     *
     * @since 2.5.3
     */
    public function test_composer_candidate_paths_covers_documented_locations(): void
    {
        $original = getenv('HOME');
        putenv('HOME=/home/tester');

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('composerCandidatePaths');
            $method->setAccessible(true);

            $candidates = $method->invoke($manager);

            $this->assertSame(
                [
                    '/usr/local/bin/composer',
                    '/opt/homebrew/bin/composer',
                    '/home/tester/.composer/vendor/bin/composer',
                    '/home/tester/.config/composer/vendor/bin/composer',
                    '/usr/bin/composer',
                ],
                $candidates,
            );
        } finally {
            putenv(false === $original ? 'HOME' : 'HOME='.$original);
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
        $tempDir = sys_get_temp_dir().'/cmsfw-composer-'.bin2hex(random_bytes(6));
        mkdir($tempDir, 0755, true);

        $missing        = $tempDir.'/missing-composer';
        $nonExecutable  = $tempDir.'/non-executable-composer';
        $executableHit  = $tempDir.'/executable-composer';
        $laterCandidate = $tempDir.'/never-reached-composer';

        file_put_contents($nonExecutable, "#!/bin/sh\n");
        chmod($nonExecutable, 0644);

        file_put_contents($executableHit, "#!/bin/sh\n");
        chmod($executableHit, 0755);

        file_put_contents($laterCandidate, "#!/bin/sh\n");
        chmod($laterCandidate, 0755);

        try {
            $manager = new class([$missing, $nonExecutable, $executableHit, $laterCandidate]) extends ApplicationUpdateManager {
                public function __construct(protected array $paths) {}

                protected function composerCandidatePaths(): array
                {
                    return $this->paths;
                }

                public function callDiscover(): ?string
                {
                    return $this->discoverComposerBinary();
                }
            };

            $this->assertSame($executableHit, $manager->callDiscover());
        } finally {
            @unlink($missing);
            @unlink($nonExecutable);
            @unlink($executableHit);
            @unlink($laterCandidate);
            @rmdir($tempDir);
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
     * @since 2.5.3
     */
    public function test_rollback_throws_composer_verification_failed_when_version_check_fails(): void
    {
        Process::fake([
            '*--version*' => Process::result('', 'command not found', 127),
        ]);

        $manager = new class extends ApplicationUpdateManager {
            protected function resolveComposerBinaryForVerification(): ?string
            {
                return '/opt/homebrew/bin/composer';
            }
        };

        $backupPath = tempnam(sys_get_temp_dir(), 'cmsfw-backup-').'.zip';
        $zip        = new ZipArchive;
        $this->assertTrue(true === $zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('placeholder.txt', 'ok');
        $zip->close();

        try {
            $this->expectException(UpdateException::class);
            $this->expectExceptionMessage('could not be executed');

            $manager->rollback($backupPath);
        } finally {
            @unlink($backupPath);
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
                $stub = tempnam(sys_get_temp_dir(), 'cmsfw-backup-');
                @unlink($stub);
                $this->backupPath = $stub.'.zip';
                file_put_contents($this->backupPath, 'not-a-zip');
            }

            protected function disableMaintenanceMode(): void
            {
                // No-op; nothing to disable in this unit context.
            }

            public function callHandleFailure(Throwable $e): void
            {
                $this->handleUpdateFailure($e);
            }
        };

        try {
            $original = new RuntimeException('composer install failed because it ran out of memory');

            try {
                $manager->callHandleFailure($original);
                $this->fail('Expected UpdateException from rollback.');
            } catch (UpdateException $e) {
                $this->assertStringContainsString('Rollback failed', $e->getMessage());
                $this->assertStringContainsString('ran out of memory', $e->getMessage());
                $this->assertStringContainsString('Manual intervention required', $e->getMessage());
            }
        } finally {
            $reflection = new ReflectionClass($manager);
            $property   = $reflection->getProperty('backupPath');
            $property->setAccessible(true);
            $path = $property->getValue($manager);
            if (is_string($path)) {
                @unlink($path);
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
        $tempDir = sys_get_temp_dir().'/cmsfw-php-'.bin2hex(random_bytes(6));
        mkdir($tempDir, 0755, true);

        $fpm = $tempDir.'/php84-fpm';
        $cli = $tempDir.'/php';

        file_put_contents($fpm, "#!/bin/sh\n");
        chmod($fpm, 0755);
        file_put_contents($cli, "#!/bin/sh\n");
        chmod($cli, 0755);

        $original = getenv('CMS_PHP_BINARY');
        putenv('CMS_PHP_BINARY');

        try {
            $manager = new class([$fpm, $cli], 'fpm-fcgi') extends ApplicationUpdateManager {
                public function __construct(protected array $paths, protected string $sapi) {}

                protected function phpCandidatePaths(): array
                {
                    return $this->paths;
                }

                public function callResolvePhpBinary(): string
                {
                    // Bypass the PHP_SAPI short-circuit by shadowing the
                    // condition — we can't mutate the runtime SAPI constant,
                    // so we run the discovery path directly.
                    foreach ($this->phpCandidatePaths() as $candidate) {
                        if ($this->isCliPhpBinary($candidate)) {
                            return $candidate;
                        }
                    }

                    return PHP_BINARY;
                }
            };

            $this->assertSame($cli, $manager->callResolvePhpBinary());
        } finally {
            @unlink($fpm);
            @unlink($cli);
            @rmdir($tempDir);
            putenv(false === $original ? 'CMS_PHP_BINARY' : 'CMS_PHP_BINARY='.$original);
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
        $original = getenv('CMS_PHP_BINARY');
        putenv('CMS_PHP_BINARY=/custom/path/to/php');

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('resolvePhpBinary');
            $method->setAccessible(true);

            $this->assertSame('/custom/path/to/php', $method->invoke($manager));
        } finally {
            putenv(false === $original ? 'CMS_PHP_BINARY' : 'CMS_PHP_BINARY='.$original);
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
        $original = getenv('HOME');
        putenv('HOME=/home/tester');

        try {
            $manager = new ApplicationUpdateManager;

            $reflection = new ReflectionClass($manager);
            $method     = $reflection->getMethod('phpCandidatePaths');
            $method->setAccessible(true);

            $this->assertSame(
                [
                    '/home/tester/Library/Application Support/Herd/bin/php',
                    '/opt/homebrew/bin/php',
                    '/usr/local/bin/php',
                    '/usr/bin/php',
                ],
                $method->invoke($manager),
            );
        } finally {
            putenv(false === $original ? 'HOME' : 'HOME='.$original);
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

        $this->assertStringContainsString('/opt/homebrew/bin/composer', $message);
        $this->assertStringContainsString('/Users/tester/Herd/bin/php84-fpm', $message);
        $this->assertStringContainsString('Exit code: 64', $message);
        $this->assertStringContainsString('Usage: php84-fpm', $message);
        $this->assertStringContainsString('CMS_PHP_BINARY', $message);
    }

    /**
     * Recursively remove a directory used by extraction tests.
     *
     * @since 2.5.2
     */
    protected function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $full = $path.DIRECTORY_SEPARATOR.$item;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }

    /**
     * Define environment setup.
     *
     * @since 1.0.0
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
}
