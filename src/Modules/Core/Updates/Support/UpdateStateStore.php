<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Jobs\PerformUpdateJob;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Update State Store
 *
 * Persists which step of `performUpdate()` is in flight so an update that is
 * killed mid-process (PHP fatal, OOM, `kill -9`, FPM `request_terminate_timeout`)
 * leaves a record behind. Without it there is no way to distinguish "update in
 * progress", "update died at step 6", and "the site was manually put into
 * maintenance mode".
 *
 * Deliberately a flat JSON file rather than the cache:
 *
 * - Step 8 of the update runs `cache:clear`, which would wipe a cache-backed
 *   marker at exactly the point it becomes interesting.
 * - The database-backed cache driver is unavailable while step 7's migrations
 *   are mid-flight.
 * - `storage/` is in `cms.updates.exclude_from_update`, so the file survives
 *   extraction of the new release.
 *
 * Every write is best-effort: failing to record state must never abort an
 * otherwise-healthy update.
 *
 * @since 2.7.1
 */
class UpdateStateStore
{
    use ResolvesConfiguredPaths;

    /**
     * Default state file location, relative to `storage_path()`.
     *
     * @since 2.7.1
     */
    public const DEFAULT_STATE_PATH = 'framework/cms-update-state.json';

    /**
     * Absolute path to the state file.
     *
     * Honours an absolute `cms.updates.state_path` verbatim so hosts (and
     * tests) can place the marker outside `storage/`.
     *
     * @since 2.7.1
     *
     * @return string Absolute path to the state file.
     */
    public function path(): string
    {
        $configured = config( 'cms.updates.state_path', self::DEFAULT_STATE_PATH );

        if ( ! is_string( $configured ) || '' === $configured ) {
            $configured = self::DEFAULT_STATE_PATH;
        }

        return $this->resolveConfiguredPath( $configured );
    }

    /**
     * Record that an update has been pushed onto the queue and is waiting for a
     * worker, replacing any previous record.
     *
     * The target version is whatever the caller asked for, which is `null` when
     * they asked for "latest" — resolving it would mean a network round-trip in
     * the request that is dispatching. `begin()` fills in the resolved version
     * once the job actually starts.
     *
     * @since 2.8.0
     *
     * @param  string|null  $targetVersion  Requested version, or null for latest.
     * @param  string|null  $currentVersion  Version installed before the update.
     * @param  string|null  $connection  Queue connection the job was pushed to.
     * @param  string|null  $queue  Queue name the job was pushed to.
     */
    public function markQueued(
        ?string $targetVersion = null,
        ?string $currentVersion = null,
        ?string $connection = null,
        ?string $queue = null,
    ): void {
        $this->write( [
            'status'           => UpdateRunStatus::Queued->value,
            'step'             => null,
            'step_number'      => null,
            'step_label'       => null,
            'target_version'   => $targetVersion,
            'current_version'  => $currentVersion,
            'php_sapi'         => PHP_SAPI,
            'pid'              => null,
            'queued_at'        => $this->timestamp(),
            'queue_connection' => $connection,
            'queue_name'       => $queue,
            'started_at'       => null,
            'error'            => null,
        ] );
    }

    /**
     * Record the start of an update run, replacing any previous record.
     *
     * @since 2.7.1
     *
     * @param  string  $targetVersion  Version being installed.
     * @param  string|null  $currentVersion  Version installed before the update.
     */
    public function begin( string $targetVersion, ?string $currentVersion = null ): void
    {
        $pid = getmypid();

        $this->write( array_merge( $this->queueProvenance(), [
            'status'          => UpdateRunStatus::InProgress->value,
            'step'            => null,
            'step_number'     => null,
            'step_label'      => null,
            'target_version'  => $targetVersion,
            'current_version' => $currentVersion,
            'php_sapi'        => PHP_SAPI,
            'pid'             => false === $pid ? null : $pid,
            'started_at'      => $this->timestamp(),
            'error'           => null,
        ] ) );
    }

    /**
     * Record that a step is now in flight.
     *
     * @since 2.7.1
     *
     * @param  UpdateStep  $step  Step being entered.
     */
    public function markStep( UpdateStep $step ): void
    {
        $this->merge( [
            'status'      => UpdateRunStatus::InProgress->value,
            'step'        => $step->value,
            'step_number' => $step->number(),
            'step_label'  => $step->label(),
        ] );
    }

    /**
     * Record a terminal status for the current run.
     *
     * @since 2.7.1
     *
     * @param  UpdateRunStatus  $status  Status to record.
     * @param  string|null  $error  Error message, when the run did not succeed.
     */
    public function markStatus( UpdateRunStatus $status, ?string $error = null ): void
    {
        $this->merge( [
            'status' => $status->value,
            'error'  => $error,
        ] );
    }

    /**
     * Record what happened to the pre-update snapshot after a failed run.
     *
     * Kept as its own field rather than folded into the status label, so the
     * two cannot drift. The label used to assert `Failed (rolled back)`
     * unconditionally, which told the operator the rollback had succeeded even
     * when it had failed, when backups were disabled, or when the failure
     * happened before a snapshot existed. A failed rollback is the single most
     * dangerous state this updater can produce, and it was being reported as
     * the safest.
     *
     * @since 2.7.1
     *
     * @param  bool|null  $rolledBack  True on success, false on failure, null when no rollback was attempted.
     * @param  string|null  $error  Combined failure message, when the rollback itself failed.
     */
    public function markRollback( ?bool $rolledBack, ?string $error = null ): void
    {
        $attributes = ['rolled_back' => $rolledBack];

        if ( null !== $error ) {
            $attributes['error'] = $error;
        }

        $this->merge( $attributes );
    }

    /**
     * Read the persisted state, or `null` when no update has been recorded or
     * the file is unreadable/corrupt.
     *
     * @since 2.7.1
     *
     * @return array<string, mixed>|null Persisted state.
     */
    public function read(): ?array
    {
        try {
            $path = $this->path();

            if ( ! File::exists( $path ) ) {
                return null;
            }

            $decoded = json_decode( (string) File::get( $path ), true );
        } catch ( Throwable $e ) {
            Log::warning( 'cms-framework: could not read the update state file.', [
                'exception' => $e->getMessage(),
            ] );

            return null;
        }

        return is_array( $decoded ) ? $decoded : null;
    }

    /**
     * Delete the state file.
     *
     * @since 2.7.1
     */
    public function clear(): void
    {
        try {
            $path = $this->path();

            if ( File::exists( $path ) ) {
                File::delete( $path );
            }
        } catch ( Throwable $e ) {
            Log::warning( 'cms-framework: could not clear the update state file.', [
                'exception' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Whether a recorded timestamp is older than the window a queued update is
     * given to be claimed.
     *
     * An unparseable or missing timestamp counts as stale — a record that
     * cannot be dated must not be trusted to describe the current run, and
     * must not block a future one either.
     *
     * @since 2.8.0
     *
     * @param  string|null  $timestamp  ISO-8601 timestamp from the record.
     *
     * @return bool True when the timestamp is outside the window.
     */
    public function isStaleTimestamp( ?string $timestamp ): bool
    {
        if ( null === $timestamp ) {
            return true;
        }

        $parsed = strtotime( $timestamp );

        if ( false === $parsed ) {
            return true;
        }

        return ( time() - $parsed ) >= PerformUpdateJob::resolveTimeout();
    }

    /**
     * The queue fields to carry into a run that started life on the queue.
     *
     * `begin()` deliberately replaces the record rather than merging, so a
     * previous run's fields cannot leak into the current one. That would
     * otherwise drop `queued_at` / `queue_connection` / `queue_name` the moment
     * the worker picked the job up — the operator would see a run in progress
     * with no indication it came from a queue, and no way to tell how long it
     * waited.
     *
     * A `Queued` record does not by itself prove the run belongs to it. The
     * abandoned-dispatch case is real and the rest of the feature works to
     * handle it: `update:perform --queue` with no worker running leaves the
     * record at `queued`, the operator gives up and runs a plain
     * `update:perform`, and without the freshness gate that inline run would
     * be stamped with the dead dispatch's queue — `update:status` showing
     * "Queue: redis / cms-updates" for a run executing in the operator's own
     * terminal, and pointing them at a worker with nothing to do with it if it
     * dies. Same window `dispatchUpdate()` uses to decide a queued record has
     * been abandoned.
     *
     * @since 2.8.0
     *
     * @return array<string, mixed> Queue provenance fields, or an empty array.
     */
    protected function queueProvenance(): array
    {
        $existing = $this->read();

        if ( null === $existing ) {
            return [];
        }

        $status = UpdateRunStatus::tryFrom(
            is_string( $existing['status'] ?? null ) ? $existing['status'] : '',
        );

        if ( UpdateRunStatus::Queued !== $status ) {
            return [];
        }

        $queuedAt = $existing['queued_at'] ?? null;

        if ( $this->isStaleTimestamp( is_string( $queuedAt ) ? $queuedAt : null ) ) {
            return [];
        }

        return [
            'queued_at'        => $queuedAt,
            'queue_connection' => $existing['queue_connection'] ?? null,
            'queue_name'       => $existing['queue_name'] ?? null,
        ];
    }

    /**
     * Merge attributes into the persisted state.
     *
     * @since 2.7.1
     *
     * @param  array<string, mixed>  $attributes  Attributes to merge.
     */
    protected function merge( array $attributes ): void
    {
        $existing = $this->read();

        if ( null === $existing ) {
            // The record is gone or unreadable — a hand-cleared state file, a
            // transient read failure. Merging into an empty array would drop
            // `target_version`, `started_at` and `pid` for the rest of the
            // run, so `update:status` would render `—` for all of them.
            // Reconstructing what we can beats silently discarding it.
            Log::warning( 'cms-framework: the update state file was unreadable during a merge; the record will be partially reconstructed.' );

            $existing = [];
        }

        $this->write( array_merge( $existing, $attributes ) );
    }

    /**
     * Write the state file. Never throws — a host whose `storage/` is
     * read-only still gets its update, it just loses the diagnostic.
     *
     * The write is staged through a temporary file and moved into place with
     * `rename()`, which is atomic within a filesystem. Writing in place would
     * truncate first, so a process dying mid-write — precisely the scenario
     * this marker exists to record — could destroy the last good record and
     * leave a truncated file behind instead.
     *
     * @since 2.7.1
     *
     * @param  array<string, mixed>  $state  State to persist.
     */
    protected function write( array $state ): void
    {
        $state['updated_at'] = $this->timestamp();

        $temporaryPath = null;

        try {
            $path      = $this->path();
            $directory = dirname( $path );

            if ( ! File::isDirectory( $directory ) ) {
                File::makeDirectory( $directory, 0700, true );
            }

            // Composer and migration output can carry arbitrary bytes, and a
            // single invalid UTF-8 sequence in an error message would
            // otherwise make the whole record unwritable.
            $encoded = json_encode(
                $state,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            if ( false === $encoded ) {
                Log::warning( 'cms-framework: could not encode the update state; an interrupted update will not be diagnosable.' );

                return;
            }

            // Randomized rather than `getmypid()`-derived, and created with
            // `x` so an existing path is never followed. The predictable name
            // written via `File::put()` followed symlinks, so a local user who
            // could create files in the state directory had a JSON write
            // primitive — narrow with the default location, but the config
            // explicitly invites absolute paths, and a world-writable
            // `/tmp/cms-update-state.json` would qualify.
            $temporaryPath = $path . '.' . bin2hex( random_bytes( 8 ) ) . '.tmp';

            $handle = @fopen( $temporaryPath, 'xb' );

            if ( false === $handle ) {
                throw new RuntimeException( "Could not create the update state temp file at {$temporaryPath}" );
            }

            if ( ! @chmod( $temporaryPath, 0600 ) ) {
                // Logged rather than thrown: the record is worth more than the
                // permission bits. Aborting here would leave a host with no
                // diagnostic at all for an interrupted update, which is the
                // problem this store exists to solve — and every write in this
                // class is deliberately best-effort.
                Log::warning( 'cms-framework: could not restrict permissions on the update state file.', [
                    'path' => $temporaryPath,
                ] );
            }

            $written = fwrite( $handle, $encoded );
            fclose( $handle );

            if ( false === $written || strlen( $encoded ) !== $written ) {
                throw new RuntimeException( "Could not write the update state file at {$temporaryPath}" );
            }

            if ( ! @rename( $temporaryPath, $path ) ) {
                throw new RuntimeException( "Could not move the update state file into place at {$path}" );
            }

            $temporaryPath = null;
        } catch ( Throwable $e ) {
            if ( null !== $temporaryPath ) {
                @unlink( $temporaryPath );
            }

            Log::warning( 'cms-framework: could not persist the update state file; an interrupted update will not be diagnosable.', [
                'exception' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Current time as an ISO-8601 string.
     *
     * @since 2.7.1
     *
     * @return string ISO-8601 timestamp.
     */
    protected function timestamp(): string
    {
        return date( DATE_ATOM );
    }
}
