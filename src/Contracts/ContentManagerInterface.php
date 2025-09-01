<?php

declare(strict_types=1);

/**
 * Content Manager Interface
 *
 * Defines the contract for content type management operations in the CMS framework.
 * This interface provides methods for managing content types and their definitions.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Contracts;

use ArtisanPackUI\CMSFramework\Models\ContentType;

/**
 * Content Manager Interface
 *
 * Defines the contract for content type management operations including content type
 * registration, retrieval, creation, and deletion.
 *
 * @since 1.0.0
 */
interface ContentManagerInterface
{
    /**
     * Register a new content type with the provided definition.
     *
     * @param  string  $handle  The content type handle/identifier.
     * @param  array  $definition  The content type definition.
     */
    public function registerContentType(string $handle, array $definition): void;

    /**
     * Get the singleton instance of the content manager.
     *
     * @return self The content manager instance.
     */
    public static function instance(): self;

    /**
     * Get a specific content type by its handle.
     *
     * @param  string  $handle  The content type handle.
     * @return array|null The content type definition if found, null otherwise.
     */
    public function getContentType(string $handle): ?array;

    /**
     * Get all registered content types.
     *
     * @return array Array of all content type definitions.
     */
    public function allContentTypes(): array;

    /**
     * Save a user-defined content type to the database.
     *
     * @param  string  $handle  The content type handle.
     * @param  string  $label  The content type label.
     * @param  string  $labelPlural  The content type plural label.
     * @param  string  $slug  The content type slug.
     * @param  array  $definition  The content type definition.
     * @return ContentType The created content type model instance.
     */
    public function saveUserContentType(
        string $handle,
        string $label,
        string $labelPlural,
        string $slug,
        array $definition
    ): ContentType;

    /**
     * Refresh the content types cache.
     */
    public function refreshContentTypesCache(): void;

    /**
     * Delete a user-defined content type.
     *
     * @param  string  $handle  The content type handle to delete.
     * @return bool True if deletion was successful, false otherwise.
     */
    public function deleteUserContentType(string $handle): bool;
}
