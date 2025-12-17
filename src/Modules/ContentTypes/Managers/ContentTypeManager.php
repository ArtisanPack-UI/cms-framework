<?php

/**
 * ContentType Manager
 *
 * Manages content type registration and operations.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;

/**
 * Manages content type registration and operations.
 *
 * @since 2.0.0
 */
class ContentTypeManager
{
    /**
     * Register a content type programmatically.
     *
     * @since 2.0.0
     *
     * @param  array  $args  Content type configuration.
     */
    public function register(array $args): void
    {
        /**
         * Filters the array of registered content types.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.registeredContentTypes
         *
         * @param  array  $contentTypes  Associative array of registered content types keyed by slug.
         * @return array Filtered content types array.
         */
        addFilter('ap.contentTypes.registeredContentTypes', function ($contentTypes) use ($args) {
            $slug = $args['slug'] ?? '';
            if ($slug) {
                $contentTypes[$slug] = $args;
            }

            return $contentTypes;
        });
    }

    /**
     * Get all registered content types.
     *
     * @since 2.0.0
     */
    public function getRegisteredContentTypes(): array
    {
        // Get from database
        $dbContentTypes = ContentType::all()->keyBy('slug')->toArray();

        /**
         * Filters the array of registered content types.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.registeredContentTypes
         *
         * @param  array  $contentTypes  Associative array of registered content types.
         * @return array Filtered content types array.
         */
        $filteredContentTypes = applyFilters('ap.contentTypes.registeredContentTypes', []);

        // Merge database and filtered content types
        return array_merge($dbContentTypes, $filteredContentTypes);
    }

    /**
     * Get a specific content type by slug.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Content type slug.
     */
    public function getContentType(string $slug): ?ContentType
    {
        return ContentType::where('slug', $slug)->first();
    }

    /**
     * Create a new content type.
     *
     * @since 2.0.0
     *
     * @param  array  $data  Content type data.
     */
    public function createContentType(array $data): ContentType
    {
        $contentType = ContentType::create($data);

        /**
         * Fires after a content type has been created.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.created
         *
         * @param  ContentType  $contentType  The created content type instance.
         */
        doAction('ap.contentTypes.created', $contentType);

        return $contentType;
    }

    /**
     * Update a content type.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Content type slug.
     * @param  array  $data  Content type data.
     */
    public function updateContentType(string $slug, array $data): ContentType
    {
        $contentType = $this->getContentType($slug);

        if (! $contentType) {
            throw new \Exception("Content type {$slug} not found.");
        }

        $contentType->update($data);

        /**
         * Fires after a content type has been updated.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.updated
         *
         * @param  ContentType  $contentType  The updated content type instance.
         */
        doAction('ap.contentTypes.updated', $contentType);

        return $contentType;
    }

    /**
     * Delete a content type.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Content type slug.
     */
    public function deleteContentType(string $slug): bool
    {
        $contentType = $this->getContentType($slug);

        if (! $contentType) {
            return false;
        }

        /**
         * Fires before a content type is deleted.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.deleting
         *
         * @param  ContentType  $contentType  The content type being deleted.
         */
        doAction('ap.contentTypes.deleting', $contentType);

        $deleted = $contentType->delete();

        if ($deleted) {
            /**
             * Fires after a content type has been deleted.
             *
             * @since 2.0.0
             *
             * @hook ap.contentTypes.deleted
             *
             * @param  string  $slug  The slug of the deleted content type.
             */
            doAction('ap.contentTypes.deleted', $slug);
        }

        return $deleted;
    }

    /**
     * Check if a content type exists.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Content type slug.
     */
    public function contentTypeExists(string $slug): bool
    {
        return ContentType::where('slug', $slug)->exists();
    }
}
