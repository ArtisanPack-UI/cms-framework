<?php

declare( strict_types=1 );

/**
 * RefreshesRuntimeAfterExtensionUpdate Trait
 *
 * Makes a plugin or theme update visible to the running site: the compiled
 * caches, PHP-FPM's OPcache and the queue workers.
 *
 * @since      2.18.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trait for the post-update step the plugin and theme update managers share.
 *
 * Extracting an update only changes files. On a production host
 * (`opcache.validate_timestamps=0`, cached routes, a queue worker) PHP-FPM
 * keeps serving the old bytecode, the route cache keeps the old routes, and
 * workers keep running the old job classes, so the new version 500s until
 * someone reloads PHP-FPM, runs `optimize` and reloads again (#450). This
 * does what the self-updater does after an update (#425, #429): rebuild the
 * caches the site had, reset the web server's OPcache, and signal the queue
 * workers to restart.
 *
 * @since 2.18.0
 */
trait RefreshesRuntimeAfterExtensionUpdate
{
    use RebuildsCompiledCaches;
    use ResetsWebOpcache;
    use ResolvesCliPhpBinary;

    /**
     * How the last update reset the web server's OPcache: `web-request`,
     * `fastcgi`, `loopback` or `command`; null before an update or when no
     * reset reached the web server.
     *
     * @since 2.18.0
     */
    protected ?string $opcacheResetMethod = null;

    /**
     * Whether PHP-FPM needs a manual reload to serve the last update.
     *
     * @since 2.18.0
     */
    protected bool $phpReloadRequired = false;

    /**
     * How the last update this instance installed reset the web server's
     * OPcache.
     *
     * @since 2.18.0
     *
     * @return string|null The mechanism, or null when none reached the web
     *                     server (or no update has run).
     */
    public function opcacheResetMethod(): ?string
    {
        return $this->opcacheResetMethod;
    }

    /**
     * Whether the last update this instance installed left PHP-FPM needing a
     * reload before it serves the new version.
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
     * Forget the outcome of the previous update, before a new one starts.
     *
     * The managers are singletons, so a long-lived worker running several
     * updates would otherwise report one update's outcome for the next.
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
     * Runs once every file is final: rebuilds the compiled caches in
     * `$cachedArtifacts` in a fresh process (rebuilt rather than cleared, so
     * production keeps its cached routes and config), then resets the web
     * server's OPcache so PHP-FPM picks up both the new files and the new
     * `routes-v7.php`, then signals the queue workers. Never throws: the
     * update has already succeeded.
     *
     * @since 2.18.0
     *
     * @param  string  $type  `plugin` or `theme`, for the log.
     * @param  string  $slug  The extension that was updated.
     * @param  array<int, string>  $cachedArtifacts  From `cachedArtifacts()`, read before the update.
     */
    protected function refreshRuntimeAfterUpdate( string $type, string $slug, array $cachedArtifacts ): void
    {
        $this->rebuildCaches( $cachedArtifacts );
        $this->resetOpcacheAfterUpdate( $type, $slug );
        $this->restartQueueWorkers( $type, $slug );
    }

    /**
     * Reset the web server's OPcache after an update and record the outcome.
     *
     * When no reset reaches the web server, the operator is told to reload
     * PHP-FPM, through the log and {@see phpReloadRequired()}.
     *
     * @since 2.18.0
     *
     * @param  string  $type  `plugin` or `theme`.
     * @param  string  $slug  The extension that was updated.
     */
    protected function resetOpcacheAfterUpdate( string $type, string $slug ): void
    {
        try {
            $method = $this->resetOpcacheForWebServer();
        } catch ( Throwable $e ) {
            $method                                 = null;
            $this->webOpcacheResetFailures['error'] = $this->sanitizeForLog( $e->getMessage() );
        }

        $this->opcacheResetMethod = $method;
        $this->phpReloadRequired  = null === $method;

        if ( null !== $method ) {
            if ( $this->runningUnderCli() ) {
                Log::info( "cms-framework: reset PHP-FPM's OPcache after updating a {$type} ({$method}).", [
                    $type      => $slug,
                    'php_sapi' => $this->phpSapi(),
                ] );
            }

            return;
        }

        Log::warning(
            "cms-framework: PHP-FPM's OPcache could not be reset after updating a {$type}. If OPcache runs with `opcache.validate_timestamps=0`, reload PHP-FPM so it serves the new version, or set `cms.updates.opcache.fpm_socket` or `cms.updates.php_reload_command`.",
            [
                $type      => $slug,
                'php_sapi' => $this->phpSapi(),
                'attempts' => $this->webOpcacheResetFailures,
            ],
        );
    }

    /**
     * Signal long-running queue workers to exit after their current job, so
     * their supervisor restarts them on the new version's classes.
     *
     * `queue:restart` only writes a timestamp to the cache, so it runs in
     * this process. Best-effort: a failure is logged.
     *
     * @since 2.18.0
     *
     * @param  string  $type  `plugin` or `theme`.
     * @param  string  $slug  The extension that was updated.
     */
    protected function restartQueueWorkers( string $type, string $slug ): void
    {
        try {
            Artisan::call( 'queue:restart' );
        } catch ( Throwable $e ) {
            Log::warning(
                "cms-framework: could not signal the queue workers to restart after updating a {$type}. Run `php artisan queue:restart` on the host.",
                [
                    $type   => $slug,
                    'error' => $this->sanitizeForLog( $e->getMessage() ),
                ],
            );
        }
    }
}
