<?php

declare(strict_types=1);

/**
 * Taxonomy Manager Interface
 *
 * Defines the contract for taxonomy management operations in the CMS framework.
 * This interface provides methods for managing taxonomies and their definitions.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Contracts;

use ArtisanPackUI\CMSFramework\Models\Taxonomy;

/**
 * Taxonomy Manager Interface
 *
 * Defines the contract for taxonomy management operations including taxonomy
 * registration, retrieval, creation, and deletion.
 *
 * @since 1.0.0
 */
interface TaxonomyManagerInterface
{
    /**
     * Load all registered taxonomies.
     */
    public function loadTaxonomies(): void;

    /**
     * Register a new taxonomy with the provided definition.
     *
     * @param  string  $handle  The taxonomy handle/identifier.
     * @param  array  $definition  The taxonomy definition.
     */
    public function registerTaxonomy(string $handle, array $definition): void;

    /**
     * Get the singleton instance of the taxonomy manager.
     *
     * @return self The taxonomy manager instance.
     */
    public function instance(): self;

    /**
     * Get a specific taxonomy by its handle.
     *
     * @param  string  $handle  The taxonomy handle.
     * @return array|null The taxonomy definition if found, null otherwise.
     */
    public function getTaxonomy(string $handle): ?array;

    /**
     * Get all registered taxonomies.
     *
     * @return array Array of all taxonomy definitions.
     */
    public function allTaxonomies(): array;

    /**
     * Save a user-defined taxonomy to the database.
     *
     * @param  string  $handle  The taxonomy handle.
     * @param  string  $label  The taxonomy label.
     * @param  string  $labelPlural  The taxonomy plural label.
     * @param  array  $contentTypes  The content types this taxonomy applies to.
     * @param  bool  $hierarchical  Whether the taxonomy is hierarchical.
     * @return Taxonomy The created taxonomy model instance.
     */
    public function saveUserTaxonomy(
        string $handle,
        string $label,
        string $labelPlural,
        array $contentTypes,
        bool $hierarchical
    ): Taxonomy;

    /**
     * Refresh the taxonomies cache.
     */
    public function refreshTaxonomiesCache(): void;

    /**
     * Delete a user-defined taxonomy.
     *
     * @param  string  $handle  The taxonomy handle to delete.
     * @return bool True if deletion was successful, false otherwise.
     */
    public function deleteUserTaxonomy(string $handle): bool;
}
