<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Jobs\PerformUpdateJob;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\ArchiveChecksum;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\UpdateStateStore;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCheckerFactory;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Composer\InstalledVersions;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * Application Update Manager
 *
 * Handles the complete update process for the application.
 *
 * @since 1.0.0
 */
class ApplicationUpdateManager
{
    /**
     * Default composer install command shipped with the framework. Used as the
     * "unchanged" sentinel when deciding whether the operator has explicitly
     * overridden `cms.updates.composer_install_command`.
     *
     * @since 2.5.3
     */
    public const DEFAULT_COMPOSER_INSTALL_COMMAND = 'composer install --no-dev --no-interaction --optimize-autoloader';

    /**
     * Default composer install arguments used when the framework builds the
     * command from a discovered or env-supplied binary path.
     *
     * @since 2.5.3
     */
    public const DEFAULT_COMPOSER_INSTALL_ARGS = 'install --no-dev --no-interaction --optimize-autoloader';

    /**
     * `composer.json` keys that participate in the lock file's `content-hash`,
     * mirroring `Composer\Package\Locker::getContentHash()`. Kept as a constant
     * so the list is greppable if a future composer release changes it.
     *
     * @since 2.7.1
     *
     * @var array<int, string>
     */
    protected const COMPOSER_CONTENT_HASH_KEYS = [
        'name',
        'version',
        'require',
        'require-dev',
        'conflict',
        'replace',
        'provide',
        'minimum-stability',
        'prefer-stable',
        'repositories',
        'extra',
    ];

    /**
     * Update checker instance.
     *
     * @since 1.0.0
     */
    protected ?UpdateChecker $checker = null;

    /**
     * Path to current backup (for rollback).
     *
     * @since 1.0.0
     */
    protected ?string $backupPath = null;

    /**
     * Relative paths, under `base_path()`, that the current extraction created
     * and that did not exist beforehand.
     *
     * The backup can only restore files it captured, so a file the release
     * *added* survives a snapshot restore untouched — leaving the tree a hybrid
     * of the old version's files and an arbitrary subset of the new version's
     * additions, migrations included. Recording exactly what extraction wrote
     * lets rollback remove precisely those additions and nothing else.
     *
     * @since 2.8.0
     *
     * @var array<int, string>
     */
    protected array $extractionAdditions = [];

    /**
     * Persisted step marker for the current update run.
     *
     * @since 2.7.1
     */
    protected ?UpdateStateStore $state = null;

    /**
     * The step currently in flight, or `null` when no update is running.
     *
     * @since 2.7.1
     */
    protected ?UpdateStep $currentStep = null;

    /**
     * Whether this instance put the site into maintenance mode and has not
     * taken it back out. Read by the shutdown guard to decide whether a dead
     * process left the site serving 503s.
     *
     * @since 2.7.1
     */
    protected bool $maintenanceModeActive = false;

    /**
     * Whether the shutdown guard has already been registered, so repeated
     * `enableMaintenanceMode()` calls don't stack handlers.
     *
     * @since 2.7.1
     */
    protected bool $shutdownGuardArmed = false;

    /**
     * Check for available updates.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     *
     * @return UpdateInfo Update information
     */
    public function checkForUpdate(): UpdateInfo
    {
        $checker = $this->getUpdateChecker();

        return $checker->checkForUpdate();
    }

    /**
     * Perform the update.
     *
     * A full update — download, extract, `composer install` across a real
     * dependency tree, migrate — routinely runs for several minutes. Two
     * guards make that survivable when the caller is an HTTP request rather
     * than the CLI:
     *
     * - `raiseExecutionLimits()` lifts PHP's `max_execution_time` (30s by
     *   default under PHP-FPM) so the parent request isn't killed long before
     *   the composer child's own timeout budget is reached.
     * - `enableMaintenanceMode()` arms a shutdown guard so that if the process
     *   dies anyway, maintenance mode is still lifted rather than leaving the
     *   site serving 503s indefinitely.
     *
     * This method performs **no authorization** — it cannot, because the
     * console commands that call it run with no authenticated user. A caller
     * reached over HTTP must authorize first:
     *
     * ```php
     * Gate::authorize( UpdateCapability::PERFORM );
     * ```
     *
     * It matters more here than almost anywhere else in the framework: this
     * pipeline overwrites PHP files and then runs `composer install`, which
     * executes `post-install-cmd` scripts from the just-overwritten
     * `composer.json`. See `UpdateCapability` and `docs/self-updater.md`.
     *
     * @since 1.0.0
     *
     * @param  string|null  $version  Version to update to (null = latest)
     * @param  bool  $allowDowngrade  Permit installing a version that is not newer than the installed one.
     *
     * @throws UpdateException
     *
     * @return bool True if update successful
     */
    public function performUpdate( ?string $version = null, bool $allowDowngrade = false ): bool
    {
        $this->raiseExecutionLimits();

        // Nothing prevented two simultaneous runs — a double-clicked admin
        // button, or the scheduled `auto_update` racing an operator's
        // `update:perform`. Both would put the site down, both would extract
        // over base_path(), and both would run `composer install` in the same
        // directory; the interleaved writes produce a tree neither rollback
        // repairs, because the second run's backup snapshots the first run's
        // half-extracted state. Run A's step-10 `up` also undoes run B's
        // step-1 `down`, serving traffic mid-extraction.
        $lock = $this->acquireUpdateLock();

        try {
            $updateInfo = $this->checkForUpdate();

            // Use specified version or latest
            $targetVersion = $version ?? $updateInfo->latestVersion;

            // `hasUpdate()` compares *latest* against current, so it only
            // answers "is there something newer to move to." That gate is
            // right when no target was pinned, but a pinned target is checked
            // directly below in both directions (guarded by $allowDowngrade) —
            // letting the latest-only gate run first made a pinned downgrade
            // off a bad *latest* release unreachable, since `noUpdateAvailable`
            // fired before the downgrade comparison was ever reached.
            if ( null === $version && ! $updateInfo->hasUpdate() ) {
                throw UpdateException::noUpdateAvailable();
            }

            // `hasUpdate()` is only ever consulted against *latest*, never
            // against the requested target, so `performUpdate( '1.0.0' )` on a
            // 2.7.1 install was accepted and installed a known-vulnerable
            // older release. Migrations are not reversed either, so the older
            // code then runs against the newer schema.
            if ( ! $allowDowngrade && ! version_compare( $targetVersion, $updateInfo->resolveCurrentVersion(), '>' ) ) {
                throw UpdateException::downgradeNotAllowed( $targetVersion, $updateInfo->resolveCurrentVersion() );
            }

            $this->assertHostSatisfiesRequirements( $updateInfo );

            return $this->runUpdateSteps( $targetVersion, $updateInfo );
        } finally {
            $this->releaseUpdateLock( $lock );
        }
    }

    /**
     * Push an update onto the queue instead of running it inline.
     *
     * **This is the supported entry point for an HTTP-triggered update.** The
     * endpoint should authorize, call this, and then poll `updateState()` for
     * progress; it must not call `performUpdate()` and block. An HTTP-triggered
     * update occupies a PHP-FPM worker for the whole run and keeps occupying it
     * after the caller disconnects, and it is subject to gateway timeouts
     * (nginx `proxy_read_timeout`, load balancers, Cloudflare's 100s) and to
     * FPM's `request_terminate_timeout` — none of which any userland call can
     * override. The 2.7.1 guards make that failure *survivable*; they do not
     * make it appropriate.
     *
     * Authorization is still the caller's job, exactly as it is for
     * `performUpdate()` — dispatching a job that will overwrite PHP files and
     * run `composer install` is the same remote-code-execution channel as
     * running it inline:
     *
     * ```php
     * Gate::authorize( UpdateCapability::PERFORM );
     * $manager->dispatchUpdate();
     * ```
     *
     * Refuses rather than degrades when the queue cannot actually run the job
     * in the background — see `guardQueueDriver()`. A `sync` connection would
     * reintroduce the exact blocking behavior this method exists to avoid,
     * silently, which is the worst outcome available here.
     *
     * @since 2.8.0
     *
     * @param  string|null  $version  Version to install, or null for latest.
     * @param  bool  $allowDowngrade  Permit a target that is not newer than the installed one.
     *
     * @throws UpdateException When the queue is unusable, or an update is already queued or running.
     *
     * @return PerformUpdateJob The dispatched job.
     */
    public function dispatchUpdate( ?string $version = null, bool $allowDowngrade = false ): PerformUpdateJob
    {
        $connection = $this->updateQueueConnection();
        $queue      = $this->updateQueueName();

        $this->guardQueueDriver( $connection );
        $this->guardNoPendingUpdate();

        $job = new PerformUpdateJob( $version, $allowDowngrade );
        $job->onConnection( $connection );

        if ( null !== $queue ) {
            $job->onQueue( $queue );
        }

        // Acquired here rather than left to `PerformUpdateJob::dispatch()`,
        // which takes the same lock from `PendingDispatch::__destruct()` and
        // then *silently returns* when it cannot get it. Silence is the wrong
        // answer for a double-clicked admin button: the caller would be told
        // the update was queued, the state file would say `queued`, and
        // nothing would ever run it.
        $lock = new UniqueLock( app( CacheRepository::class ) );

        if ( ! $lock->acquire( $job ) ) {
            throw UpdateException::updateAlreadyQueued( $this->queuedAt() );
        }

        $this->state()->markQueued( $version, $this->installedVersion(), $connection, $queue );

        try {
            app( Dispatcher::class )->dispatch( $job );
        } catch ( Throwable $e ) {
            // A queue backend that is configured but unreachable — Redis down,
            // a missing `jobs` table. Without this the record would sit at
            // `queued` forever describing a job that was never accepted.
            //
            // Only when the push itself failed, though. Under `allow_sync` the
            // dispatch *is* the whole update: `SyncQueue::handleException()`
            // fails the job — releasing the lock and running
            // `handleFailedUpdateJob()` — and only then rethrows. Re-stamping
            // here would overwrite the outcome that reconciliation just
            // settled, and release a lock that is already released. A record
            // still sitting at `queued` is the signal that nothing ran.
            $recorded = $this->updateState();
            $recorded = is_string( $recorded['status'] ?? null ) ? $recorded['status'] : '';

            if ( UpdateRunStatus::Queued === UpdateRunStatus::tryFrom( $recorded ) ) {
                $lock->release( $job );

                $this->state()->markStatus( UpdateRunStatus::Failed, $e->getMessage() );
            }

            throw $e;
        }

        Log::info( 'cms-framework: an application update was queued.', [
            'target_version'   => $version,
            'queue_connection' => $connection,
            'queue_name'       => $queue,
            'timeout'          => $job->timeout,
        ] );

        return $job;
    }

    /**
     * Reconcile the persisted record after the queued update job failed.
     *
     * Called from `PerformUpdateJob::failed()`. The case this exists for is the
     * worker timeout: the worker kills the run mid-step, so `performUpdate()`'s
     * `catch` never marks the record and — under `kill -9`-style termination —
     * the shutdown guard never lifts maintenance mode either. Left alone, the
     * record would claim `in_progress` forever and the site would keep serving
     * 503s, which is precisely the failure the interruption machinery exists to
     * prevent, reintroduced through the queue.
     *
     * @since 2.8.0
     *
     * @param  Throwable|null  $exception  The failure, when there was one.
     */
    public function handleFailedUpdateJob( ?Throwable $exception = null ): void
    {
        $message = null !== $exception
            ? $exception->getMessage()
            : 'The queued update job failed without reporting an error.';

        // The record this job failed against may not be its own. Two ways to
        // arrive here holding someone else's:
        //
        // - `performUpdate()` takes the `flock` sentinel *before* its own try
        //   block, so losing that race throws `updateAlreadyRunning` straight
        //   past the bookkeeping and into the worker.
        // - The queue redelivers a long-running job once `retry_after`
        //   elapses. With `$tries = 1` the duplicate is failed without
        //   `handle()` ever running, so it arrives here having done nothing.
        //
        // In both cases the live record belongs to a run that may still be
        // extracting over `base_path()`. Marking it `Interrupted` would report
        // a healthy run as dead, and lifting maintenance mode would serve
        // traffic from a half-extracted tree — the exact outcome the whole
        // interruption machinery exists to prevent, caused by the machinery
        // itself. `runningUpdatePid()` is already the liveness probe for this.
        $foreignPid = $this->runningUpdatePid();

        if ( null !== $foreignPid ) {
            Log::warning( 'cms-framework: a queued application update job failed against a run owned by another live process; leaving that run alone.', [
                'running_pid' => $foreignPid,
                'error'       => $message,
            ] );

            return;
        }

        $recorded = $this->updateState();
        $status   = UpdateRunStatus::tryFrom(
            is_string( $recorded['status'] ?? null ) ? $recorded['status'] : '',
        );

        if ( null !== $status && $status->isTerminal() ) {
            // `performUpdate()` caught the error, rolled back, and recorded the
            // real reason before rethrowing it into the worker. Overwriting
            // that with the same message would be noise; overwriting a
            // `Completed` record — a job that finished the update and then
            // failed on the way out — would be a lie.
            Log::error( 'cms-framework: the queued application update job failed after the run had already been recorded as finished.', [
                'recorded_status' => $status->value,
                'error'           => $message,
            ] );

            return;
        }

        $step = is_string( $recorded['step'] ?? null ) ? $recorded['step'] : null;

        if ( UpdateRunStatus::InProgress === $status ) {
            Log::critical( 'cms-framework: the queued application update was killed while it was running; the job is gone but the update never finished.', [
                'step'       => $step,
                'error'      => $message,
                'state_file' => $this->state()->path(),
                'hint'       => 'Run `php artisan update:status` to see how far it got and what remains.',
            ] );

            $this->state()->markStatus( UpdateRunStatus::Interrupted, $message );

            if ( $this->maintenanceModeActive || null !== $step ) {
                $this->liftMaintenanceModeAfterFailure(
                    'the failed queued update job',
                    null !== $step ? UpdateStep::tryFrom( $step ) : null,
                );
            }

            return;
        }

        // Still `queued` (or no readable record at all): the job never reached
        // `performUpdate()`'s own bookkeeping, so nothing else will record this.
        Log::error( 'cms-framework: the queued application update failed before it started.', [
            'recorded_status' => $status?->value,
            'error'           => $message,
        ] );

        $this->state()->markStatus( UpdateRunStatus::Failed, $message );
    }

    /**
     * Persisted record of the most recent update run, or `null` when no update
     * has been recorded. Host applications can poll this to surface progress
     * and to detect an update that died mid-flight.
     *
     * @since 2.7.1
     *
     * @return array<string, mixed>|null Persisted update state.
     */
    public function updateState(): ?array
    {
        return $this->state()->read();
    }

    /**
     * Discard the persisted update state record.
     *
     * Also releases the dispatch lock. The record and the lock live in
     * different places — a file under `storage/` and the cache — so they can
     * desynchronise, and clearing only the record would leave the operator
     * unable to dispatch until the lock aged out: `guardNoPendingUpdate()`
     * would pass (no record) and the lock acquire would then fail, reporting
     * "an update is already queued" while `update:status` reported that none
     * had ever been recorded. The lock key is Laravel-internal, so the only
     * other way out was `cache:clear`. Discarding the record is the operator's
     * reset button; it has to actually reset.
     *
     * @since 2.7.1
     */
    public function clearUpdateState(): void
    {
        $this->state()->clear();

        try {
            ( new UniqueLock( app( CacheRepository::class ) ) )->release( new PerformUpdateJob );
        } catch ( Throwable $e ) {
            Log::warning( 'cms-framework: could not release the queued-update dispatch lock while clearing the update state.', [
                'exception' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Shutdown guard: lift maintenance mode when the update process died
     * before reaching step 10.
     *
     * An execution-time or out-of-memory fatal is not a catchable `Throwable`
     * — it is raised at shutdown, so `performUpdate()`'s `catch` block never
     * runs, `handleUpdateFailure()` never rolls back, and step 10 never
     * executes. Without this guard the operator is left with a site returning
     * 503 to every visitor, no error in the UI (the request died before
     * rendering a response), and no automatic way back.
     *
     * Registered by `enableMaintenanceMode()` and public only so it can be
     * exercised directly by tests; treat it as internal.
     *
     * @since 2.7.1
     */
    public function handleInterruptedUpdate(): void
    {
        if ( ! $this->maintenanceModeActive ) {
            return;
        }

        // Clear first: a failure below must not re-enter this handler.
        $this->maintenanceModeActive = false;

        // The persisted record may already be terminal. `performUpdate()`'s
        // catch marks `Failed` with the real error and then calls
        // `handleUpdateFailure()`; if lifting maintenance mode threw in there,
        // the active flag is still set and this guard fires at shutdown even
        // though the process never died and the error was caught and handled.
        // Re-stamping would replace a real SQL error with "the update process
        // terminated before completing" and print a resume checklist for a
        // tree that has already been rolled back. Maintenance mode still gets
        // lifted below — we simply do not rewrite history.
        $recorded        = $this->updateState();
        $recordedStatus  = UpdateRunStatus::tryFrom(
            is_string( $recorded['status'] ?? null ) ? $recorded['status'] : '',
        );
        $alreadyTerminal = null !== $recordedStatus && $recordedStatus->isTerminal();

        $fatal = $this->lastFatalError();

        Log::critical(
            $alreadyTerminal
                ? 'cms-framework: the update run had already finished, but maintenance mode was still active at shutdown.'
                : 'cms-framework: the update process terminated before it finished; the site was left in maintenance mode.',
            [
                'step'             => $this->currentStep?->value,
                'step_label'       => $this->currentStep?->label(),
                'php_sapi'         => PHP_SAPI,
                'error'            => $fatal,
                'recorded_status'  => $recordedStatus?->value,
                'state_file'       => $this->state()->path(),
                'hint'             => 'Run `php artisan update:status` to see how far the update got and what remains.',
            ],
        );

        if ( ! $alreadyTerminal ) {
            // Re-record the step from memory before stamping the status: an
            // earlier per-step write may have failed (read-only storage, a disk
            // that filled up), and the whole value of the marker is that it agrees
            // with where the update actually was when it died.
            if ( null !== $this->currentStep ) {
                $this->state()->markStep( $this->currentStep );
            }

            $this->state()->markStatus(
                UpdateRunStatus::Interrupted,
                $fatal['message'] ?? 'The update process terminated before completing.',
            );
        }

        $this->liftMaintenanceModeAfterFailure( 'the update shutdown guard', $this->currentStep );
    }

    /**
     * Set a custom update checker.
     *
     * @since 1.0.0
     *
     * @param  UpdateChecker  $checker  Update checker instance
     */
    public function setUpdateChecker( UpdateChecker $checker ): void
    {
        $this->checker = $checker;
    }

    /**
     * Rollback to a previous backup.
     *
     * Performs no authorization, for the same reason `performUpdate()` does
     * not. A caller reached over HTTP must first
     * `Gate::authorize( UpdateCapability::ROLLBACK )` — restoring a snapshot
     * reinstates its `composer.json` and `composer.lock` and then runs
     * `composer install` against them.
     *
     * @since 1.0.0
     *
     * @param  string  $backupPath  Path to backup ZIP
     *
     * @throws UpdateException
     */
    public function rollback( string $backupPath ): void
    {
        // Rollback re-runs `composer install`, so it is subject to the same
        // execution-time ceiling as the update itself when invoked over HTTP.
        $this->raiseExecutionLimits();

        if ( ! File::exists( $backupPath ) ) {
            throw UpdateException::rollbackFailed( "Backup not found: {$backupPath}" );
        }

        $zip = new ZipArchive;

        if ( true !== $zip->open( $backupPath ) ) {
            throw UpdateException::rollbackFailed( 'Could not open backup ZIP' );
        }

        // Unlike extractUpdate(), rollback consulted no exclusion filter at
        // all — so a backup ZIP carrying `.env` or `vendor/autoload.php`
        // replaced the live copies, and `runComposerInstall()` below then
        // executed scripts from the restored `composer.json`. Backups this
        // framework writes never contain `.env` (they reuse the same exclusion
        // list), but `update:rollback` will extract whatever ZIP it is
        // pointed at.
        $excludePaths = config( 'cms.updates.exclude_from_update', [] );
        $restore      = [];

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name      = (string) $zip->getNameIndex( $i );
            $canonical = $this->canonicalizeArchivePath( $name );

            if ( null === $canonical || '' === $canonical ) {
                continue;
            }

            if ( $this->isPathExcluded( $canonical, $excludePaths ) ) {
                Log::info( 'cms-framework: skipping an excluded entry while restoring a backup.', [
                    'entry' => $name,
                ] );

                continue;
            }

            $restore[] = $name;
        }

        // `extractTo()` with an explicit entry list stays the right tool here:
        // it was empirically confirmed safe against traversal — `..` and
        // absolute paths stripped, symlink entries written as regular files,
        // permission bits discarded. Only the exclusion filter was missing.
        //
        // Its return value is checked: `extractTo()` returns false on a
        // mid-extraction failure (disk full, permission error), and swallowing
        // that would let `update:status` report "the snapshot was restored
        // successfully" over a half-restored tree — the exact false reassurance
        // the `rolled_back` field exists to prevent.
        if ( true !== $zip->extractTo( base_path(), $restore ) ) {
            $zip->close();

            throw UpdateException::rollbackFailed( 'backup extraction failed partway' );
        }

        $zip->close();

        // Before invoking composer install, verify the resolved binary is
        // reachable. If it's not (e.g. PHP-FPM's PATH doesn't include composer
        // on a Herd host) surface a specific error rather than "Manual
        // intervention required" — no filesystem state has actually been
        // damaged at this point since vendor/ was untouched by the update.
        $this->verifyComposerBinaryAvailable();

        // Restore composer dependencies
        $this->runComposerInstall();

        // Clear caches
        $this->clearCaches();
    }

    /**
     * Clear the update check cache.
     *
     * @since 1.0.0
     */
    public function clearCache(): void
    {
        $this->getUpdateChecker()->clearCache();
    }

    /**
     * Remove extraction additions the persisted ledger recorded, for a manual
     * rollback in a fresh process.
     *
     * `handleUpdateFailure()` still has the in-memory ledger and calls
     * {@see removeExtractionAdditions()} directly. The manual
     * `update:rollback` path runs in a new process where that ledger is empty,
     * so this reloads it from the state file (written by `extractUpdate()`)
     * before removing — turning the automatic-only #272 cleanup into one the
     * manual path performs too. Every path is re-validated by
     * `removeExtractionAdditions()` (exclusion list, regular-file check,
     * containment), so a stale or tampered ledger entry is safe to process.
     *
     * @since 2.8.0
     */
    public function removeOrphanedExtractionAdditions(): void
    {
        if ( empty( $this->extractionAdditions ) ) {
            $persisted = $this->updateState()['extraction_additions'] ?? null;

            if ( is_array( $persisted ) ) {
                $this->extractionAdditions = array_values( array_filter( $persisted, 'is_string' ) );
            }
        }

        $this->removeExtractionAdditions();
    }

    /**
     * Refuse a release the host cannot run before any file is touched.
     *
     * `UpdateInfo` parses, caches, and rehydrates `minPhpVersion` /
     * `minFrameworkVersion` but nothing checked them, so a release declaring
     * `min_php_version: 8.4` installed on 8.2 and only failed once the new code
     * hit a syntax it could not parse — after the tree had already been
     * overwritten. Checking here fails closed before the first step runs.
     *
     * @since 2.8.0
     *
     * @param  UpdateInfo  $updateInfo  Resolved update metadata.
     *
     * @throws UpdateException When the host does not meet a declared minimum.
     */
    protected function assertHostSatisfiesRequirements( UpdateInfo $updateInfo ): void
    {
        $minPhp = $updateInfo->minPhpVersion;

        if ( is_string( $minPhp ) && '' !== $minPhp && version_compare( PHP_VERSION, $minPhp, '<' ) ) {
            throw UpdateException::incompatiblePhpVersion( $minPhp, PHP_VERSION );
        }

        $minFramework = $updateInfo->minFrameworkVersion;

        if ( ! is_string( $minFramework ) || '' === $minFramework ) {
            return;
        }

        $currentFramework = $this->installedFrameworkVersion();

        // Only compare when the installed version reads as a numeric version.
        // A `dev-main` / branch alias install (CI, path repos) has no ordering
        // against a release constraint, so skip the check rather than refuse.
        if ( null !== $currentFramework
            && 1 === preg_match( '/^\d+\.\d+/', $currentFramework )
            && version_compare( $currentFramework, $minFramework, '<' ) ) {
            throw UpdateException::incompatibleFrameworkVersion( $minFramework, $currentFramework );
        }
    }

    /**
     * The installed cms-framework version, or null when it cannot be resolved.
     *
     * @since 2.8.0
     *
     * @return string|null Pretty version with any leading `v` stripped.
     */
    protected function installedFrameworkVersion(): ?string
    {
        if ( ! class_exists( InstalledVersions::class ) ) {
            return null;
        }

        try {
            $version = InstalledVersions::getPrettyVersion( 'artisanpack-ui/cms-framework' );
        } catch ( Throwable ) {
            return null;
        }

        return is_string( $version ) ? ltrim( $version, 'vV' ) : null;
    }

    /**
     * Run the ten update steps under an already-held update lock.
     *
     * @since 2.7.1
     *
     * @param  string  $targetVersion  Version being installed.
     * @param  UpdateInfo  $updateInfo  Resolved update metadata.
     *
     * @throws UpdateException
     *
     * @return bool True if update successful
     */
    protected function runUpdateSteps( string $targetVersion, UpdateInfo $updateInfo ): bool
    {
        // Clear the additions ledger at the very start of the run, not just in
        // `extractUpdate()`. The manager is a singleton ( see
        // `CoreServiceProvider` ), so on a long-lived queue worker one instance
        // drives many runs. A run that succeeds leaves its additions in the
        // ledger, and a *later* run that fails before ever reaching the Extract
        // step would otherwise inherit them — and `removeExtractionAdditions()`
        // would delete the previous, successful update's files during this
        // run's rollback. Resetting here means a run that never extracts has an
        // empty ledger and removes nothing.
        $this->extractionAdditions = [];

        $this->state()->begin( $targetVersion, $updateInfo->resolveCurrentVersion() );

        try {
            // Step 1: Enable maintenance mode
            $this->beginStep( UpdateStep::EnableMaintenanceMode );
            $this->enableMaintenanceMode();

            // Step 2: Create backup
            $this->beginStep( UpdateStep::Backup );
            if ( config( 'cms.updates.backup_enabled', true ) ) {
                $this->createBackup();
            }

            // Step 3: Download update
            $this->beginStep( UpdateStep::Download );
            $zipPath = $this->getUpdateChecker()->downloadUpdate( $targetVersion );

            // Step 4: Verify checksum (or surface the silent skip)
            $this->beginStep( UpdateStep::VerifyChecksum );
            $this->maybeVerifyChecksum( $zipPath, $updateInfo, $targetVersion );

            // Step 5: Extract update
            $this->beginStep( UpdateStep::Extract );
            $this->extractUpdate( $zipPath );

            // Step 6: Run composer install
            $this->beginStep( UpdateStep::ComposerInstall );
            $this->runComposerInstall();

            // Step 7: Run migrations
            $this->beginStep( UpdateStep::Migrations );
            $this->runMigrations();

            // Step 8: Clear caches
            $this->beginStep( UpdateStep::ClearCaches );
            $this->clearCaches();

            // Step 9: Clean up
            $this->beginStep( UpdateStep::Cleanup );
            $this->cleanup( $zipPath );

            // Step 10: Disable maintenance mode
            $this->beginStep( UpdateStep::DisableMaintenanceMode );
            $this->disableMaintenanceMode();

            $this->currentStep = null;
            $this->state()->markStatus( UpdateRunStatus::Completed );

            return true;
        } catch ( Throwable $e ) {
            $this->state()->markStatus( UpdateRunStatus::Failed, $e->getMessage() );

            // Rollback on failure
            $this->handleUpdateFailure( $e );

            throw $e;
        }
    }

    /**
     * Take the exclusive update lock, or refuse to start.
     *
     * `flock` on a sentinel beside the state file — deliberately not a cache
     * lock, since step 8 runs `cache:clear` and would drop it mid-run. The
     * lock covers same-host concurrency, which is the realistic case here: the
     * updater rewrites `base_path()` on the machine it runs on.
     *
     * The recorded PID is the second line of defence. #256 added exactly the
     * artifact a mutex needs — a persistent `in_progress` marker carrying the
     * PID that wrote it — and never consulted it. A stale `in_progress` from a
     * `kill -9`'d run must not block updates forever, so the marker only
     * refuses when that PID is still alive.
     *
     * @since 2.7.1
     *
     * @throws UpdateException When another update is already running.
     *
     * @return resource|null The held lock handle to pass to
     *                       releaseUpdateLock(), or null when locking was
     *                       unavailable and the run proceeded unprotected.
     */
    protected function acquireUpdateLock()
    {
        $path = $this->state()->path() . '.lock';

        // On a fresh install the state directory does not exist yet — the
        // store creates it lazily on its first write, which happens *after*
        // this point. Without this, the very first update on a host would
        // silently run unlocked.
        $directory = dirname( $path );

        if ( ! File::isDirectory( $directory ) ) {
            try {
                File::makeDirectory( $directory, 0700, true );
            } catch ( Throwable $e ) {
                // Fall through to the fopen failure path below, which logs
                // and proceeds without concurrency protection.
            }
        }

        $handle = @fopen( $path, 'cb' );

        if ( false === $handle ) {
            // The file lock is unavailable, but the state marker still records
            // whether another run is live. Consult it before proceeding: an
            // unwritable lock file must not become a licence to run a second
            // concurrent update over the same tree.
            $recordedPid = $this->runningUpdatePid();

            if ( null !== $recordedPid ) {
                throw UpdateException::updateAlreadyRunning( $recordedPid );
            }

            // An unwritable state directory already degrades the step marker
            // to best-effort; it must not also block updates outright.
            Log::warning( 'cms-framework: could not open the update lock file; proceeding without concurrency protection.', [
                'path' => $path,
            ] );

            return null;
        }

        if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
            fclose( $handle );

            throw UpdateException::updateAlreadyRunning( $this->runningUpdatePid() );
        }

        $recordedPid = $this->runningUpdatePid();

        if ( null !== $recordedPid ) {
            flock( $handle, LOCK_UN );
            fclose( $handle );

            throw UpdateException::updateAlreadyRunning( $recordedPid );
        }

        return $handle;
    }

    /**
     * Release the update lock.
     *
     * @since 2.7.1
     *
     * @param  resource|null  $handle  Handle returned by acquireUpdateLock().
     */
    protected function releaseUpdateLock( $handle ): void
    {
        if ( ! is_resource( $handle ) ) {
            return;
        }

        flock( $handle, LOCK_UN );
        fclose( $handle );
    }

    /**
     * PID of a live update recorded as still in progress, or null.
     *
     * Returns null for a stale marker — a run that died without clearing its
     * status leaves `in_progress` behind forever, and that must not wedge the
     * updater permanently.
     *
     * @since 2.7.1
     *
     * @return int|null Live PID, or null when no update is genuinely running.
     */
    protected function runningUpdatePid(): ?int
    {
        $state = $this->updateState();

        if ( null === $state ) {
            return null;
        }

        $status = UpdateRunStatus::tryFrom( is_string( $state['status'] ?? null ) ? $state['status'] : '' );

        if ( UpdateRunStatus::InProgress !== $status ) {
            return null;
        }

        $pid = $state['pid'] ?? null;

        if ( ! is_int( $pid ) || $pid <= 0 || $pid === getmypid() ) {
            return null;
        }

        // `posix_kill( $pid, 0 )` is the liveness probe; without the extension
        // we cannot tell a live run from a stale marker, and refusing would be
        // the safer of the two errors.
        if ( ! function_exists( 'posix_kill' ) ) {
            return $pid;
        }

        if ( posix_kill( $pid, 0 ) ) {
            return $pid;
        }

        // `posix_kill` returns false both for a dead process (ESRCH) and for a
        // live process this user may not signal (EPERM = 1) — the exact case
        // of a root `auto_update` cron run versus a `www-data` queue worker.
        // Only ESRCH means the run is gone; EPERM proves it still exists, so
        // reconciliation must treat it as alive and leave the healthy run be.
        return 1 === posix_get_last_error() ? $pid : null;
    }

    /**
     * Get or create update checker instance.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     *
     * @return UpdateChecker Update checker
     */
    protected function getUpdateChecker(): UpdateChecker
    {
        if ( $this->checker ) {
            return $this->checker;
        }

        $updateUrl = config( 'cms.updates.update_source_url' );

        if ( ! $updateUrl ) {
            throw UpdateException::noUpdateUrlConfigured();
        }

        // The slug keys the update-check cache and identifies the application
        // to the source. It is configurable rather than the product-specific
        // literal it used to be, so this generic framework does not hard-code a
        // single downstream product's name. Changing it invalidates any
        // existing `cms.application.<old-slug>.update_check` cache entry, which
        // simply forces one fresh check.
        $this->checker = UpdateCheckerFactory::buildUpdateChecker(
            url: $updateUrl,
            type: UpdateType::Application,
            slug: (string) config( 'cms.updates.application_slug', 'application' ),
        );

        return $this->checker;
    }

    /**
     * Create a backup of the current installation.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     */
    protected function createBackup(): void
    {
        $backupDir  = storage_path( config( 'cms.updates.backup_path', 'backups/application' ) );
        $backupName = 'backup-' . date( 'Y-m-d-His' ) . '.zip';
        $backupPath = "{$backupDir}/{$backupName}";

        // Create backup directory
        if ( ! File::exists( $backupDir ) ) {
            // 0700, not 0755: a backup is a full copy of the application
            // source. It is not web-reachable in a standard layout, but on
            // shared hosting any local user could otherwise read it.
            File::makeDirectory( $backupDir, 0700, true );
        }

        // Create backup ZIP
        $zip = new ZipArchive;

        if ( true !== $zip->open( $backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            throw UpdateException::backupFailed( $backupPath );
        }

        // Add all files except excluded paths
        $excludePaths = config( 'cms.updates.exclude_from_update', [] );
        $this->addDirectoryToZip( $zip, base_path(), '', $excludePaths );

        $zip->close();

        $this->backupPath = $backupPath;

        // Clean old backups
        $this->cleanOldBackups( $backupDir );
    }

    /**
     * Add directory to ZIP archive recursively.
     *
     * @since 1.0.0
     *
     * @param  ZipArchive  $zip  ZIP archive
     * @param  string  $sourcePath  Source directory path
     * @param  string  $localPath  Local path in ZIP
     * @param  array<string>  $excludePaths  Paths to exclude
     */
    protected function addDirectoryToZip( ZipArchive $zip, string $sourcePath, string $localPath, array $excludePaths ): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $sourcePath ),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $basePath = base_path();

        foreach ( $files as $file ) {
            if ( $file->isDir() ) {
                continue;
            }

            // Get real path and validate it
            $filePath = $file->getRealPath();

            if ( false === $filePath ) {
                // getRealPath() failed - log and skip
                Log::warning( 'Failed to get real path for file during backup', [
                    'file' => $file->getPathname(),
                ] );

                continue;
            }

            // Verify the resolved path starts with base_path() to prevent traversal issues
            if ( ! str_starts_with( $filePath, $basePath ) ) {
                // File is outside base path (symlink or external) - log and skip
                Log::warning( 'Skipping file outside base path during backup', [
                    'file'      => $filePath,
                    'base_path' => $basePath,
                ] );

                continue;
            }

            // Safe to compute relative path
            $relativePath = substr( $filePath, strlen( $basePath ) + 1 );

            // Skip excluded paths
            if ( $this->isPathExcluded( $relativePath, $excludePaths ) ) {
                continue;
            }

            $zipPath = $localPath . DIRECTORY_SEPARATOR . $relativePath;
            $zip->addFile( $filePath, $zipPath );
        }
    }

    /**
     * Check if path should be excluded.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Path to check
     * @param  array<string>  $excludePaths  Excluded paths
     *
     * @return bool True if excluded
     */
    protected function isPathExcluded( string $path, array $excludePaths ): bool
    {
        foreach ( $excludePaths as $exclude ) {
            // Match on a path-segment boundary, not a bare string prefix.
            // `str_starts_with( 'storage-helpers.php', 'storage' )` is true,
            // which silently excluded any release file whose name merely
            // *began* with an excluded name — `storage.php`, `vendors/`,
            // `.envelope.json` — from both the backup and the extraction, so
            // it was neither snapshotted nor ever updated again.
            if ( $path === $exclude || str_starts_with( $path, rtrim( $exclude, '/' ) . '/' ) ) {
                return true;
            }

            if ( str_contains( $exclude, '*' ) && fnmatch( $exclude, $path ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip control characters from an attacker-controlled value before it
     * reaches a log record.
     *
     * Entry names come from the archive. They already go into the log
     * *context* array rather than being interpolated into the message, which
     * is the important half — but a name carrying newlines or terminal escape
     * sequences can still corrupt a plain-text log or a console tailing it.
     *
     * @since 2.7.1
     *
     * @param  string  $value  Untrusted value.
     *
     * @return string Value with control characters removed and length bounded.
     */
    protected function sanitizeForLog( string $value ): string
    {
        $clean = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );

        if ( ! is_string( $clean ) ) {
            $clean = preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) ?? '';
        }

        return mb_strimwidth( $clean, 0, 512, '…' );
    }

    /**
     * Canonicalize an archive entry name for path comparison.
     *
     * Splits on `/`, drops empty and `.` segments, and rejoins. Returns null
     * when the entry cannot be safely placed — an absolute path, or one
     * carrying a `..` segment.
     *
     * The exclusion list is matched against entry names verbatim, so without
     * canonicalization an entry named `./.env` was simply a different string
     * from `.env` and slipped past `exclude_from_update` entirely — while
     * `realpath( dirname( '/base/./.env' ) )` is just `/base`, so the
     * containment check waved it through too. A malicious release archive
     * could therefore overwrite the host's `.env`, `vendor/`,
     * `bootstrap/cache/*.php` and `database/database.sqlite`, every one of
     * which the operator believes the exclusion list protects.
     *
     * @since 2.7.1
     *
     * @param  string  $path  Raw archive entry name.
     *
     * @return string|null Canonical relative path, or null when unsafe.
     */
    protected function canonicalizeArchivePath( string $path ): ?string
    {
        $normalized = str_replace( '\\', '/', $path );

        if ( str_starts_with( $normalized, '/' ) ) {
            return null;
        }

        $segments = [];

        foreach ( explode( '/', $normalized ) as $segment ) {
            if ( '' === $segment || '.' === $segment ) {
                continue;
            }

            if ( '..' === $segment ) {
                return null;
            }

            $segments[] = $segment;
        }

        return implode( '/', $segments );
    }

    /**
     * Walk up from a path until an existing filesystem entry is found.
     *
     * Containment has to be validated *before* directories are created, and
     * `realpath()` returns false for a path that does not exist yet — so the
     * nearest existing ancestor is the deepest thing that can be resolved.
     *
     * @since 2.7.1
     *
     * @param  string  $path  Absolute path that may not exist yet.
     *
     * @return string The deepest existing ancestor ( possibly `$path` itself ).
     */
    protected function nearestExistingPath( string $path ): string
    {
        $current = $path;

        while ( ! file_exists( $current ) ) {
            $parent = dirname( $current );

            if ( $parent === $current ) {
                break;
            }

            $current = $parent;
        }

        return $current;
    }

    /**
     * Clean old backups based on retention days.
     *
     * @since 1.0.0
     *
     * @param  string  $backupDir  Backup directory
     */
    protected function cleanOldBackups( string $backupDir ): void
    {
        $retentionDays = config( 'cms.updates.backup_retention_days', 30 );
        $cutoffTime    = time() - ( $retentionDays * 86400 );

        $backups = glob( "{$backupDir}/backup-*.zip" );

        if ( false === $backups ) {
            return;
        }

        foreach ( $backups as $backup ) {
            if ( filemtime( $backup ) < $cutoffTime ) {
                File::delete( $backup );
            }
        }
    }

    /**
     * Verify ZIP checksum.
     *
     * @since 1.0.0
     *
     * @param  string  $zipPath  Path to ZIP file
     * @param  string  $expectedHash  Expected SHA-256 hash
     *
     * @throws UpdateException
     */
    protected function verifyChecksum( string $zipPath, string $expectedHash ): void
    {
        // Normalize through the shared helper so an uppercase or padded digest
        // (e.g. from a custom JSON feed) verifies rather than failing as a
        // spurious "checksum mismatch", and a malformed value fails closed. The
        // theme and plugin managers verify through the same helper.
        $expected   = ArchiveChecksum::normalize( $expectedHash );
        $actualHash = (string) hash_file( 'sha256', $zipPath );

        if ( null === $expected || ! hash_equals( $expected, $actualHash ) ) {
            throw UpdateException::checksumMismatch( $expectedHash, $actualHash );
        }
    }

    /**
     * Verify the downloaded ZIP against its declared checksum, or surface that
     * verification was skipped because the source did not advertise one.
     *
     * @since 2.0.0
     *
     * @param  string  $zipPath  Path to the downloaded ZIP.
     * @param  UpdateInfo  $updateInfo  Update metadata from the source.
     * @param  string  $targetVersion  Version being installed.
     *
     * @throws UpdateException When the checksum is present but does not match.
     */
    protected function maybeVerifyChecksum( string $zipPath, UpdateInfo $updateInfo, string $targetVersion ): void
    {
        if ( ! config( 'cms.updates.verify_checksum', true ) ) {
            return;
        }

        // `$updateInfo` describes the *latest* release. When the caller pinned
        // a different version, its digest belongs to a different archive: with
        // `--target-version=1.2.3` while latest is 2.0.0, the 1.2.3 archive was
        // being compared against 2.0.0's digest. That fails closed today, but
        // combined with `allow_unverified_updates=true` — which the GitHub
        // source effectively forces operators into — the pinned path installed
        // an arbitrary older archive with no integrity check at all.
        $sha256 = $updateInfo->sha256;

        if ( $targetVersion !== $updateInfo->latestVersion ) {
            $sha256 = $this->resolveChecksumForVersion( $targetVersion );
        }

        if ( $sha256 ) {
            $this->verifyChecksum( $zipPath, $sha256 );

            return;
        }

        if ( ! config( 'cms.updates.allow_unverified_updates', false ) ) {
            throw UpdateException::checksumRequired( $targetVersion );
        }

        Log::warning( 'Skipping update integrity verification: update source did not advertise a SHA-256 checksum.', [
            'target_version' => $targetVersion,
            'source'         => $updateInfo->metadata['source'] ?? null,
        ] );
    }

    /**
     * Resolve the SHA-256 digest advertised for a specific release.
     *
     * Returns null when the source cannot resolve that version, in which case
     * `maybeVerifyChecksum()` falls back to the same fail-closed path a
     * missing digest takes on the latest release.
     *
     * @since 2.7.1
     *
     * @param  string  $targetVersion  Version being installed.
     *
     * @return string|null Digest for that release, when the source advertises one.
     */
    protected function resolveChecksumForVersion( string $targetVersion ): ?string
    {
        $source = $this->getUpdateChecker()->getSource();

        if ( ! method_exists( $source, 'checksumForVersion' ) ) {
            return null;
        }

        try {
            $sha256 = $source->checksumForVersion( $targetVersion );
        } catch ( Throwable $e ) {
            Log::warning( 'cms-framework: could not resolve a checksum for the pinned target version.', [
                'target_version' => $targetVersion,
                'exception'      => $e->getMessage(),
            ] );

            return null;
        }

        return is_string( $sha256 ) && '' !== $sha256 ? $sha256 : null;
    }

    /**
     * Extract update ZIP.
     *
     * @since 1.0.0
     *
     * @param  string  $zipPath  Path to ZIP file
     *
     * @throws UpdateException
     */
    protected function extractUpdate( string $zipPath ): void
    {
        $zip = new ZipArchive;

        if ( true !== $zip->open( $zipPath ) ) {
            throw UpdateException::extractionFailed( $zipPath );
        }

        $extractPath  = base_path();
        $excludePaths = config( 'cms.updates.exclude_from_update', [] );

        // Start each extraction with an empty ledger. A manager instance can
        // drive more than one run, and rollback removes exactly what *this*
        // extraction added — never a previous run's additions.
        $this->extractionAdditions = [];

        // Detect common root prefix by scanning all entry names
        $commonPrefix = $this->detectCommonRootPrefix( $zip, $excludePaths );

        // Extract files (except excluded paths), stripping common prefix
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $filename = $zip->getNameIndex( $i );

            // Canonicalize before anything looks at the path. Both the
            // exclusion check and the containment check operate on strings,
            // and `./`-prefixed or `//`-infixed entries are different strings
            // naming the same file — see canonicalizeArchivePath(). A null
            // here means the entry is absolute or escapes via `..` (Zip Slip);
            // extraction bypasses ZipArchive::extractTo(), so PHP's own
            // traversal mitigations do not apply and the guard must live here.
            $canonicalName = $this->canonicalizeArchivePath( $filename );

            if ( null === $canonicalName ) {
                Log::warning( 'Skipping update ZIP entry with unsafe path', [
                    'entry' => $this->sanitizeForLog( $filename ),
                ] );

                continue;
            }

            // Strip common prefix if detected
            $targetPath = $canonicalName;
            if ( $commonPrefix && str_starts_with( $canonicalName, $commonPrefix ) ) {
                $targetPath = substr( $canonicalName, strlen( $commonPrefix ) );
            }

            // Skip excluded paths (check both original and stripped paths)
            if ( $this->isPathExcluded( $canonicalName, $excludePaths ) || $this->isPathExcluded( $targetPath, $excludePaths ) ) {
                continue;
            }

            // Skip empty paths (directories become empty after prefix stripping)
            if ( '' === $targetPath ) {
                continue;
            }

            // Get file info. A stat failure used to skip the entry silently,
            // leaving the previous version of that file on disk while the
            // update reported success.
            $stat = $zip->statIndex( $i );
            if ( false === $stat ) {
                Log::warning( 'Skipping update ZIP entry whose metadata could not be read', [
                    'entry' => $this->sanitizeForLog( $filename ),
                    'index' => $i,
                ] );

                continue;
            }

            $fullTargetPath = $extractPath . DIRECTORY_SEPARATOR . $targetPath;

            // Handle directories
            if ( str_ends_with( $filename, '/' ) ) {
                // Validate *before* creating. The string-level filter above
                // has already removed `..`, so the only remaining escape is a
                // pre-existing symlink inside base_path() pointing outside it
                // — routine in Envoyer/Forge/Deployer layouts where `storage`
                // or `bootstrap/cache` are symlinked to shared directories.
                // Creating first and checking second ran `mkdir -p` through
                // that symlink, and never removed the directories it made.
                if ( ! $this->isPathWithinExtractRoot( $this->nearestExistingPath( $fullTargetPath ), $extractPath ) ) {
                    Log::warning( 'Skipping update ZIP entry that resolved outside extract root', [
                        'entry' => $filename,
                    ] );

                    continue;
                }

                if ( ! File::exists( $fullTargetPath ) ) {
                    File::makeDirectory( $fullTargetPath, 0755, true );
                }

                continue;
            }

            // Handle files - ensure parent directory exists, validating the
            // deepest ancestor that already exists before creating anything.
            $targetDir = dirname( $fullTargetPath );

            if ( ! $this->isPathWithinExtractRoot( $this->nearestExistingPath( $targetDir ), $extractPath ) ) {
                Log::warning( 'Skipping update ZIP entry that resolved outside extract root', [
                    'entry' => $this->sanitizeForLog( $filename ),
                ] );

                continue;
            }

            if ( ! File::exists( $targetDir ) ) {
                File::makeDirectory( $targetDir, 0755, true );
            }

            // Defense-in-depth: after the parent exists, resolve it and confirm
            // it still sits under the extract root before opening the write stream.
            if ( ! $this->isPathWithinExtractRoot( $targetDir, $extractPath ) ) {
                Log::warning( 'Skipping update ZIP entry that resolved outside extract root', [
                    'entry' => $this->sanitizeForLog( $filename ),
                ] );

                continue;
            }

            // The containment check above validates the parent directory, not
            // the target itself. `fopen( …, 'wb' )` follows an existing
            // symlink and truncates whatever it points at, and `@chmod` below
            // would chmod the link target. The archive cannot *introduce* a
            // symlink — every entry is written as a regular file and
            // `symlink()` is never called — so this is strictly about links
            // already on disk: shared config, a log file, or a sibling release
            // directory in a blue/green deploy.
            if ( is_link( $fullTargetPath ) || ( file_exists( $fullTargetPath ) && ! is_file( $fullTargetPath ) ) ) {
                Log::warning( 'Skipping update ZIP entry whose target is not a regular file', [
                    'entry'  => $this->sanitizeForLog( $filename ),
                    'target' => $fullTargetPath,
                ] );

                continue;
            }

            // Stream the entry to disk rather than materializing it as a PHP
            // string via `getFromIndex()`. On a 128M host, a single large file
            // inside the release (bundled JS/CSS, images) can otherwise OOM in
            // the middle of extraction.
            // By index, not by name: metadata is read via `statIndex( $i )`,
            // so fetching content by name meant that with duplicate entry
            // names the file written for occurrence 2 got occurrence 1's
            // content and occurrence 2's permissions.
            // Whether this path already existed decides what rollback owes it.
            // An overwrite is restored from the backup; a brand-new file is
            // not in the backup at all, so rollback has to delete it or it
            // survives orphaned. Captured before the write because `fopen(
            // …, 'wb' )` below creates the file either way.
            $existedBeforeWrite = File::exists( $fullTargetPath );

            $entryStream = $zip->getStreamIndex( $i );
            if ( false === $entryStream ) {
                Log::error( 'Failed to open ZIP entry stream during update extraction', [
                    'entry' => $this->sanitizeForLog( $filename ),
                ] );

                throw UpdateException::extractionEntryFailed( $filename, 'could not open entry stream from archive' );
            }

            $out = @fopen( $fullTargetPath, 'wb' );
            if ( false === $out ) {
                fclose( $entryStream );

                $error = error_get_last();
                Log::error( 'Failed to open update target for writing', [
                    'entry'  => $filename,
                    'target' => $fullTargetPath,
                    'errno'  => $error['type'] ?? null,
                    'reason' => $error['message'] ?? null,
                ] );

                throw UpdateException::extractionEntryFailed(
                    $filename,
                    "could not open target for writing: {$fullTargetPath}",
                );
            }

            // Record the addition the moment `fopen` creates the file, before
            // streaming — not after. If `streamEntryToDisk()` throws partway,
            // it best-effort `@unlink`s the target, but should that unlink fail
            // ( read-only parent, races ) a brand-new, half-written file would
            // survive un-ledgered and neither rollback nor the backup could
            // remove it. Ledgering here lets rollback delete it. A later
            // successful overwrite of a path that existed before is still not
            // recorded, because `$existedBeforeWrite` was captured pre-`fopen`.
            if ( ! $existedBeforeWrite ) {
                $this->extractionAdditions[] = $targetPath;
            }

            $this->streamEntryToDisk( $entryStream, $out, $filename, $fullTargetPath );

            // Preserve file permissions if available, clamped. setuid/setgid/
            // sticky are already stripped by `& 0777`, but an archive-supplied
            // `0777` on an extracted `.php` file under the docroot is
            // persistent code execution for a neighbouring tenant on shared
            // hosting. `& ~0022` drops group- and world-writability.
            if ( isset( $stat['external_attributes'] ) ) {
                $permissions = ( $stat['external_attributes'] >> 16 ) & 0777 & ~0022;
                if ( $permissions > 0 ) {
                    @chmod( $fullTargetPath, $permissions );
                }
            }
        }

        $zip->close();

        // Persist the additions ledger once the extraction is complete, so a
        // manual `update:rollback` in a fresh process (the Interrupted case)
        // can still remove the files this extraction added — the in-memory
        // ledger dies with the process, and a snapshot restore alone leaves
        // those additions orphaned (#272).
        $this->state()->recordExtractionAdditions( $this->extractionAdditions );
    }

    /**
     * Copy one archive entry stream to its already-opened target handle.
     *
     * Both handles are closed before returning, whatever happens.
     *
     * Every failure here throws rather than returning, because throwing is
     * what engages `performUpdate()`'s rollback — a silently truncated file is
     * strictly worse than an aborted update. The read side was already checked;
     * the write side was not, so a disk that filled mid-extraction produced a
     * short `fwrite()` with no exception, the loop ran to completion, the entry
     * counted as extracted, and the update proceeded into `composer install`
     * and migrations over truncated PHP files.
     *
     * @since 2.7.1
     *
     * @param  resource  $entryStream  Open read handle on the archive entry.
     * @param  resource  $out  Open write handle on the extraction target.
     * @param  string  $filename  Archive entry name, for diagnostics.
     * @param  string  $target  Absolute target path, for diagnostics.
     *
     * @throws UpdateException On any read, write, or close failure.
     */
    protected function streamEntryToDisk( $entryStream, $out, string $filename, string $target ): void
    {
        $failed = false;

        try {
            while ( ! feof( $entryStream ) ) {
                $chunk = fread( $entryStream, 1024 * 1024 );

                if ( false === $chunk ) {
                    Log::error( 'Read failure while streaming update entry', [
                        'entry'  => $filename,
                        'target' => $target,
                    ] );

                    throw UpdateException::extractionEntryFailed( $filename, 'read failure while streaming entry from archive' );
                }

                if ( '' === $chunk ) {
                    break;
                }

                $written = fwrite( $out, $chunk );

                if ( false === $written || strlen( $chunk ) !== $written ) {
                    Log::error( 'Short write while streaming update entry', [
                        'entry'    => $filename,
                        'target'   => $target,
                        'expected' => strlen( $chunk ),
                        'written'  => false === $written ? null : $written,
                    ] );

                    throw UpdateException::extractionEntryFailed( $filename, 'short write while extracting entry ( disk full? )' );
                }
            }
        } catch ( Throwable $e ) {
            $failed = true;

            throw $e;
        } finally {
            fclose( $entryStream );
            $closed = fclose( $out );

            // Remove the partial file rather than leaving a truncated one on
            // disk. Throwing engages the rollback, which would normally
            // restore it — but rollback is skipped when backups are disabled,
            // and a truncated PHP file left behind is exactly the outcome this
            // check exists to prevent.
            if ( $failed ) {
                @unlink( $target );
            }
        }

        // Buffered data can fail to reach the disk at close time, so a clean
        // write loop is not on its own proof the file landed intact.
        if ( false === $closed ) {
            Log::error( 'Failed to close update target after writing', [
                'entry'  => $filename,
                'target' => $target,
            ] );

            @unlink( $target );

            throw UpdateException::extractionEntryFailed( $filename, 'failed to close target after writing ( disk full? )' );
        }
    }

    /**
     * Determine whether a resolved path sits within the extraction root.
     *
     * Uses `realpath()` on both sides so that any `..` traversal or symlink is
     * resolved before the comparison. Callers must ensure the target directory
     * has been created first — `realpath()` returns false for missing paths.
     *
     * @since 2.5.4
     *
     * @param  string  $path  Absolute path to validate.
     * @param  string  $extractPath  Extraction root the path must sit within.
     */
    protected function isPathWithinExtractRoot( string $path, string $extractPath ): bool
    {
        $realExtractPath = realpath( $extractPath );
        $realPath        = realpath( $path );

        if ( false === $realExtractPath || false === $realPath ) {
            return false;
        }

        return $realPath === $realExtractPath
            || str_starts_with( $realPath, $realExtractPath . DIRECTORY_SEPARATOR );
    }

    /**
     * Detect common root prefix in ZIP archive.
     *
     * @since 1.0.0
     *
     * @param  ZipArchive  $zip  ZIP archive
     * @param  array<string>  $excludePaths  Paths to exclude
     *
     * @return string|null Common root prefix, or null if none detected
     */
    protected function detectCommonRootPrefix( ZipArchive $zip, array $excludePaths ): ?string
    {
        $firstSegments = [];

        // Scan all non-excluded entries to find first path segment
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $filename = $zip->getNameIndex( $i );

            // Canonicalized so this agrees with the extraction loop about what
            // an entry is named — otherwise `./app/x` and `app/x` look like
            // two different roots and prefix detection gives up.
            $canonicalName = $this->canonicalizeArchivePath( $filename );

            if ( null === $canonicalName || '' === $canonicalName ) {
                continue;
            }

            // Skip excluded paths
            if ( $this->isPathExcluded( $canonicalName, $excludePaths ) ) {
                continue;
            }

            // Get first path segment
            $parts = explode( '/', $canonicalName );
            if ( ! empty( $parts[0] ) ) {
                $firstSegments[] = $parts[0];
            }
        }

        // If all entries share the same first segment, that's our common prefix
        if ( empty( $firstSegments ) ) {
            return null;
        }

        $uniqueSegments = array_unique( $firstSegments );
        if ( 1 === count( $uniqueSegments ) ) {
            return reset( $uniqueSegments ) . '/';
        }

        return null;
    }

    /**
     * Run composer install using the resolved composer command.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     */
    protected function runComposerInstall(): void
    {
        $command = $this->resolveComposerCommand();

        $composerFilesDiverged = $this->verifyComposerFilesInSync( $command );

        $timeout = config( 'cms.updates.composer_timeout', 600 );

        $result = Process::timeout( $timeout )
            ->path( base_path() )
            ->run( $command );

        if ( $result->successful() ) {
            return;
        }

        // #273: when the install failed *because* the on-disk lock is merely
        // the previous release's, a targeted `composer update` can re-resolve the
        // flagged packages and land a lock that satisfies the new `composer.json`,
        // rather than aborting into a rollback the operator can only escape with
        // shell access. This keys off composer's *own* "Required package"
        // diagnosis of an unsatisfiable lock — never pre-emptively — and so is
        // independent of `verifyComposerFilesInSync()`'s content-hash check
        // above, which only enriches the failure message. That independence is
        // deliberate: an operator disables the hash check precisely when a
        // future composer changes the hash algorithm, which is exactly when a
        // stale lock is most likely and recovery most wanted.
        if ( $this->attemptStaleLockRecovery( $result->errorOutput() ) ) {
            return;
        }

        throw UpdateException::composerInstallFailed( $result->errorOutput(), $composerFilesDiverged );
    }

    /**
     * Recover from a `composer install` that aborted because the extracted
     * `composer.json` requires a dependency set the still-in-place, previous
     * release's `composer.lock` cannot satisfy (#273).
     *
     * This is the escape from the chicken-and-egg #255 left behind: the fix for
     * a broken lock ships *inside* the update the broken lock prevents from
     * running, so an install on the affected line has no updater-reachable path
     * to the fix. Where composer names the packages the lock cannot satisfy,
     * the framework runs a **targeted** `composer update <packages>` — only the
     * flagged packages and their dependencies are re-resolved; everything else
     * stays pinned at the lock — writing a lock that matches `composer.json` and
     * letting the update proceed instead of rolling back.
     *
     * It is deliberately conservative, and every guard below fails *toward* the
     * original abort:
     *
     * - It runs only when composer's output names at least one unsatisfied
     *   package — composer's own diagnosis of a lock that cannot satisfy
     *   `composer.json`. An unrelated failure (network, a plugin error) names
     *   none and is never "recovered", and a full-tree `composer update` — the
     *   blast radius #255 argued against — is never triggered on a guess.
     * - It declines when the composer command is an operator override that
     *   cannot be safely rewritten into an `update`.
     * - It is gated behind `cms.updates.recover_stale_lock` (default enabled),
     *   because re-resolving on the operator's behalf is arguably not the
     *   updater's call — an operator who wants the loud, safe rollback instead
     *   sets the key to `false`.
     *
     * @since 2.8.0
     *
     * @param  string  $composerErrorOutput  The stderr from the failed install.
     *
     * @return bool True when a recovery `composer update` ran and succeeded.
     */
    protected function attemptStaleLockRecovery( string $composerErrorOutput ): bool
    {
        if ( ! config( 'cms.updates.recover_stale_lock', true ) ) {
            return false;
        }

        $packages = $this->parseUnsatisfiedComposerPackages( $composerErrorOutput );
        if ( empty( $packages ) ) {
            return false;
        }

        $command = $this->resolveComposerRecoveryCommand( $packages );
        if ( null === $command ) {
            Log::warning(
                'A stale composer.lock blocked the update, but the composer command is an operator override that cannot be rewritten into a targeted `composer update`; leaving the failure to roll back. Run `composer update ' . implode( ' ', $packages ) . ' --with-all-dependencies` on the host once, or clear the override.',
                [ 'packages' => $packages ],
            );

            return false;
        }

        Log::warning(
            'composer install aborted on a stale composer.lock; attempting a targeted `composer update` to reach the release the lock could not deliver (#273).',
            [ 'packages' => $packages ],
        );

        $timeout = config( 'cms.updates.composer_timeout', 600 );

        $result = Process::timeout( $timeout )
            ->path( base_path() )
            ->run( $command );

        if ( $result->successful() ) {
            Log::info(
                'Stale composer.lock recovered: `composer update` re-resolved the flagged packages and the update is proceeding.',
                [ 'packages' => $packages ],
            );

            return true;
        }

        Log::error(
            'Stale composer.lock recovery failed: the targeted `composer update` could not resolve the flagged packages either; the update will roll back.',
            [
                'packages' => $packages,
                'output'   => $result->errorOutput(),
            ],
        );

        return false;
    }

    /**
     * Extract the package names composer reported as unsatisfiable by the
     * on-disk lock, from a failed `composer install`'s output.
     *
     * Composer names them in one of two shapes, both opening `Required package
     * "vendor/name"`:
     *
     * ```
     * Required package "artisanpack-ui/cms-framework" is in the lock file as
     *   "2.5.4" but that does not satisfy your constraint "^2.7.1".
     * Required package "psr/container" is not present in the lock file.
     * ```
     *
     * Composer emits a third, `Required (in require-dev) package "..."` shape
     * for dev requirements, deliberately not matched here: the shipped install
     * runs `--no-dev`, so composer never reports a dev requirement, and any
     * override that drops `--no-dev` makes recovery decline anyway (its command
     * is no longer the default). If the install args ever stop passing
     * `--no-dev`, revisit this pattern.
     *
     * Matches are filtered to well-formed composer package names before they
     * are ever handed to a shelled-out `composer update`, so unparseable output
     * yields an empty list (declining recovery) rather than passing junk — or
     * anything crafted — to composer on this RCE-adjacent path.
     *
     * @since 2.8.0
     *
     * @param  string  $output  The failed install's stderr.
     *
     * @return array<int, string> Unique, validated package names; empty when none parse.
     */
    protected function parseUnsatisfiedComposerPackages( string $output ): array
    {
        if ( 0 === preg_match_all( '/Required package "([^"]+)"/', $output, $matches ) ) {
            return [];
        }

        $packages = array_filter(
            array_unique( $matches[1] ),
            static fn ( string $name ): bool => 1 === preg_match(
                '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/i',
                $name,
            ),
        );

        return array_values( $packages );
    }

    /**
     * Build the targeted `composer update <packages>` command used to recover
     * from a stale lock, mirroring `resolveComposerCommand()`'s binary
     * discovery so recovery runs the same toolchain the install did.
     *
     * Returns `null` when the operator has overridden the full composer command
     * string: an arbitrary command cannot be safely rewritten from `install`
     * into `update`, so recovery declines rather than guess.
     *
     * @since 2.8.0
     *
     * @param  array<int, string>  $packages  Validated package names to update.
     *
     * @return string|null The recovery command, or null when it cannot be derived.
     */
    protected function resolveComposerRecoveryCommand( array $packages ): ?string
    {
        // `--with-all-dependencies` (`-W`) so the flagged packages' own
        // dependencies — including root requirements pinned by the stale lock —
        // may move too. A bare `composer update <pkg>` leaves them at their
        // locked versions and fails to resolve exactly when the new version of a
        // flagged package needs a transitive bump, which is the case recovery
        // exists to unstick.
        $args = 'update ' . implode( ' ', array_map( 'escapeshellarg', $packages ) )
            . ' --with-all-dependencies --no-dev --no-interaction --optimize-autoloader';

        $envBinary = $this->envComposerBinary();
        if ( null !== $envBinary ) {
            return $this->buildComposerCommand( $envBinary, $args );
        }

        $configured = config( 'cms.updates.composer_install_command' );
        if ( is_string( $configured ) && self::DEFAULT_COMPOSER_INSTALL_COMMAND !== $configured ) {
            return null;
        }

        $discovered = $this->discoverComposerBinary();
        if ( null !== $discovered ) {
            return $this->buildComposerCommand( $discovered, $args );
        }

        return 'composer ' . $args;
    }

    /**
     * Check whether the on-disk `composer.json` and `composer.lock` agree,
     * logging a warning when they positively disagree.
     *
     * This is a *diagnostic*, not a gate. `composer install` reads the lock and
     * only ever *warns* about a stale `content-hash` — it installs from the lock
     * regardless, and hard-fails solely when a required package is absent from
     * the lock or violates one of `composer.json`'s constraints. An earlier
     * version of this method aborted step 6 (and triggered a full backup
     * rollback) on *any* divergence, turning a release that shipped an
     * old-but-satisfying lock into a failed update composer would have installed
     * cleanly. It no longer pre-empts composer.
     *
     * What it keeps is composer's *diagnosis*: when composer does fail on a
     * genuinely unsatisfiable lock, its own message ("This usually happens when
     * composer files are incorrectly merged or the composer.json file is
     * manually edited") sends the operator hunting for a merge conflict or a
     * hand-edit that never happened, when the real cause is a release that
     * shipped no lock or a host whose `exclude_from_update` override still
     * excludes it. The boolean returned here lets `runComposerInstall()` wrap
     * composer's *own* failure with that accurate diagnosis — but only after
     * composer, not this check, has decided the update cannot proceed.
     *
     * Fails *open*: a missing `composer.json`, a missing or unparseable
     * `composer.lock`, or a lock carrying no `content-hash` returns `false` and
     * is left for composer to adjudicate.
     *
     * @since 2.7.1
     *
     * @param  string  $command  The composer command about to be run.
     *
     * @return bool True when the two files positively disagree, false otherwise.
     */
    protected function verifyComposerFilesInSync( string $command ): bool
    {
        if ( ! config( 'cms.updates.verify_composer_lock_sync', true ) ) {
            return false;
        }

        // A host that has overridden `composer_install_command` to run
        // `composer update` has deliberately opted into re-resolving the tree,
        // and a lock that disagrees with composer.json is precisely what
        // `update` exists to reconcile. Only `install` reads the lock as fixed.
        if ( ! preg_match( '/(?:^|\s)install(?:\s|$)/', $command ) ) {
            return false;
        }

        $jsonPath = base_path( 'composer.json' );
        $lockPath = base_path( 'composer.lock' );

        if ( ! is_file( $jsonPath ) ) {
            return false;
        }

        if ( ! is_file( $lockPath ) ) {
            Log::warning( 'Update left no composer.lock on disk; composer will resolve dependencies itself.', [
                'lock_path' => $lockPath,
            ] );

            return false;
        }

        $expectedHash = $this->composerContentHash( $jsonPath );
        if ( null === $expectedHash ) {
            return false;
        }

        $lock = json_decode( (string) @file_get_contents( $lockPath ), true );
        if ( ! is_array( $lock ) || ! is_string( $lock['content-hash'] ?? null ) ) {
            return false;
        }

        if ( $lock['content-hash'] === $expectedHash ) {
            return false;
        }

        Log::warning(
            'composer.json and composer.lock are out of sync after extraction; composer will install from the lock and abort only if it cannot satisfy composer.json.',
            [
                'expected_content_hash' => $expectedHash,
                'lock_content_hash'     => $lock['content-hash'],
            ],
        );

        return true;
    }

    /**
     * Compute the `content-hash` composer would write into a lock file for the
     * given `composer.json`, mirroring `Composer\Package\Locker::getContentHash()`.
     *
     * @since 2.7.1
     *
     * @param  string  $jsonPath  Absolute path to a `composer.json`.
     *
     * @return string|null The hash, or null when the file cannot be read or parsed.
     */
    protected function composerContentHash( string $jsonPath ): ?string
    {
        $contents = @file_get_contents( $jsonPath );
        if ( false === $contents ) {
            return null;
        }

        $manifest = json_decode( $contents, true );
        if ( ! is_array( $manifest ) ) {
            return null;
        }

        $relevant = [];
        foreach ( array_intersect( self::COMPOSER_CONTENT_HASH_KEYS, array_keys( $manifest ) ) as $key ) {
            $relevant[ $key ] = $manifest[ $key ];
        }

        if ( isset( $manifest['config']['platform'] ) ) {
            $relevant['config']['platform'] = $manifest['config']['platform'];
        }

        ksort( $relevant );

        $encoded = json_encode( $relevant );
        if ( false === $encoded ) {
            return null;
        }

        return hash( 'md5', $encoded );
    }

    /**
     * Resolve the command used to run composer install.
     *
     * Discovery is deliberately explicit-first so operators keep full control
     * over the invocation:
     *
     * 1. `COMPOSER_BINARY` environment variable (absolute path).
     * 2. `cms.updates.composer_install_command` config value, when it differs
     *    from the shipped default. Backwards-compatible escape hatch for hosts
     *    already carrying a bespoke command.
     * 3. Auto-discovery across common install paths; when a hit is found the
     *    command is built as `{PHP_BINARY} {binary} install ...` so PHP-FPM
     *    hosts with a stripped `PATH` don't have to resolve composer's
     *    `#!/usr/bin/env php` shebang.
     * 4. Bare `composer install ...` — the pre-2.5.3 behavior.
     *
     * @since 2.5.3
     */
    protected function resolveComposerCommand(): string
    {
        $envBinary = $this->envComposerBinary();
        if ( null !== $envBinary ) {
            return $this->buildComposerCommand( $envBinary );
        }

        $configured = config( 'cms.updates.composer_install_command' );
        if ( is_string( $configured ) && self::DEFAULT_COMPOSER_INSTALL_COMMAND !== $configured ) {
            return $configured;
        }

        $discovered = $this->discoverComposerBinary();
        if ( null !== $discovered ) {
            return $this->buildComposerCommand( $discovered );
        }

        return is_string( $configured ) ? $configured : self::DEFAULT_COMPOSER_INSTALL_COMMAND;
    }

    /**
     * Verify that the resolved composer binary is executable before invoking
     * it during rollback. When we cannot introspect the resolved binary
     * (because the operator overrode the full command string), we skip the
     * check and trust the override.
     *
     * @since 2.5.3
     *
     * @throws UpdateException When `--version` cannot be executed — as
     *                         `configuredComposerBinaryMissing` if the binary
     *                         is also absent from PHP's view of the
     *                         filesystem, otherwise as
     *                         `composerVerificationFailed`.
     */
    protected function verifyComposerBinaryAvailable(): void
    {
        $binary = $this->resolveComposerBinaryForVerification();
        if ( null === $binary ) {
            return;
        }

        $php     = $this->resolvePhpBinary();
        $command = escapeshellarg( $php ) . ' ' . escapeshellarg( $binary ) . ' --version';

        $result = Process::timeout( 10 )
            ->path( base_path() )
            ->run( $command );

        if ( ! $result->successful() ) {
            // Select the diagnosis *after* the probe, never before it. A path
            // PHP cannot stat is not automatically a wrong path: PHP-FPM
            // sandboxes (macOS Herd Pro, chrooted pools, restrictive
            // `open_basedir`) hide real files from `is_file()` while the
            // shelled-out child reaches them fine, which is precisely why
            // `COMPOSER_BINARY` is the documented escape hatch for that case
            // (#233). Gating the probe on `is_file()` would close it. Once the
            // probe has already failed, though, a path PHP also cannot see is
            // overwhelmingly a typo'd or stale override rather than a
            // sandboxed one — and saying so beats "located but could not be
            // executed" plus a `CMS_PHP_BINARY` hint, which blames the PHP
            // interpreter on hosts where it resolved perfectly well (#254).
            if ( ! is_file( $binary ) ) {
                throw UpdateException::configuredComposerBinaryMissing( $binary );
            }

            $stderr = trim( (string) $result->errorOutput() );
            $stdout = trim( (string) $result->output() );
            $detail = '' !== $stderr ? $stderr : $stdout;

            throw UpdateException::composerVerificationFailed(
                $binary,
                $php,
                (int) $result->exitCode(),
                $detail,
            );
        }
    }

    /**
     * Resolve the composer binary path used for the rollback `--version`
     * precheck. Returns `null` when the operator has overridden the full
     * command (nothing sensible to introspect) or discovery could not find a
     * binary (the actual failure will surface from `runComposerInstall()`).
     *
     * @since 2.5.3
     */
    protected function resolveComposerBinaryForVerification(): ?string
    {
        $envBinary = $this->envComposerBinary();
        if ( null !== $envBinary ) {
            return $envBinary;
        }

        $configured = config( 'cms.updates.composer_install_command' );
        if ( is_string( $configured ) && self::DEFAULT_COMPOSER_INSTALL_COMMAND !== $configured ) {
            return null;
        }

        return $this->discoverComposerBinary();
    }

    /**
     * Absolute path from the `COMPOSER_BINARY` environment variable, or `null`
     * when not set. Reads `cms.updates.composer_binary` first — which is
     * populated from `env('COMPOSER_BINARY')` in the shipped config and
     * therefore sees values placed in the Laravel `.env` file under both CLI
     * and HTTP-request contexts — then falls back to `getenv()` for hosts that
     * export `COMPOSER_BINARY` at the OS level (PHP-FPM pool env, shell before
     * starting FPM, container ENV, etc.).
     *
     * @since 2.5.3
     */
    protected function envComposerBinary(): ?string
    {
        $configured = config( 'cms.updates.composer_binary' );
        if ( is_string( $configured ) && '' !== $configured ) {
            return $configured;
        }

        $envBinary = getenv( 'COMPOSER_BINARY' );
        if ( false === $envBinary || '' === $envBinary ) {
            return null;
        }

        return $envBinary;
    }

    /**
     * Locate a composer binary on disk by walking a curated list of common
     * install paths. Returns the first executable hit or `null`.
     *
     * When discovery finds nothing, emits a structured `Log::warning` with
     * per-candidate `is_file()` / `is_executable()` results so operators can
     * distinguish "the path was wrong" from "PHP-FPM sandboxing hid a path
     * that exists at the OS level" (common on macOS Herd Pro, chrooted FPM
     * pools, and containers with restrictive `open_basedir`) without having
     * to reflect into the framework or diff their environment.
     *
     * @since 2.5.3
     */
    protected function discoverComposerBinary(): ?string
    {
        $results = [];

        foreach ( $this->composerCandidatePaths() as $path ) {
            $isFile       = is_file( $path );
            $isExecutable = $isFile ? is_executable( $path ) : false;

            $results[] = [
                'path'          => $path,
                'is_file'       => $isFile,
                'is_executable' => $isExecutable,
            ];

            if ( $isFile && $isExecutable ) {
                return $path;
            }
        }

        Log::warning(
            'cms-framework: composer binary discovery failed; no candidate path was both a file and executable in the current PHP process context.',
            [
                'candidates' => $results,
                'php_sapi'   => PHP_SAPI,
                'hint'       => 'If a candidate exists at the OS level but reports is_file=false here, the PHP process likely cannot stat it (macOS Herd Pro sandbox, chrooted FPM pool, restrictive open_basedir). Set COMPOSER_BINARY in .env or override cms.updates.composer_install_command with an absolute path.',
            ],
        );

        return null;
    }

    /**
     * Common composer install paths, in preference order. Extracted so tests
     * and the `composerBinaryNotFound` diagnostic can share the same list.
     *
     * @since 2.5.3
     *
     * @return array<int, string>
     */
    protected function composerCandidatePaths(): array
    {
        $home    = getenv( 'HOME' );
        $herdBin = $this->herdBinPath();

        $candidates = [];

        if ( null !== $herdBin ) {
            // Laravel Herd on macOS bundles composer alongside its PHP
            // binaries. Prefer it for the same reason phpCandidatePaths()
            // prefers Herd's php: hosts that use Herd for both FPM and CLI
            // stay on a single toolchain. Without this a Herd-only machine —
            // no Homebrew composer, no global install — matches none of the
            // paths below and discovery falls through to bare `composer`,
            // which PHP-FPM's stripped `PATH` cannot resolve.
            $candidates[] = $herdBin . '/composer';
        }

        $candidates[] = '/usr/local/bin/composer';
        $candidates[] = '/opt/homebrew/bin/composer';

        if ( is_string( $home ) && '' !== $home ) {
            $candidates[] = $home . '/.composer/vendor/bin/composer';
            $candidates[] = $home . '/.config/composer/vendor/bin/composer';
        }

        $candidates[] = '/usr/bin/composer';

        return $candidates;
    }

    /**
     * Absolute path to Laravel Herd's macOS `bin/` directory, or `null` when
     * `HOME` is unset or empty.
     *
     * Herd keeps both its `php` symlink and its bundled `composer` script in
     * this one directory, and both `phpCandidatePaths()` and
     * `composerCandidatePaths()` need it. Deriving it in one place keeps the
     * two lists from drifting apart — the drift that produced #254, where the
     * PHP list learned about Herd in #225 and the composer list never did.
     *
     * The directory is not checked for existence: callers append a filename
     * and stat that, and a non-Herd host simply misses on every candidate.
     *
     * @since 2.7.1
     */
    protected function herdBinPath(): ?string
    {
        $home = getenv( 'HOME' );

        if ( ! is_string( $home ) || '' === $home ) {
            return null;
        }

        return $home . '/Library/Application Support/Herd/bin';
    }

    /**
     * Build the composer command line from a binary path. Invokes composer via
     * a resolved CLI PHP interpreter so PHP-FPM's `PATH` never needs to
     * resolve `php` for composer's shebang, and so we never hand the PHAR to
     * an FPM daemon binary (which prints usage and exits 64).
     *
     * @since 2.5.3
     *
     * @param  string  $binary  Absolute path to the composer PHAR.
     * @param  string|null  $args  Composer sub-command and flags. Defaults to the
     *                             install arguments; the stale-lock recovery path
     *                             (#273) passes an `update <packages>` string.
     */
    protected function buildComposerCommand( string $binary, ?string $args = null ): string
    {
        $args ??= self::DEFAULT_COMPOSER_INSTALL_ARGS;

        return escapeshellarg( $this->resolvePhpBinary() ) . ' ' . escapeshellarg( $binary ) . ' ' . $args;
    }

    /**
     * Resolve a CLI PHP binary suitable for executing composer's PHAR.
     *
     * `PHP_BINARY` is only trustworthy when the current SAPI is `cli` — under
     * PHP-FPM (which is where the self-updater actually runs from the admin
     * UI) it resolves to the FPM daemon binary, which cannot execute scripts.
     * Handing composer to it produces the classic "prints usage, exits 64,
     * empty stderr" failure that led to #225 and reappeared under Herd.
     *
     * Resolution order:
     * 1. `CMS_PHP_BINARY` environment variable — absolute path, wins.
     * 2. `PHP_BINARY` when `PHP_SAPI === 'cli'` — the CLI-invoked update
     *    command path (artisan, workers) hits this and gets the same
     *    interpreter as the parent process.
     * 3. A curated candidate list of CLI installs, filtering out anything
     *    whose basename smells like an FPM/CGI SAPI binary.
     * 4. `PHP_BINARY` as a last-resort fallback so callers still get *a*
     *    command to execute; the surrounding diagnostic will surface the
     *    real failure.
     *
     * @since 2.5.4
     */
    protected function resolvePhpBinary(): string
    {
        $override = getenv( 'CMS_PHP_BINARY' );
        if ( is_string( $override ) && '' !== $override ) {
            return $override;
        }

        if ( 'cli' === PHP_SAPI ) {
            return PHP_BINARY;
        }

        foreach ( $this->phpCandidatePaths() as $candidate ) {
            if ( $this->isCliPhpBinary( $candidate ) ) {
                return $candidate;
            }
        }

        return PHP_BINARY;
    }

    /**
     * Common CLI PHP install paths, in preference order. Extracted so tests
     * and the `composerVerificationFailed` diagnostic can share the same list.
     *
     * @since 2.5.4
     *
     * @return array<int, string>
     */
    protected function phpCandidatePaths(): array
    {
        $herdBin = $this->herdBinPath();

        $candidates = [];

        if ( null !== $herdBin ) {
            // Laravel Herd on macOS ships a `php` symlink pointing at the
            // currently-active CLI binary (e.g. `php84`). Prefer it so hosts
            // that use Herd for both FPM and CLI stay on a single toolchain.
            $candidates[] = $herdBin . '/php';
        }

        $candidates[] = '/opt/homebrew/bin/php';
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';

        return $candidates;
    }

    /**
     * Return true when the path points at what looks like a CLI PHP binary —
     * executable, and whose basename does not smell like an FPM or CGI SAPI
     * binary (`php-fpm`, `php84-fpm`, `php-cgi`, …). Cheap heuristic; the
     * only real cost of a false-positive is the caller falling through to
     * `PHP_BINARY` and surfacing the resulting diagnostic.
     *
     * @since 2.5.4
     */
    protected function isCliPhpBinary( string $path ): bool
    {
        if ( ! is_file( $path ) || ! is_executable( $path ) ) {
            return false;
        }

        $basename = strtolower( basename( $path ) );

        return ! str_contains( $basename, 'fpm' ) && ! str_contains( $basename, 'cgi' );
    }

    /**
     * Run database migrations.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     */
    protected function runMigrations(): void
    {
        try {
            Artisan::call( 'migrate', ['--force' => true] );
        } catch ( Throwable $e ) {
            throw UpdateException::migrationFailed( $e->getMessage() );
        }
    }

    /**
     * Clear application caches.
     *
     * @since 1.0.0
     */
    protected function clearCaches(): void
    {
        Artisan::call( 'config:clear' );
        Artisan::call( 'cache:clear' );
        Artisan::call( 'route:clear' );
        Artisan::call( 'view:clear' );
    }

    /**
     * Clean up temporary files.
     *
     * @since 1.0.0
     *
     * @param  string  $zipPath  Path to ZIP file
     */
    protected function cleanup( string $zipPath ): void
    {
        if ( File::exists( $zipPath ) ) {
            File::delete( $zipPath );
        }
    }

    /**
     * Enable maintenance mode and arm the shutdown guard that lifts it again
     * if this process dies before step 10.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     */
    protected function enableMaintenanceMode(): void
    {
        try {
            $exitCode = Artisan::call( 'down', ['--render' => 'errors::503'] );
        } catch ( Throwable $e ) {
            throw UpdateException::maintenanceModeFailure( 'enable' );
        }

        // `DownCommand::handle()` wraps its own body in a try/catch that logs
        // and `return 1`s, so a genuine failure — an unwritable
        // `storage/framework/`, say — arrives as a non-zero exit code and
        // never as an exception. The catch above is very nearly dead code in
        // practice; the exit code is the signal that actually fires. Without
        // this check the updater would proceed to overwrite application files
        // on a **live** site while believing it had taken the site down.
        if ( 0 !== $exitCode ) {
            throw UpdateException::maintenanceModeFailure( 'enable' );
        }

        $this->maintenanceModeActive = true;

        $this->armShutdownGuard();
    }

    /**
     * Disable maintenance mode.
     *
     * The active flag is only cleared once `up` has actually succeeded, so a
     * failure here leaves the shutdown guard armed for one more attempt.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     */
    protected function disableMaintenanceMode(): void
    {
        try {
            $exitCode = Artisan::call( 'up' );
        } catch ( Throwable $e ) {
            throw UpdateException::maintenanceModeFailure( 'disable' );
        }

        // `UpCommand::handle()` catches its own exceptions and `return 1`s, so
        // a failure to remove `storage/framework/down` — permission denied,
        // say — comes back as a non-zero exit code rather than a throw.
        // Trusting the absence of an exception cleared the active flag,
        // disarmed the shutdown guard, and let the run be marked `Completed`,
        // leaving the site serving 503 to every visitor while `update:status`
        // reported a clean finish. That is the exact outcome the interruption
        // machinery exists to prevent.
        if ( 0 !== $exitCode ) {
            throw UpdateException::maintenanceModeFailure( 'disable' );
        }

        $this->maintenanceModeActive = false;
    }

    /**
     * Lift PHP's own limits on how long the update may run.
     *
     * `runComposerInstall()` gives the composer child process a
     * `cms.updates.composer_timeout` budget (default 600s), but that only
     * bounds the child — the parent PHP request is still governed by
     * `max_execution_time`, which defaults to 30 seconds under PHP-FPM. Left
     * alone, the request is killed roughly twenty times sooner than composer's
     * own budget allows for, and because an execution-time fatal is raised at
     * shutdown rather than thrown, it bypasses `performUpdate()`'s catch block
     * entirely.
     *
     * `ignore_user_abort( true )` matters for the same reason: without it,
     * closing the browser tab mid-update aborts the request and produces the
     * identical stuck-in-maintenance-mode outcome.
     *
     * Neither call is guaranteed to succeed — shared hosts routinely put
     * `set_time_limit` in `disable_functions`, and FPM's
     * `request_terminate_timeout` cannot be overridden from userland at all.
     * That is what the shutdown guard in `handleInterruptedUpdate()` is for.
     *
     * @since 2.7.1
     */
    protected function raiseExecutionLimits(): void
    {
        if ( ! function_exists( 'set_time_limit' ) || ! @set_time_limit( 0 ) ) {
            Log::warning(
                'cms-framework: could not lift PHP\'s max_execution_time for the update; the request may be killed mid-flight.',
                [
                    'php_sapi'           => PHP_SAPI,
                    'max_execution_time' => ini_get( 'max_execution_time' ),
                    'hint'               => 'Run `php artisan update:perform` from the CLI instead, or remove set_time_limit from the host\'s disable_functions.',
                ],
            );
        }

        if ( function_exists( 'ignore_user_abort' ) ) {
            ignore_user_abort( true );
        }
    }

    /**
     * Record that a step is now in flight, both in memory (for the shutdown
     * guard's log context) and on disk (so the step survives the process).
     *
     * @since 2.7.1
     *
     * @param  UpdateStep  $step  Step being entered.
     */
    protected function beginStep( UpdateStep $step ): void
    {
        $this->currentStep = $step;

        $this->state()->markStep( $step );
    }

    /**
     * Persisted step marker for this manager instance.
     *
     * @since 2.7.1
     *
     * @return UpdateStateStore State store.
     */
    protected function state(): UpdateStateStore
    {
        return $this->state ??= new UpdateStateStore;
    }

    /**
     * Register the shutdown guard exactly once per manager instance.
     *
     * @since 2.7.1
     */
    protected function armShutdownGuard(): void
    {
        if ( $this->shutdownGuardArmed ) {
            return;
        }

        $this->shutdownGuardArmed = true;

        register_shutdown_function( function (): void {
            $this->handleInterruptedUpdate();
        } );
    }

    /**
     * The fatal error that ended the process, or `null` when the process is
     * ending for some other reason (a client abort, an `exit()`).
     *
     * `error_get_last()` alone is not enough: it returns the last diagnostic
     * of *any* severity, and the extractor deliberately uses `@fopen()` /
     * `@chmod()`, so a suppressed warning from step 5 would otherwise be
     * reported as the reason an update died at step 7.
     *
     * @since 2.7.1
     *
     * @return array{type: int, message: string, file: string, line: int}|null Fatal error details.
     */
    protected function lastFatalError(): ?array
    {
        $error = error_get_last();

        if ( null === $error ) {
            return null;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

        return in_array( $error['type'] ?? 0, $fatalTypes, true ) ? $error : null;
    }

    /**
     * Take the site back out of maintenance mode after an update that did not
     * reach step 10, honouring `cms.updates.lift_maintenance_on_interrupt`.
     *
     * Shared by the in-process shutdown guard and by the queued job's `failed()`
     * hook, which face the same situation from different angles: a run that
     * stopped somewhere between step 1 and step 10, with the site still down.
     *
     * Honours the three `cms.updates.lift_maintenance_on_interrupt` policies:
     *
     * - `false` — fail closed. Never lift; leave the site down for an operator.
     * - `true` — always lift, whatever step the update died on.
     * - `'step_aware'` (the default) — lift only when the death step left the
     *   application whole. A death in steps 5-7 (extract, composer install,
     *   migrations) leaves a half-applied tree or schema that must not go back
     *   on the public internet, so the site stays down; a death anywhere else
     *   is lifted as before.
     *
     * @since 2.8.0
     *
     * @param  string  $liftedBy  What is doing the lifting, for the log line.
     * @param  UpdateStep|null  $step  Step the update was in when it died, or null when unknown.
     */
    protected function liftMaintenanceModeAfterFailure( string $liftedBy, ?UpdateStep $step = null ): void
    {
        $mode = $this->resolveLiftPolicy( config( 'cms.updates.lift_maintenance_on_interrupt', 'step_aware' ) );

        if ( 'never' === $mode ) {
            Log::critical(
                'cms-framework: cms.updates.lift_maintenance_on_interrupt is disabled, so the site is being left in maintenance mode. Run `php artisan up` once you have verified the install.',
            );

            return;
        }

        if ( 'step_aware' === $mode && $this->interruptLeftSiteUnsafe( $step ) ) {
            Log::critical(
                sprintf(
                    'cms-framework: the update was interrupted during a step that leaves the application partially applied (%s), so %s kept the site in maintenance mode rather than serve a half-applied install. Restore the pre-update snapshot or finish the update by hand, then run `php artisan up`. Run `php artisan update:status` to see what remains.',
                    $step?->label() ?? __( 'an unknown step' ),
                    $liftedBy,
                ),
            );

            return;
        }

        try {
            $this->disableMaintenanceMode();

            Log::critical( "cms-framework: maintenance mode was lifted by {$liftedBy}. The installation may be half-applied — run `php artisan update:status` before trusting it." );
        } catch ( Throwable $e ) {
            Log::critical( "cms-framework: {$liftedBy} could not lift maintenance mode via `artisan up`.", [
                'exception' => $e->getMessage(),
            ] );

            $this->forceLiftMaintenanceMode();
        }
    }

    /**
     * Normalise a `lift_maintenance_on_interrupt` config value to one of the
     * three canonical modes: `'always'`, `'never'`, or `'step_aware'`.
     *
     * The mapping fails *safe*. The boolean `true` is `'always'`; any falsy
     * value (`false`, `0`, `''`, `null`) is `'never'`, preserving the pre-2.8
     * `! config(...)` fail-closed semantics; the `'step_aware'` token —
     * matched case-insensitively and trimmed so an `.env` value survives stray
     * whitespace or capitalisation — is `'step_aware'`. Any other unrecognized
     * non-empty value (a typo such as `step-aware`, or an unexpected truthy
     * scalar) is treated as `'step_aware'` rather than `'always'`, with a
     * warning: a misconfiguration must never be the reason a possibly
     * half-applied tree goes back on the public internet.
     *
     * @since 2.8.0
     *
     * @param  mixed  $policy  Configured policy value.
     *
     * @return string One of `'always'`, `'never'`, or `'step_aware'`.
     */
    protected function resolveLiftPolicy( mixed $policy ): string
    {
        if ( is_string( $policy ) && 'step_aware' === strtolower( trim( $policy ) ) ) {
            return 'step_aware';
        }

        if ( true === $policy ) {
            return 'always';
        }

        if ( ! $policy ) {
            return 'never';
        }

        Log::warning(
            sprintf(
                'cms-framework: unrecognized cms.updates.lift_maintenance_on_interrupt value %s; defaulting to the step-aware policy. Set it to true, false, or "step_aware".',
                var_export( $policy, true ),
            ),
        );

        return 'step_aware';
    }

    /**
     * Whether a death in the given step left the site unsafe to serve, for the
     * step-aware lift policy.
     *
     * An interruption whose step cannot be identified is treated as unsafe:
     * step-aware mode fails closed rather than assume the tree is clean.
     *
     * @since 2.8.0
     *
     * @param  UpdateStep|null  $step  Step the update was in when it died, or null when unknown.
     *
     * @return bool True when the site should stay in maintenance mode.
     */
    protected function interruptLeftSiteUnsafe( ?UpdateStep $step ): bool
    {
        return null === $step || $step->interruptionLeavesSiteUnsafe();
    }

    /**
     * Queue connection a queued update is dispatched to.
     *
     * `cms.updates.queue.connection` when set, otherwise the application
     * default. Resolved eagerly rather than left null so the driver guard and
     * the persisted record both name the connection the operator would have to
     * run a worker against.
     *
     * @since 2.8.0
     *
     * @return string|null Connection name, or null when none is configured.
     */
    protected function updateQueueConnection(): ?string
    {
        $configured = config( 'cms.updates.queue.connection' );

        if ( is_string( $configured ) && '' !== $configured ) {
            return $configured;
        }

        $default = config( 'queue.default' );

        return is_string( $default ) && '' !== $default ? $default : null;
    }

    /**
     * Queue name a queued update is pushed onto, or null for the connection's
     * default queue.
     *
     * @since 2.8.0
     *
     * @return string|null Queue name.
     */
    protected function updateQueueName(): ?string
    {
        $configured = config( 'cms.updates.queue.queue' );

        return is_string( $configured ) && '' !== $configured ? $configured : null;
    }

    /**
     * Refuse to queue an update onto a connection that will not run it in the
     * background.
     *
     * Three connections are unusable, for two different reasons:
     *
     * - **`sync`** runs the job inline in the dispatching process. Dispatching
     *   an update to it produces exactly the multi-minute blocking HTTP request
     *   that queueing exists to avoid, while looking from the outside like the
     *   feature works. Opt in with `cms.updates.queue.allow_sync` if that is
     *   genuinely intended — a CLI caller, say — and the framework warns and
     *   proceeds.
     * - **`null`** discards the job. The site would simply never be updated.
     * - **An unconfigured connection** cannot be dispatched to at all.
     *
     * The last two are not covered by `allow_sync`: neither ever runs the
     * update, so there is nothing to opt in to.
     *
     * @since 2.8.0
     *
     * @param  string|null  $connection  Resolved connection name.
     *
     * @throws UpdateException When the connection cannot run a background job.
     */
    protected function guardQueueDriver( ?string $connection ): void
    {
        $driver = null === $connection ? null : config( "queue.connections.{$connection}.driver" );
        $driver = is_string( $driver ) ? $driver : null;

        if ( 'sync' === $driver && config( 'cms.updates.queue.allow_sync', false ) ) {
            Log::warning( 'cms-framework: queueing an application update onto the sync driver, so it will run inline and block the caller for the whole update.', [
                'queue_connection' => $connection,
                'hint'             => 'This is opt-in via cms.updates.queue.allow_sync. Configure a real queue connection and run a worker to get the non-blocking behavior.',
            ] );

            return;
        }

        if ( null === $driver || 'sync' === $driver || 'null' === $driver ) {
            throw UpdateException::updateQueueUnusable( $connection, $driver );
        }

        $this->guardQueueRetryAfter( $connection );
    }

    /**
     * Refuse a connection that would redeliver the update before it finishes.
     *
     * `retry_after` is the queue's "this reserved job must have died" timer.
     * Laravel ships 90 seconds for the `database`, `redis` and `beanstalkd`
     * connections; a real update runs for minutes, so the stock configuration
     * hands the same update to a second worker while the first is still
     * running `composer install`.
     *
     * `$tries = 1` does not save this — it is what makes it land here. The
     * duplicate exceeds max attempts, so the worker fails it *without* calling
     * `handle()`, and it goes straight to `failed()` carrying a
     * `MaxAttemptsExceededException` against a run that is perfectly healthy.
     * (`handleFailedUpdateJob()`'s ownership check is the second line of
     * defence for exactly that, but a spurious `failed_jobs` row and a
     * `critical` log line on every long update is not something to ship.)
     *
     * Only connections that actually carry the setting are checked: SQS
     * expresses the same idea as a queue-side visibility timeout that is not
     * readable from here.
     *
     * @since 2.8.0
     *
     * @param  string|null  $connection  Resolved connection name.
     *
     * @throws UpdateException When redelivery would happen mid-update.
     */
    protected function guardQueueRetryAfter( ?string $connection ): void
    {
        $retryAfter = config( "queue.connections.{$connection}.retry_after" );

        if ( ! is_numeric( $retryAfter ) ) {
            return;
        }

        $retryAfter = (int) $retryAfter;
        $timeout    = PerformUpdateJob::resolveTimeout();

        if ( $retryAfter > $timeout ) {
            return;
        }

        throw UpdateException::updateQueueRetryTooShort( $connection, $retryAfter, $timeout );
    }

    /**
     * Refuse to queue an update while another one is still expected to run.
     *
     * A live in-progress run is refused outright. A `queued` record is refused
     * only while it is young enough to plausibly still be waiting: a host with
     * no worker running would otherwise be wedged permanently by its own first
     * dispatch, with nothing to tell the operator that clearing the record is
     * what unblocks it.
     *
     * @since 2.8.0
     *
     * @throws UpdateException When an update is already running or queued.
     */
    protected function guardNoPendingUpdate(): void
    {
        $runningPid = $this->runningUpdatePid();

        if ( null !== $runningPid ) {
            throw UpdateException::updateAlreadyRunning( $runningPid );
        }

        $state  = $this->updateState();
        $status = UpdateRunStatus::tryFrom( is_string( $state['status'] ?? null ) ? $state['status'] : '' );

        if ( UpdateRunStatus::Queued !== $status ) {
            return;
        }

        $queuedAt = $this->queuedAt();

        // Shares the window with the job's unique lock, so the two expire
        // together and a retry that clears this guard is not then swallowed by
        // a lock that has not.
        if ( $this->state()->isStaleTimestamp( $queuedAt ) ) {
            Log::warning( 'cms-framework: a previously queued application update was never picked up by a worker; queueing a new one over it.', [
                'queued_at' => $queuedAt,
                'hint'      => 'Check that a queue worker is running against the configured connection.',
            ] );

            return;
        }

        throw UpdateException::updateAlreadyQueued( $queuedAt );
    }

    /**
     * When the recorded run was pushed onto the queue, or null.
     *
     * @since 2.8.0
     *
     * @return string|null ISO-8601 timestamp.
     */
    protected function queuedAt(): ?string
    {
        $state    = $this->updateState();
        $queuedAt = $state['queued_at'] ?? null;

        return is_string( $queuedAt ) && '' !== $queuedAt ? $queuedAt : null;
    }

    /**
     * The currently installed application version, recorded alongside a queued
     * run so `update:status` can render it before the job resolves the target.
     *
     * Reads `config('app.version')` — the same source `UpdateInfo::resolveCurrentVersion()`
     * consults — rather than asking the update checker, which would mean a
     * network round-trip in the request doing the dispatching.
     *
     * @since 2.8.0
     *
     * @return string|null Installed version.
     */
    protected function installedVersion(): ?string
    {
        $version = config( 'app.version' );

        return is_string( $version ) && '' !== $version ? $version : null;
    }

    /**
     * Last-ditch attempt to take the site out of maintenance mode by removing
     * the file-driver marker directly.
     *
     * Reached only when `artisan up` itself failed — typically because the
     * process is shutting down after an out-of-memory fatal and there isn't
     * enough headroom left to boot a console command. Unlinking a single file
     * needs almost none, so it is worth trying before giving up and leaving
     * the site serving 503s. Hosts using the cache-backed maintenance driver
     * have no such file; the log line says so rather than claiming success.
     *
     * @since 2.7.1
     */
    protected function forceLiftMaintenanceMode(): void
    {
        try {
            $downFile = storage_path( 'framework/down' );

            if ( ! File::exists( $downFile ) ) {
                Log::critical( 'cms-framework: no storage/framework/down marker to remove; if this host uses the cache-backed maintenance driver, run `php artisan up` manually.' );

                return;
            }

            File::delete( $downFile );

            Log::critical( 'cms-framework: removed storage/framework/down directly to take the site out of maintenance mode.' );
        } catch ( Throwable $e ) {
            Log::critical( 'cms-framework: could not remove the maintenance-mode marker; the site is still serving 503s and needs `php artisan up`.', [
                'exception' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Remove the files the current extraction added, after the snapshot has
     * been restored.
     *
     * `rollback()` puts back every file the pre-update snapshot captured, but a
     * snapshot cannot restore a file that did not exist when it was taken.
     * Without this, every path the extraction *added* survives the restore,
     * leaving the tree a hybrid of the old version's files and an arbitrary
     * subset of the new version's additions — migrations included, whose next
     * `migrate` run would apply schema for a version that was rolled back.
     *
     * Only paths this extraction recorded as newly created are touched, and the
     * exclusion list is honoured a second time so a rollback can never delete
     * `storage/`, `.env`, or `vendor/` contents even if one slipped into the
     * ledger.
     *
     * @since 2.8.0
     */
    protected function removeExtractionAdditions(): void
    {
        if ( empty( $this->extractionAdditions ) ) {
            return;
        }

        $excludePaths = config( 'cms.updates.exclude_from_update', [] );
        $basePath     = base_path();
        $removed      = 0;

        foreach ( $this->extractionAdditions as $relativePath ) {
            if ( $this->isPathExcluded( $relativePath, $excludePaths ) ) {
                continue;
            }

            $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;

            // The extraction only ever wrote regular files, so a symlink or a
            // directory at this path is something else on disk — never follow
            // or remove it. Also covers the case where restoring the backup
            // already replaced the addition with something the snapshot owned.
            if ( is_link( $fullPath ) || ! is_file( $fullPath ) ) {
                continue;
            }

            // Defence in depth: resolve the path and confirm it still sits
            // under base_path() before unlinking, mirroring the extraction
            // guards. A file always resolves, so realpath() cannot fail here.
            if ( ! $this->isPathWithinExtractRoot( $fullPath, $basePath ) ) {
                continue;
            }

            // `File::delete()` reports failure by returning false, not by
            // throwing, so the count has to consult the return value — the old
            // try/catch treated a failed unlink as a successful removal. A file
            // that cannot be removed is logged, not fatal: the rollback of the
            // backed-up files has already succeeded, and aborting here would
            // turn a partial cleanup into a reported rollback failure.
            if ( ! File::delete( $fullPath ) ) {
                Log::warning( 'cms-framework: could not remove a file the rolled-back update had added; it remains on disk.', [
                    'path' => $relativePath,
                ] );

                continue;
            }

            $removed++;
        }

        if ( $removed > 0 ) {
            Log::info( 'cms-framework: removed files the rolled-back update had added.', [
                'count' => $removed,
            ] );
        }

        $this->extractionAdditions = [];
    }

    /**
     * Handle update failure and attempt rollback.
     *
     * @since 1.0.0
     *
     * @param  Throwable  $exception  The throwable that caused failure
     */
    protected function handleUpdateFailure( Throwable $exception ): void
    {
        // Log the original exception for debugging
        Log::error( 'Update failed, beginning rollback', [
            'exception' => $exception->getMessage(),
            'trace'     => $exception->getTraceAsString(),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
        ] );

        // Persist whatever the extraction ledgered before it threw. A failure
        // *during* `extractUpdate()` (a short write, a failed `fopen`/close)
        // never reaches that method's end-of-loop persist, so without this the
        // state file would carry no `extraction_additions` entry and a later
        // manual `update:rollback` in a fresh process could not remove the
        // files the partial extraction added (#272). The automatic path below
        // still uses the in-memory ledger directly.
        $this->state()->recordExtractionAdditions( $this->extractionAdditions );

        // Maintenance mode is deliberately NOT lifted up front. Lifting before
        // the rollback would serve public traffic from a half-applied tree
        // (and, with backups on, run `composer install` over live requests
        // during the restore). Each branch below decides when — and whether —
        // the site may go back up.

        // Only roll back while a rollback can still help. Steps 8-10
        // (`cache:clear`, cleanup, `up`) run *after* the code and the schema
        // are already updated, so restoring the snapshot there discards a
        // fully-applied update — and migrations are not reversed either,
        // leaving old code against a new schema. A `cache:clear` hiccup must
        // not undo a successful install; `update:status` already prints the
        // commands to finish forward.
        if ( null !== $this->currentStep && $this->currentStep->number() > UpdateStep::Migrations->number() ) {
            Log::warning(
                'cms-framework: the update failed after the code and schema were already applied, so the snapshot was NOT restored. Finish forward using `php artisan update:status`.',
                ['step' => $this->currentStep->value],
            );

            $this->state()->markFinishForward();

            // The code and schema are already in place, so the site can serve
            // the new version. Bring it back up (best-effort, as before).
            try {
                $this->disableMaintenanceMode();
            } catch ( Throwable $e ) {
                Log::error(
                    'Failed to disable maintenance mode after a finish-forward update failure; host may remain in maintenance mode.',
                    ['exception' => $e->getMessage()],
                );
            }

            return;
        }

        // If we have a backup, attempt rollback
        if ( $this->backupPath && File::exists( $this->backupPath ) ) {
            try {
                $this->rollback( $this->backupPath );

                // The snapshot restore reinstated every backed-up file, but a
                // snapshot cannot restore a file that did not exist when it
                // was taken. Remove the files this extraction added so the
                // tree matches the pre-update version rather than a hybrid of
                // both — otherwise orphaned code and, worse, orphaned
                // migrations are left behind.
                $this->removeExtractionAdditions();

                $this->state()->markRollback( true );
            } catch ( Throwable $e ) {
                // Rollback failed - this is critical. Preserve the original
                // update-failure message alongside the rollback message so the
                // operator can see both failures rather than only the trailing
                // one.
                $combined = UpdateException::rollbackAfterFailure( $exception->getMessage(), $e->getMessage() );

                // Record it before throwing: the exception carries both
                // messages to the caller, but `update:status` reads the state
                // file, and without this the file still says the run merely
                // "failed" with the original error — the rollback failure, the
                // thing that actually needs a human, would never reach it. The
                // site is left in maintenance mode: a failed rollback means the
                // tree is in an unknown state that must not serve traffic.
                $this->state()->markRollback( false, $combined->getMessage() );

                throw $combined;
            }

            // Only now that the pre-update snapshot is back in place is it safe
            // to lift maintenance — and only per the `lift_maintenance_on_interrupt`
            // policy, exactly as the interruption path does.
            $this->liftMaintenanceModeAfterFailure( 'the update failure handler', $this->currentStep );

            return;
        }

        // No snapshot to restore: backups disabled, or the run failed before
        // `createBackup()` got that far. The tree may be half-applied and
        // cannot be restored, so the lift policy governs whether it goes back
        // on the public internet — rather than lifting unconditionally and
        // serving a mix of old and new code.
        $this->state()->markRollback( null );

        $this->liftMaintenanceModeAfterFailure( 'the update failure handler', $this->currentStep );
    }
}
