<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use Orchestra\Testbench\TestCase;

/**
 * Update Step / Update Run Status Enum Tests
 *
 * @since 2.7.1
 */
class UpdateStepTest extends TestCase
{
    /**
     * Test that step numbers are unique, contiguous, and ordered to match the
     * sequence in `performUpdate()`.
     *
     * @since 2.7.1
     */
    public function test_step_numbers_are_contiguous_and_ordered(): void
    {
        $numbers = array_map( fn ( UpdateStep $step ): int => $step->number(), UpdateStep::cases() );

        $this->assertSame( range( 1, count( UpdateStep::cases() ) ), $numbers );
    }

    /**
     * Test that every step has a non-empty label.
     *
     * @since 2.7.1
     */
    public function test_every_step_has_a_label(): void
    {
        foreach ( UpdateStep::cases() as $step ) {
            $this->assertNotSame( '', $step->label() );
        }
    }

    /**
     * Test that outstanding steps include the step itself — an interrupted
     * update was *in* that step when it died, so it cannot be assumed done.
     *
     * @since 2.7.1
     */
    public function test_outstanding_steps_include_the_current_step(): void
    {
        $outstanding = UpdateStep::Migrations->outstandingSteps();

        $this->assertSame(
            [
                UpdateStep::Migrations,
                UpdateStep::ClearCaches,
                UpdateStep::Cleanup,
                UpdateStep::DisableMaintenanceMode,
            ],
            $outstanding,
        );
    }

    /**
     * Test that the last step has exactly one outstanding step: itself.
     *
     * @since 2.7.1
     */
    public function test_last_step_is_its_own_only_outstanding_step(): void
    {
        $this->assertSame(
            [UpdateStep::DisableMaintenanceMode],
            UpdateStep::DisableMaintenanceMode->outstandingSteps(),
        );
    }

    /**
     * Test that the recoverable steps expose the command an operator would run
     * by hand, and the unrecoverable ones do not pretend to.
     *
     * @since 2.7.1
     */
    public function test_recovery_commands_are_exposed_for_resumable_steps_only(): void
    {
        $this->assertSame( 'php artisan migrate --force', UpdateStep::Migrations->recoveryCommand() );
        $this->assertSame( 'php artisan up', UpdateStep::DisableMaintenanceMode->recoveryCommand() );
        $this->assertStringContainsString( 'composer install', (string) UpdateStep::ComposerInstall->recoveryCommand() );

        $this->assertNull( UpdateStep::Download->recoveryCommand() );
        $this->assertNull( UpdateStep::Extract->recoveryCommand() );
        $this->assertNull( UpdateStep::Backup->recoveryCommand() );
    }

    /**
     * Test that only the three tree/schema-mutating steps (extract, composer
     * install, migrations) report an interruption as leaving the site unsafe.
     * This is the boundary the `step_aware` lift policy gates on, so it must
     * match the 5-7 window exactly.
     *
     * @since 2.8.0
     */
    public function test_interruption_leaves_site_unsafe_only_for_the_mutating_steps(): void
    {
        $unsafe = array_values( array_filter(
            UpdateStep::cases(),
            fn ( UpdateStep $step ): bool => $step->interruptionLeavesSiteUnsafe(),
        ) );

        $this->assertSame(
            [
                UpdateStep::Extract,
                UpdateStep::ComposerInstall,
                UpdateStep::Migrations,
            ],
            $unsafe,
        );

        foreach ( [UpdateStep::EnableMaintenanceMode, UpdateStep::Backup, UpdateStep::Download, UpdateStep::VerifyChecksum] as $safeBefore ) {
            $this->assertFalse( $safeBefore->interruptionLeavesSiteUnsafe() );
        }

        foreach ( [UpdateStep::ClearCaches, UpdateStep::Cleanup, UpdateStep::DisableMaintenanceMode] as $safeAfter ) {
            $this->assertFalse( $safeAfter->interruptionLeavesSiteUnsafe() );
        }
    }

    /**
     * Test which run statuses warrant operator attention.
     *
     * @since 2.7.1
     */
    public function test_statuses_needing_attention(): void
    {
        $this->assertTrue( UpdateRunStatus::Interrupted->needsAttention() );
        $this->assertTrue( UpdateRunStatus::Failed->needsAttention() );
        $this->assertFalse( UpdateRunStatus::Completed->needsAttention() );
        $this->assertFalse( UpdateRunStatus::InProgress->needsAttention() );
    }
}
