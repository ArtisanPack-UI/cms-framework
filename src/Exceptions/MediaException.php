<?php

declare(strict_types=1);

/**
 * Media Exception
 *
 * Exception class for media-related errors in the CMS framework.
 * Handles media upload, processing, validation, and management errors
 * with specific error codes and context data.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

use Throwable;

/**
 * Media Exception Class
 *
 * Specialized exception for media operations.
 */
class MediaException extends CMSException
{
    // Media-specific error codes
    public const MEDIA_NOT_FOUND = 2001;
    public const MEDIA_UPLOAD_FAILED = 2002;
    public const MEDIA_INVALID_TYPE = 2003;
    public const MEDIA_SIZE_EXCEEDED = 2004;
    public const MEDIA_PROCESSING_FAILED = 2005;
    public const MEDIA_STORAGE_FAILED = 2006;
    public const MEDIA_DELETION_FAILED = 2007;
    public const MEDIA_INVALID_DIMENSIONS = 2008;
    public const MEDIA_CORRUPT_FILE = 2009;
    public const MEDIA_PERMISSION_DENIED = 2010;
    public const MEDIA_VIRUS_DETECTED = 2011;
    public const MEDIA_QUOTA_EXCEEDED = 2012;
    public const MEDIA_OPTIMIZATION_FAILED = 2013;
    public const MEDIA_METADATA_ERROR = 2014;

    /**
     * Error category for media.
     */
    protected string $category = 'media';

    /**
     * Create a media not found exception.
     */
    public static function mediaNotFound(int $mediaId, ?string $userMessage = null): static
    {
        return new static(
            message: "Media with ID '{$mediaId}' not found",
            code: self::MEDIA_NOT_FOUND,
            context: ['media_id' => $mediaId],
            userMessage: $userMessage ?? "The requested media file could not be found."
        );
    }

    /**
     * Create an upload failed exception.
     */
    public static function uploadFailed(string $filename, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Upload failed for '{$filename}': {$reason}",
            code: self::MEDIA_UPLOAD_FAILED,
            previous: $previous,
            context: ['filename' => $filename, 'reason' => $reason],
            userMessage: $userMessage ?? "File upload failed. Please try again."
        );
    }

    /**
     * Create an invalid type exception.
     */
    public static function invalidType(string $filename, string $actualType, array $allowedTypes, ?string $userMessage = null): static
    {
        $allowedTypesString = implode(', ', $allowedTypes);
        
        return new static(
            message: "Invalid file type '{$actualType}' for '{$filename}'. Allowed types: {$allowedTypesString}",
            code: self::MEDIA_INVALID_TYPE,
            context: [
                'filename' => $filename,
                'actual_type' => $actualType,
                'allowed_types' => $allowedTypes
            ],
            userMessage: $userMessage ?? "This file type is not allowed. Please upload a supported file format."
        );
    }

    /**
     * Create a size exceeded exception.
     */
    public static function sizeExceeded(string $filename, int $actualSize, int $maxSize, ?string $userMessage = null): static
    {
        return new static(
            message: "File '{$filename}' size ({$actualSize} bytes) exceeds maximum allowed size ({$maxSize} bytes)",
            code: self::MEDIA_SIZE_EXCEEDED,
            context: [
                'filename' => $filename,
                'actual_size' => $actualSize,
                'max_size' => $maxSize
            ],
            userMessage: $userMessage ?? "File is too large. Please upload a smaller file."
        );
    }

    /**
     * Create a processing failed exception.
     */
    public static function processingFailed(string $filename, string $operation, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Processing operation '{$operation}' failed for '{$filename}': {$reason}",
            code: self::MEDIA_PROCESSING_FAILED,
            previous: $previous,
            context: [
                'filename' => $filename,
                'operation' => $operation,
                'reason' => $reason
            ],
            userMessage: $userMessage ?? "Media processing failed. The file may be corrupted."
        );
    }

    /**
     * Create a storage failed exception.
     */
    public static function storageFailed(string $filename, string $path, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Failed to store '{$filename}' at '{$path}': {$reason}",
            code: self::MEDIA_STORAGE_FAILED,
            previous: $previous,
            context: [
                'filename' => $filename,
                'path' => $path,
                'reason' => $reason
            ],
            userMessage: $userMessage ?? "Failed to save the file. Please try again."
        );
    }

    /**
     * Create a deletion failed exception.
     */
    public static function deletionFailed(int $mediaId, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Failed to delete media ID '{$mediaId}': {$reason}",
            code: self::MEDIA_DELETION_FAILED,
            previous: $previous,
            context: ['media_id' => $mediaId, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to delete the file. Please try again."
        );
    }

    /**
     * Create an invalid dimensions exception.
     */
    public static function invalidDimensions(string $filename, array $actualDimensions, array $requiredDimensions, ?string $userMessage = null): static
    {
        return new static(
            message: "Image '{$filename}' dimensions invalid. Required: {$requiredDimensions['width']}x{$requiredDimensions['height']}, Actual: {$actualDimensions['width']}x{$actualDimensions['height']}",
            code: self::MEDIA_INVALID_DIMENSIONS,
            context: [
                'filename' => $filename,
                'actual_dimensions' => $actualDimensions,
                'required_dimensions' => $requiredDimensions
            ],
            userMessage: $userMessage ?? "Image dimensions do not meet requirements."
        );
    }

    /**
     * Create a corrupt file exception.
     */
    public static function corruptFile(string $filename, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "File '{$filename}' is corrupted: {$reason}",
            code: self::MEDIA_CORRUPT_FILE,
            context: ['filename' => $filename, 'reason' => $reason],
            userMessage: $userMessage ?? "The uploaded file is corrupted or damaged."
        );
    }

    /**
     * Create a permission denied exception.
     */
    public static function permissionDenied(int $userId, string $operation, int $mediaId, ?string $userMessage = null): static
    {
        return new static(
            message: "User '{$userId}' denied permission to '{$operation}' on media '{$mediaId}'",
            code: self::MEDIA_PERMISSION_DENIED,
            context: [
                'user_id' => $userId,
                'operation' => $operation,
                'media_id' => $mediaId
            ],
            userMessage: $userMessage ?? "You don't have permission to perform this action."
        );
    }

    /**
     * Create a virus detected exception.
     */
    public static function virusDetected(string $filename, string $virusName, ?string $userMessage = null): static
    {
        return new static(
            message: "Virus '{$virusName}' detected in file '{$filename}'",
            code: self::MEDIA_VIRUS_DETECTED,
            context: ['filename' => $filename, 'virus_name' => $virusName],
            userMessage: $userMessage ?? "File upload blocked due to security concerns."
        )->setReportable(true)->setSeverity('critical');
    }

    /**
     * Create a quota exceeded exception.
     */
    public static function quotaExceeded(int $userId, int $currentUsage, int $quota, ?string $userMessage = null): static
    {
        return new static(
            message: "User '{$userId}' storage quota exceeded. Current: {$currentUsage} bytes, Quota: {$quota} bytes",
            code: self::MEDIA_QUOTA_EXCEEDED,
            context: [
                'user_id' => $userId,
                'current_usage' => $currentUsage,
                'quota' => $quota
            ],
            userMessage: $userMessage ?? "Storage quota exceeded. Please delete some files or contact support."
        );
    }

    /**
     * Create an optimization failed exception.
     */
    public static function optimizationFailed(string $filename, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Optimization failed for '{$filename}': {$reason}",
            code: self::MEDIA_OPTIMIZATION_FAILED,
            previous: $previous,
            context: ['filename' => $filename, 'reason' => $reason],
            userMessage: $userMessage ?? "Image optimization failed, but the file was uploaded successfully."
        )->setSeverity('warning');
    }

    /**
     * Create a metadata error exception.
     */
    public static function metadataError(string $filename, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Metadata extraction failed for '{$filename}': {$reason}",
            code: self::MEDIA_METADATA_ERROR,
            previous: $previous,
            context: ['filename' => $filename, 'reason' => $reason],
            userMessage: $userMessage ?? "Unable to extract file information, but the file was uploaded successfully."
        )->setSeverity('warning');
    }
}