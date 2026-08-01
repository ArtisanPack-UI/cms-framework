<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use ArtisanPackUI\CMSFramework\Tests\Support\RecordingApplicationUpdateManager;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use RuntimeException;

/**
 * Update Interruption Guard Tests
 *
 * Regression coverage for #256: `performUpdate()` had no execution-time guard,
 * so PHP's default 30s `max_execution_time` under FPM killed it mid-flight.
 * The resulting fatal is raised at shutdown rather than thrown, so the catch
 * block never ran, no rollback happened, and the site was left serving 503s
 * indefinitely.
 *
 * @since 2.7.1
 */
class UpdateInterruptionGuardTest extends TestCase
{
    /**
     * Absolute path to the state file used by the test.
     *
     * @since 2.7.1
     */
    protected string $statePath = '';

    /**
     * Point the state store at a temporary file for each test.
     *
     * @since 2.7.1
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
     * @since 2.7.1
     */
    protected function tearDown(): void
    {
        if ( '' !== $this->statePath && file_exists( $this->statePath ) ) {
            @unlink( $this->statePath );
        }

        parent::tearDown();
    }

    /**
     * Test that `performUpdate()` lifts PHP's execution-time limit. Without
     * this the parent request dies after 30s under FPM while the composer
     * child is still inside its own 600s budget.
     *
     * @since 2.7.1
     */
    public function test_raise_execution_limits_lifts_max_execution_time(): void
    {
        $original = ini_get( 'max_execution_time' );

        try {
            ini_set( 'max_execution_time', '30' );

            $manager = new RecordingApplicationUpdateManager;
            $manager->callRaiseExecutionLimits();

            $this->assertSame( '0', ini_get( 'max_execution_time' ) );
        } finally {
            ini_set( 'max_execution_time', false === $original ? '0' : $original );
        }
    }

    /**
     * Test that `performUpdate()` opts out of connection-abort handling, so
     * closing the browser tab mid-update doesn't strand the site in
     * maintenance mode.
     *
     * @since 2.7.1
     */
    public function test_raise_execution_limits_ignores_user_abort(): void
    {
        $original = ignore_user_abort();

        try {
            ignore_user_abort( false );

            $manager = new RecordingApplicationUpdateManager;
            $manager->callRaiseExecutionLimits();

            $this->assertSame( 1, ignore_user_abort() );
        } finally {
            ignore_user_abort( 1 === $original );
        }
    }

    /**
     * Test that a successful update records every step in order and finishes
     * with a `completed` status.
     *
     * @since 2.7.1
     */
    public function test_successful_update_records_every_step_and_completes(): void
    {
        $manager = $this->managerWithUpdateAvailable();

        $this->assertTrue( $manager->performUpdate() );

        $this->assertSame(
            array_map( fn ( UpdateStep $step ): string => $step->value, UpdateStep::cases() ),
            array_keys( $manager->stateAtStep ),
        );

        // Each snapshot must name the step that was in flight when it was taken.
        foreach ( UpdateStep::cases() as $step ) {
            $this->assertSame( $step->value, $manager->stateAtStep[ $step->value ]['step'] );
            $this->assertSame( $step->number(), $manager->stateAtStep[ $step->value ]['step_number'] );
        }

        $state = $manager->updateState();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateRunStatus::Completed->value, $state['status'] );
        $this->assertSame( '2.0.0', $state['target_version'] );
        $this->assertNull( $state['error'] );
    }

    /**
     * Test that a caught failure records a `failed` status and the error, and
     * still leaves maintenance mode lifted.
     *
     * @since 2.7.1
     */
    public function test_caught_failure_records_failed_status(): void
    {
        $manager             = $this->managerWithUpdateAvailable();
        $manager->failAtStep = UpdateStep::ComposerInstall;

        try {
            $manager->performUpdate();
            $this->fail( 'Expected the simulated failure to propagate.' );
        } catch ( RuntimeException $e ) {
            $this->assertStringContainsString( 'Simulated failure', $e->getMessage() );
        }

        $state = $manager->updateState();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateRunStatus::Failed->value, $state['status'] );
        $this->assertSame( UpdateStep::ComposerInstall->value, $state['step'] );
        $this->assertStringContainsString( 'Simulated failure', (string) $state['error'] );
        $this->assertFalse( $manager->believesMaintenanceModeIsActive() );
    }

    /**
     * Test that the shutdown guard lifts maintenance mode when the process
     * died mid-update. This is the core #256 regression — before the fix a
     * timed-out request left the site returning 503 to every visitor with no
     * automatic way back.
     *
     * @since 2.7.1
     */
    public function test_shutdown_guard_lifts_maintenance_mode_after_a_mid_flight_death(): void
    {
        Log::spy();

        $manager = new RecordingApplicationUpdateManager;
        $manager->simulateInFlight( UpdateStep::Migrations );

        $manager->handleInterruptedUpdate();

        $this->assertContains( 'disable', $manager->calls );
        $this->assertFalse( $manager->believesMaintenanceModeIsActive() );

        Log::shouldHaveReceived( 'critical' );
    }

    /**
     * Test that the shutdown guard records where the update died, so the
     * operator can tell "died at step 7" from "manually put into maintenance
     * mode".
     *
     * @since 2.7.1
     */
    public function test_shutdown_guard_records_the_interrupted_step(): void
    {
        Log::spy();

        $manager = new RecordingApplicationUpdateManager;
        $manager->simulateInFlight( UpdateStep::Migrations );
        $manager->handleInterruptedUpdate();

        $state = $manager->updateState();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateRunStatus::Interrupted->value, $state['status'] );
        $this->assertSame( UpdateStep::Migrations->value, $state['step'] );
        $this->assertNotEmpty( $state['error'] );
    }

    /**
     * Test that a stale suppressed warning is not reported as the reason the
     * update died. The extractor deliberately uses `@fopen()`/`@chmod()`, so
     * `error_get_last()` routinely holds a non-fatal diagnostic from an
     * earlier step that has nothing to do with the interruption.
     *
     * @since 2.7.1
     */
    public function test_shutdown_guard_ignores_stale_non_fatal_warnings(): void
    {
        Log::spy();

        // Leave a suppressed warning in error_get_last(), as step 5 would.
        @file_get_contents( '/definitely/not/a/real/path' );

        $manager = new RecordingApplicationUpdateManager;
        $manager->simulateInFlight( UpdateStep::Migrations );
        $manager->handleInterruptedUpdate();

        $state = $manager->updateState();

        $this->assertIsArray( $state );
        $this->assertSame( 'The update process terminated before completing.', $state['error'] );
        $this->assertStringNotContainsString( 'file_get_contents', (string) $state['error'] );
    }

    /**
     * Test that the shutdown guard does nothing when the update finished
     * normally — the handler stays registered for the rest of the request, so
     * it must be inert once maintenance mode has been lifted.
     *
     * @since 2.7.1
     */
    public function test_shutdown_guard_is_inert_after_a_successful_update(): void
    {
        $manager = $this->managerWithUpdateAvailable();
        $manager->performUpdate();

        $callsAfterUpdate = $manager->calls;

        $manager->handleInterruptedUpdate();

        $this->assertSame( $callsAfterUpdate, $manager->calls );

        $state = $manager->updateState();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateRunStatus::Completed->value, $state['status'] );
    }

    /**
     * Test that the shutdown guard is inert when no update ever started.
     *
     * @since 2.7.1
     */
    public function test_shutdown_guard_is_inert_when_no_update_ran(): void
    {
        $manager = new RecordingApplicationUpdateManager;

        $manager->handleInterruptedUpdate();

        $this->assertSame( [], $manager->calls );
        $this->assertNull( $manager->updateState() );
    }

    /**
     * Test that the guard only fires once, even if the handler is somehow
     * invoked twice.
     *
     * @since 2.7.1
     */
    public function test_shutdown_guard_only_fires_once(): void
    {
        Log::spy();

        $manager = new RecordingApplicationUpdateManager;
        $manager->simulateInFlight( UpdateStep::Extract );

        $manager->handleInterruptedUpdate();
        $manager->handleInterruptedUpdate();

        $this->assertSame( ['disable'], $manager->calls );
    }

    /**
     * Test that a host can opt into failing closed — leaving the site down
     * until an operator has verified a possibly half-applied install.
     *
     * @since 2.7.1
     */
    public function test_shutdown_guard_respects_the_fail_closed_opt_out(): void
    {
        Log::spy();

        config( ['cms.updates.lift_maintenance_on_interrupt' => false] );

        $manager = new RecordingApplicationUpdateManager;
        $manager->simulateInFlight( UpdateStep::ComposerInstall );

        $manager->handleInterruptedUpdate();

        $this->assertNotContains( 'disable', $manager->calls );

        // The run is still recorded so `update:status` can explain the outage.
        $state = $manager->updateState();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateRunStatus::Interrupted->value, $state['status'] );
    }

    /**
     * Test that when `artisan up` itself fails — typical when the process is
     * shutting down after an out-of-memory fatal and there is no headroom to
     * boot a console command — the guard falls back to removing the
     * file-driver maintenance marker directly.
     *
     * @since 2.7.1
     */
    public function test_shutdown_guard_falls_back_to_removing_the_down_marker(): void
    {
        Log::spy();

        $downFile = storage_path( 'framework/down' );

        if ( ! is_dir( dirname( $downFile ) ) ) {
            mkdir( dirname( $downFile ), 0755, true );
        }

        file_put_contents( $downFile, '{}' );

        try {
            $manager                             = new RecordingApplicationUpdateManager;
            $manager->failDisableMaintenanceMode = true;
            $manager->simulateInFlight( UpdateStep::Extract );

            $manager->handleInterruptedUpdate();

            $this->assertSame( ['disable', 'force-lift'], $manager->calls );
            $this->assertFileDoesNotExist( $downFile );
        } finally {
            @unlink( $downFile );
        }
    }

    /**
     * Test that entering maintenance mode arms the shutdown guard. Arming it
     * anywhere later would leave a window in which a death produces the
     * original stuck-in-maintenance-mode outcome.
     *
     * @since 2.7.1
     */
    public function test_entering_maintenance_mode_arms_the_shutdown_guard(): void
    {
        $manager             = $this->managerWithUpdateAvailable();
        $manager->failAtStep = UpdateStep::Backup;

        try {
            $manager->performUpdate();
        } catch ( RuntimeException $e ) {
            // Expected.
        }

        $this->assertTrue( $manager->shutdownGuardWasArmed );
    }

    /**
     * Build a recording manager wired to a checker that reports an available
     * update.
     *
     * @since 2.7.1
     *
     * @return RecordingApplicationUpdateManager Configured test double.
     */
    protected function managerWithUpdateAvailable(): RecordingApplicationUpdateManager
    {
        $manager = new RecordingApplicationUpdateManager;

        $updateInfo = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        );

        $checker = $this->createMock( UpdateChecker::class );
        $checker->method( 'checkForUpdate' )->willReturn( $updateInfo );
        $checker->method( 'downloadUpdate' )->willReturn( '/tmp/does-not-matter.zip' );

        $manager->setUpdateChecker( $checker );

        return $manager;
    }
}
