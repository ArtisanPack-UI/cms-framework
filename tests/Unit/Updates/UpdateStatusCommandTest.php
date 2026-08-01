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

        $this->artisan( 'update:status' )
            ->expectsOutputToContain( 'storage/backups/application/' )
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
