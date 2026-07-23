<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\Cache;

/**
 * Update Checker
 *
 * Main API for checking and downloading updates.
 *
 * @since 1.0.0
 */
class UpdateChecker
{
    /**
     * Create a new UpdateChecker instance.
     *
     * @since 1.0.0
     *
     * @param  UpdateSourceInterface  $source  The update source
     * @param  UpdateType  $type  Update type (application, plugin, theme)
     * @param  string  $slug  Unique identifier
     */
    public function __construct(
        protected UpdateSourceInterface $source,
        protected UpdateType $type,
        protected string $slug,
    ) {
    }

    /**
     * Check for available updates (with caching).
     *
     * @since 1.0.0
     *
     * @return UpdateInfo Update information
     */
    public function checkForUpdate(): UpdateInfo
    {
        $cacheKey = "cms.{$this->type->value}.{$this->slug}.update_check";
        $cacheTtl = config( 'cms.updates.cache_ttl', 43200 );

        if ( config( 'cms.updates.cache_enabled', true ) ) {
            $cached = Cache::get( $cacheKey );

            // Discard cached UpdateInfo whose `currentVersion` snapshot
            // disagrees with the currently installed version. Without this, an
            // out-of-band version bump (manual `composer install`,
            // unzip-over-site, deploy script) leaves the cache serving a stale
            // "update available to X" for a site already on X, up to
            // `cms.updates.cache_ttl` seconds. Belt-and-suspenders with
            // `UpdateInfo::hasUpdate()`, which also re-reads the current
            // version at call time. Hosts that never set `app.version` — in
            // which case we have nothing fresher to compare against — keep
            // the pre-2.5.3 "serve cache until TTL" behavior.
            if ( $cached instanceof UpdateInfo && ! $this->cacheIsStale( $cached ) ) {
                return $cached;
            }
        }

        $updateInfo = $this->source->checkForUpdate();

        if ( config( 'cms.updates.cache_enabled', true ) ) {
            Cache::put( $cacheKey, $updateInfo, $cacheTtl );
        }

        return $updateInfo;
    }

    /**
     * Download the update.
     *
     * @since 1.0.0
     *
     * @param  string  $version  Version to download
     *
     * @return string Path to downloaded ZIP file
     */
    public function downloadUpdate( string $version ): string
    {
        return $this->source->downloadUpdate( $version );
    }

    /**
     * Set authentication credentials.
     *
     * @since 1.0.0
     *
     * @param  array|string  $credentials  Authentication credentials
     *
     * @return $this
     */
    public function setAuthentication( string|array $credentials ): self
    {
        $this->source->setAuthentication( $credentials );

        return $this;
    }

    /**
     * Get the update source name.
     *
     * @since 1.0.0
     *
     * @return string Source name
     */
    public function getSourceName(): string
    {
        return $this->source->getName();
    }

    /**
     * Clear the update check cache.
     *
     * @since 1.0.0
     */
    public function clearCache(): void
    {
        $cacheKey = "cms.{$this->type->value}.{$this->slug}.update_check";
        Cache::forget( $cacheKey );
    }

    /**
     * Get the update type.
     *
     * @since 1.0.0
     *
     * @return UpdateType Update type
     */
    public function getType(): UpdateType
    {
        return $this->type;
    }

    /**
     * Get the item slug.
     *
     * @since 1.0.0
     *
     * @return string Item slug
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Decide whether a cached `UpdateInfo` should be evicted because the host's
     * installed version has moved on out-of-band. When `app.version` is unset
     * we have nothing fresher to compare against, so treat the cache as fresh.
     *
     * @since 2.5.3
     */
    protected function cacheIsStale( UpdateInfo $cached ): bool
    {
        $configured = config( 'app.version' );

        if ( ! is_string( $configured ) || '' === $configured ) {
            return false;
        }

        return $cached->currentVersion !== $configured;
    }
}
