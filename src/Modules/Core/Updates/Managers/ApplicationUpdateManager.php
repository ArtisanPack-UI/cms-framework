<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateRunStatus;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateStep;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\UpdateStateStore;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCheckerFactory;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
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
     * @since 1.0.0
     *
     * @param  string|null  $version  Version to update to (null = latest)
     *
     * @throws UpdateException
     *
     * @return bool True if update successful
     */
    public function performUpdate( ?string $version = null ): bool
    {
        $this->raiseExecutionLimits();

        $updateInfo = $this->checkForUpdate();

        if ( ! $updateInfo->hasUpdate() ) {
            throw UpdateException::noUpdateAvailable();
        }

        // Use specified version or latest
        $targetVersion = $version ?? $updateInfo->latestVersion;

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
     * @since 2.7.1
     */
    public function clearUpdateState(): void
    {
        $this->state()->clear();
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

        $fatal = $this->lastFatalError();

        Log::critical(
            'cms-framework: the update process terminated before it finished; the site was left in maintenance mode.',
            [
                'step'       => $this->currentStep?->value,
                'step_label' => $this->currentStep?->label(),
                'php_sapi'   => PHP_SAPI,
                'error'      => $fatal,
                'state_file' => $this->state()->path(),
                'hint'       => 'Run `php artisan update:status` to see how far the update got and what remains.',
            ],
        );

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

        if ( ! config( 'cms.updates.lift_maintenance_on_interrupt', true ) ) {
            Log::critical(
                'cms-framework: cms.updates.lift_maintenance_on_interrupt is disabled, so the site is being left in maintenance mode. Run `php artisan up` once you have verified the install.',
            );

            return;
        }

        try {
            $this->disableMaintenanceMode();

            Log::critical( 'cms-framework: maintenance mode was lifted by the update shutdown guard. The installation may be half-applied — run `php artisan update:status` before trusting it.' );
        } catch ( Throwable $e ) {
            Log::critical( 'cms-framework: the update shutdown guard could not lift maintenance mode via `artisan up`.', [
                'exception' => $e->getMessage(),
            ] );

            $this->forceLiftMaintenanceMode();
        }
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

        $zip->extractTo( base_path() );
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

        $this->checker = UpdateCheckerFactory::buildUpdateChecker(
            url: $updateUrl,
            type: UpdateType::Application,
            slug: 'digital-shopfront-cms',
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
            File::makeDirectory( $backupDir, 0755, true );
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
            if ( str_starts_with( $path, $exclude ) ) {
                return true;
            }

            if ( str_contains( $exclude, '*' ) && fnmatch( $exclude, $path ) ) {
                return true;
            }
        }

        return false;
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
        $actualHash = hash_file( 'sha256', $zipPath );

        if ( $actualHash !== $expectedHash ) {
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

        if ( $updateInfo->sha256 ) {
            $this->verifyChecksum( $zipPath, $updateInfo->sha256 );

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

        // Detect common root prefix by scanning all entry names
        $commonPrefix = $this->detectCommonRootPrefix( $zip, $excludePaths );

        // Extract files (except excluded paths), stripping common prefix
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $filename = $zip->getNameIndex( $i );

            // Strip common prefix if detected
            $targetPath = $commonPrefix ? substr( $filename, strlen( $commonPrefix ) ) : $filename;

            // Skip excluded paths (check both original and stripped paths)
            if ( $this->isPathExcluded( $filename, $excludePaths ) || $this->isPathExcluded( $targetPath, $excludePaths ) ) {
                continue;
            }

            // Skip empty paths (directories become empty after prefix stripping)
            if ( empty( $targetPath ) ) {
                continue;
            }

            // Get file info
            $stat = $zip->statIndex( $i );
            if ( false === $stat ) {
                continue;
            }

            // Reject archive entries whose path would escape the extraction root
            // (Zip Slip). Because extraction here bypasses ZipArchive::extractTo(),
            // PHP's own traversal mitigations do not apply, so every entry must be
            // validated against the base directory before any write.
            $normalizedTarget = str_replace( '\\', '/', $targetPath );
            if (
                str_starts_with( $normalizedTarget, '/' )
                || '..' === $normalizedTarget
                || str_starts_with( $normalizedTarget, '../' )
                || str_contains( $normalizedTarget, '/../' )
                || str_ends_with( $normalizedTarget, '/..' )
            ) {
                Log::warning( 'Skipping update ZIP entry with unsafe path', [
                    'entry' => $filename,
                ] );

                continue;
            }

            $fullTargetPath = $extractPath . DIRECTORY_SEPARATOR . $targetPath;

            // Handle directories
            if ( str_ends_with( $filename, '/' ) ) {
                if ( ! File::exists( $fullTargetPath ) ) {
                    File::makeDirectory( $fullTargetPath, 0755, true );
                }

                if ( ! $this->isPathWithinExtractRoot( $fullTargetPath, $extractPath ) ) {
                    Log::warning( 'Skipping update ZIP entry that resolved outside extract root', [
                        'entry' => $filename,
                    ] );

                    continue;
                }

                continue;
            }

            // Handle files - ensure parent directory exists
            $targetDir = dirname( $fullTargetPath );
            if ( ! File::exists( $targetDir ) ) {
                File::makeDirectory( $targetDir, 0755, true );
            }

            // Defense-in-depth: after the parent exists, resolve it and confirm
            // it still sits under the extract root before opening the write stream.
            if ( ! $this->isPathWithinExtractRoot( $targetDir, $extractPath ) ) {
                Log::warning( 'Skipping update ZIP entry that resolved outside extract root', [
                    'entry' => $filename,
                ] );

                continue;
            }

            // Stream the entry to disk rather than materializing it as a PHP
            // string via `getFromIndex()`. On a 128M host, a single large file
            // inside the release (bundled JS/CSS, images) can otherwise OOM in
            // the middle of extraction.
            $entryStream = $zip->getStream( $filename );
            if ( false === $entryStream ) {
                Log::error( 'Failed to open ZIP entry stream during update extraction', [
                    'entry' => $filename,
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

            try {
                while ( ! feof( $entryStream ) ) {
                    $chunk = fread( $entryStream, 1024 * 1024 );
                    if ( false === $chunk ) {
                        Log::error( 'Read failure while streaming update entry', [
                            'entry'  => $filename,
                            'target' => $fullTargetPath,
                        ] );

                        throw UpdateException::extractionEntryFailed( $filename, 'read failure while streaming entry from archive' );
                    }

                    if ( '' === $chunk ) {
                        break;
                    }

                    fwrite( $out, $chunk );
                }
            } finally {
                fclose( $entryStream );
                fclose( $out );
            }

            // Preserve file permissions if available
            if ( isset( $stat['external_attributes'] ) ) {
                $permissions = ( $stat['external_attributes'] >> 16 ) & 0777;
                if ( $permissions > 0 ) {
                    @chmod( $fullTargetPath, $permissions );
                }
            }
        }

        $zip->close();
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

            // Skip excluded paths
            if ( $this->isPathExcluded( $filename, $excludePaths ) ) {
                continue;
            }

            // Get first path segment
            $parts = explode( '/', $filename );
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

        $this->verifyComposerFilesInSync( $command );

        $timeout = config( 'cms.updates.composer_timeout', 600 );

        $result = Process::timeout( $timeout )
            ->path( base_path() )
            ->run( $command );

        if ( ! $result->successful() ) {
            throw UpdateException::composerInstallFailed( $result->errorOutput() );
        }
    }

    /**
     * Verify the on-disk `composer.json` and `composer.lock` agree before
     * handing them to composer.
     *
     * `composer install` only ever *reads* a lock file — it never writes one —
     * and aborts when the lock disagrees with `composer.json`. Its own
     * diagnosis of that state ("This usually happens when composer files are
     * incorrectly merged or the composer.json file is manually edited") sends
     * the operator hunting for a merge conflict or a hand-edit that never
     * happened, when the real cause is a release that shipped no lock or a host
     * whose `exclude_from_update` override still excludes it.
     *
     * Deliberately fails *open*. A missing `composer.json`, a missing or
     * unparseable `composer.lock`, or a lock carrying no `content-hash` is left
     * for composer to adjudicate — the framework only aborts on a positively
     * detected mismatch, so a false alarm can never block an update that would
     * otherwise have installed cleanly.
     *
     * @since 2.7.1
     *
     * @param  string  $command  The composer command about to be run.
     *
     * @throws UpdateException When the two files positively disagree.
     */
    protected function verifyComposerFilesInSync( string $command ): void
    {
        if ( ! config( 'cms.updates.verify_composer_lock_sync', true ) ) {
            return;
        }

        // A host that has overridden `composer_install_command` to run
        // `composer update` has deliberately opted into re-resolving the tree,
        // and a lock that disagrees with composer.json is precisely what
        // `update` exists to reconcile. Only `install` needs the guard.
        if ( ! preg_match( '/(?:^|\s)install(?:\s|$)/', $command ) ) {
            return;
        }

        $jsonPath = base_path( 'composer.json' );
        $lockPath = base_path( 'composer.lock' );

        if ( ! is_file( $jsonPath ) ) {
            return;
        }

        if ( ! is_file( $lockPath ) ) {
            Log::warning( 'Update left no composer.lock on disk; composer will resolve dependencies itself.', [
                'lock_path' => $lockPath,
            ] );

            return;
        }

        $expectedHash = $this->composerContentHash( $jsonPath );
        if ( null === $expectedHash ) {
            return;
        }

        $lock = json_decode( (string) @file_get_contents( $lockPath ), true );
        if ( ! is_array( $lock ) || ! is_string( $lock['content-hash'] ?? null ) ) {
            return;
        }

        if ( $lock['content-hash'] === $expectedHash ) {
            return;
        }

        Log::error( 'composer.json and composer.lock are out of sync after extraction.', [
            'expected_content_hash' => $expectedHash,
            'lock_content_hash'     => $lock['content-hash'],
        ] );

        throw UpdateException::composerFilesOutOfSync(
            'the lock file records a different set of dependency constraints than composer.json declares.',
        );
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
     * @throws UpdateException When the binary is resolvable but `--version`
     *                         cannot be executed.
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
        $home = getenv( 'HOME' );

        $candidates = [
            '/usr/local/bin/composer',
            '/opt/homebrew/bin/composer',
        ];

        if ( is_string( $home ) && '' !== $home ) {
            $candidates[] = $home . '/.composer/vendor/bin/composer';
            $candidates[] = $home . '/.config/composer/vendor/bin/composer';
        }

        $candidates[] = '/usr/bin/composer';

        return $candidates;
    }

    /**
     * Build the composer command line from a binary path. Invokes composer via
     * a resolved CLI PHP interpreter so PHP-FPM's `PATH` never needs to
     * resolve `php` for composer's shebang, and so we never hand the PHAR to
     * an FPM daemon binary (which prints usage and exits 64).
     *
     * @since 2.5.3
     */
    protected function buildComposerCommand( string $binary ): string
    {
        return escapeshellarg( $this->resolvePhpBinary() ) . ' ' . escapeshellarg( $binary ) . ' ' . self::DEFAULT_COMPOSER_INSTALL_ARGS;
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
        $home = getenv( 'HOME' );

        $candidates = [];

        if ( is_string( $home ) && '' !== $home ) {
            // Laravel Herd on macOS ships a `php` symlink pointing at the
            // currently-active CLI binary (e.g. `php84`). Prefer it so hosts
            // that use Herd for both FPM and CLI stay on a single toolchain.
            $candidates[] = $home . '/Library/Application Support/Herd/bin/php';
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
            Artisan::call( 'down', ['--render' => 'errors::503'] );
        } catch ( Throwable $e ) {
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
            Artisan::call( 'up' );
        } catch ( Throwable $e ) {
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

        // Attempt to disable maintenance mode
        try {
            $this->disableMaintenanceMode();
        } catch ( Throwable $e ) {
            Log::error(
                'Failed to disable maintenance mode during update rollback; host may remain in maintenance mode.',
                ['exception' => $e->getMessage()],
            );
        }

        // If we have a backup, attempt rollback
        if ( $this->backupPath && File::exists( $this->backupPath ) ) {
            try {
                $this->rollback( $this->backupPath );
            } catch ( Throwable $e ) {
                // Rollback failed - this is critical. Preserve the original
                // update-failure message alongside the rollback message so the
                // operator can see both failures rather than only the trailing
                // one.
                throw UpdateException::rollbackAfterFailure( $exception->getMessage(), $e->getMessage());
            }
        }
    }
}
