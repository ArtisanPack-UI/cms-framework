<?php

declare( strict_types=1 );

/**
 * RestartsQueueWorkers Trait
 *
 * Signals long-running queue workers to restart once the code they run has
 * changed on disk.
 *
 * @since      2.18.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trait for the `queue:restart` step the self-updater and the plugin and theme
 * managers share.
 *
 * A queue worker loads the framework, plugin and job classes once and keeps
 * them in memory, so after an update, install or activation it keeps running
 * the previous code until it is restarted (#450, #452). The using class also
 * supplies `sanitizeForLog()` ({@see ResetsWebOpcache}).
 *
 * @since 2.18.0
 */
trait RestartsQueueWorkers
{
    /**
     * Strip control characters from a value before it reaches a log record.
     *
     * @since 2.18.0
     */
    abstract protected function sanitizeForLog( string $value ): string;

    /**
     * Signal long-running queue workers to exit after their current job, so
     * their supervisor restarts them on the new code.
     *
     * `queue:restart` only writes a timestamp to the cache, so it runs in
     * this process. The worker running the change itself, such as the queued
     * self-update, exits once that job finishes. Best-effort: a failure is
     * logged, never thrown, since the change has already been applied.
     *
     * @since 2.18.0
     *
     * @param  string  $after  What changed, for the log, e.g. `updating a plugin`.
     * @param  array<string, mixed>  $context  Extra log context, e.g. the slug.
     */
    protected function restartQueueWorkers( string $after, array $context = [] ): void
    {
        try {
            Artisan::call( 'queue:restart' );
        } catch ( Throwable $e ) {
            Log::warning(
                "cms-framework: could not signal the queue workers to restart after {$after}. Run `php artisan queue:restart` on the host.",
                array_merge( $this->sanitizeLogContext( $context ), [
                    'error' => $this->sanitizeForLog( $e->getMessage() ),
                ] ),
            );
        }
    }

    /**
     * Strip control characters from every string in a log context.
     *
     * A slug can come straight from a route parameter, so it is treated as
     * untrusted like an archive entry name.
     *
     * @since 2.18.0
     *
     * @param  array<array-key, mixed>  $context  Log context.
     *
     * @return array<array-key, mixed> The context with its strings sanitized.
     */
    protected function sanitizeLogContext( array $context ): array
    {
        return array_map(
            fn ( mixed $value ): mixed => match ( true ) {
                is_string( $value ) => $this->sanitizeForLog( $value ),
                is_array( $value )  => $this->sanitizeLogContext( $value ),
                default             => $value,
            },
            $context,
        );
    }
}
