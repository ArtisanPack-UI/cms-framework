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
     * The underlying update source.
     *
     * @since 2.7.1
     *
     * @return UpdateSourceInterface The configured source.
     */
    public function getSource(): UpdateSourceInterface
    {
        return $this->source;
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
            // Rehydrated from a primitive array rather than cached as an
            // object. `UpdateInfo` controls `downloadUrl` and `sha256`, so on
            // a shared or unauthenticated cache backend a serialized object
            // here is both an RCE-on-next-update primitive and a PHP
            // object-injection sink. Storing plain scalars removes the
            // unserialize step entirely; the values are still only as
            // trustworthy as the cache, but they can no longer instantiate
            // anything.
            $cached = $this->hydrateCachedUpdateInfo( Cache::get( $cacheKey ) );

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
            Cache::put( $cacheKey, $this->dehydrateUpdateInfo( $updateInfo ), $cacheTtl );
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
     * Reduce an `UpdateInfo` to plain scalars for caching.
     *
     * @since 2.7.1
     *
     * @param  UpdateInfo  $updateInfo  Value object to flatten.
     *
     * @return array<string, mixed> Primitive representation.
     */
    protected function dehydrateUpdateInfo( UpdateInfo $updateInfo ): array
    {
        return [
            'currentVersion'      => $updateInfo->currentVersion,
            'latestVersion'       => $updateInfo->latestVersion,
            'downloadUrl'         => $updateInfo->downloadUrl,
            'changelog'           => $updateInfo->changelog,
            'releaseDate'         => $updateInfo->releaseDate,
            'minPhpVersion'       => $updateInfo->minPhpVersion,
            'minFrameworkVersion' => $updateInfo->minFrameworkVersion,
            'sha256'              => $updateInfo->sha256,
            'fileSize'            => $updateInfo->fileSize,
            'metadata'            => $updateInfo->metadata,
        ];
    }

    /**
     * Rebuild an `UpdateInfo` from its cached primitive form.
     *
     * Returns null for anything that is not a well-formed record, so a cache
     * entry written by an older release — or by anyone else with write access
     * to the cache — simply misses rather than being trusted.
     *
     * @since 2.7.1
     *
     * @param  mixed  $cached  Raw cache payload.
     *
     * @return UpdateInfo|null Rehydrated value object, or null when unusable.
     */
    protected function hydrateCachedUpdateInfo( mixed $cached ): ?UpdateInfo
    {
        if ( ! is_array( $cached ) ) {
            return null;
        }

        foreach ( ['currentVersion', 'latestVersion', 'downloadUrl'] as $required ) {
            if ( ! isset( $cached[ $required ] ) || ! is_string( $cached[ $required ] ) ) {
                return null;
            }
        }

        $string = static fn ( $value ): ?string => is_string( $value ) ? $value : null;

        return new UpdateInfo(
            currentVersion: $cached['currentVersion'],
            latestVersion: $cached['latestVersion'],
            downloadUrl: $cached['downloadUrl'],
            changelog: $string( $cached['changelog'] ?? null ),
            releaseDate: $string( $cached['releaseDate'] ?? null ),
            minPhpVersion: $string( $cached['minPhpVersion'] ?? null ),
            minFrameworkVersion: $string( $cached['minFrameworkVersion'] ?? null ),
            sha256: $string( $cached['sha256'] ?? null ),
            fileSize: is_int( $cached['fileSize'] ?? null ) ? $cached['fileSize'] : null,
            metadata: is_array( $cached['metadata'] ?? null ) ? $cached['metadata'] : [],
        );
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
