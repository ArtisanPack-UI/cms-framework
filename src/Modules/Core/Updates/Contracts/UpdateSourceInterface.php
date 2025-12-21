<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;

/**
 * Update Source Interface
 *
 * Contract for all update sources (GitHub, GitLab, custom JSON, etc.).
 *
 * @since 1.0.0
 */
interface UpdateSourceInterface
{
    /**
     * Check if this source can handle the given URL.
     *
     * @since 1.0.0
     *
     * @param  string  $url  The update source URL
     *
     * @return bool True if this source supports the URL
     */
    public function supports( string $url ): bool;

    /**
     * Check for available updates.
     *
     * @since 1.0.0
     *
     * @throws UpdateException If update check fails
     *
     * @return UpdateInfo Update information object
     */
    public function checkForUpdate(): UpdateInfo;

    /**
     * Download the specified version.
     *
     * @since 1.0.0
     *
     * @param  string  $version  Version to download
     *
     * @throws UpdateException If download fails
     *
     * @return string Path to downloaded ZIP file
     */
    public function downloadUpdate( string $version ): string;

    /**
     * Set authentication credentials.
     *
     * @since 1.0.0
     *
     * @param  array|string  $credentials  Token string or array of credentials
     */
    public function setAuthentication( string|array $credentials ): void;

    /**
     * Get the source name (for logging/debugging).
     *
     * @since 1.0.0
     *
     * @return string Source name (e.g., "GitHub", "GitLab", "Custom JSON")
     */
    public function getName(): string;
}
