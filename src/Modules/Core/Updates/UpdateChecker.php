<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\Cache;

/**
 * Update Checker
 *
 * Main API for checking and downloading updates.
 *
 * @since 2.0.0
 */
class UpdateChecker
{
    /**
     * Create a new UpdateChecker instance.
     *
     * @since 2.0.0
     *
     * @param  UpdateSourceInterface  $source  The update source
     * @param  string  $type  Update type (application, plugin, theme)
     * @param  string  $slug  Unique identifier
     */
    public function __construct(
        protected UpdateSourceInterface $source,
        protected string $type,
        protected string $slug,
    ) {}

    /**
     * Check for available updates (with caching).
     *
     * @since 2.0.0
     *
     * @return UpdateInfo Update information
     */
    public function checkForUpdate(): UpdateInfo
    {
        $cacheKey = "cms.{$this->type}.{$this->slug}.update_check";
        $cacheTtl = config('cms.updates.cache_ttl', 43200);

        if (config('cms.updates.cache_enabled', true)) {
            $cached = Cache::get($cacheKey);
            if ($cached instanceof UpdateInfo) {
                return $cached;
            }
        }

        $updateInfo = $this->source->checkForUpdate();

        if (config('cms.updates.cache_enabled', true)) {
            Cache::put($cacheKey, $updateInfo, $cacheTtl);
        }

        return $updateInfo;
    }

    /**
     * Download the update.
     *
     * @since 2.0.0
     *
     * @param  string  $version  Version to download
     *
     * @return string Path to downloaded ZIP file
     */
    public function downloadUpdate(string $version): string
    {
        return $this->source->downloadUpdate($version);
    }

    /**
     * Set authentication credentials.
     *
     * @since 2.0.0
     *
     * @param  array|string  $credentials  Authentication credentials
     *
     * @return $this
     */
    public function setAuthentication(string|array $credentials): self
    {
        $this->source->setAuthentication($credentials);

        return $this;
    }

    /**
     * Get the update source name.
     *
     * @since 2.0.0
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
     * @since 2.0.0
     */
    public function clearCache(): void
    {
        $cacheKey = "cms.{$this->type}.{$this->slug}.update_check";
        Cache::forget($cacheKey);
    }

    /**
     * Get the update type.
     *
     * @since 2.0.0
     *
     * @return string Update type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the item slug.
     *
     * @since 2.0.0
     *
     * @return string Item slug
     */
    public function getSlug(): string
    {
        return $this->slug;
    }
}
