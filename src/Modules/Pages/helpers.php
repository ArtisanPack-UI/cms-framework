<?php

/**
 * Pages Module Helper Functions
 *
 * Helper functions for the Pages module.
 *
 * @since 2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Pages
 */
if (! function_exists('getPage')) {
    /**
     * Get a page by slug.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The page slug.
     * @return \ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page|null
     */
    function getPage(string $slug)
    {
        return \ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page::where('slug', $slug)->first();
    }
}

if (! function_exists('getPageTree')) {
    /**
     * Get the hierarchical page tree.
     *
     * @since 2.0.0
     *
     * @param  array  $filters  Optional filters (status, author, template).
     * @return \Illuminate\Support\Collection
     */
    function getPageTree(array $filters = [])
    {
        $pageManager = app(\ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager::class);

        return $pageManager->getPageTree($filters);
    }
}
