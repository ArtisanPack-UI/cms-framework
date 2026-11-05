<?php

declare( strict_types=1 );

/**
 * RebuildsCompiledCaches Trait
 *
 * Puts back the compiled config, route and event caches a site had in place
 * before its code changed on disk.
 *
 * @since      2.18.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Trait for rebuilding Laravel's compiled caches in a fresh process.
 *
 * Extracted from ApplicationUpdateManager so the plugin and theme update
 * managers rebuild the caches the self-updater does (#450). The using class
 * also supplies `resolvePhpBinary()` ({@see ResolvesCliPhpBinary}) and
 * `sanitizeForLog()` ({@see ResetsWebOpcache}).
 *
 * @since 2.18.0
 */
trait RebuildsCompiledCaches
{
    /**
     * Resolve a CLI PHP binary to run `artisan` with.
     *
     * @since 2.18.0
     */
    abstract protected function resolvePhpBinary(): string;

    /**
     * Strip control characters from a value before it reaches a log record.
     *
     * @since 2.18.0
     */
    abstract protected function sanitizeForLog( string $value ): string;

    /**
     * The `artisan` commands that rebuild the compiled caches the site has in
     * place right now, in the order they should run.
     *
     * Read before anything clears them, so the caller puts back exactly what
     * the operator had cached, and nothing they had not. The files are
     * checked directly because `configurationIsCached()` and its siblings
     * memoise their answer for the life of the process, which on a
     * long-running queue worker can predate the operator's last `optimize`.
     *
     * @since 2.15.6
     * @since 2.18.0 Moved from ApplicationUpdateManager.
     *
     * @return array<int, string> Cache commands, e.g. `config:cache`.
     */
    protected function cachedArtifacts(): array
    {
        $app      = app();
        $commands = [];

        if ( is_file( $app->getCachedConfigPath() ) ) {
            $commands[] = 'config:cache';
        }

        if ( is_file( $app->getCachedRoutesPath() ) ) {
            $commands[] = 'route:cache';
        }

        if ( is_file( $app->getCachedEventsPath() ) ) {
            $commands[] = 'event:cache';
        }

        return $commands;
    }

    /**
     * Rebuild the compiled caches that were in place before the update (#425).
     *
     * Each command runs in a fresh PHP process rather than through
     * `Artisan::call()`. This process still holds the pre-update classes in
     * memory, so a cache compiled here could describe providers and routes
     * that no longer match the code on disk. A route cache naming a middleware
     * alias the stale provider never registered is the 500 #425 reports.
     *
     * Best-effort by design: the update is already applied, and a missing
     * cache only costs performance until the operator rebuilds it, so a
     * failure is logged rather than thrown.
     *
     * @since 2.15.6
     * @since 2.18.0 Moved from ApplicationUpdateManager.
     *
     * @param  array<int, string>  $commands  Cache commands from `cachedArtifacts()`.
     */
    protected function rebuildCaches( array $commands ): void
    {
        foreach ( $commands as $command ) {
            try {
                $result = Process::timeout( 120 )
                    ->path( base_path() )
                    ->run( [$this->resolvePhpBinary(), 'artisan', $command] );

                if ( $result->successful() ) {
                    continue;
                }

                $error = '' !== trim( $result->errorOutput() ) ? $result->errorOutput() : $result->output();
            } catch ( Throwable $e ) {
                $error = $e->getMessage();
            }

            Log::warning(
                "cms-framework: could not rebuild a compiled cache after the update. Run `php artisan {$command}` on the host.",
                [
                    'command' => $command,
                    'error'   => $this->sanitizeForLog( trim( $error ) ),
                ],
            );
        }
    }
}
