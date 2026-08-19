<?php

declare( strict_types=1 );

/**
 * Taxonomy Manager
 *
 * Manages taxonomy registration and operations.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Taxonomy;
use Exception;
use Illuminate\Support\Collection;

/**
 * Manages taxonomy registration and operations.
 *
 * @since 1.0.0
 */
class TaxonomyManager
{
    /**
     * Register a taxonomy programmatically.
     *
     * @since 1.0.0
     *
     * @param  array  $args  Taxonomy configuration.
     */
    public function registerTaxonomy( array $args ): void
    {
        /**
         * Filters the array of registered taxonomies.
         *
         * @since 1.0.0
         *
         * @hook ap.taxonomies.registeredTaxonomies
         *
         * @param  array  $taxonomies  Associative array of registered taxonomies keyed by slug.
         *
         * @return array Filtered taxonomies array.
         */
        addFilter( 'ap.taxonomies.registeredTaxonomies', function ( $taxonomies ) use ( $args ) {
            $slug = $args['slug'] ?? '';
            if ( $slug ) {
                $taxonomies[ $slug ] = $args;
            }

            return $taxonomies;
        } );
    }

    /**
     * Get all registered taxonomies.
     *
     * @since 1.0.0
     */
    public function getRegisteredTaxonomies(): array
    {
        // Get from database
        $dbTaxonomies = Taxonomy::all()->keyBy( 'slug' )->toArray();

        /**
         * Filters the array of registered taxonomies.
         *
         * @since 1.0.0
         *
         * @hook ap.taxonomies.registeredTaxonomies
         *
         * @param  array  $taxonomies  Associative array of registered taxonomies.
         *
         * @return array Filtered taxonomies array.
         */
        $filteredTaxonomies = applyFilters( 'ap.taxonomies.registeredTaxonomies', [] );

        // Merge database and filtered taxonomies
        return array_merge( $dbTaxonomies, $filteredTaxonomies );
    }

    /**
     * Get taxonomies for a specific content type.
     *
     * @since 1.0.0
     *
     * @param  string  $contentTypeSlug  Content type slug.
     */
    public function getTaxonomiesForContentType( string $contentTypeSlug ): Collection
    {
        $contentTypeSlug = sanitizeText( $contentTypeSlug );

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $contentTypeSlug is sanitizeText()-normalized above and bound via Eloquent where().
        $dbTaxonomies = Taxonomy::where( 'content_type_slug', $contentTypeSlug )->get();

        $filtered = applyFilters( 'ap.taxonomies.registeredTaxonomies', [] );
        if ( ! is_array( $filtered ) ) {
            return $dbTaxonomies;
        }

        $existingSlugs = $dbTaxonomies->pluck( 'slug' )->all();

        foreach ( $filtered as $slug => $args ) {
            if ( ! is_array( $args ) ) {
                continue;
            }
            if ( ! isset( $args['content_type_slug'] ) || $args['content_type_slug'] !== $contentTypeSlug ) {
                continue;
            }
            $resolvedSlug = ( string ) ( $args['slug'] ?? $slug );
            if ( in_array( $resolvedSlug, $existingSlugs, true ) ) {
                continue;
            }

            $args['slug'] = $resolvedSlug;
            $dbTaxonomies->push( $this->hydrateTaxonomyFromArgs( $args ) );
        }

        return $dbTaxonomies;
    }

    /**
     * Check if a taxonomy exists.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Taxonomy slug.
     */
    public function taxonomyExists( string $slug ): bool
    {
        $slug = sanitizeText( $slug );

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $slug is sanitizeText()-normalized above and bound via Eloquent where('slug', ...)->exists().
        if ( Taxonomy::where( 'slug', $slug )->exists() ) {
            return true;
        }

        return null !== $this->hydrateFilterTaxonomy( $slug );
    }

    /**
     * Get a specific taxonomy by slug.
     *
     * Checks the database first; falls back to filter-registered taxonomies,
     * hydrated into an unpersisted `Taxonomy` model so callers can treat both
     * sources uniformly.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Taxonomy slug.
     */
    public function getTaxonomy( string $slug ): ?Taxonomy
    {
        $slug = sanitizeText( $slug );

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $slug is sanitizeText()-normalized above and bound via Eloquent where('slug', ...).
        $taxonomy = Taxonomy::where( 'slug', $slug )->first();
        if ( $taxonomy ) {
            return $taxonomy;
        }

        return $this->hydrateFilterTaxonomy( $slug );
    }

    /**
     * Get a specific taxonomy by slug, restricted to database-persisted rows.
     * Never returns a filter-hydrated model.
     *
     * Use this in privileged paths (update, delete, migration generation)
     * where the returned model must correspond to a real DB row.
     *
     * @since 2.4.0
     */
    public function getPersistedTaxonomy( string $slug ): ?Taxonomy
    {
        return Taxonomy::where( 'slug', sanitizeText( $slug ) )->first();
    }

    /**
     * Create a new taxonomy.
     *
     * @since 1.0.0
     *
     * @param  array  $data  Taxonomy data.
     */
    public function createTaxonomy( array $data ): Taxonomy
    {
        $taxonomy = Taxonomy::create( $data );

        /**
         * Fires after a taxonomy has been created.
         *
         * @since 1.0.0
         *
         * @hook ap.taxonomies.created
         *
         * @param  Taxonomy  $taxonomy  The created taxonomy instance.
         */
        doAction( 'ap.taxonomies.created', $taxonomy );

        return $taxonomy;
    }

    /**
     * Update a taxonomy.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Taxonomy slug.
     * @param  array  $data  Taxonomy data.
     */
    public function updateTaxonomy( string $slug, array $data ): Taxonomy
    {
        // Persisted-only lookup — a filter-registered taxonomy would silently
        // INSERT a phantom row on `update()` because Eloquent runs
        // performInsert() when `exists === false`.
        $taxonomy = $this->getPersistedTaxonomy( $slug );

        if ( ! $taxonomy ) {
            throw new Exception( "Taxonomy {$slug} not found." );
        }

        $taxonomy->update( $data );

        /**
         * Fires after a taxonomy has been updated.
         *
         * @since 1.0.0
         *
         * @hook ap.taxonomies.updated
         *
         * @param  Taxonomy  $taxonomy  The updated taxonomy instance.
         */
        doAction( 'ap.taxonomies.updated', $taxonomy );

        return $taxonomy;
    }

    /**
     * Delete a taxonomy.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Taxonomy slug.
     */
    public function deleteTaxonomy( string $slug ): bool
    {
        // Persisted-only lookup so we never no-op `delete()` on an
        // `exists=false` filter-hydrated model ( which would return false
        // AND skip firing the `ap.taxonomies.deleted` hook, breaking the
        // legacy "false == not found" contract ).
        $taxonomy = $this->getPersistedTaxonomy( $slug );

        if ( ! $taxonomy ) {
            return false;
        }

        /**
         * Fires before a taxonomy is deleted.
         *
         * @since 1.0.0
         *
         * @hook ap.taxonomies.deleting
         *
         * @param  Taxonomy  $taxonomy  The taxonomy being deleted.
         */
        doAction( 'ap.taxonomies.deleting', $taxonomy );

        $deleted = $taxonomy->delete();

        if ( $deleted ) {
            /**
             * Fires after a taxonomy has been deleted.
             *
             * @since 1.0.0
             *
             * @hook ap.taxonomies.deleted
             *
             * @param  string  $slug  The slug of the deleted taxonomy.
             */
            doAction( 'ap.taxonomies.deleted', $slug );
        }

        return $deleted;
    }

    /**
     * Look up a filter-registered taxonomy by slug and hydrate it into an
     * unpersisted `Taxonomy` model. Returns null if no filter entry matches.
     *
     * @since 2.4.0
     */
    protected function hydrateFilterTaxonomy( string $slug ): ?Taxonomy
    {
        $filtered = applyFilters( 'ap.taxonomies.registeredTaxonomies', [] );

        if ( ! is_array( $filtered ) || ! isset( $filtered[ $slug ] ) || ! is_array( $filtered[ $slug ] ) ) {
            return null;
        }

        $args = $filtered[ $slug ];
        if ( ! isset( $args['slug'] ) ) {
            $args['slug'] = $slug;
        }

        return $this->hydrateTaxonomyFromArgs( $args );
    }

    /**
     * Hydrate an unpersisted `Taxonomy` model from a raw args array.
     *
     * Strips persistence-critical keys before `forceFill()` so a plugin can
     * never seed `id`, `created_at`, `updated_at`, or `deleted_at` via the
     * `ap.taxonomies.registeredTaxonomies` filter.
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $args
     */
    protected function hydrateTaxonomyFromArgs( array $args ): Taxonomy
    {
        $args = array_diff_key( $args, array_flip( [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ] ) );

        $model = new Taxonomy;
        $model->forceFill( $args );
        $model->exists = false;

        return $model;
    }
}
