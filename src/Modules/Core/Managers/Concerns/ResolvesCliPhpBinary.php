<?php

declare( strict_types=1 );

/**
 * ResolvesCliPhpBinary Trait
 *
 * Locates a CLI PHP interpreter that a manager can hand a script to from any
 * SAPI, including PHP-FPM.
 *
 * @since      2.16.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns;

/**
 * Trait for resolving a CLI PHP binary to run subprocesses with.
 *
 * Extracted from ApplicationUpdateManager so PluginManager can run a fresh
 * `php artisan` process with the same interpreter resolution the self-updater
 * uses for Composer (#431).
 *
 * @since 2.16.0
 */
trait ResolvesCliPhpBinary
{
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
     * Resolve a CLI PHP binary suitable for executing a script such as
     * composer's PHAR or the host's `artisan`.
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
}
