<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Jobs\PerformUpdateJob;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\UpdateStateStore;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Orchestra\Testbench\TestCase;
use RuntimeException;

/**
 * Queued Application Update Tests
 *
 * Covers `ApplicationUpdateManager::dispatchUpdate()`, `PerformUpdateJob`, and
 * the failure reconciliation the job's `failed()` hook performs.
 *
 * @since 2.8.0
 */
class PerformUpdateJobTest extends TestCase
{
    /**
     * Absolute path to the state file used by the test.
     *
     * @since 2.8.0
     */
    protected string $statePath = '';

    /**
     * Point the state store at a temporary file and give the app a queue
     * connection that is neither sync nor null.
     *
     * @since 2.8.0
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath = sys_get_temp_dir() . '/cmsfw-queued-update-' . uniqid() . '.json';

        config( [
            'cms.updates.state_path'       => $this->statePath,
            'queue.default'                => 'database',
            'queue.connections.database'   => [
                'driver' => 'database',
                'table'  => 'jobs',
                'queue'  => 'default',
            ],
            'cms.updates.queue.connection' => null,
            'cms.updates.queue.queue'      => null,
            'cms.updates.queue.timeout'    => null,
            'cms.updates.queue.allow_sync' => false,
        ] );
    }

    /**
     * Remove the temporary state file and the lock beside it.
     *
     * @since 2.8.0
     */
    protected function tearDown(): void
    {
        foreach ( [$this->statePath, $this->statePath . '.lock'] as $path ) {
            if ( '' !== $path && file_exists( $path ) ) {
                @unlink( $path );
            }
        }

        parent::tearDown();
    }

    /**
     * Test dispatching pushes the job and records the queued state.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_queues_the_job_and_records_the_state(): void
    {
        Queue::fake();

        $manager = new ApplicationUpdateManager;

        $job = $manager->dispatchUpdate( '2.0.0' );

        Queue::assertPushed( PerformUpdateJob::class, function ( PerformUpdateJob $pushed ): bool {
            return '2.0.0' === $pushed->version && false === $pushed->allowDowngrade;
        } );

        $this->assertSame( '2.0.0', $job->version );

        $state = $manager->updateState();

        $this->assertNotNull( $state );
        $this->assertSame( UpdateRunStatus::Queued->value, $state['status'] );
        $this->assertSame( '2.0.0', $state['target_version'] );
        $this->assertSame( 'database', $state['queue_connection'] );
        $this->assertNull( $state['pid'] );
        $this->assertNotEmpty( $state['queued_at'] );
    }

    /**
     * Test the requested queue connection and queue name are honoured.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_honours_the_configured_connection_and_queue(): void
    {
        Queue::fake();

        config( [
            'queue.connections.updates'    => ['driver' => 'redis', 'queue' => 'default'],
            'cms.updates.queue.connection' => 'updates',
            'cms.updates.queue.queue'      => 'cms-updates',
        ] );

        $manager = new ApplicationUpdateManager;
        $manager->dispatchUpdate();

        Queue::assertPushed( PerformUpdateJob::class, function ( PerformUpdateJob $pushed ): bool {
            return 'updates' === $pushed->connection && 'cms-updates' === $pushed->queue;
        } );

        $state = $manager->updateState();

        $this->assertSame( 'updates', $state['queue_connection'] );
        $this->assertSame( 'cms-updates', $state['queue_name'] );
        $this->assertNull( $state['target_version'] );
    }

    /**
     * Test the sync driver is refused, because dispatching to it would run the
     * update inline and block the caller exactly as before.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_refuses_the_sync_driver(): void
    {
        Queue::fake();

        config( ['queue.default' => 'sync', 'queue.connections.sync' => ['driver' => 'sync']] );

        $manager = new ApplicationUpdateManager;

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessageMatches( '/sync driver/' );

        $manager->dispatchUpdate();
    }

    /**
     * Test the sync driver is permitted once the host has explicitly opted in.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_allows_the_sync_driver_when_opted_in(): void
    {
        Queue::fake();

        config( [
            'queue.default'                => 'sync',
            'queue.connections.sync'       => ['driver' => 'sync'],
            'cms.updates.queue.allow_sync' => true,
        ] );

        ( new ApplicationUpdateManager )->dispatchUpdate();

        Queue::assertPushed( PerformUpdateJob::class );
    }

    /**
     * Test the null driver is refused even with the sync opt-in, since it never
     * runs the job at all.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_refuses_the_null_driver_regardless_of_the_sync_opt_in(): void
    {
        Queue::fake();

        config( [
            'queue.default'                => 'discard',
            'queue.connections.discard'    => ['driver' => 'null'],
            'cms.updates.queue.allow_sync' => true,
        ] );

        $manager = new ApplicationUpdateManager;

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessageMatches( '/null driver/' );

        $manager->dispatchUpdate();
    }

    /**
     * Test an unconfigured connection is refused with a message naming the
     * config key to fix.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_refuses_an_unconfigured_connection(): void
    {
        Queue::fake();

        config( ['cms.updates.queue.connection' => 'nope'] );

        $manager = new ApplicationUpdateManager;

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessageMatches( '/is not configured/' );

        $manager->dispatchUpdate();
    }

    /**
     * Test a second dispatch is refused loudly rather than being swallowed by
     * the unique lock — the double-clicked admin button.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_refuses_a_second_dispatch(): void
    {
        Queue::fake();

        $manager = new ApplicationUpdateManager;
        $manager->dispatchUpdate();

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessageMatches( '/already queued/' );

        $manager->dispatchUpdate();
    }

    /**
     * Test a queued record older than the job timeout stops blocking, so a host
     * with no worker is not wedged permanently by its own first dispatch.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_replaces_a_stale_queued_record(): void
    {
        Queue::fake();

        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'database', null );

        // Age the record past the job timeout, and release the unique lock the
        // abandoned dispatch would have left behind.
        $state              = $store->read();
        $state['queued_at'] = date( DATE_ATOM, time() - PerformUpdateJob::resolveTimeout() - 60 );
        file_put_contents( $this->statePath, (string) json_encode( $state ) );

        $manager = new ApplicationUpdateManager;
        $manager->dispatchUpdate( '3.0.0' );

        Queue::assertPushed( PerformUpdateJob::class );

        $this->assertSame( '3.0.0', $manager->updateState()['target_version'] );
    }

    /**
     * Test the state is not left claiming `queued` when the queue backend
     * refuses the push.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_marks_the_run_failed_when_the_push_throws(): void
    {
        $manager = new ApplicationUpdateManager;

        $this->app->bind( \Illuminate\Contracts\Bus\Dispatcher::class, function () {
            return new class {
                public function dispatch( $job ): void
                {
                    throw new RuntimeException( 'Connection refused' );
                }
            };
        } );

        try {
            $manager->dispatchUpdate( '2.0.0' );
            $this->fail( 'Expected the dispatch failure to propagate.' );
        } catch ( RuntimeException $e ) {
            $this->assertSame( 'Connection refused', $e->getMessage() );
        }

        $state = $manager->updateState();

        $this->assertSame( UpdateRunStatus::Failed->value, $state['status'] );
        $this->assertSame( 'Connection refused', $state['error'] );

        // The unique lock is released too, so a corrected retry is not blocked.
        $lock = new UniqueLock( $this->app->make( CacheRepository::class ) );

        $this->assertTrue( $lock->acquire( new PerformUpdateJob ) );
    }

    /**
     * Test the job hands its work to the manager verbatim.
     *
     * @since 2.8.0
     */
    public function test_the_job_delegates_to_perform_update(): void
    {
        $manager = $this->createMock( ApplicationUpdateManager::class );
        $manager->expects( $this->once() )
            ->method( 'performUpdate' )
            ->with( '2.0.0', true )
            ->willReturn( true );

        ( new PerformUpdateJob( '2.0.0', true ) )->handle( $manager );
    }

    /**
     * Test the job's timeout is derived from the updater's own phase budgets.
     *
     * @since 2.8.0
     */
    public function test_the_job_timeout_is_derived_from_the_update_timeouts(): void
    {
        config( [
            'cms.updates.download_timeout' => 100,
            'cms.updates.composer_timeout' => 200,
        ] );

        $this->assertSame(
            300 + PerformUpdateJob::DEFAULT_TIMEOUT_BUFFER,
            ( new PerformUpdateJob )->timeout,
        );

        config( ['cms.updates.queue.timeout' => 4242] );

        $this->assertSame( 4242, ( new PerformUpdateJob )->timeout );
    }

    /**
     * Test the job never retries. A retry would restart an update at step 1
     * over a tree the previous attempt had already partly overwritten.
     *
     * @since 2.8.0
     */
    public function test_the_job_never_retries(): void
    {
        $job = new PerformUpdateJob;

        $this->assertSame( 1, $job->tries );
        $this->assertTrue( $job->failOnTimeout );
        $this->assertSame( PerformUpdateJob::UNIQUE_ID, $job->uniqueId() );
    }

    /**
     * Test a job that fails before the update starts marks the queued record
     * failed, rather than leaving it claiming `queued` forever.
     *
     * @since 2.8.0
     */
    public function test_a_failure_before_the_run_starts_marks_the_queued_record_failed(): void
    {
        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'database', null );

        $manager = new ApplicationUpdateManager;
        $manager->handleFailedUpdateJob( new RuntimeException( 'No update available' ) );

        $state = $manager->updateState();

        $this->assertSame( UpdateRunStatus::Failed->value, $state['status'] );
        $this->assertSame( 'No update available', $state['error'] );
    }

    /**
     * Test a job killed mid-run — the worker timeout — is recorded as
     * interrupted and, under the always-lift policy, the site is taken back out
     * of maintenance mode.
     *
     * @since 2.8.0
     */
    public function test_a_failure_mid_run_is_recorded_as_interrupted_and_lifts_maintenance_mode(): void
    {
        config( ['cms.updates.lift_maintenance_on_interrupt' => true] );

        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'database', null );
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::ComposerInstall );

        $artisan = $this->swapArtisan();

        $manager = new ApplicationUpdateManager;
        $manager->handleFailedUpdateJob( new RuntimeException( 'The job timed out.' ) );

        $state = $manager->updateState();

        $this->assertSame( UpdateRunStatus::Interrupted->value, $state['status'] );
        $this->assertSame( 'The job timed out.', $state['error'] );
        $this->assertSame( UpdateStep::ComposerInstall->value, $state['step'] );
        $this->assertSame( ['up'], $artisan->calls, 'The site must be taken back out of maintenance mode.' );
    }

    /**
     * Test that under the step-aware default a job killed inside a
     * tree/schema-mutating step (5-7) is recorded as interrupted but the site
     * is left down — the recorded step travels to the worker's `failed()` hook,
     * so the queued path is step-aware too, not just the in-process guard.
     *
     * @since 2.8.0
     */
    public function test_a_failure_in_the_danger_zone_leaves_the_site_down_under_the_step_aware_default(): void
    {
        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'database', null );
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Extract );

        $artisan = $this->swapArtisan();

        $manager = new ApplicationUpdateManager;
        $manager->handleFailedUpdateJob( new RuntimeException( 'The job timed out.' ) );

        $state = $manager->updateState();

        $this->assertSame( UpdateRunStatus::Interrupted->value, $state['status'] );
        $this->assertSame( UpdateStep::Extract->value, $state['step'] );
        $this->assertSame(
            [],
            $artisan->calls,
            'A half-extracted tree must be left in maintenance mode under the step-aware default.',
        );
    }

    /**
     * Test that under the step-aware default a job killed before the tree was
     * touched (steps 1-4) still lifts maintenance mode — nothing on disk
     * changed, so the outage is the only failure worth avoiding.
     *
     * @since 2.8.0
     */
    public function test_a_failure_before_the_tree_is_touched_lifts_under_the_step_aware_default(): void
    {
        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'database', null );
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Download );

        $artisan = $this->swapArtisan();

        $manager = new ApplicationUpdateManager;
        $manager->handleFailedUpdateJob( new RuntimeException( 'The job timed out.' ) );

        $state = $manager->updateState();

        $this->assertSame( UpdateRunStatus::Interrupted->value, $state['status'] );
        $this->assertSame( UpdateStep::Download->value, $state['step'] );
        $this->assertSame( ['up'], $artisan->calls, 'A death before the tree is touched must lift maintenance mode.' );
    }

    /**
     * Test that an unrecognized recorded step string leaves the site down under
     * the step-aware default. The recorded step is the only thing the worker's
     * `failed()` hook has to go on, so a corrupt or unknown value
     * (`UpdateStep::tryFrom()` returns null) must fail closed rather than lift.
     *
     * @since 2.8.0
     */
    public function test_an_unknown_recorded_step_leaves_the_site_down_under_the_step_aware_default(): void
    {
        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'database', null );
        $store->begin( '2.0.0', '1.0.0' );

        // A step value no `UpdateStep` case maps to — a truncated write, or a
        // record left by a newer framework version.
        $state         = $store->read();
        $state['step'] = 'not_a_real_step';
        file_put_contents( $this->statePath, (string) json_encode( $state ) );

        $artisan = $this->swapArtisan();

        $manager = new ApplicationUpdateManager;
        $manager->handleFailedUpdateJob( new RuntimeException( 'The job timed out.' ) );

        $this->assertSame( UpdateRunStatus::Interrupted->value, $manager->updateState()['status'] );
        $this->assertSame(
            [],
            $artisan->calls,
            'An unidentifiable recorded step must fail closed under the step-aware default.',
        );
    }

    /**
     * Test the site is left down when the host has opted out of the automatic
     * lift.
     *
     * @since 2.8.0
     */
    public function test_a_failure_mid_run_respects_the_fail_closed_opt_out(): void
    {
        config( ['cms.updates.lift_maintenance_on_interrupt' => false] );

        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Extract );

        $artisan = $this->swapArtisan();

        $manager = new ApplicationUpdateManager;
        $manager->handleFailedUpdateJob( new RuntimeException( 'The job timed out.' ) );

        $this->assertSame( UpdateRunStatus::Interrupted->value, $manager->updateState()['status'] );
        $this->assertSame( [], $artisan->calls, 'The site must be left down when the host has opted out.' );
    }

    /**
     * Test a terminal record is left alone. `performUpdate()` has already
     * recorded the real error and rolled back; re-stamping would replace it
     * with the worker's generic message.
     *
     * @since 2.8.0
     */
    public function test_a_failure_after_the_run_finished_does_not_rewrite_the_record(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Migrations );
        $store->markStatus( UpdateRunStatus::Failed, 'SQLSTATE[42S01]: table already exists' );

        $artisan = $this->swapArtisan();

        $manager = new ApplicationUpdateManager;
        $manager->handleFailedUpdateJob( new RuntimeException( 'Job failed.' ) );

        $state = $manager->updateState();

        $this->assertSame( UpdateRunStatus::Failed->value, $state['status'] );
        $this->assertSame( 'SQLSTATE[42S01]: table already exists', $state['error'] );
        $this->assertSame( [], $artisan->calls );
    }

    /**
     * Test the in-process shutdown guard does not overwrite the reason the
     * worker already recorded.
     *
     * A worker timeout runs both: `failed()` stamps `interrupted` with the
     * real reason, and the handler then `exit`s, which fires the shutdown
     * guard in the same process. The guard used to treat `interrupted` as
     * non-terminal and re-stamp it with its own generic message, discarding
     * the only line that said what actually happened.
     *
     * Reachable specifically on the fail-closed path: when the host has opted
     * out of the automatic lift, `handleFailedUpdateJob()` leaves the active
     * flag set, so the guard does not take its early return.
     *
     * @since 2.8.0
     */
    public function test_the_shutdown_guard_does_not_overwrite_the_workers_reason(): void
    {
        config( ['cms.updates.lift_maintenance_on_interrupt' => false] );

        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::ComposerInstall );

        $this->swapArtisan();

        $manager = new class extends ApplicationUpdateManager {
            public function markMaintenanceModeActive(): void
            {
                $this->maintenanceModeActive = true;
            }
        };

        $manager->markMaintenanceModeActive();
        $manager->handleFailedUpdateJob( new RuntimeException( 'The job timed out.' ) );

        // The guard fires next, in the same process, at exit.
        $manager->handleInterruptedUpdate();

        $state = $manager->updateState();

        $this->assertSame( UpdateRunStatus::Interrupted->value, $state['status'] );
        $this->assertSame(
            'The job timed out.',
            $state['error'],
            'The shutdown guard must not replace the worker-recorded reason with its own generic one.',
        );
    }

    /**
     * Test a job that failed against a run owned by another live process
     * leaves that run completely alone.
     *
     * Two ways to get here: losing the `flock` race (which throws before
     * `performUpdate()`'s bookkeeping starts), and a `retry_after`
     * redelivery, which is failed without `handle()` ever running. Both used
     * to mark the *winner's* healthy record `interrupted` and then run
     * `artisan up` on a site that was mid-extraction.
     *
     * @since 2.8.0
     */
    public function test_a_failure_does_not_touch_a_run_owned_by_another_process(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Extract );

        // Re-stamp the record as owned by a different, definitely-live PID.
        // The parent process is alive, is never this process, and — unlike
        // PID 1 — is owned by this user, so `posix_kill( $pid, 0 )` reports it
        // as alive rather than failing with EPERM.
        $state        = $store->read();
        $state['pid'] = function_exists( 'posix_getppid' ) ? posix_getppid() : 1;
        file_put_contents( $this->statePath, (string) json_encode( $state ) );

        $artisan = $this->swapArtisan();

        $manager = new ApplicationUpdateManager;
        $manager->handleFailedUpdateJob( new RuntimeException( 'An application update is already running.' ) );

        $state = $manager->updateState();

        $this->assertSame(
            UpdateRunStatus::InProgress->value,
            $state['status'],
            'A losing racer must not report the winning run as interrupted.',
        );
        $this->assertSame(
            [],
            $artisan->calls,
            'A losing racer must not lift maintenance mode on a run that is still extracting.',
        );
    }

    /**
     * Test a connection whose `retry_after` is shorter than the job timeout is
     * refused. Laravel ships 90s, which every real update outruns — the queue
     * would hand the same update to a second worker mid-`composer install`.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_refuses_a_retry_after_shorter_than_the_timeout(): void
    {
        Queue::fake();

        config( ['queue.connections.database.retry_after' => 90] );

        $manager = new ApplicationUpdateManager;

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessageMatches( '/retry_after=90/' );

        $manager->dispatchUpdate();
    }

    /**
     * Test a connection with a long enough `retry_after` dispatches normally,
     * and that a connection carrying no such setting (SQS) is not blocked by
     * a check that cannot apply to it.
     *
     * @since 2.8.0
     */
    public function test_dispatch_update_accepts_a_sufficient_or_absent_retry_after(): void
    {
        Queue::fake();

        config( ['queue.connections.database.retry_after' => PerformUpdateJob::resolveTimeout() + 1] );

        ( new ApplicationUpdateManager )->dispatchUpdate();

        Queue::assertPushed( PerformUpdateJob::class );

        // A connection with no retry_after at all — the SQS shape.
        ( new ApplicationUpdateManager )->clearUpdateState();

        config( [
            'queue.connections.sqsish'     => ['driver' => 'sqs'],
            'cms.updates.queue.connection' => 'sqsish',
        ] );

        ( new ApplicationUpdateManager )->dispatchUpdate();

        Queue::assertPushed( PerformUpdateJob::class, 2 );
    }

    /**
     * Test clearing the recorded state also releases the dispatch lock.
     *
     * The record is a file and the lock is a cache entry, so clearing only the
     * record left dispatch refusing with "already queued" while `update:status`
     * reported nothing had ever been recorded — escapable only via
     * `cache:clear` or by waiting out the TTL.
     *
     * @since 2.8.0
     */
    public function test_clearing_the_state_releases_the_dispatch_lock(): void
    {
        Queue::fake();

        $manager = new ApplicationUpdateManager;
        $manager->dispatchUpdate();

        $manager->clearUpdateState();

        $this->assertNull( $manager->updateState() );

        // Would throw updateAlreadyQueued if the lock had survived.
        $manager->dispatchUpdate();

        Queue::assertPushed( PerformUpdateJob::class, 2 );
    }

    /**
     * Test an abandoned queued record does not stamp a later inline run with
     * its queue details.
     *
     * @since 2.8.0
     */
    public function test_a_stale_queued_record_does_not_leak_into_a_later_inline_run(): void
    {
        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'redis', 'cms-updates' );

        $state              = $store->read();
        $state['queued_at'] = date( DATE_ATOM, time() - PerformUpdateJob::resolveTimeout() - 60 );
        file_put_contents( $this->statePath, (string) json_encode( $state ) );

        $store->begin( '2.0.0', '1.0.0' );

        $fresh = $store->read();

        $this->assertArrayNotHasKey(
            'queue_connection',
            $fresh,
            'An inline run must not inherit the queue of a dispatch that was never picked up.',
        );
    }

    /**
     * Test the queue provenance survives the transition from `queued` to
     * `in_progress`, so an operator can still see which worker is running it.
     *
     * @since 2.8.0
     */
    public function test_queue_provenance_survives_the_start_of_the_run(): void
    {
        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'redis', 'cms-updates' );

        $queuedAt = $store->read()['queued_at'];

        $store->begin( '2.0.0', '1.0.0' );

        $state = $store->read();

        $this->assertSame( UpdateRunStatus::InProgress->value, $state['status'] );
        $this->assertSame( $queuedAt, $state['queued_at'] );
        $this->assertSame( 'redis', $state['queue_connection'] );
        $this->assertSame( 'cms-updates', $state['queue_name'] );
    }

    /**
     * Test a run started directly by `performUpdate()` carries no queue fields,
     * so a previous queued run cannot make an inline run look queued.
     *
     * @since 2.8.0
     */
    public function test_a_direct_run_does_not_inherit_queue_provenance(): void
    {
        $store = new UpdateStateStore;
        $store->markQueued( '2.0.0', '1.0.0', 'redis', 'cms-updates' );
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStatus( UpdateRunStatus::Completed );

        $store->begin( '3.0.0', '2.0.0' );

        $state = $store->read();

        $this->assertArrayNotHasKey( 'queued_at', $state );
        $this->assertArrayNotHasKey( 'queue_connection', $state );
    }

    /**
     * Stand a recording double in for the Artisan facade.
     *
     * `Artisan::shouldReceive()` cannot be used here — Testbench's console
     * kernel is `final`, so Mockery refuses to mock it.
     *
     * @since 2.8.0
     *
     * @return object Recording double exposing a `$calls` array.
     */
    protected function swapArtisan(): object
    {
        $artisan = new class {
            /**
             * Commands this double was asked to run.
             *
             * @var array<int, string>
             */
            public array $calls = [];

            public function call( $command, array $parameters = [], $outputBuffer = null ): int
            {
                $this->calls[] = $command;

                return 0;
            }
        };

        Artisan::swap( $artisan );

        return $artisan;
    }
}
