<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Jobs;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Perform Update Job
 *
 * Runs `ApplicationUpdateManager::performUpdate()` on a queue worker instead of
 * inline in the request that asked for it.
 *
 * `performUpdate()` is a multi-minute operation — download, extract,
 * `composer install` across a real dependency tree, migrate. Running it from an
 * HTTP handler survives (2.7.1 lifted `max_execution_time`, armed a shutdown
 * guard, and persisted a step marker) but is never appropriate: it occupies a
 * PHP-FPM worker for the whole run and keeps occupying it after the caller
 * disconnects, it is subject to gateway timeouts (nginx `proxy_read_timeout`,
 * load balancers, Cloudflare's 100s) and to FPM's `request_terminate_timeout`
 * that no userland call can override, and it gives the operator no feedback
 * beyond polling the state file.
 *
 * Dispatch this instead — via `ApplicationUpdateManager::dispatchUpdate()`,
 * which validates the queue connection first — and poll
 * `ApplicationUpdateManager::updateState()` for progress.
 *
 * `performUpdate()` remains a supported direct call; integrators calling it
 * inline are unaffected by this job existing.
 *
 * @since 2.8.0
 */
class PerformUpdateJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Lock key for `ShouldBeUnique`.
     *
     * Deliberately constant rather than derived from the target version: the
     * constraint being expressed is "one application update at a time on this
     * host", and two dispatches naming different versions are the worst case,
     * not an exemption.
     *
     * @since 2.8.0
     */
    public const UNIQUE_ID = 'artisanpack-cms-application-update';

    /**
     * Seconds added to the download and composer budgets to cover the steps
     * that have no timeout of their own — backup, extraction, migrations,
     * cache clearing.
     *
     * @since 2.8.0
     */
    public const DEFAULT_TIMEOUT_BUFFER = 900;

    /**
     * Attempts allowed for this job.
     *
     * Exactly one, and not negotiable through config. A retry would restart an
     * update at step 1 over a tree the previous attempt had already partly
     * overwritten, extracting a second release over a half-extracted first one
     * — the interleaving that `performUpdate()`'s `flock` exists to prevent,
     * reintroduced by the queue rather than by a concurrent caller. A failed
     * update is rolled back or reported by `update:status`; it is never
     * something to repeat unattended.
     *
     * @since 2.8.0
     *
     * @var int
     */
    public int $tries = 1;

    /**
     * Whether exhausting the timeout marks the job failed.
     *
     * True so `failed()` runs and the persisted record stops saying
     * `in_progress` for a run whose process is gone.
     *
     * @since 2.8.0
     *
     * @var bool
     */
    public bool $failOnTimeout = true;

    /**
     * Seconds the worker allows this job before killing it.
     *
     * @since 2.8.0
     *
     * @var int
     */
    public int $timeout;

    /**
     * Create a new job instance.
     *
     * @since 2.8.0
     *
     * @param  string|null  $version  Version to install, or null for latest.
     * @param  bool  $allowDowngrade  Permit a target that is not newer than the installed version.
     */
    public function __construct(
        public readonly ?string $version = null,
        public readonly bool $allowDowngrade = false,
    ) {
        $this->timeout = self::resolveTimeout();
    }

    /**
     * Seconds a queued update is allowed to run, derived from the per-phase
     * timeouts the updater already honours.
     *
     * This value wins over the worker's `--timeout`, not the other way round:
     * `Worker::timeoutForJob()` is `$job->timeout() ?? $options->timeout`, so a
     * job carrying its own timeout makes the worker flag a fallback. An
     * operator running `queue:work --timeout=60` therefore still gets the full
     * budget rather than a kill sixty seconds in.
     *
     * The setting that genuinely has to be raised by hand is the connection's
     * `retry_after` — see `ApplicationUpdateManager::guardQueueRetryAfter()`,
     * which refuses to dispatch when it is too short to survive the run.
     *
     * @since 2.8.0
     *
     * @return int Timeout in seconds.
     */
    public static function resolveTimeout(): int
    {
        $configured = config( 'cms.updates.queue.timeout' );

        if ( is_numeric( $configured ) && (int) $configured > 0 ) {
            return (int) $configured;
        }

        $download = (int) config( 'cms.updates.download_timeout', 300 );
        $composer = (int) config( 'cms.updates.composer_timeout', 600 );

        return max( $download, 0 ) + max( $composer, 0 ) + self::DEFAULT_TIMEOUT_BUFFER;
    }

    /**
     * Execute the job.
     *
     * @since 2.8.0
     *
     * @param  ApplicationUpdateManager  $manager  Update manager.
     *
     * @throws \ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException
     */
    public function handle( ApplicationUpdateManager $manager ): void
    {
        $manager->performUpdate( $this->version, $this->allowDowngrade );
    }

    /**
     * Reconcile the persisted record when the job did not finish cleanly.
     *
     * Reached for a thrown failure, and — the case that matters — for a worker
     * timeout, where the run is killed mid-step in a process whose own
     * `catch` and shutdown guard may never get to run.
     *
     * @since 2.8.0
     *
     * @param  Throwable|null  $exception  The failure, when there was one.
     */
    public function failed( ?Throwable $exception = null ): void
    {
        app( ApplicationUpdateManager::class )->handleFailedUpdateJob( $exception );
    }

    /**
     * Unique lock key.
     *
     * @since 2.8.0
     *
     * @return string Lock key.
     */
    public function uniqueId(): string
    {
        return self::UNIQUE_ID;
    }

    /**
     * Seconds the unique lock is held before it expires on its own.
     *
     * Matched to the job timeout so a lock orphaned by a hard kill (`kill -9`,
     * OOM) cannot wedge the updater permanently.
     *
     * Note this lock lives in the cache, and step 8 of the update runs
     * `cache:clear` — so it is dropped near the end of every successful run.
     * That is why it is a convenience and not the guarantee: the guarantee is
     * the `flock` sentinel `performUpdate()` takes beside the state file, which
     * no cache flush can touch.
     *
     * @since 2.8.0
     *
     * @return int Lock TTL in seconds.
     */
    public function uniqueFor(): int
    {
        return self::resolveTimeout();
    }
}
