<?php

/**
 * Asset manager for registering and retrieving assets across contexts.
 *
 * Provides methods to enqueue and dequeue assets in the admin, public, and
 * authenticated areas. Uses filter hooks to allow third-parties to modify
 * the collections of assets before retrieval.
 *
 * @since      2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers;

class AssetManager
{
    /**
     * Enqueue an asset for the admin area.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     * @param  string  $path  Path or URL to the asset file.
     * @param  bool  $inFooter  Whether to load the script in the footer.
     */
    public function adminEnqueueAsset(string $handle, string $path, bool $inFooter = false): void
    {
        /**
         * Filters the collection of enqueued admin assets.
         *
         * Allows plugins and modules to add or modify assets available to the admin area.
         *
         * @since 2.0.0
         *
         * @hook ap.admin.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Associative array of asset definitions keyed by handle.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        addFilter('ap.admin.enqueuedAssets', function ($scripts) use ($handle, $path, $inFooter) {
            $scripts[$handle] = [
                'path' => $path,
                'inFooter' => $inFooter,
            ];

            return $scripts;
        });
    }

    /**
     * Dequeue an asset from the admin area.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     */
    public function adminDequeueAsset(string $handle): void
    {
        /**
         * Filters the collection of enqueued admin assets to remove an asset.
         *
         * @since 2.0.0
         *
         * @hook ap.admin.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Current assets array.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        addFilter('ap.admin.enqueuedAssets', function ($scripts) use ($handle) {
            unset($scripts[$handle]);

            return $scripts;
        });
    }

    /**
     * Retrieve the current list of enqueued admin assets.
     *
     * @since 2.0.0
     *
     * @return array<string,array{path:string,inFooter:bool}> Associative array of enqueued assets keyed by handle.
     */
    public function adminAssets(): array
    {
        /**
         * Filters the final admin assets collection.
         *
         * @since 2.0.0
         *
         * @hook ap.admin.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Current assets array.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        return applyFilters('ap.admin.enqueuedAssets', []);
    }

    /**
     * Enqueue an asset for the public area.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     * @param  string  $path  Path or URL to the asset file.
     * @param  bool  $inFooter  Whether to load the script in the footer.
     */
    public function publicEnqueueAsset(string $handle, string $path, bool $inFooter = false): void
    {
        /**
         * Filters the collection of enqueued public assets.
         *
         * @since 2.0.0
         *
         * @hook ap.public.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Assets array.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        addFilter('ap.public.enqueuedAssets', function ($scripts) use ($handle, $path, $inFooter) {
            $scripts[$handle] = [
                'path' => $path,
                'inFooter' => $inFooter,
            ];

            return $scripts;
        });
    }

    /**
     * Dequeue an asset from the public area.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     */
    public function publicDequeueAsset(string $handle): void
    {
        /**
         * Filters the collection of enqueued public assets to remove an asset.
         *
         * @since 2.0.0
         *
         * @hook ap.public.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Current assets array.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        addFilter('ap.public.enqueuedAssets', function ($scripts) use ($handle) {
            unset($scripts[$handle]);

            return $scripts;
        });
    }

    /**
     * Retrieve the current list of enqueued public assets.
     *
     * @since 2.0.0
     *
     * @return array<string,array{path:string,inFooter:bool}> Associative array of enqueued assets keyed by handle.
     */
    public function publicAssets(): array
    {
        /**
         * Filters the final public assets collection.
         *
         * @since 2.0.0
         *
         * @hook ap.public.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Current assets array.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        return applyFilters('ap.public.enqueuedAssets', []);
    }

    /**
     * Enqueue an asset for the authenticated area.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     * @param  string  $path  Path or URL to the asset file.
     * @param  bool  $inFooter  Whether to load the script in the footer.
     */
    public function authEnqueueAsset(string $handle, string $path, bool $inFooter = false): void
    {
        /**
         * Filters the collection of enqueued authenticated-area assets.
         *
         * @since 2.0.0
         *
         * @hook ap.auth.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Assets array.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        addFilter('ap.auth.enqueuedAssets', function ($scripts) use ($handle, $path, $inFooter) {
            $scripts[$handle] = [
                'path' => $path,
                'inFooter' => $inFooter,
            ];

            return $scripts;
        });
    }

    /**
     * Dequeue an asset from the authenticated area.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     */
    public function authDequeueAsset(string $handle): void
    {
        /**
         * Filters the collection of enqueued authenticated-area assets to remove an asset.
         *
         * @since 2.0.0
         *
         * @hook ap.auth.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Current assets array.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        addFilter('ap.auth.enqueuedAssets', function ($scripts) use ($handle) {
            unset($scripts[$handle]);

            return $scripts;
        });
    }

    /**
     * Retrieve the current list of enqueued authenticated-area assets.
     *
     * @since 2.0.0
     *
     * @return array<string,array{path:string,inFooter:bool}> Associative array of enqueued assets keyed by handle.
     */
    public function authAssets(): array
    {
        /**
         * Filters the final authenticated assets collection.
         *
         * @since 2.0.0
         *
         * @hook ap.auth.enqueuedAssets
         *
         * @param  array<string,array{path:string,inFooter:bool}>  $scripts  Current assets array.
         * @return array<string,array{path:string,inFooter:bool}> Modified assets array.
         */
        return applyFilters('ap.auth.enqueuedAssets', []);
    }
}
