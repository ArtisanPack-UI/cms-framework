<?php

declare( strict_types=1 );

/**
 * ContentType Manager
 *
 * Manages content type registration and operations.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use Exception;

/**
 * Manages content type registration and operations.
 *
 * @since 1.0.0
 */
class ContentTypeManager
{

    /**
     * Persistence-critical keys a plugin must never be able to seed via the
     * `ap.contentTypes.registeredContentTypes` filter. Blocking them at
     * hydration time keeps a filter payload from carrying primary keys or
     * soft-delete timestamps that would collide with real DB rows or steer
     * downstream persistence.
     *
     * @var array<int,string>
     */
    protected const FILTER_HYDRATION_BLOCKLIST = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Register a content type programmatically.
     *
     * @since 1.0.0
     *
     * @param  array  $args  Content type configuration.
     */
    public function register( array $args ): void
    {
        /**
         * Filters the array of registered content types.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.registeredContentTypes
         *
         * @param  array  $contentTypes  Associative array of registered content types keyed by slug.
         *
         * @return array Filtered content types array.
         */
        addFilter( 'ap.contentTypes.registeredContentTypes', function ( $contentTypes ) use ( $args ) {
            $slug = $args['slug'] ?? '';
            if ( $slug ) {
                $contentTypes[ $slug ] = $args;
            }

            return $contentTypes;
        } );
    }

    /**
     * Get all registered content types.
     *
     * @since 1.0.0
     */
    public function getRegisteredContentTypes(): array
    {
        // Get from database
        $dbContentTypes = ContentType::all()->keyBy( 'slug' )->toArray();

        /**
         * Filters the array of registered content types.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.registeredContentTypes
         *
         * @param  array  $contentTypes  Associative array of registered content types.
         *
         * @return array Filtered content types array.
         */
        $filteredContentTypes = applyFilters( 'ap.contentTypes.registeredContentTypes', [] );

        // Merge database and filtered content types
        return array_merge( $dbContentTypes, $filteredContentTypes );
    }

    /**
     * Get a specific content type by slug.
     *
     * Checks the database first; falls back to filter-registered content types,
     * hydrated into an unpersisted `ContentType` model so callers can treat both
     * sources uniformly. Filter-only entries have no primary key — callers that
     * need to persist them should re-create via `createContentType()`.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Content type slug.
     */
    public function getContentType( string $slug ): ?ContentType
    {
        $slug = sanitizeText( $slug );

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $slug is sanitizeText()-normalized above and bound via Eloquent where('slug', ...).
        $contentType = ContentType::where( 'slug', $slug )->first();
        if ( $contentType ) {
            return $contentType;
        }

        return $this->hydrateFilterContentType( $slug );
    }

    /**
     * Get a specific content type by slug, restricted to database-persisted
     * rows. Never returns a filter-hydrated model.
     *
     * Use this instead of `getContentType()` in privileged paths that trust
     * the returned model's `table_name` — schema mutations, migration
     * generation, or anything that writes to the database on behalf of the
     * content type. Filter-registered content types can carry plugin-supplied
     * `table_name` values and must not steer such operations.
     *
     * @since 2.4.0
     */
    public function getPersistedContentType( string $slug ): ?ContentType
    {
        return ContentType::where( 'slug', sanitizeText( $slug ) )->first();
    }

    /**
     * Create a new content type.
     *
     * @since 1.0.0
     *
     * @param  array  $data  Content type data.
     */
    public function createContentType( array $data ): ContentType
    {
        $contentType = ContentType::create( $data );

        /**
         * Fires after a content type has been created.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.created
         *
         * @param  ContentType  $contentType  The created content type instance.
         */
        doAction( 'ap.contentTypes.created', $contentType );

        return $contentType;
    }

    /**
     * Update a content type.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Content type slug.
     * @param  array  $data  Content type data.
     */
    public function updateContentType( string $slug, array $data ): ContentType
    {
        // Persisted-only lookup — filter-registered content types have no DB
        // row and would silently INSERT a phantom row on `update()` because
        // Eloquent runs performInsert() when `exists === false`.
        $contentType = $this->getPersistedContentType( $slug );

        if ( ! $contentType ) {
            throw new Exception( "Content type {$slug} not found." );
        }

        $contentType->update( $data );

        /**
         * Fires after a content type has been updated.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.updated
         *
         * @param  ContentType  $contentType  The updated content type instance.
         */
        doAction( 'ap.contentTypes.updated', $contentType );

        return $contentType;
    }

    /**
     * Delete a content type.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Content type slug.
     */
    public function deleteContentType( string $slug ): bool
    {
        // Persisted-only lookup — Eloquent `delete()` on an `exists=false`
        // model is a no-op, so passing a filter-only slug here would return
        // false but skip firing the `ap.contentTypes.deleted` hook. Keep the
        // legacy "false == not found" contract by refusing the call outright.
        $contentType = $this->getPersistedContentType( $slug );

        if ( ! $contentType ) {
            return false;
        }

        /**
         * Fires before a content type is deleted.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.deleting
         *
         * @param  ContentType  $contentType  The content type being deleted.
         */
        doAction( 'ap.contentTypes.deleting', $contentType );

        $deleted = $contentType->delete();

        if ( $deleted ) {
            /**
             * Fires after a content type has been deleted.
             *
             * @since 1.0.0
             *
             * @hook ap.contentTypes.deleted
             *
             * @param  string  $slug  The slug of the deleted content type.
             */
            doAction( 'ap.contentTypes.deleted', $slug );
        }

        return $deleted;
    }

    /**
     * Check if a content type exists.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Content type slug.
     */
    public function contentTypeExists( string $slug ): bool
    {
        $slug = sanitizeText( $slug );

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $slug is sanitizeText()-normalized above and bound via Eloquent where('slug', ...)->exists().
        if ( ContentType::where( 'slug', $slug )->exists() ) {
            return true;
        }

        return null !== $this->hydrateFilterContentType( $slug );
    }

    /**
     * Look up a filter-registered content type by slug and hydrate it into an
     * unpersisted `ContentType` model. Returns null if no filter entry matches.
     *
     * @since 2.4.0
     */
    protected function hydrateFilterContentType( string $slug ): ?ContentType
    {
        $filtered = applyFilters( 'ap.contentTypes.registeredContentTypes', [] );

        if ( ! is_array( $filtered ) || ! isset( $filtered[ $slug ] ) || ! is_array( $filtered[ $slug ] ) ) {
            return null;
        }

        $args = array_diff_key( $filtered[ $slug ], array_flip( self::FILTER_HYDRATION_BLOCKLIST ) );
        if ( ! isset( $args['slug'] ) ) {
            $args['slug'] = $slug;
        }

        $model = new ContentType;
        $model->forceFill( $args );
        $model->exists = false;

        return $model;
    }
}
