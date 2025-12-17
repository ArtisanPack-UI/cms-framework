<?php

/**
 * Core helper functions for assets management.
 *
 * These helpers proxy calls to the AssetManager for registering and retrieving
 * assets in different application contexts (admin, public, and auth areas).
 *
 * WordPress-style inline docs are used throughout, including proper @since,
 *
 * @param, and @return tags.
 *
 * @since      2.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\AssetManager;

if (! function_exists('apAdminEnqueueAsset')) {
    /**
     * Enqueue an asset for the admin area.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     * @param  string  $path  Path or URL to the asset file.
     * @param  bool  $inFooter  Whether to load the script in the footer.
     */
    function apAdminEnqueueAsset(string $handle, string $path, bool $inFooter = false): void
    {
        app(AssetManager::class)->adminEnqueueAsset($handle, $path, $inFooter);
    }
}

if (! function_exists('apAdminDequeueAsset')) {
    /**
     * Dequeue a previously enqueued admin asset.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     */
    function apAdminDequeueAsset(string $handle): void
    {
        app(AssetManager::class)->adminDequeueAsset($handle);
    }
}

if (! function_exists('apAdminAssets')) {
    /**
     * Retrieve all enqueued admin assets.
     *
     * @since 2.0.0
     *
     * @return array<string,array{path:string,inFooter:bool}> Associative array of enqueued assets keyed by handle.
     */
    function apAdminAssets(): array
    {
        return app(AssetManager::class)->adminAssets();
    }
}

if (! function_exists('apPublicEnqueueAsset')) {
    /**
     * Enqueue an asset for the public area.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     * @param  string  $path  Path or URL to the asset file.
     * @param  bool  $inFooter  Whether to load the script in the footer.
     */
    function apPublicEnqueueAsset(string $handle, string $path, bool $inFooter = false): void
    {
        app(AssetManager::class)->publicEnqueueAsset($handle, $path, $inFooter);
    }
}

if (! function_exists('apPublicDequeueAsset')) {
    /**
     * Dequeue a previously enqueued public asset.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     */
    function apPublicDequeueAsset(string $handle): void
    {
        app(AssetManager::class)->publicDequeueAsset($handle);
    }
}

if (! function_exists('apPublicAssets')) {
    /**
     * Retrieve all enqueued public assets.
     *
     * @since 2.0.0
     *
     * @return array<string,array{path:string,inFooter:bool}> Associative array of enqueued assets keyed by handle.
     */
    function apPublicAssets(): array
    {
        return app(AssetManager::class)->publicAssets();
    }
}

if (! function_exists('apAuthEnqueueAsset')) {
    /**
     * Enqueue an asset for the authenticated area (e.g., user dashboard).
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     * @param  string  $path  Path or URL to the asset file.
     * @param  bool  $inFooter  Whether to load the script in the footer.
     */
    function apAuthEnqueueAsset(string $handle, string $path, bool $inFooter = false): void
    {
        app(AssetManager::class)->authEnqueueAsset($handle, $path, $inFooter);
    }
}

if (! function_exists('apAuthDequeueAsset')) {
    /**
     * Dequeue a previously enqueued authenticated-area asset.
     *
     * @since 2.0.0
     *
     * @param  string  $handle  Unique handle for the asset.
     */
    function apAuthDequeueAsset(string $handle): void
    {
        app(AssetManager::class)->authDequeueAsset($handle);
    }
}

if (! function_exists('apAuthAssets')) {
    /**
     * Retrieve all enqueued authenticated-area assets.
     *
     * @since 2.0.0
     *
     * @return array<string,array{path:string,inFooter:bool}> Associative array of enqueued assets keyed by handle.
     */
    function apAuthAssets(): array
    {
        return app(AssetManager::class)->authAssets();
    }
}
