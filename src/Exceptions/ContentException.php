<?php

declare(strict_types=1);

/**
 * Content Exception
 *
 * Exception class for content-related errors in the CMS framework.
 * Handles content creation, updates, publishing, and management errors
 * with specific error codes and context data.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

use Throwable;

/**
 * Content Exception Class
 *
 * Specialized exception for content operations.
 */
class ContentException extends CMSException
{
    // Content-specific error codes
    public const CONTENT_NOT_FOUND = 3001;
    public const CONTENT_CREATION_FAILED = 3002;
    public const CONTENT_UPDATE_FAILED = 3003;
    public const CONTENT_DELETION_FAILED = 3004;
    public const CONTENT_PUBLISHING_FAILED = 3005;
    public const CONTENT_INVALID_STATUS = 3006;
    public const CONTENT_SLUG_DUPLICATE = 3007;
    public const CONTENT_TYPE_INVALID = 3008;
    public const CONTENT_PERMISSION_DENIED = 3009;
    public const CONTENT_VALIDATION_FAILED = 3010;
    public const CONTENT_HIERARCHY_ERROR = 3011;
    public const CONTENT_REVISION_ERROR = 3012;
    public const CONTENT_METADATA_ERROR = 3013;
    public const CONTENT_TAXONOMY_ERROR = 3014;
    public const CONTENT_SCHEDULE_ERROR = 3015;

    /**
     * Error category for content.
     */
    protected string $category = 'content';

    /**
     * Create a content not found exception.
     */
    public static function contentNotFound(int $contentId, ?string $userMessage = null): static
    {
        return new static(
            message: "Content with ID '{$contentId}' not found",
            code: self::CONTENT_NOT_FOUND,
            context: ['content_id' => $contentId],
            userMessage: $userMessage ?? "The requested content could not be found."
        );
    }

    /**
     * Create a content not found by slug exception.
     */
    public static function contentNotFoundBySlug(string $slug, ?string $userMessage = null): static
    {
        return new static(
            message: "Content with slug '{$slug}' not found",
            code: self::CONTENT_NOT_FOUND,
            context: ['slug' => $slug],
            userMessage: $userMessage ?? "The requested page could not be found."
        );
    }

    /**
     * Create a creation failed exception.
     */
    public static function creationFailed(array $data, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Content creation failed: {$reason}",
            code: self::CONTENT_CREATION_FAILED,
            previous: $previous,
            context: ['data' => $data, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to create content. Please try again."
        );
    }

    /**
     * Create an update failed exception.
     */
    public static function updateFailed(int $contentId, array $data, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Content update failed for ID '{$contentId}': {$reason}",
            code: self::CONTENT_UPDATE_FAILED,
            previous: $previous,
            context: ['content_id' => $contentId, 'data' => $data, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to update content. Please try again."
        );
    }

    /**
     * Create a deletion failed exception.
     */
    public static function deletionFailed(int $contentId, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Content deletion failed for ID '{$contentId}': {$reason}",
            code: self::CONTENT_DELETION_FAILED,
            previous: $previous,
            context: ['content_id' => $contentId, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to delete content. Please try again."
        );
    }

    /**
     * Create a publishing failed exception.
     */
    public static function publishingFailed(int $contentId, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Content publishing failed for ID '{$contentId}': {$reason}",
            code: self::CONTENT_PUBLISHING_FAILED,
            previous: $previous,
            context: ['content_id' => $contentId, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to publish content. Please check the content and try again."
        );
    }

    /**
     * Create an invalid status exception.
     */
    public static function invalidStatus(string $status, array $allowedStatuses, ?string $userMessage = null): static
    {
        $allowedStatusesString = implode(', ', $allowedStatuses);
        
        return new static(
            message: "Invalid content status '{$status}'. Allowed statuses: {$allowedStatusesString}",
            code: self::CONTENT_INVALID_STATUS,
            context: ['status' => $status, 'allowed_statuses' => $allowedStatuses],
            userMessage: $userMessage ?? "Invalid content status selected."
        );
    }

    /**
     * Create a slug duplicate exception.
     */
    public static function slugDuplicate(string $slug, int $existingContentId, ?string $userMessage = null): static
    {
        return new static(
            message: "Content slug '{$slug}' already exists (used by content ID '{$existingContentId}')",
            code: self::CONTENT_SLUG_DUPLICATE,
            context: ['slug' => $slug, 'existing_content_id' => $existingContentId],
            userMessage: $userMessage ?? "This URL slug is already in use. Please choose a different one."
        );
    }

    /**
     * Create an invalid type exception.
     */
    public static function invalidType(string $type, array $allowedTypes, ?string $userMessage = null): static
    {
        $allowedTypesString = implode(', ', $allowedTypes);
        
        return new static(
            message: "Invalid content type '{$type}'. Allowed types: {$allowedTypesString}",
            code: self::CONTENT_TYPE_INVALID,
            context: ['type' => $type, 'allowed_types' => $allowedTypes],
            userMessage: $userMessage ?? "Invalid content type selected."
        );
    }

    /**
     * Create a permission denied exception.
     */
    public static function permissionDenied(int $userId, string $operation, int $contentId, ?string $userMessage = null): static
    {
        return new static(
            message: "User '{$userId}' denied permission to '{$operation}' on content '{$contentId}'",
            code: self::CONTENT_PERMISSION_DENIED,
            context: [
                'user_id' => $userId,
                'operation' => $operation,
                'content_id' => $contentId
            ],
            userMessage: $userMessage ?? "You don't have permission to perform this action on this content."
        );
    }

    /**
     * Create a validation failed exception.
     */
    public static function validationFailed(array $errors, array $data, ?string $userMessage = null): static
    {
        return new static(
            message: "Content validation failed: " . implode(', ', array_keys($errors)),
            code: self::CONTENT_VALIDATION_FAILED,
            context: ['validation_errors' => $errors, 'data' => $data],
            userMessage: $userMessage ?? "Please correct the validation errors and try again."
        );
    }

    /**
     * Create a hierarchy error exception.
     */
    public static function hierarchyError(int $contentId, int $parentId, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "Content hierarchy error for content '{$contentId}' with parent '{$parentId}': {$reason}",
            code: self::CONTENT_HIERARCHY_ERROR,
            context: ['content_id' => $contentId, 'parent_id' => $parentId, 'reason' => $reason],
            userMessage: $userMessage ?? "Invalid content hierarchy. Please check the parent-child relationships."
        );
    }

    /**
     * Create a revision error exception.
     */
    public static function revisionError(int $contentId, string $operation, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Content revision '{$operation}' failed for content '{$contentId}': {$reason}",
            code: self::CONTENT_REVISION_ERROR,
            previous: $previous,
            context: ['content_id' => $contentId, 'operation' => $operation, 'reason' => $reason],
            userMessage: $userMessage ?? "Content revision operation failed."
        );
    }

    /**
     * Create a metadata error exception.
     */
    public static function metadataError(int $contentId, string $key, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Content metadata error for content '{$contentId}', key '{$key}': {$reason}",
            code: self::CONTENT_METADATA_ERROR,
            previous: $previous,
            context: ['content_id' => $contentId, 'meta_key' => $key, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to process content metadata."
        )->setSeverity('warning');
    }

    /**
     * Create a taxonomy error exception.
     */
    public static function taxonomyError(int $contentId, array $taxonomyIds, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Content taxonomy error for content '{$contentId}' with taxonomy IDs [" . implode(', ', $taxonomyIds) . "]: {$reason}",
            code: self::CONTENT_TAXONOMY_ERROR,
            previous: $previous,
            context: ['content_id' => $contentId, 'taxonomy_ids' => $taxonomyIds, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to assign categories or tags to content."
        );
    }

    /**
     * Create a schedule error exception.
     */
    public static function scheduleError(int $contentId, string $publishedAt, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "Content schedule error for content '{$contentId}' with publish date '{$publishedAt}': {$reason}",
            code: self::CONTENT_SCHEDULE_ERROR,
            context: ['content_id' => $contentId, 'published_at' => $publishedAt, 'reason' => $reason],
            userMessage: $userMessage ?? "Invalid publication date. Please check the date and try again."
        );
    }
}