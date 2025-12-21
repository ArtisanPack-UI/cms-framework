<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCheckerFactory;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
        $updateInfo = $this->checkForUpdate();

        if ( ! $updateInfo->hasUpdate ) {
            throw UpdateException::noUpdateAvailable();
        }

        // Use specified version or latest
        $targetVersion = $version ?? $updateInfo->latestVersion;

        try {
            // Step 1: Enable maintenance mode
            $this->enableMaintenanceMode();

            // Step 2: Create backup
            if ( config( 'cms.updates.backup_enabled', true ) ) {
                $this->createBackup();
            }

            // Step 3: Download update
            $zipPath = $this->getUpdateChecker()->downloadUpdate( $targetVersion );

            // Step 4: Verify checksum (if available)
            if ( config( 'cms.updates.verify_checksum', true ) && $updateInfo->sha256 ) {
                $this->verifyChecksum( $zipPath, $updateInfo->sha256 );
            }

            // Step 5: Extract update
            $this->extractUpdate( $zipPath );

            // Step 6: Run composer install
            $this->runComposerInstall();

            // Step 7: Run migrations
            $this->runMigrations();

            // Step 8: Clear caches
            $this->clearCaches();

            // Step 9: Clean up
            $this->cleanup( $zipPath );

            // Step 10: Disable maintenance mode
            $this->disableMaintenanceMode();

            return true;
        } catch ( Exception $e ) {
            // Rollback on failure
            $this->handleUpdateFailure( $e );

            throw $e;
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
        if ( ! File::exists( $backupPath ) ) {
            throw UpdateException::rollbackFailed( "Backup not found: {$backupPath}" );
        }

        $zip = new ZipArchive;

        if ( true !== $zip->open( $backupPath ) ) {
            throw UpdateException::rollbackFailed( 'Could not open backup ZIP' );
        }

        $zip->extractTo( base_path() );
        $zip->close();

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
            type: 'application',
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
                \Illuminate\Support\Facades\Log::warning( 'Failed to get real path for file during backup', [
                    'file' => $file->getPathname(),
                ] );

                continue;
            }

            // Verify the resolved path starts with base_path() to prevent traversal issues
            if ( ! str_starts_with( $filePath, $basePath ) ) {
                // File is outside base path (symlink or external) - log and skip
                \Illuminate\Support\Facades\Log::warning( 'Skipping file outside base path during backup', [
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

            $fullTargetPath = $extractPath . DIRECTORY_SEPARATOR . $targetPath;

            // Handle directories
            if ( str_ends_with( $filename, '/' ) ) {
                if ( ! File::exists( $fullTargetPath ) ) {
                    File::makeDirectory( $fullTargetPath, 0755, true );
                }

                continue;
            }

            // Handle files - ensure parent directory exists
            $targetDir = dirname( $fullTargetPath );
            if ( ! File::exists( $targetDir ) ) {
                File::makeDirectory( $targetDir, 0755, true );
            }

            // Extract file
            $fileContent = $zip->getFromIndex( $i );
            if ( false === $fileContent ) {
                continue;
            }

            File::put( $fullTargetPath, $fileContent );

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
     * Run composer install.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     */
    protected function runComposerInstall(): void
    {
        $command = config( 'cms.updates.composer_install_command' );
        $timeout = config( 'cms.updates.composer_timeout', 600 );

        $result = Process::timeout( $timeout )
            ->path( base_path() )
            ->run( $command );

        if ( ! $result->successful() ) {
            throw UpdateException::composerInstallFailed( $result->errorOutput() );
        }
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
        } catch ( Exception $e ) {
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
     * Enable maintenance mode.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     */
    protected function enableMaintenanceMode(): void
    {
        try {
            Artisan::call( 'down', ['--render' => 'errors::503'] );
        } catch ( Exception $e ) {
            throw UpdateException::maintenanceModeFailure( 'enable' );
        }
    }

    /**
     * Disable maintenance mode.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     */
    protected function disableMaintenanceMode(): void
    {
        try {
            Artisan::call( 'up' );
        } catch ( Exception $e ) {
            throw UpdateException::maintenanceModeFailure( 'disable' );
        }
    }

    /**
     * Handle update failure and attempt rollback.
     *
     * @since 1.0.0
     *
     * @param  Exception  $exception  The exception that caused failure
     */
    protected function handleUpdateFailure( Exception $exception ): void
    {
        // Log the original exception for debugging
        \Illuminate\Support\Facades\Log::error( 'Update failed, beginning rollback', [
            'exception' => $exception->getMessage(),
            'trace'     => $exception->getTraceAsString(),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
        ] );

        // Attempt to disable maintenance mode
        try {
            $this->disableMaintenanceMode();
        } catch ( Exception $e ) {
            // Maintenance mode failure is not critical during rollback
        }

        // If we have a backup, attempt rollback
        if ( $this->backupPath && File::exists( $this->backupPath ) ) {
            try {
                $this->rollback( $this->backupPath );
            } catch ( Exception $e ) {
                // Rollback failed - this is critical
                throw UpdateException::rollbackFailed( $e->getMessage());
            }
        }
    }
}
