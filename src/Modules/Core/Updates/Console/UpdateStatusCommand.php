<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\ResolvesConfiguredPaths;
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
    use ResolvesConfiguredPaths;

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

        // The record is read after a crash by definition, so treat every field
        // as untrusted rather than casting a half-written value.
        $status = UpdateRunStatus::tryFrom( $this->stringField( $state, 'status' ) );
        $step   = UpdateStep::tryFrom( $this->stringField( $state, 'step' ) );

        // Resolved before the output branch so both modes agree. `--json` is
        // the machine-consumption path — `php artisan update:status --json ||
        // alert` is the natural monitoring idiom — and it returned SUCCESS
        // unconditionally, so it never alerted on a failed or interrupted run
        // while the very same state without `--json` exited 1.
        $exitCode = null !== $status && $status->needsAttention() ? self::FAILURE : self::SUCCESS;

        if ( $this->option( 'json' ) ) {
            $this->line( (string) json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

            $this->maybeClear( $manager );

            return $exitCode;
        }

        $this->renderSummary( $state, $status, $step );

        if ( UpdateRunStatus::Interrupted === $status ) {
            $this->renderRecovery( $step );
        }

        $this->maybeClear( $manager );

        return $exitCode;
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

        if ( UpdateRunStatus::Failed === $status ) {
            $this->renderRollbackOutcome( $state );
        }

        if ( UpdateRunStatus::InProgress === $status ) {
            $this->newLine();
            $this->comment( __( 'An update is either still running or died without triggering its shutdown handler (e.g. `kill -9`). Check whether the recorded PID is still alive before intervening.' ) );
        }
    }

    /**
     * Report what became of the pre-update snapshot after a failed run.
     *
     * The status label used to assert "Failed (rolled back)" unconditionally,
     * so an operator looking at a **failed rollback** — the most dangerous
     * state this updater produces — was told the restore had succeeded.
     *
     * @since 2.7.1
     *
     * @param  array<string, mixed>  $state  Persisted state.
     */
    protected function renderRollbackOutcome( array $state ): void
    {
        // Treated as untrusted like every other field this command reads —
        // the record is examined after a crash by definition, so a
        // half-written or hand-edited value must not be able to masquerade as
        // a successful rollback.
        $rolledBack = $state['rolled_back'] ?? null;
        $rolledBack = is_bool( $rolledBack ) ? $rolledBack : null;

        $this->newLine();

        if ( true === $rolledBack ) {
            $this->info( __( 'The pre-update snapshot was restored successfully.' ) );

            return;
        }

        if ( false === $rolledBack ) {
            $this->error( __( 'The rollback ITSELF failed. The application tree is in an unknown state and needs manual attention — restore from :dir by hand before bringing the site back up.', ['dir' => $this->backupDirectory()] ) );

            return;
        }

        $this->warn( __( 'No rollback was attempted — either backups are disabled, or the run failed before the snapshot was taken. The tree may still carry a partial update.' ) );
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

        $backupDir = $this->backupDirectory();

        if ( null === $step ) {
            $this->line( __( 'The step in flight was not recorded. Restore the pre-update snapshot from :dir before continuing.', ['dir' => $backupDir] ) );

            return;
        }

        // Steps 1-2 are special: nothing has overwritten the application tree
        // yet. Sending the operator to "restore the snapshot" is wrong at step
        // 1 (no snapshot exists) and actively dangerous at step 2, where a
        // death mid-backup can leave a *truncated* backup-*.zip that this
        // advice would invite them to extract over a perfectly healthy tree.
        if ( $step->number() <= UpdateStep::Backup->number() ) {
            $this->line( __( 'It died before any application files were modified, so the tree is untouched. Run `php artisan up` and retry the update — do not restore a snapshot.' ) );

            if ( UpdateStep::Backup === $step ) {
                $this->newLine();
                $this->comment( __( 'A partial backup archive may have been left behind in :dir. Delete it before retrying.', ['dir' => $backupDir] ) );
            }

            return;
        }

        if ( $step->number() < UpdateStep::ComposerInstall->number() ) {
            $this->line( __( 'It died during download or extraction, which is not resumable — the application tree may be partially overwritten. Restore the pre-update snapshot from :dir instead of finishing by hand.', ['dir' => $backupDir] ) );

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
        $this->line( __( 'If you would rather not finish by hand, restore the pre-update snapshot from :dir.', ['dir' => $backupDir] ) );
    }

    /**
     * Absolute path to the configured backup directory.
     *
     * The operator guidance above used to hardcode
     * `storage/backups/application/`, so a host that had customized
     * `cms.updates.backup_path` was pointed at a directory that does not
     * exist.
     *
     * @since 2.7.1
     *
     * @return string Resolved backup directory.
     */
    protected function backupDirectory(): string
    {
        return $this->resolveConfiguredPath(
            (string) config( 'cms.updates.backup_path', 'backups/application' ),
        );
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
