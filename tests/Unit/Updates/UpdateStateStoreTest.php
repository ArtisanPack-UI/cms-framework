<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\UpdateStateStore;
use Orchestra\Testbench\TestCase;

/**
 * Update State Store Tests
 *
 * Regression coverage for #256 — an update killed mid-flight must leave a
 * durable record of how far it got.
 *
 * @since 2.7.1
 */
class UpdateStateStoreTest extends TestCase
{
    /**
     * Absolute path to the state file used by the test.
     *
     * @since 2.7.1
     */
    protected string $statePath = '';

    /**
     * Set up a temporary, absolute state path for each test.
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
     * Test that an absolute `state_path` is honoured verbatim.
     *
     * @since 2.7.1
     */
    public function test_absolute_state_path_is_used_verbatim(): void
    {
        $store = new UpdateStateStore;

        $this->assertSame( $this->statePath, $store->path() );
    }

    /**
     * Test that a relative `state_path` resolves against storage_path().
     *
     * @since 2.7.1
     */
    public function test_relative_state_path_resolves_against_storage_path(): void
    {
        config( ['cms.updates.state_path' => 'framework/cms-update-state.json'] );

        $store = new UpdateStateStore;

        $this->assertSame( storage_path( 'framework/cms-update-state.json' ), $store->path() );
    }

    /**
     * Test that a blank or non-string `state_path` falls back to the default.
     *
     * @since 2.7.1
     */
    public function test_blank_state_path_falls_back_to_default(): void
    {
        config( ['cms.updates.state_path' => ''] );

        $store = new UpdateStateStore;

        $this->assertSame( storage_path( UpdateStateStore::DEFAULT_STATE_PATH ), $store->path() );
    }

    /**
     * Test that reading returns null when nothing has been recorded.
     *
     * @since 2.7.1
     */
    public function test_read_returns_null_when_no_state_recorded(): void
    {
        $store = new UpdateStateStore;

        $this->assertNull( $store->read() );
    }

    /**
     * Test that beginning a run records the versions and an in-progress status.
     *
     * @since 2.7.1
     */
    public function test_begin_records_versions_and_in_progress_status(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '0.3.0', '0.2.4' );

        $state = $store->read();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateRunStatus::InProgress->value, $state['status'] );
        $this->assertSame( '0.3.0', $state['target_version'] );
        $this->assertSame( '0.2.4', $state['current_version'] );
        $this->assertSame( PHP_SAPI, $state['php_sapi'] );
        $this->assertNull( $state['step'] );
        $this->assertNotEmpty( $state['started_at'] );
        $this->assertNotEmpty( $state['updated_at'] );
    }

    /**
     * Test that marking a step records its identifier, number, and label.
     *
     * @since 2.7.1
     */
    public function test_mark_step_records_step_details(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '0.3.0', '0.2.4' );
        $store->markStep( UpdateStep::ComposerInstall );

        $state = $store->read();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateStep::ComposerInstall->value, $state['step'] );
        $this->assertSame( 6, $state['step_number'] );
        $this->assertSame( UpdateStep::ComposerInstall->label(), $state['step_label'] );
        // Merging must not drop attributes recorded by begin().
        $this->assertSame( '0.3.0', $state['target_version'] );
    }

    /**
     * Test that marking a status preserves the last recorded step, which is
     * the whole point of the marker — an interrupted run must still say where
     * it stopped.
     *
     * @since 2.7.1
     */
    public function test_mark_status_preserves_last_step(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '0.3.0', '0.2.4' );
        $store->markStep( UpdateStep::Migrations );
        $store->markStatus( UpdateRunStatus::Interrupted, 'Maximum execution time of 30 seconds exceeded' );

        $state = $store->read();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateRunStatus::Interrupted->value, $state['status'] );
        $this->assertSame( UpdateStep::Migrations->value, $state['step'] );
        $this->assertSame( 'Maximum execution time of 30 seconds exceeded', $state['error'] );
    }

    /**
     * Test that clearing removes the state file.
     *
     * @since 2.7.1
     */
    public function test_clear_removes_the_state_file(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '0.3.0', '0.2.4' );

        $this->assertFileExists( $this->statePath );

        $store->clear();

        $this->assertFileDoesNotExist( $this->statePath );
        $this->assertNull( $store->read() );
    }

    /**
     * Test that clearing when nothing was recorded is a no-op rather than an
     * error — the shutdown path must never throw.
     *
     * @since 2.7.1
     */
    public function test_clear_is_a_no_op_when_no_state_exists(): void
    {
        $store = new UpdateStateStore;
        $store->clear();

        $this->assertNull( $store->read() );
    }

    /**
     * Test that an error message carrying invalid UTF-8 — composer and
     * migration output can contain arbitrary bytes — still produces a
     * readable record rather than silently failing to encode.
     *
     * @since 2.7.1
     */
    public function test_invalid_utf8_in_an_error_message_still_persists(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '0.3.0', '0.2.4' );
        $store->markStatus( UpdateRunStatus::Interrupted, "composer failed: \xB1\x31 bad bytes" );

        $state = $store->read();

        $this->assertIsArray( $state );
        $this->assertSame( UpdateRunStatus::Interrupted->value, $state['status'] );
        $this->assertStringContainsString( 'composer failed', (string) $state['error'] );
    }

    /**
     * Test that writes are staged atomically and leave no temporary file
     * behind. Writing in place would truncate first, so a process dying
     * mid-write — exactly what this marker exists to record — could destroy
     * the last good record.
     *
     * @since 2.7.1
     */
    public function test_writes_are_staged_and_leave_no_temporary_file(): void
    {
        $store = new UpdateStateStore;
        $store->begin( '0.3.0', '0.2.4' );
        $store->markStep( UpdateStep::Extract );

        $strays = glob( $this->statePath . '.*.tmp' );

        $this->assertSame( [], false === $strays ? [] : $strays );
        $this->assertIsArray( $store->read() );
    }

    /**
     * Test that a corrupt state file reads as null rather than throwing. A
     * truncated write (the process died mid-`fwrite`) must not take down the
     * status command that exists to diagnose it.
     *
     * @since 2.7.1
     */
    public function test_corrupt_state_file_reads_as_null(): void
    {
        file_put_contents( $this->statePath, '{ this is not json' );

        $store = new UpdateStateStore;

        $this->assertNull( $store->read() );
    }

    /**
     * Test that a JSON scalar (valid JSON, wrong shape) reads as null.
     *
     * @since 2.7.1
     */
    public function test_non_array_state_file_reads_as_null(): void
    {
        file_put_contents( $this->statePath, '"in_progress"' );

        $store = new UpdateStateStore;

        $this->assertNull( $store->read() );
    }
}
