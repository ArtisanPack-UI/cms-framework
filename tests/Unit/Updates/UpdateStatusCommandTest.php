<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Providers\CoreServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\UpdateStateStore;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

/**
 * Update Status Command Tests
 *
 * @since 2.7.1
 */
class UpdateStatusCommandTest extends TestCase
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

        config( ['cms.updates.state_path' => $this->statePath] );
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
     * Test the command reports cleanly when no update has ever been recorded.
     *
     * @since 2.7.1
     */
    public function test_reports_when_no_update_has_been_recorded(): void
    {
        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'No application update has been recorded.' )
            ->assertSuccessful();
    }

    /**
     * Test the command exits successfully for a completed update.
     *
     * @since 2.7.1
     */
    public function test_reports_a_completed_update(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::DisableMaintenanceMode );
        $store->markStatus( UpdateRunStatus::Completed );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'Completed' )
            ->assertSuccessful();
    }

    /**
     * Test the command fails and prints the outstanding recovery steps when
     * an update was interrupted mid-flight. This is the diagnostic that used
     * to require reading framework source.
     *
     * @since 2.7.1
     */
    public function test_reports_recovery_steps_for_an_interrupted_update(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '0.3.0', '0.2.4' );
        $store->markStep( UpdateStep::Migrations );
        $store->markStatus( UpdateRunStatus::Interrupted, 'Maximum execution time of 30 seconds exceeded' );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'Maximum execution time of 30 seconds exceeded' )
            ->expectsOutputToContain( 'php artisan migrate --force' )
            ->expectsOutputToContain( 'php artisan up' )
            ->assertFailed();
    }

    /**
     * Test that an update interrupted during extraction points the operator at
     * the snapshot rather than at a resume-by-hand checklist — a partially
     * overwritten application tree cannot be finished manually.
     *
     * @since 2.7.1
     */
    public function test_recommends_snapshot_restore_when_interrupted_during_extraction(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '0.3.0', '0.2.4' );
        $store->markStep( UpdateStep::Extract );
        $store->markStatus( UpdateRunStatus::Interrupted, 'Allowed memory size exhausted' );

        // The guidance now names the *configured* backup directory rather
        // than a hardcoded `storage/backups/application/`, which pointed a
        // host with a custom `backup_path` at a directory that did not exist.
        $this->artisan( 'update:status' )
            ->expectsOutputToContain( storage_path( 'backups/application' ) )
            ->doesntExpectOutputToContain( 'php artisan migrate --force' )
            ->assertFailed();
    }

    /**
     * Test that a hand-edited or half-written record renders without notices
     * instead of printing "Array". The command exists to be run after a crash,
     * so it must tolerate a malformed record.
     *
     * @since 2.7.1
     */
    public function test_renders_a_malformed_record_without_erroring(): void
    {
        file_put_contents( $this->statePath, (string) json_encode( [
            'status'         => ['unexpected' => 'shape'],
            'step'           => 99,
            'target_version' => ['also' => 'wrong'],
            'error'          => null,
        ] ) );

        $this->artisan( 'update:status' )->assertSuccessful();
    }

    /**
     * Test the `--json` option emits the raw persisted state.
     *
     * @since 2.7.1
     */
    public function test_json_option_emits_the_raw_state(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::ComposerInstall );

        $this->artisan( 'update:status', ['--json' => true] )
            ->expectsOutputToContain( '"step": "composer_install"' )
            ->assertSuccessful();
    }

    /**
     * Test the `--clear` option discards the recorded state.
     *
     * @since 2.7.1
     */
    public function test_clear_option_discards_the_recorded_state(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStatus( UpdateRunStatus::Completed );

        $this->artisan( 'update:status', ['--clear' => true] )->assertSuccessful();

        $this->assertNull( ( new UpdateStateStore )->read() );
    }

    /**
     * Regression for TEST-1 / BUG-9: the `--json` branch returned SUCCESS
     * before the exit-code logic ever ran, so `update:status --json || alert`
     * — the natural machine-consumption path, and the one the docs advertise
     * — never fired on a dead update, while the same state without `--json`
     * exited 1.
     *
     * @since 2.7.1
     */
    public function test_json_option_exits_non_zero_for_a_failed_run(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Migrations );
        $store->markStatus( UpdateRunStatus::Failed, 'SQLSTATE[42S02]: Base table or view not found' );

        $this->artisan( 'update:status', ['--json' => true] )
            ->expectsOutputToContain( '"status": "failed"' )
            ->assertFailed();
    }

    /**
     * The same, for an interrupted run — the other status `needsAttention()`
     * covers.
     *
     * @since 2.7.1
     */
    public function test_json_option_exits_non_zero_for_an_interrupted_run(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::ComposerInstall );
        $store->markStatus( UpdateRunStatus::Interrupted, 'The update process terminated before completing.' );

        $this->artisan( 'update:status', ['--json' => true] )->assertFailed();
    }

    /**
     * A completed run must still exit zero in JSON mode, so the exit code is
     * a signal rather than a constant.
     *
     * @since 2.7.1
     */
    public function test_json_option_exits_zero_for_a_completed_run(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStatus( UpdateRunStatus::Completed );

        $this->artisan( 'update:status', ['--json' => true] )->assertSuccessful();
    }

    /**
     * TEST-1: a `Failed` record renders its own summary — previously only
     * `Interrupted` had coverage, so the failed-run rendering path was
     * exercised by nothing.
     *
     * @since 2.7.1
     */
    public function test_reports_a_failed_update(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Migrations );
        $store->markStatus( UpdateRunStatus::Failed, 'Migration failed: duplicate column' );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'Migration failed: duplicate column' )
            ->assertFailed();
    }

    /**
     * TEST-1: `--json` and `--clear` together must emit the record *and*
     * discard it, and still report the failure through the exit code.
     *
     * @since 2.7.1
     */
    public function test_json_and_clear_together_emit_then_discard_the_record(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStatus( UpdateRunStatus::Failed, 'boom' );

        $this->artisan( 'update:status', ['--json' => true, '--clear' => true] )
            ->expectsOutputToContain( '"status": "failed"' )
            ->assertFailed();

        $this->assertNull( ( new UpdateStateStore )->read() );
    }

    /**
     * BUG-4: a failed rollback is the most dangerous state this updater can
     * produce, and the `Failed (rolled back)` label asserted the opposite.
     *
     * @since 2.7.1
     */
    public function test_reports_a_failed_rollback_distinctly(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Migrations );
        $store->markStatus( UpdateRunStatus::Failed, 'Migration failed' );
        $store->markRollback( false, 'Rollback failed: could not open backup ZIP' );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'rollback ITSELF failed' )
            ->doesntExpectOutputToContain( 'restored successfully' )
            ->assertFailed();
    }

    /**
     * The successful-rollback case must still say so.
     *
     * @since 2.7.1
     */
    public function test_reports_a_successful_rollback(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStatus( UpdateRunStatus::Failed, 'Migration failed' );
        $store->markRollback( true );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'restored successfully' )
            ->assertFailed();
    }

    /**
     * BUG-4: no rollback attempted at all — backups disabled, or the failure
     * landed before the snapshot existed.
     *
     * @since 2.7.1
     */
    public function test_reports_when_no_rollback_was_attempted(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStatus( UpdateRunStatus::Failed, 'Download failed' );
        $store->markRollback( null );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'No rollback was attempted' )
            ->assertFailed();
    }

    /**
     * BUG-10: at steps 1-2 nothing has overwritten the tree, so "restore the
     * snapshot" is wrong at step 1 (none exists) and dangerous at step 2,
     * where a truncated backup archive may have been left behind.
     *
     * @since 2.7.1
     */
    public function test_recommends_retry_not_restore_when_interrupted_before_any_writes(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::EnableMaintenanceMode );
        $store->markStatus( UpdateRunStatus::Interrupted, 'died early' );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'the tree is untouched' )
            ->doesntExpectOutputToContain( 'Restore the pre-update snapshot' )
            ->assertFailed();
    }

    /**
     * BUG-10: a death during the backup step warns about the partial archive.
     *
     * @since 2.7.1
     */
    public function test_warns_about_a_partial_archive_when_interrupted_during_backup(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '2.0.0', '1.0.0' );
        $store->markStep( UpdateStep::Backup );
        $store->markStatus( UpdateRunStatus::Interrupted, 'died during backup' );

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'the tree is untouched' )
            ->expectsOutputToContain( 'partial backup archive' )
            ->assertFailed();
    }

    /**
     * Register the core provider so the update commands are available.
     *
     * @since 2.7.1
     *
     * @param  Application  $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders( $app ): array
    {
        return [CoreServiceProvider::class];
    }
}
