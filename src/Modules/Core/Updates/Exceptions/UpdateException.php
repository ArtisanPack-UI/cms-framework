<?php

declare( strict_types = 1 );

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
     * ZIP extraction failed.
     *
     * @since 1.0.0
     */
    public static function extractionFailed( string $zipPath ): self
    {
        return new self( "Failed to extract ZIP archive: {$zipPath}" );
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
