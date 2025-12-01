<?php

/**
 * Taxonomy Manager
 *
 * Manages taxonomy registration and operations.
 *
 * @since 2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Taxonomy;
use Illuminate\Support\Collection;

/**
 * Manages taxonomy registration and operations.
 *
 * @since 2.0.0
 */
class TaxonomyManager
{
    /**
     * Register a taxonomy programmatically.
     *
     * @since 2.0.0
     *
     * @param  array  $args  Taxonomy configuration.
     */
    public function registerTaxonomy(array $args): void
    {
        /**
         * Filters the array of registered taxonomies.
         *
         * @since 2.0.0
         *
         * @hook ap.taxonomies.registeredTaxonomies
         *
         * @param  array  $taxonomies  Associative array of registered taxonomies keyed by slug.
         * @return array Filtered taxonomies array.
         */
        addFilter('ap.taxonomies.registeredTaxonomies', function ($taxonomies) use ($args) {
            $slug = $args['slug'] ?? '';
            if ($slug) {
                $taxonomies[$slug] = $args;
            }

            return $taxonomies;
        });
    }

    /**
     * Get all registered taxonomies.
     *
     * @since 2.0.0
     */
    public function getRegisteredTaxonomies(): array
    {
        // Get from database
        $dbTaxonomies = Taxonomy::all()->keyBy('slug')->toArray();

        /**
         * Filters the array of registered taxonomies.
         *
         * @since 2.0.0
         *
         * @hook ap.taxonomies.registeredTaxonomies
         *
         * @param  array  $taxonomies  Associative array of registered taxonomies.
         * @return array Filtered taxonomies array.
         */
        $filteredTaxonomies = applyFilters('ap.taxonomies.registeredTaxonomies', []);

        // Merge database and filtered taxonomies
        return array_merge($dbTaxonomies, $filteredTaxonomies);
    }

    /**
     * Get taxonomies for a specific content type.
     *
     * @since 2.0.0
     *
     * @param  string  $contentTypeSlug  Content type slug.
     */
    public function getTaxonomiesForContentType(string $contentTypeSlug): Collection
    {
        return Taxonomy::where('content_type_slug', $contentTypeSlug)->get();
    }

    /**
     * Check if a taxonomy exists.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Taxonomy slug.
     */
    public function taxonomyExists(string $slug): bool
    {
        return Taxonomy::where('slug', $slug)->exists();
    }

    /**
     * Get a specific taxonomy by slug.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Taxonomy slug.
     */
    public function getTaxonomy(string $slug): ?Taxonomy
    {
        return Taxonomy::where('slug', $slug)->first();
    }

    /**
     * Create a new taxonomy.
     *
     * @since 2.0.0
     *
     * @param  array  $data  Taxonomy data.
     */
    public function createTaxonomy(array $data): Taxonomy
    {
        $taxonomy = Taxonomy::create($data);

        /**
         * Fires after a taxonomy has been created.
         *
         * @since 2.0.0
         *
         * @hook ap.taxonomies.created
         *
         * @param  Taxonomy  $taxonomy  The created taxonomy instance.
         */
        doAction('ap.taxonomies.created', $taxonomy);

        return $taxonomy;
    }

    /**
     * Update a taxonomy.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Taxonomy slug.
     * @param  array  $data  Taxonomy data.
     */
    public function updateTaxonomy(string $slug, array $data): Taxonomy
    {
        $taxonomy = $this->getTaxonomy($slug);

        if (! $taxonomy) {
            throw new \Exception("Taxonomy {$slug} not found.");
        }

        $taxonomy->update($data);

        /**
         * Fires after a taxonomy has been updated.
         *
         * @since 2.0.0
         *
         * @hook ap.taxonomies.updated
         *
         * @param  Taxonomy  $taxonomy  The updated taxonomy instance.
         */
        doAction('ap.taxonomies.updated', $taxonomy);

        return $taxonomy;
    }

    /**
     * Delete a taxonomy.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Taxonomy slug.
     */
    public function deleteTaxonomy(string $slug): bool
    {
        $taxonomy = $this->getTaxonomy($slug);

        if (! $taxonomy) {
            return false;
        }

        /**
         * Fires before a taxonomy is deleted.
         *
         * @since 2.0.0
         *
         * @hook ap.taxonomies.deleting
         *
         * @param  Taxonomy  $taxonomy  The taxonomy being deleted.
         */
        doAction('ap.taxonomies.deleting', $taxonomy);

        $deleted = $taxonomy->delete();

        if ($deleted) {
            /**
             * Fires after a taxonomy has been deleted.
             *
             * @since 2.0.0
             *
             * @hook ap.taxonomies.deleted
             *
             * @param  string  $slug  The slug of the deleted taxonomy.
             */
            doAction('ap.taxonomies.deleted', $slug);
        }

        return $deleted;
    }
}
