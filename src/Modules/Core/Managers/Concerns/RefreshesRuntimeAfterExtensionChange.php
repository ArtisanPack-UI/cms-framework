<?php

declare( strict_types=1 );

/**
 * RefreshesRuntimeAfterExtensionChange Trait
 *
 * Makes a plugin or theme change visible to the running site: the compiled
 * caches, PHP-FPM's OPcache and the queue workers.
 *
 * @since      2.18.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trait for the post-change step the plugin and theme managers share.
 *
 * Installing, activating, updating or deleting an extension only changes
 * files and rows. On a production host (`opcache.validate_timestamps=0`,
 * cached routes, a queue worker) PHP-FPM keeps serving the old bytecode, the
 * route cache keeps the old routes, and workers keep running the old job
 * classes, so the change doesn't take effect until someone reloads PHP-FPM,
 * runs `optimize` and reloads again (#450, #453). This does what the
 * self-updater does after an update (#425, #429): rebuild the caches the site
 * had, reset the web server's OPcache, and signal the queue workers to
 * restart.
 *
 * {@see withRuntimeRefresh()} wraps one change. A change that runs others, such
 * as an update that deactivates and reactivates a plugin, wraps them in
 * {@see withoutRuntimeRefresh()} and refreshes once itself, so the caches are
 * rebuilt and OPcache reset once per request rather than once per step.
 *
 * @since 2.18.0
 */
trait RefreshesRuntimeAfterExtensionChange
{
    use RebuildsCompiledCaches;
    use ResetsWebOpcache;
    use ResolvesCliPhpBinary;
    use RestartsQueueWorkers;

    /**
     * How the last change reset the web server's OPcache: `web-request`,
     * `fastcgi`, `loopback` or `command`; null before a change or when no
     * reset reached the web server.
     *
     * @since 2.18.0
     */
    protected ?string $opcacheResetMethod = null;

    /**
     * Whether PHP-FPM needs a manual reload to serve the last change.
     *
     * @since 2.18.0
     */
    protected bool $phpReloadRequired = false;

    /**
     * How many {@see withRuntimeRefresh()} or {@see withoutRuntimeRefresh()}
     * scopes are open. Only the outermost one refreshes.
     *
     * @since 2.18.0
     */
    protected int $runtimeRefreshDepth = 0;

    /**
     * Whether a step inside an open scope skipped an OPcache reset it needed,
     * leaving it to the scope (#438's reset after a Composer install).
     *
     * @since 2.18.0
     */
    protected bool $opcacheResetOwed = false;

    /**
     * Whether a step inside the open scope has already persisted its change
     * (a DB row, a setting or files on disk), so a failure after that point
     * must still refresh the runtime.
     *
     * @since 2.18.0
     */
    protected bool $runtimeChangePersisted = false;

    /**
     * How the last change this instance made reset the web server's OPcache.
     *
     * @since 2.18.0
     *
     * @return string|null The mechanism, or null when none reached the web
     *                     server (or no change has run).
     */
    public function opcacheResetMethod(): ?string
    {
        return $this->opcacheResetMethod;
    }

    /**
     * Whether the last change this instance made left PHP-FPM needing a
     * reload before it serves the change.
     *
     * @since 2.18.0
     *
     * @return bool True when no OPcache reset reached the web server.
     */
    public function phpReloadRequired(): bool
    {
        return $this->phpReloadRequired;
    }

    /**
     * Run a change without refreshing the runtime, because the caller
     * refreshes once its own change is done.
     *
     * Used by an update around the deactivate, Composer install and
     * reactivate it runs. The caller takes over every refresh the steps would
     * have made, including the OPcache reset after a Composer install.
     *
     * @since 2.18.0
     *
     * @template TResult
     *
     * @param  callable(): TResult  $operation  The change.
     *
     * @return TResult What the change returned.
     */
    public function withoutRuntimeRefresh( callable $operation ): mixed
    {
        $this->runtimeRefreshDepth++;

        try {
            return $operation();
        } finally {
            $this->runtimeRefreshDepth--;

            if ( 0 === $this->runtimeRefreshDepth ) {
                $this->opcacheResetOwed       = false;
                $this->runtimeChangePersisted = false;
            }
        }
    }

    /**
     * Run a change, then make it visible to the running site.
     *
     * The caches in place are read first, before the change can clear them
     * (`cms.plugins.autoClearFrameworkCaches`). When the change succeeds,
     * {@see refreshRuntime()} rebuilds them and resets OPcache. When it fails
     * after a step called {@see notePersistedRuntimeChange()}, the runtime is
     * refreshed in full before the failure is rethrown; otherwise only an
     * OPcache reset a step skipped is made up for. Inside another
     * scope, the change just runs and the outer scope refreshes.
     *
     * @since 2.18.0
     *
     * @template TResult
     *
     * @param  string  $change  What changed, for the log, e.g. `activating a plugin`.
     * @param  array<string, mixed>  $context  Log context, e.g. the slug.
     * @param  callable(): TResult  $operation  The change.
     *
     * @return TResult What the change returned.
     */
    protected function withRuntimeRefresh( string $change, array $context, callable $operation ): mixed
    {
        if ( $this->runtimeRefreshDepth > 0 ) {
            return $operation();
        }

        $cachedArtifacts = $this->cachedArtifacts();

        $this->resetRuntimeRefreshOutcome();
        $this->opcacheResetOwed       = false;
        $this->runtimeChangePersisted = false;

        $this->runtimeRefreshDepth++;

        try {
            $result = $operation();
        } catch ( Throwable $e ) {
            $this->runtimeRefreshDepth--;

            $persisted = $this->runtimeChangePersisted;
            $owed      = $this->opcacheResetOwed;

            $this->runtimeChangePersisted = false;
            $this->opcacheResetOwed       = false;

            if ( $persisted ) {
                // The change is already in the database or on disk, and a
                // later step (often a listener) failed. Make what was
                // persisted live, as syncFromDisk() does, then rethrow.
                $this->refreshRuntime( $change, $context, $cachedArtifacts );
            } elseif ( $owed ) {
                $this->resetOpcacheAfterChange( $change, $context );
            }

            throw $e;
        }

        $this->runtimeRefreshDepth--;
        $this->opcacheResetOwed       = false;
        $this->runtimeChangePersisted = false;

        $this->refreshRuntime( $change, $context, $cachedArtifacts );

        return $result;
    }

    /**
     * Whether a step is running inside a scope that will reset OPcache once
     * the change is done.
     *
     * A step that would reset OPcache itself, such as #438's reset after a
     * Composer install, calls this first: when it returns true the reset is
     * left to the scope and recorded as owed, so a failing change still
     * makes it.
     *
     * @since 2.18.0
     *
     * @return bool True when the reset was deferred to the scope.
     */
    protected function deferOpcacheResetToScope(): bool
    {
        if ( 0 === $this->runtimeRefreshDepth ) {
            return false;
        }

        $this->opcacheResetOwed = true;

        return true;
    }

    /**
     * Record that the running change has persisted its effect, so a failure
     * after this point still refreshes the runtime.
     *
     * Call it right after the write that makes the change real: the row is
     * saved, the setting is updated or the files are kept on disk. A no-op
     * outside a scope.
     *
     * @since 2.18.0
     */
    protected function notePersistedRuntimeChange(): void
    {
        if ( $this->runtimeRefreshDepth > 0 ) {
            $this->runtimeChangePersisted = true;
        }
    }

    /**
     * Forget the outcome of the previous change, before a new one starts.
     *
     * The managers are singletons, so a long-lived worker running several
     * changes would otherwise report one change's outcome for the next.
     *
     * @since 2.18.0
     */
    protected function resetRuntimeRefreshOutcome(): void
    {
        $this->opcacheResetMethod = null;
        $this->phpReloadRequired  = false;
    }

    /**
     * Make an installed update visible to the running site.
     *
     * @since 2.18.0
     *
     * @param  string  $type  `plugin` or `theme`, for the log.
     * @param  string  $slug  The extension that was updated.
     * @param  array<int, string>  $cachedArtifacts  From `cachedArtifacts()`, read before the update.
     */
    protected function refreshRuntimeAfterUpdate( string $type, string $slug, array $cachedArtifacts ): void
    {
        $this->refreshRuntime( "updating a {$type}", [$type => $slug], $cachedArtifacts );
    }

    /**
     * Make a rolled-back update's restored files visible to the running site
     * (#454).
     *
     * A failed update can leave PHP-FPM holding bytecode compiled from the
     * failed version's files, since OPcache is shared across the pool, and
     * the deactivate around it can have cleared the compiled caches. This
     * puts back the caches the site had and resets OPcache so the restored
     * version is what runs. Never throws, so the failure that caused the
     * rollback is the one reported.
     *
     * @since 2.18.0
     *
     * @param  string  $type  `plugin` or `theme`, for the log.
     * @param  string  $slug  The extension whose update was rolled back.
     * @param  array<int, string>  $cachedArtifacts  From `cachedArtifacts()`, read before the update.
     */
    protected function refreshRuntimeAfterRollback( string $type, string $slug, array $cachedArtifacts ): void
    {
        $this->refreshRuntime( "rolling back a {$type} update", [$type => $slug], $cachedArtifacts );
    }

    /**
     * Make a change visible to the running site.
     *
     * Runs once every file is final: rebuilds the compiled caches in
     * `$cachedArtifacts` in a fresh process (rebuilt rather than cleared, so
     * production keeps its cached routes and config), then resets the web
     * server's OPcache so PHP-FPM picks up both the changed files and the new
     * `routes-v7.php`, then signals the queue workers. Never throws: the
     * change has already been made.
     *
     * @since 2.18.0
     *
     * @param  string  $change  What changed, for the log, e.g. `updating a plugin`.
     * @param  array<string, mixed>  $context  Log context, e.g. the slug.
     * @param  array<int, string>  $cachedArtifacts  From `cachedArtifacts()`, read before the change.
     */
    protected function refreshRuntime( string $change, array $context, array $cachedArtifacts ): void
    {
        $this->rebuildCaches( $cachedArtifacts );
        $this->resetOpcacheAfterChange( $change, $context );
        $this->restartQueueWorkers( $change, $context );
    }

    /**
     * Reset the web server's OPcache after a change and record the outcome.
     *
     * When no reset reaches the web server, the operator is told to reload
     * PHP-FPM, through the log and {@see phpReloadRequired()}. A reset that
     * `cms.updates.opcache.web_reset` skipped is neither (#456).
     *
     * @since 2.18.0
     *
     * @param  string  $change  What changed, for the log.
     * @param  array<string, mixed>  $context  Log context, e.g. the slug.
     */
    protected function resetOpcacheAfterChange( string $change, array $context ): void
    {
        $context = $this->sanitizeLogContext( $context );

        try {
            $method = $this->resetOpcacheForWebServer();
        } catch ( Throwable $e ) {
            $method                                 = null;
            $this->webOpcacheResetFailures['error'] = $this->sanitizeForLog( $e->getMessage() );
        }

        $this->opcacheResetMethod = $method;
        $this->phpReloadRequired  = null === $method && ! $this->webOpcacheResetWasSkipped();

        if ( $this->webOpcacheResetWasSkipped() ) {
            return;
        }

        if ( null !== $method ) {
            if ( $this->runningUnderCli() ) {
                Log::info( "cms-framework: reset PHP-FPM's OPcache after {$change} ({$method}).", array_merge( $context, [
                    'php_sapi' => $this->phpSapi(),
                ] ) );
            }

            return;
        }

        Log::warning(
            "cms-framework: PHP-FPM's OPcache could not be reset after {$change}. If OPcache runs with `opcache.validate_timestamps=0`, reload PHP-FPM so it serves the files now on disk, or set `cms.updates.opcache.fpm_socket` or `cms.updates.php_reload_command`.",
            array_merge( $context, [
                'php_sapi' => $this->phpSapi(),
                'attempts' => $this->webOpcacheResetFailures,
            ] ),
        );
    }
}
