<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use RuntimeException;

/**
 * Recording Application Update Manager
 *
 * Test double for #256's execution-time and shutdown-guard coverage. Every
 * side-effecting step of `performUpdate()` is stubbed out and recorded, so a
 * test can drive the real orchestration — step tracking, maintenance-mode
 * bookkeeping, the shutdown guard — without shelling out to composer or
 * putting the test process into maintenance mode.
 *
 * @since 2.7.1
 */
class RecordingApplicationUpdateManager extends ApplicationUpdateManager
{
    /**
     * Ordered record of the stubbed steps that ran.
     *
     * @since 2.7.1
     *
     * @var array<int, string>
     */
    public array $calls = [];

    /**
     * State snapshots taken as each step was entered, keyed by step value.
     *
     * @since 2.7.1
     *
     * @var array<string, array<string, mixed>>
     */
    public array $stateAtStep = [];

    /**
     * Step at which `performUpdate()` should throw, or null to run clean.
     *
     * @since 2.7.1
     */
    public ?UpdateStep $failAtStep = null;

    /**
     * Whether `disableMaintenanceMode()` should throw, exercising the
     * force-lift fallback.
     *
     * @since 2.7.1
     */
    public bool $failDisableMaintenanceMode = false;

    /**
     * Whether the real shutdown handler should actually be registered with
     * PHP. Off by default so tests don't leave handlers that fire at the end
     * of the suite; tests invoke `handleInterruptedUpdate()` directly.
     *
     * @since 2.7.1
     */
    public bool $registerRealShutdownHandler = false;

    /**
     * Whether `armShutdownGuard()` was reached.
     *
     * @since 2.7.1
     */
    public bool $shutdownGuardWasArmed = false;

    /**
     * Put the double into the state a live update would be in when the process
     * dies mid-flight.
     *
     * @since 2.7.1
     *
     * @param  UpdateStep  $step  Step to appear stuck on.
     */
    public function simulateInFlight( UpdateStep $step ): void
    {
        $this->currentStep           = $step;
        $this->maintenanceModeActive = true;
    }

    /**
     * Whether the manager currently believes the site is in maintenance mode.
     *
     * @since 2.7.1
     *
     * @return bool True when maintenance mode is believed active.
     */
    public function believesMaintenanceModeIsActive(): bool
    {
        return $this->maintenanceModeActive;
    }

    /**
     * Invoke the protected execution-limit guard.
     *
     * @since 2.7.1
     */
    public function callRaiseExecutionLimits(): void
    {
        $this->raiseExecutionLimits();
    }

    /**
     * Force the maintenance-mode flag on, so a test can drive
     * `handleInterruptedUpdate()` directly.
     *
     * @since 2.7.1
     */
    public function forceMaintenanceModeActive(): void
    {
        $this->maintenanceModeActive = true;
    }

    /**
     * Start a run in the state store without executing any steps.
     *
     * @since 2.7.1
     *
     * @param  string  $targetVersion  Version being installed.
     * @param  string  $currentVersion  Version installed before the update.
     */
    public function beginRunForTest( string $targetVersion, string $currentVersion ): void
    {
        $this->state()->begin( $targetVersion, $currentVersion );
    }

    /**
     * Record a step as in flight without executing it.
     *
     * @since 2.7.1
     *
     * @param  UpdateStep  $step  Step to record.
     */
    public function markStepForTest( UpdateStep $step ): void
    {
        $this->currentStep = $step;
        $this->state()->markStep( $step );
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function beginStep( UpdateStep $step ): void
    {
        parent::beginStep( $step );

        $this->stateAtStep[ $step->value ] = $this->updateState() ?? [];

        if ( $this->failAtStep === $step ) {
            throw new RuntimeException( "Simulated failure at {$step->value}" );
        }
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function armShutdownGuard(): void
    {
        $this->shutdownGuardWasArmed = true;

        if ( $this->registerRealShutdownHandler ) {
            parent::armShutdownGuard();
        }
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function enableMaintenanceMode(): void
    {
        $this->calls[] = 'enable';

        $this->maintenanceModeActive = true;

        $this->armShutdownGuard();
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function disableMaintenanceMode(): void
    {
        $this->calls[] = 'disable';

        if ( $this->failDisableMaintenanceMode ) {
            throw new RuntimeException( 'Simulated `artisan up` failure' );
        }

        $this->maintenanceModeActive = false;
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function createBackup(): void
    {
        $this->calls[] = 'backup';
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function maybeVerifyChecksum( string $zipPath, UpdateInfo $updateInfo, string $targetVersion ): void
    {
        $this->calls[] = 'checksum';
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function extractUpdate( string $zipPath ): void
    {
        $this->calls[] = 'extract';
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function runComposerInstall(): void
    {
        $this->calls[] = 'composer';
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function runMigrations(): void
    {
        $this->calls[] = 'migrate';
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function clearCaches(): void
    {
        $this->calls[] = 'caches';
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function cleanup( string $zipPath ): void
    {
        $this->calls[] = 'cleanup';
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.7.1
     */
    protected function forceLiftMaintenanceMode(): void
    {
        $this->calls[] = 'force-lift';

        parent::forceLiftMaintenanceMode();
    }
}
