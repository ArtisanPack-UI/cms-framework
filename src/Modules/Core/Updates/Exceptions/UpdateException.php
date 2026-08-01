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
     * @since 1.0.0
     */
    public static function composerInstallFailed( string $output ): self
    {
        return new self( "Composer install failed. Output:\n{$output}" );
    }

    /**
     * The on-disk `composer.json` and `composer.lock` disagree, so
     * `composer install` cannot succeed.
     *
     * Raised *before* composer is invoked, because composer's own diagnosis of
     * this state — "This usually happens when composer files are incorrectly
     * merged or the composer.json file is manually edited" — sends the operator
     * hunting for a merge conflict or a hand-edit that never happened. In the
     * updater's case the cause is nearly always a release that shipped no
     * `composer.lock`, or a host whose `exclude_from_update` override still
     * excludes it.
     *
     * @since 2.7.1
     *
     * @param  string  $reason  Which half of the pair is at fault.
     */
    public static function composerFilesOutOfSync( string $reason ): self
    {
        return new self(
            "composer.json and composer.lock are out of sync after extraction: {$reason} "
            . '`composer install` only ever reads a lock file — it never writes one — so it cannot '
            . 'reconcile this. Confirm the release archive ships a committed `composer.lock` that '
            . 'matches its `composer.json`, and that `cms.updates.exclude_from_update` does not list '
            . '`composer.lock` (it is not in the framework default). This is not a merge conflict or '
            . 'a hand-edited `composer.json`, whatever composer would have told you.',
        );
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
     * Permission denied.
     *
     * @since 1.0.0
     */
    public static function permissionDenied(): self
    {
        return new self( 'You do not have permission to perform core updates.' );
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
