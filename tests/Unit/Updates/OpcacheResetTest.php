<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Providers\CoreServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\UpdateStateStore;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use ArtisanPackUI\CMSFramework\Tests\Support\RecordingApplicationUpdateManager;
use Illuminate\Foundation\Application;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use ZipArchive;

/**
 * OPcache Reset Tests
 *
 * Regression coverage for #425: the self-updater cleared Laravel's caches but
 * never OPcache, so with `opcache.validate_timestamps=0` PHP-FPM kept running
 * the pre-update bytecode for every file it had already compiled, and the
 * site ran a mix of old and new code until PHP-FPM was reloaded.
 *
 * @since 2.15.6
 */
class OpcacheResetTest extends TestCase
{
    /**
     * Absolute path to the state file used by the test.
     *
     * @since 2.15.6
     */
    protected string $statePath = '';

    /**
     * Point the state store at a temporary file for each test.
     *
     * @since 2.15.6
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath = sys_get_temp_dir() . '/cmsfw-update-state-' . uniqid() . '.json';

        config( [
            'cms.updates.state_path'                    => $this->statePath,
            'cms.updates.backup_enabled'                => true,
            'cms.updates.lift_maintenance_on_interrupt' => true,
        ] );
    }

    /**
     * Remove the temporary state file.
     *
     * @since 2.15.6
     */
    protected function tearDown(): void
    {
        foreach ( [$this->statePath, $this->statePath . '.lock'] as $path ) {
            if ( '' !== $this->statePath && file_exists( $path ) ) {
                @unlink( $path );
            }
        }

        parent::tearDown();
    }

    /**
     * Test that an update run inside PHP-FPM resets OPcache once every file is
     * final, before the site comes back up, and records that no reload is
     * needed.
     *
     * @since 2.15.6
     */
    public function test_successful_update_under_fpm_resets_opcache_before_lifting_maintenance(): void
    {
        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'fpm-fcgi';
        $manager->opcacheResetResult = true;

        $this->assertTrue( $manager->performUpdate() );

        $this->assertSame( ['cleanup', 'opcache-reset', 'disable'], array_slice( $manager->calls, -3 ) );
        $this->assertSame( 1, count( array_keys( $manager->calls, 'opcache-reset', true ) ) );

        $this->assertFalse( $manager->phpReloadRequired() );
        $this->assertFalse( $manager->updateState()['php_reload_required'] );
        $this->assertSame( 'web-request', $manager->opcacheResetMethod() );
        $this->assertSame( 'web-request', $manager->updateState()['opcache_reset_method'] );
        $this->assertNotContains( 'web-opcache-reset', $manager->calls, 'A web request needs no second route to PHP-FPM.' );
        $this->assertSame( UpdateRunStatus::Completed->value, $manager->updateState()['status'] );
    }

    /**
     * Test that an update run from the CLI (the `update:perform` command or a
     * queue worker), whose OPcache is not PHP-FPM's, warns that PHP-FPM must
     * be reloaded and records it for `update:status` when no mechanism could
     * reach the web server.
     *
     * @since 2.15.6
     */
    public function test_successful_update_under_cli_flags_a_php_fpm_reload(): void
    {
        Log::spy();

        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'cli';
        $manager->opcacheResetResult = true;

        $this->assertTrue( $manager->performUpdate() );

        $this->assertContains( 'opcache-reset', $manager->calls, 'The worker\'s own OPcache is still reset.' );
        $this->assertContains( 'web-opcache-reset', $manager->calls );
        $this->assertTrue( $manager->phpReloadRequired() );
        $this->assertTrue( $manager->updateState()['php_reload_required'] );
        $this->assertNull( $manager->opcacheResetMethod() );
        $this->assertNull( $manager->updateState()['opcache_reset_method'] );

        Log::shouldHaveReceived( 'warning' )->withArgs(
            fn ( string $message ): bool => str_contains( $message, 'reload PHP-FPM' ),
        );
    }

    /**
     * Test that a CLI update that reaches PHP-FPM records how, needs no
     * reload and logs no warning (#429).
     *
     * @since 2.16.0
     */
    public function test_successful_update_under_cli_records_how_the_web_opcache_was_reset(): void
    {
        Log::spy();

        $manager                        = $this->managerWithUpdateAvailable();
        $manager->sapi                  = 'cli';
        $manager->opcacheResetResult    = true;
        $manager->webOpcacheResetMethod = 'fastcgi';

        $this->assertTrue( $manager->performUpdate() );

        $this->assertSame( ['cleanup', 'opcache-reset', 'web-opcache-reset', 'disable'], array_slice( $manager->calls, -4 ) );
        $this->assertFalse( $manager->phpReloadRequired() );
        $this->assertSame( 'fastcgi', $manager->opcacheResetMethod() );
        $this->assertFalse( $manager->updateState()['php_reload_required'] );
        $this->assertSame( 'fastcgi', $manager->updateState()['opcache_reset_method'] );

        Log::shouldNotHaveReceived( 'warning' );
    }

    /**
     * Test that a CLI rollback also reaches PHP-FPM.
     *
     * @since 2.16.0
     */
    public function test_cli_rollback_resets_the_web_opcache(): void
    {
        $manager                        = new RecordingApplicationUpdateManager;
        $manager->sapi                  = 'cli';
        $manager->webOpcacheResetMethod = 'command';

        $manager->callResetOpcache();

        $this->assertSame( ['opcache-reset', 'web-opcache-reset'], $manager->calls );
        $this->assertSame( 'command', $manager->opcacheResetMethod() );
        $this->assertFalse( $manager->phpReloadRequired() );
    }

    /**
     * Test that a reset refused under PHP-FPM (`opcache.restrict_api`) is
     * reported rather than treated as success.
     *
     * @since 2.15.6
     */
    public function test_failed_reset_under_fpm_flags_a_php_fpm_reload(): void
    {
        Log::spy();

        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'fpm-fcgi';
        $manager->opcacheResetResult = false;

        $this->assertTrue( $manager->performUpdate() );

        $this->assertTrue( $manager->phpReloadRequired() );
        $this->assertTrue( $manager->updateState()['php_reload_required'] );

        Log::shouldHaveReceived( 'warning' )->withArgs(
            fn ( string $message ): bool => str_contains( $message, 'OPcache could not be reset' ),
        );
    }

    /**
     * Test that a web server without OPcache enabled needs no reload and
     * logs no warning.
     *
     * @since 2.15.6
     */
    public function test_disabled_opcache_under_fpm_needs_no_reload(): void
    {
        Log::spy();

        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'fpm-fcgi';
        $manager->opcacheResetResult = null;

        $this->assertTrue( $manager->performUpdate() );

        $this->assertFalse( $manager->phpReloadRequired() );
        $this->assertFalse( $manager->updateState()['php_reload_required'] );

        Log::shouldNotHaveReceived( 'warning' );
    }

    /**
     * Test that a failure after the new code and schema are applied (the
     * finish-forward case) still resets OPcache, since the site comes back up
     * on the new code.
     *
     * @since 2.15.6
     */
    public function test_finish_forward_failure_resets_opcache(): void
    {
        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'fpm-fcgi';
        $manager->opcacheResetResult = true;
        $manager->failAtStep         = UpdateStep::Cleanup;

        $this->expectFailure( $manager );

        $this->assertContains( 'opcache-reset', $manager->calls );
        $this->assertTrue( $manager->updateState()['finish_forward'] );
    }

    /**
     * Test that a failure before extraction, which never touched the tree,
     * leaves OPcache alone.
     *
     * @since 2.15.6
     */
    public function test_failure_before_extraction_does_not_reset_opcache(): void
    {
        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'fpm-fcgi';
        $manager->opcacheResetResult = true;
        $manager->failAtStep         = UpdateStep::Download;

        $this->expectFailure( $manager );

        $this->assertNotContains( 'opcache-reset', $manager->calls );
        $this->assertArrayNotHasKey( 'php_reload_required', $manager->updateState() );
    }

    /**
     * Test that a failure with no snapshot to restore resets OPcache, since
     * the tree may be half-applied.
     *
     * @since 2.15.6
     */
    public function test_failure_without_a_snapshot_resets_opcache(): void
    {
        config( ['cms.updates.backup_enabled' => false] );

        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'fpm-fcgi';
        $manager->opcacheResetResult = true;
        $manager->failAtStep         = UpdateStep::ComposerInstall;

        $this->expectFailure( $manager );

        $this->assertContains( 'opcache-reset', $manager->calls );
    }

    /**
     * Test that restoring a snapshot resets OPcache once the restored files
     * and dependencies are in place.
     *
     * @since 2.15.6
     */
    public function test_rollback_resets_opcache_after_restoring_the_snapshot(): void
    {
        $tempRoot = sys_get_temp_dir() . '/cmsfw-opcache-rollback-' . bin2hex( random_bytes( 6 ) );
        $target   = $tempRoot . '/target';
        mkdir( $target . '/app', 0755, true );

        $backupPath = $tempRoot . '/backup.zip';
        $backup     = new ZipArchive;
        $this->assertTrue( true === $backup->open( $backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
        $backup->addFromString( 'app/Old.php', "<?php\n// old\n" );
        $backup->close();

        $manager = new class extends RecordingApplicationUpdateManager {
            protected function verifyComposerBinaryAvailable(): void
            {
            }
        };
        $manager->sapi               = 'fpm-fcgi';
        $manager->opcacheResetResult = true;

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            $manager->rollback( $backupPath );

            $this->assertFileExists( $target . '/app/Old.php' );
            $this->assertSame( ['composer', 'caches', 'opcache-reset'], $manager->calls );
            $this->assertFalse( $manager->phpReloadRequired() );
        } finally {
            app()->setBasePath( $originalBase );
            @unlink( $target . '/app/Old.php' );
            @rmdir( $target . '/app' );
            @rmdir( $target );
            @unlink( $backupPath );
            @rmdir( $tempRoot );
        }
    }

    /**
     * Test that the caches the site had compiled before the update are
     * rebuilt, each in a fresh PHP process so it is compiled from the new
     * code rather than the classes this process still holds.
     *
     * @since 2.15.6
     */
    public function test_successful_update_rebuilds_the_caches_that_were_in_place(): void
    {
        Process::fake();

        $manager                         = $this->managerWithUpdateAvailable();
        $manager->cachedArtifactCommands = ['config:cache', 'route:cache'];

        $this->assertTrue( $manager->performUpdate() );

        Process::assertRanTimes( fn ( PendingProcess $process ): bool => $this->isArtisan( $process, 'config:cache' ), 1 );
        Process::assertRanTimes( fn ( PendingProcess $process ): bool => $this->isArtisan( $process, 'route:cache' ), 1 );
        Process::assertNotRan( fn ( PendingProcess $process ): bool => $this->isArtisan( $process, 'event:cache' ) );
    }

    /**
     * Test that caches are rebuilt only after the removed-paths reconcile, so
     * an event cache cannot name a listener the release deleted, and before
     * OPcache is reset.
     *
     * @since 2.15.6
     */
    public function test_caches_are_rebuilt_after_the_removed_paths_reconcile(): void
    {
        $manager = new class extends RecordingApplicationUpdateManager {
            protected function applyRemovedPathsManifest(): void
            {
                $this->calls[] = 'removed-paths';
            }

            protected function rebuildCaches( array $commands ): void
            {
                $this->calls[] = 'rebuild:' . implode( ',', $commands );
            }
        };

        $checker = $this->createMock( UpdateChecker::class );
        $checker->method( 'checkForUpdate' )->willReturn( new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        ) );
        $checker->method( 'downloadUpdate' )->willReturn( '/tmp/does-not-matter.zip' );

        $manager->setUpdateChecker( $checker );
        $manager->sapi                   = 'fpm-fcgi';
        $manager->cachedArtifactCommands = ['config:cache', 'event:cache'];

        $this->assertTrue( $manager->performUpdate() );

        $this->assertSame(
            ['caches', 'cleanup', 'removed-paths', 'rebuild:config:cache,event:cache', 'opcache-reset', 'disable'],
            array_slice( $manager->calls, -6 ),
        );
    }

    /**
     * Test that nothing is rebuilt when nothing was cached, so an update does
     * not start caching config on a site that deliberately runs uncached.
     *
     * @since 2.15.6
     */
    public function test_successful_update_rebuilds_nothing_when_nothing_was_cached(): void
    {
        Process::fake();

        $manager = $this->managerWithUpdateAvailable();

        $this->assertTrue( $manager->performUpdate() );

        Process::assertNothingRan();
    }

    /**
     * Test that a failed cache rebuild is logged and does not fail an update
     * that has already been applied.
     *
     * @since 2.15.6
     */
    public function test_failed_cache_rebuild_is_logged_and_does_not_fail_the_update(): void
    {
        Log::spy();
        Process::fake( ['*' => Process::result( errorOutput: 'Unable to prepare route [x] for serialization.', exitCode: 1 )] );

        $manager                         = $this->managerWithUpdateAvailable();
        $manager->cachedArtifactCommands = ['route:cache'];

        $this->assertTrue( $manager->performUpdate() );
        $this->assertSame( UpdateRunStatus::Completed->value, $manager->updateState()['status'] );

        Log::shouldHaveReceived( 'warning' )->withArgs(
            fn ( string $message, array $context = [] ): bool => str_contains( $message, 'php artisan route:cache' )
                && 'Unable to prepare route [x] for serialization.' === $context['error'],
        );
    }

    /**
     * Test that the cache commands are read from the caches actually on disk.
     *
     * @since 2.15.6
     */
    public function test_cached_artifacts_reports_the_caches_on_disk(): void
    {
        $manager = new class extends ApplicationUpdateManager {
            /**
             * @return array<int, string>
             */
            public function callCachedArtifacts(): array
            {
                return $this->cachedArtifacts();
            }
        };

        $routesPath = app()->getCachedRoutesPath();

        $this->assertFileDoesNotExist( $routesPath );
        $this->assertSame( [], $manager->callCachedArtifacts() );

        @mkdir( dirname( $routesPath ), 0755, true );
        file_put_contents( $routesPath, "<?php\n" );

        try {
            $this->assertSame( ['route:cache'], $manager->callCachedArtifacts() );
        } finally {
            @unlink( $routesPath );
        }
    }

    /**
     * Test that the reload flag is only merged into an existing record, so a
     * manual rollback with no update on record leaves no state file behind.
     *
     * @since 2.15.6
     */
    public function test_reload_flag_is_not_recorded_without_an_update_record(): void
    {
        $store = new UpdateStateStore;
        $store->recordPhpReloadRequired( true );

        $this->assertNull( $store->read() );
        $this->assertFileDoesNotExist( $this->statePath );

        $store->begin( '2.0.0', '1.0.0' );
        $store->recordPhpReloadRequired( true );

        $this->assertTrue( $store->read()['php_reload_required'] );
    }

    /**
     * Test that `update:perform` tells the operator to reload PHP-FPM after a
     * CLI update.
     *
     * @since 2.15.6
     */
    public function test_perform_command_tells_the_operator_to_reload_php_fpm(): void
    {
        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'cli';
        $manager->opcacheResetResult = null;

        $this->app->instance( ApplicationUpdateManager::class, $manager );

        $this->artisan( 'update:perform', ['--force' => true] )
            ->expectsOutputToContain( 'Reload PHP-FPM' )
            ->assertSuccessful();
    }

    /**
     * Test that `update:perform` says how a CLI update reset the web server's
     * OPcache, and asks for no reload.
     *
     * @since 2.16.0
     */
    public function test_perform_command_reports_how_the_web_opcache_was_reset(): void
    {
        $manager                        = $this->managerWithUpdateAvailable();
        $manager->sapi                  = 'cli';
        $manager->webOpcacheResetMethod = 'loopback';

        $this->app->instance( ApplicationUpdateManager::class, $manager );

        $this->artisan( 'update:perform', ['--force' => true] )
            ->expectsOutputToContain( 'OPcache reset for the web server (loopback)' )
            ->doesntExpectOutputToContain( 'Reload PHP-FPM' )
            ->assertSuccessful();
    }

    /**
     * Test that `update:perform` stays quiet when OPcache was reset for the
     * web server.
     *
     * @since 2.15.6
     */
    public function test_perform_command_omits_the_reload_notice_when_not_needed(): void
    {
        $manager                     = $this->managerWithUpdateAvailable();
        $manager->sapi               = 'fpm-fcgi';
        $manager->opcacheResetResult = true;

        $this->app->instance( ApplicationUpdateManager::class, $manager );

        $this->artisan( 'update:perform', ['--force' => true] )
            ->doesntExpectOutputToContain( 'Reload PHP-FPM' )
            ->assertSuccessful();
    }

    /**
     * Test that `update:status` surfaces a recorded reload requirement.
     *
     * @since 2.15.6
     */
    public function test_status_command_surfaces_a_required_reload(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::DisableMaintenanceMode );
        $store->recordPhpReloadRequired( true );
        $store->markStatus( UpdateRunStatus::Completed );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'reload PHP-FPM' )
            ->assertSuccessful();
    }

    /**
     * Test that `update:status` shows how the web server's OPcache was reset.
     *
     * @since 2.16.0
     */
    public function test_status_command_shows_the_opcache_reset_method(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::DisableMaintenanceMode );
        $store->recordOpcacheReset( 'fastcgi' );
        $store->markStatus( UpdateRunStatus::Completed );

        $this->assertFalse( $store->read()['php_reload_required'] );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'Over FastCGI to PHP-FPM' )
            ->doesntExpectOutputToContain( 'reload PHP-FPM' )
            ->assertSuccessful();
    }

    /**
     * Test that `update:status` says nothing about PHP-FPM when the reset
     * reached the web server.
     *
     * @since 2.15.6
     */
    public function test_status_command_omits_the_notice_when_no_reload_is_needed(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::DisableMaintenanceMode );
        $store->recordPhpReloadRequired( false );
        $store->markStatus( UpdateRunStatus::Completed );

        $this->artisan( 'update:status' )
            ->doesntExpectOutputToContain( 'reload PHP-FPM' )
            ->assertSuccessful();
    }

    /**
     * Register the core provider so the update commands are available.
     *
     * @since 2.15.6
     *
     * @param  Application  $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders( $app ): array
    {
        return [CoreServiceProvider::class];
    }

    /**
     * Run an update the double is set to fail, asserting the failure surfaces.
     *
     * @since 2.15.6
     *
     * @param  RecordingApplicationUpdateManager  $manager  Manager with `failAtStep` set.
     */
    protected function expectFailure( RecordingApplicationUpdateManager $manager ): void
    {
        try {
            $manager->performUpdate();
            $this->fail( 'Expected the simulated failure to propagate.' );
        } catch ( RuntimeException $e ) {
            $this->assertStringContainsString( 'Simulated failure', $e->getMessage() );
        }
    }

    /**
     * Whether a faked process ran the given `artisan` command from the
     * application root.
     *
     * @since 2.15.6
     *
     * @param  PendingProcess  $process  The recorded process.
     * @param  string  $command  The expected `artisan` command.
     *
     * @return bool True when it matches.
     */
    protected function isArtisan( PendingProcess $process, string $command ): bool
    {
        return is_array( $process->command )
            && ['artisan', $command] === array_slice( $process->command, 1 )
            && base_path() === $process->path;
    }

    /**
     * Build a recording manager whose checker offers 1.0.0 → 2.0.0.
     *
     * @since 2.15.6
     *
     * @return RecordingApplicationUpdateManager Manager ready to update.
     */
    protected function managerWithUpdateAvailable(): RecordingApplicationUpdateManager
    {
        $manager = new RecordingApplicationUpdateManager;

        $checker = $this->createMock( UpdateChecker::class );
        $checker->method( 'checkForUpdate' )->willReturn( new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        ) );
        $checker->method( 'downloadUpdate' )->willReturn( '/tmp/does-not-matter.zip' );

        $manager->setUpdateChecker( $checker );

        return $manager;
    }
}
