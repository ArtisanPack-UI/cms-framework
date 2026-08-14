<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Update Exception
 *
 * Exception thrown during update operations.
 *
 * @since 1.0.0
 */
class UpdateException extends CMSFrameworkException
{
    /**
     * Version check failed.
     *
     * @since 1.0.0
     */
    public static function versionCheckFailed( string $reason ): self
    {
        return new self( "Failed to check for updates: {$reason}" );
    }

    /**
     * Update URL not configured.
     *
     * @since 1.0.0
     */
    public static function noUpdateUrlConfigured(): self
    {
        return new self( 'Update URL not configured. Please set UPDATE_SOURCE_URL in .env' );
    }

    /**
     * Invalid JSON response from update source.
     *
     * @since 1.0.0
     */
    public static function invalidJsonResponse( string $url ): self
    {
        return new self( "Invalid JSON response from update URL: {$url}" );
    }

    /**
     * Required field missing from update JSON.
     *
     * @since 1.0.0
     */
    public static function missingRequiredField( string $field ): self
    {
        return new self( "Update JSON missing required field: {$field}" );
    }

    /**
     * Backup creation failed.
     *
     * @since 1.0.0
     */
    public static function backupFailed( string $path ): self
    {
        return new self( "Failed to create backup at: {$path}" );
    }

    /**
     * Download failed.
     *
     * @since 1.0.0
     */
    public static function downloadFailed( string $url ): self
    {
        return new self( "Failed to download update from: {$url}" );
    }

    /**
     * Checksum verification failed.
     *
     * @since 1.0.0
     */
    public static function checksumMismatch( string $expected, string $actual ): self
    {
        return new self( "Checksum mismatch. Expected: {$expected}, Got: {$actual}" );
    }

    /**
     * Update source did not advertise a SHA-256 checksum and the host has not
     * opted in to accepting unverified updates.
     *
     * @since 2.5.4
     */
    public static function checksumRequired( string $targetVersion ): self
    {
        return new self(
            "Refusing to install update {$targetVersion}: the update source did not advertise a SHA-256 checksum. "
            . 'Set `cms.updates.allow_unverified_updates` to true to opt in to unverified updates on trusted networks.',
        );
    }

    /**
     * ZIP extraction failed.
     *
     * @since 1.0.0
     */
    public static function extractionFailed( string $zipPath ): self
    {
        return new self( "Failed to extract ZIP archive: {$zipPath}" );
    }

    /**
     * A single entry in the update ZIP could not be written to disk.
     *
     * Distinct from {@see self::extractionFailed()} because the archive itself
     * opened successfully — the failure is per-entry (fopen/fread on the target
     * file), and the streaming loop must surface it so that `performUpdate()`
     * can roll back to the pre-update snapshot instead of leaving a partial
     * install on disk.
     *
     * @since 2.5.4
     */
    public static function extractionEntryFailed( string $entry, string $reason ): self
    {
        return new self( "Failed to extract update entry '{$entry}': {$reason}" );
    }

    /**
     * Composer install failed.
     *
     * When the pre-flight sync check detected that `composer.json` and
     * `composer.lock` had diverged, the message appends the framework's own
     * diagnosis. `composer install` installs from the lock despite a stale
     * `content-hash` and hard-fails only when the lock cannot satisfy
     * `composer.json` — a required package missing from the lock, or a
     * constraint it violates. At that point composer's own "incorrectly merged
     * or manually edited" guess sends the operator hunting for a merge conflict
     * that never happened, when the real cause in the updater is nearly always a
     * release that shipped no `composer.lock`, or a host whose
     * `exclude_from_update` override still excludes it. The divergence is
     * reported here, wrapping composer's *own* failure, rather than pre-empting
     * an install composer would have completed.
     *
     * @since 1.0.0
     *
     * @param  string  $output                 Composer's own error output.
     * @param  bool    $composerFilesDiverged  Whether the pre-flight check found the lock out of sync.
     */
    public static function composerInstallFailed( string $output, bool $composerFilesDiverged = false ): self
    {
        $message = "Composer install failed. Output:\n{$output}";

        if ( $composerFilesDiverged ) {
            $message .= "\n\nBefore this run, composer.json and composer.lock were detected out of sync: "
                . 'the lock records a different set of dependency constraints than composer.json declares. '
                . 'composer installs from the lock despite that, so this failure most likely means the lock '
                . 'cannot satisfy composer.json — a required package missing from the lock, or a constraint it '
                . 'violates. Confirm the release archive ships a committed `composer.lock` that matches its '
                . '`composer.json`, and that `cms.updates.exclude_from_update` does not list `composer.lock` '
                . '(it is not in the framework default). This is not a merge conflict or a hand-edited '
                . '`composer.json`, whatever composer reported above.';
        }

        return new self( $message );
    }

    /**
     * Composer binary could not be located on the host.
     *
     * Accepts either a flat list of searched paths (legacy 2.5.3 signature)
     * or a list of per-candidate diagnostic results shaped as
     * `['path' => string, 'is_file' => bool, 'is_executable' => bool]`. When
     * diagnostic results are supplied, the rendered message surfaces the
     * `is_file()` / `is_executable()` outcome for each path so operators can
     * distinguish a wrong-path failure from a PHP-FPM sandboxed-stat failure
     * (macOS Herd Pro, chrooted FPM pools, restrictive `open_basedir`).
     *
     * @since 2.5.3
     *
     * @param  array<int, array{path: string, is_file: bool, is_executable: bool}>|array<int, string>  $searched
     *         Either legacy string paths, or per-candidate diagnostic entries.
     */
    public static function composerBinaryNotFound( array $searched ): self
    {
        if ( empty( $searched ) ) {
            $rendered = '(none)';
        } else {
            $allDiagnostic = true;
            foreach ( $searched as $entry ) {
                if ( ! is_array( $entry ) ) {
                    $allDiagnostic = false;
                    break;
                }
            }

            if ( $allDiagnostic ) {
                $rendered = implode( ', ', array_map(
                    static function ( array $entry ): string {
                        $path = $entry['path'] ?? '(unknown)';
                        $file = ( $entry['is_file'] ?? false ) ? 'true' : 'false';
                        $exec = ( $entry['is_file'] ?? false )
                            ? ( ( $entry['is_executable'] ?? false ) ? 'true' : 'false' )
                            : 'n/a';

                        return "{$path} (is_file={$file}, is_executable={$exec})";
                    },
                    $searched,
                ) );
            } else {
                $rendered = implode( ', ', array_map(
                    static fn ( $entry ): string => is_array( $entry ) ? (string) ( $entry['path'] ?? '(unknown)' ) : (string) $entry,
                    $searched,
                ) );
            }
        }

        $message = 'Composer binary could not be located on the host. '
            . "Searched: {$rendered}. "
            . 'If `is_file` reports `false` for a path that exists at the OS level, the PHP process '
            . 'likely cannot stat it (macOS Herd Pro sandboxes `/opt/homebrew/*` from PHP-FPM; chrooted '
            . 'FPM pools and restrictive `open_basedir` behave the same way). '
            . 'Set `COMPOSER_BINARY` in your Laravel `.env` file (populates '
            . '`cms.updates.composer_binary`) or export it at the OS level '
            . '(PHP-FPM pool env, shell before starting FPM), or override '
            . '`cms.updates.composer_install_command` with an absolute path to composer.';

        return new self( $message );
    }

    /**
     * An explicitly configured composer binary failed its `--version` probe
     * and is not visible to PHP on disk either.
     *
     * Distinct from `composerBinaryNotFound()` (auto-discovery exhausted its
     * candidate list) and from `composerVerificationFailed()` (the probe
     * failed against a binary PHP *can* see, so the interpreter is a live
     * suspect). Raised only after the probe has already failed, never as a
     * pre-flight gate: a path PHP cannot `stat()` may still be perfectly
     * reachable by the shelled-out child under PHP-FPM sandboxing, which is
     * the case `COMPOSER_BINARY` exists to work around.
     *
     * Once the probe has failed too, though, the path is overwhelmingly just
     * wrong — and saying so beats `composerVerificationFailed()`'s "located
     * but could not be executed" plus its trailing `CMS_PHP_BINARY` hint,
     * which points the operator at the PHP interpreter when the composer path
     * is the sole fault.
     *
     * @since 2.7.1
     *
     * @param  string  $binary  The configured path that could not be reached.
     */
    public static function configuredComposerBinaryMissing( string $binary ): self
    {
        return new self(
            "The configured composer binary could not be found at: {$binary}. "
            . 'It failed a `--version` probe and PHP cannot see a file there either. '
            . 'This path came from `COMPOSER_BINARY` in your `.env` file, an OS-level '
            . '`COMPOSER_BINARY` export, or `cms.updates.composer_binary` — not from '
            . 'auto-discovery, which only ever returns a path it has already verified. '
            . 'Correct the path or unset it to fall back to auto-discovery. '
            . 'On macOS, run `which composer` in a shell to find the real one; Laravel '
            . 'Herd bundles it at `~/Library/Application Support/Herd/bin/composer`, '
            . 'which auto-discovery already checks. Quote the value in `.env` if the '
            . 'path contains spaces.',
        );
    }

    /**
     * Composer binary was located but `--version` execution failed. Surfaces
     * the resolved binary, the PHP interpreter used to invoke it, the exit
     * code, and any captured output so operators can distinguish an
     * unreachable-binary failure from an unusable-interpreter one (e.g. the
     * FPM daemon binary being handed a PHAR).
     *
     * @since 2.5.4
     */
    public static function composerVerificationFailed(
        string $composerBinary,
        string $phpBinary,
        int $exitCode,
        string $detail,
    ): self {
        $detail  = '' === $detail ? '(no output captured)' : $detail;
        $message = 'Composer binary was located but could not be executed. '
            . "Ran: {$phpBinary} {$composerBinary} --version. "
            . "Exit code: {$exitCode}. Output: {$detail}. "
            . 'If the PHP path points at an FPM/CGI SAPI binary, set the '
            . '`CMS_PHP_BINARY` environment variable to an absolute path to a '
            . 'CLI PHP binary.';

        return new self( $message );
    }

    /**
     * Rollback failed after an initial update failure. Preserves the original
     * error message so the operator can see *why* the update failed alongside
     * the rollback failure.
     *
     * @since 2.5.3
     */
    public static function rollbackAfterFailure( string $originalError, string $rollbackError ): self
    {
        return new self(
            "Rollback failed: {$rollbackError}. Original update error: {$originalError}. Manual intervention required.",
        );
    }

    /**
     * Database migration failed.
     *
     * @since 1.0.0
     */
    public static function migrationFailed( string $output ): self
    {
        return new self( "Migration failed. Output:\n{$output}" );
    }

    /**
     * Rollback failed.
     *
     * @since 1.0.0
     */
    public static function rollbackFailed( string $reason ): self
    {
        return new self( "Rollback failed: {$reason}. Manual intervention required." );
    }

    /**
     * No update available.
     *
     * @since 1.0.0
     */
    public static function noUpdateAvailable(): self
    {
        return new self( 'No update available. Already running the latest version.' );
    }

    /**
     * Another update is already running on this host.
     *
     * @since 2.7.1
     *
     * @param  int|null  $pid  PID of the running update, when known.
     */
    public static function updateAlreadyRunning( ?int $pid = null ): self
    {
        return new self( sprintf(
            'An application update is already running%s. Concurrent updates extract over each other and produce a tree that '
            . 'no rollback can repair, because the second run snapshots the first run\'s half-applied state. '
            . 'Wait for it to finish, or run `php artisan update:status` to see where it got to.',
            null === $pid ? '' : " ( PID {$pid} )",
        ) );
    }

    /**
     * An update is already sitting on the queue waiting for a worker.
     *
     * @since 2.8.0
     *
     * @param  string|null  $queuedAt  ISO-8601 timestamp the run was queued at, when known.
     */
    public static function updateAlreadyQueued( ?string $queuedAt = null ): self
    {
        return new self( sprintf(
            'An application update is already queued%s and has not been picked up yet. Dispatching a second one would put two '
            . 'updates through the same application tree. Run `php artisan update:status` to see whether it is still waiting, '
            . 'and check that a queue worker is running.',
            null === $queuedAt ? '' : " ( since {$queuedAt} )",
        ) );
    }

    /**
     * The configured queue connection cannot run a queued update.
     *
     * The `sync` driver is the dangerous one: dispatching to it executes the
     * job inline in the dispatching process, which is exactly the multi-minute
     * blocking HTTP request that queueing exists to avoid — the feature would
     * look like it worked while changing nothing.
     *
     * @since 2.8.0
     *
     * @param  string|null  $connection  Queue connection name.
     * @param  string|null  $driver  Resolved driver, or null when the connection is not configured.
     */
    public static function updateQueueUnusable( ?string $connection, ?string $driver ): self
    {
        $name = null === $connection || '' === $connection ? '(none)' : $connection;

        if ( 'sync' === $driver ) {
            return new self( sprintf(
                'The queue connection "%s" uses the sync driver, which runs the job inline in the dispatching process — a queued '
                . 'update would block the caller for the whole update exactly as a direct performUpdate() call does. Configure a '
                . 'real queue connection and run a worker, call performUpdate() directly if blocking is genuinely what you want, '
                . 'or set cms.updates.queue.allow_sync=true to opt in to the inline behavior deliberately.',
                $name,
            ) );
        }

        if ( 'null' === $driver ) {
            return new self( sprintf(
                'The queue connection "%s" uses the null driver, which discards every job it is given. A queued update would be '
                . 'thrown away silently and the site would never be updated. Configure a real queue connection and run a worker.',
                $name,
            ) );
        }

        return new self( sprintf(
            'The queue connection "%s" is not configured, so a queued update cannot be dispatched. Set '
            . 'cms.updates.queue.connection to a connection defined in config/queue.php, or leave it null to use the '
            . 'application default.',
            $name,
        ) );
    }

    /**
     * The queue connection would redeliver the update before it can finish.
     *
     * `retry_after` is how long the queue waits before deciding a reserved job
     * has died and making it visible again. Laravel's shipped default is 90
     * seconds; a real update runs for minutes. A redelivered update is not a
     * second attempt at the work — `$tries = 1` means the duplicate is failed
     * without `handle()` ever running — but it does arrive in `failed()` while
     * the original is still mid-flight.
     *
     * @since 2.8.0
     *
     * @param  string|null  $connection  Queue connection name.
     * @param  int  $retryAfter  Configured retry_after, in seconds.
     * @param  int  $timeout  Seconds this update is allowed to run.
     */
    public static function updateQueueRetryTooShort( ?string $connection, int $retryAfter, int $timeout ): self
    {
        $name = null === $connection || '' === $connection ? '(none)' : $connection;

        return new self( sprintf(
            'The queue connection "%s" has retry_after=%d, but a queued update is allowed to run for %d seconds. The queue would '
            . 'hand the same update to a second worker %d seconds in, while the first is still extracting over the application '
            . 'tree. Raise retry_after above %d in config/queue.php, or lower cms.updates.queue.timeout below %d.',
            $name,
            $retryAfter,
            $timeout,
            $retryAfter,
            $timeout,
            $retryAfter,
        ) );
    }

    /**
     * A release archive was about to be fetched over an insecure transport.
     *
     * @since 2.7.1
     *
     * @param  string  $url  The offending URL.
     */
    public static function insecureDownloadUrl( string $url ): self
    {
        return new self( sprintf(
            'Refusing to download an update over an insecure transport: %s. The update pipeline overwrites PHP files and then '
            . 'runs composer install, so a plaintext download is a remote-code-execution channel for anyone on the path. '
            . 'Use https, or set cms.updates.allow_insecure_transport=true if this is a trusted air-gapped mirror.',
            $url,
        ) );
    }

    /**
     * A downgrade was requested without an explicit opt-in.
     *
     * @since 2.7.1
     *
     * @param  string  $target  Requested version.
     * @param  string  $current  Currently installed version.
     */
    public static function downgradeNotAllowed( string $target, string $current ): self
    {
        return new self( sprintf(
            'Refusing to install %s over %s: it is not newer. Installing an older release re-introduces every '
            . 'vulnerability fixed since it shipped, and migrations are not reversed. Pass --allow-downgrade if that is genuinely intended.',
            $target,
            $current,
        ) );
    }

    /**
     * Maintenance mode operation failed.
     *
     * @since 1.0.0
     */
    public static function maintenanceModeFailure( string $action ): self
    {
        return new self( "Failed to {$action} maintenance mode." );
    }

    /**
     * PHP version incompatible.
     *
     * @since 1.0.0
     */
    public static function incompatiblePhpVersion( string $required, string $current ): self
    {
        return new self( "Update requires PHP {$required}, but you have {$current}" );
    }

    /**
     * Framework version incompatible.
     *
     * @since 1.0.0
     */
    public static function incompatibleFrameworkVersion( string $required, string $current ): self
    {
        return new self( "Update requires cms-framework {$required}, but you have {$current}");
    }
}
