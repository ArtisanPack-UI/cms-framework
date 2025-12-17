<?php

/**
 * ContentTypes Module Helper Functions
 *
 * Provides convenient helper functions for working with content types.
 *
 * @since 2.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;

if (! function_exists('getContentType')) {
    /**
     * Get a content type by slug.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The content type slug.
     * @return ContentType|null The content type instance or null if not found.
     */
    function getContentType(string $slug): ?ContentType
    {
        return app(ContentTypeManager::class)->getContentType($slug);
    }
}

if (! function_exists('contentTypeExists')) {
    /**
     * Check if a content type exists by slug.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The content type slug.
     * @return bool True if the content type exists, false otherwise.
     */
    function contentTypeExists(string $slug): bool
    {
        return app(ContentTypeManager::class)->contentTypeExists($slug);
    }
}
