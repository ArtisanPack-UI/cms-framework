<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use Illuminate\Console\Command;

/**
 * Update Status Command
 *
 * Reports the outcome of the most recent `performUpdate()` run from the
 * persisted state marker. Exists so that an update killed mid-flight is
 * diagnosable — before this, "update in progress", "update died at step 6",
 * and "the site was manually put into maintenance mode" were
 * indistinguishable without reading framework source.
 *
 * @since 2.7.1
 */
class UpdateStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 2.7.1
     *
     * @var string
     */
    protected $signature = 'update:status
                            {--json : Output the raw persisted state as JSON}
                            {--clear : Discard the recorded state after reporting it}';

    /**
     * The console command description.
     *
     * @since 2.7.1
     *
     * @var string
     */
    protected $description = 'Report the status of the most recent application update';

    /**
     * Execute the console command.
     *
     * @since 2.7.1
     *
     * @param  ApplicationUpdateManager  $manager  Update manager.
     *
     * @return int Command exit code.
     */
    public function handle( ApplicationUpdateManager $manager ): int
    {
        $state = $manager->updateState();

        if ( null === $state ) {
            if ( $this->option( 'json' ) ) {
                $this->line( (string) json_encode( null ) );
            } else {
                $this->info( __( 'No application update has been recorded.' ) );
            }

            return self::SUCCESS;
        }

        if ( $this->option( 'json' ) ) {
            $this->line( (string) json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

            $this->maybeClear( $manager );

            return self::SUCCESS;
        }

        // The record is read after a crash by definition, so treat every field
        // as untrusted rather than casting a half-written value.
        $status = UpdateRunStatus::tryFrom( $this->stringField( $state, 'status' ) );
        $step   = UpdateStep::tryFrom( $this->stringField( $state, 'step' ) );

        $this->renderSummary( $state, $status, $step );

        if ( UpdateRunStatus::Interrupted === $status ) {
            $this->renderRecovery( $step );
        }

        $this->maybeClear( $manager );

        return null !== $status && $status->needsAttention() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Render the headline status block.
     *
     * @since 2.7.1
     *
     * @param  array<string, mixed>  $state  Persisted state.
     * @param  UpdateRunStatus|null  $status  Parsed status, when recognised.
     * @param  UpdateStep|null  $step  Parsed step, when recognised.
     */
    protected function renderSummary( array $state, ?UpdateRunStatus $status, ?UpdateStep $step ): void
    {
        $label = $status?->label() ?? ( $this->stringField( $state, 'status' ) ?: __( 'Unknown' ) );

        $this->newLine();

        match ( $status ) {
            UpdateRunStatus::Completed   => $this->info( '✓ ' . $label ),
            UpdateRunStatus::InProgress  => $this->warn( '… ' . $label ),
            UpdateRunStatus::Failed,
            UpdateRunStatus::Interrupted => $this->error( '✗ ' . $label ),
            default                      => $this->line( $label ),
        };

        $this->newLine();

        $totalSteps = count( UpdateStep::cases() );

        $rows = [
            [__( 'From version' ), $this->stringField( $state, 'current_version' ) ?: '—'],
            [__( 'To version' ), $this->stringField( $state, 'target_version' ) ?: '—'],
            [
                __( 'Last step' ),
                null !== $step
                    ? "{$step->number()}/{$totalSteps} — {$step->label()}"
                    : ( $this->stringField( $state, 'step_label' ) ?: '—' ),
            ],
            [__( 'Started' ), $this->stringField( $state, 'started_at' ) ?: '—'],
            [__( 'Last updated' ), $this->stringField( $state, 'updated_at' ) ?: '—'],
            [__( 'PHP SAPI' ), $this->stringField( $state, 'php_sapi' ) ?: '—'],
        ];

        $this->table( [__( 'Field' ), __( 'Value' )], $rows );

        $error = $this->stringField( $state, 'error' );

        if ( '' !== $error ) {
            $this->newLine();
            $this->line( __( 'Error:' ) );
            $this->error( $error );
        }

        if ( UpdateRunStatus::InProgress === $status ) {
            $this->newLine();
            $this->comment( __( 'An update is either still running or died without triggering its shutdown handler (e.g. `kill -9`). Check whether the recorded PID is still alive before intervening.' ) );
        }
    }

    /**
     * Render the manual recovery checklist for an interrupted update.
     *
     * @since 2.7.1
     *
     * @param  UpdateStep|null  $step  Step the update died on.
     */
    protected function renderRecovery( ?UpdateStep $step ): void
    {
        $this->newLine();
        $this->warn( __( 'The update process died before finishing. The install may be half-applied.' ) );
        $this->newLine();

        if ( null === $step ) {
            $this->line( __( 'The step in flight was not recorded. Restore the pre-update snapshot from storage/backups/application/ before continuing.' ) );

            return;
        }

        if ( $step->number() < UpdateStep::ComposerInstall->number() ) {
            $this->line( __( 'It died during download or extraction, which is not resumable — the application tree may be partially overwritten. Restore the pre-update snapshot from storage/backups/application/ instead of finishing by hand.' ) );

            return;
        }

        $this->line( __( 'These steps had not completed. Run them in order to finish the update:' ) );
        $this->newLine();

        foreach ( $step->outstandingSteps() as $outstanding ) {
            $command = $outstanding->recoveryCommand();

            if ( null === $command ) {
                continue;
            }

            $this->line( "  {$outstanding->number()}. {$outstanding->label()}" );
            $this->comment( "     {$command}" );
        }

        $this->newLine();
        $this->line( __( 'If you would rather not finish by hand, restore the pre-update snapshot from storage/backups/application/.' ) );
    }

    /**
     * Read a field from the persisted record as a string, returning `''` for
     * anything that isn't a scalar. Guards against rendering a half-written or
     * hand-edited record — casting an array to string would emit a notice and
     * print "Array".
     *
     * @since 2.7.1
     *
     * @param  array<string, mixed>  $state  Persisted state.
     * @param  string  $key  Field to read.
     *
     * @return string Field value, or an empty string.
     */
    protected function stringField( array $state, string $key ): string
    {
        $value = $state[ $key ] ?? null;

        return is_scalar( $value ) ? trim( (string) $value ) : '';
    }

    /**
     * Discard the recorded state when `--clear` was passed.
     *
     * @since 2.7.1
     *
     * @param  ApplicationUpdateManager  $manager  Update manager.
     */
    protected function maybeClear( ApplicationUpdateManager $manager ): void
    {
        if ( ! $this->option( 'clear' ) ) {
            return;
        }

        $manager->clearUpdateState();

        if ( ! $this->option( 'json' ) ) {
            $this->newLine();
            $this->info( __( 'Recorded update state cleared.' ) );
        }
    }
}
