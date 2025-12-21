<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects;

use InvalidArgumentException;

/**
 * Update Information Value Object
 *
 * Immutable object containing update metadata.
 *
 * @since 1.0.0
 */
class UpdateInfo
{
    /**
     * Create a new UpdateInfo instance.
     *
     * @since 1.0.0
     *
     * @param  string  $currentVersion  Current installed version
     * @param  string  $latestVersion  Latest available version
     * @param  bool  $hasUpdate  Whether an update is available
     * @param  string  $downloadUrl  URL to download the update
     * @param  string|null  $changelog  Release notes/changelog
     * @param  string|null  $releaseDate  ISO 8601 release date
     * @param  string|null  $minPhpVersion  Minimum PHP version required
     * @param  string|null  $minFrameworkVersion  Minimum framework version required
     * @param  string|null  $sha256  SHA-256 checksum for verification
     * @param  int|null  $fileSize  File size in bytes
     * @param  array  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $currentVersion,
        public readonly string $latestVersion,
        public readonly bool $hasUpdate,
        public readonly string $downloadUrl,
        public readonly ?string $changelog = null,
        public readonly ?string $releaseDate = null,
        public readonly ?string $minPhpVersion = null,
        public readonly ?string $minFrameworkVersion = null,
        public readonly ?string $sha256 = null,
        public readonly ?int $fileSize = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Create UpdateInfo from array (for JSON responses).
     *
     * @since 1.0.0
     *
     * @param  array  $data  Update data array
     * @param  string  $currentVersion  Current version
     */
    public static function fromArray( array $data, string $currentVersion ): self
    {
        if ( ! isset( $data['version'], $data['download_url'] ) ) {
            throw new InvalidArgumentException( 'Missing required keys: version, download_url' );
        }

        return new self(
            currentVersion: $currentVersion,
            latestVersion: $data['version'],
            hasUpdate: version_compare( $data['version'], $currentVersion, '>' ),
            downloadUrl: $data['download_url'],
            changelog: $data['changelog'] ?? null,
            releaseDate: $data['release_date'] ?? null,
            minPhpVersion: $data['min_php_version'] ?? null,
            minFrameworkVersion: $data['min_framework_version'] ?? null,
            sha256: $data['sha256'] ?? null,
            fileSize: $data['file_size'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Convert to array (for JSON responses).
     *
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return [
            'current'               => $this->currentVersion,
            'latest'                => $this->latestVersion,
            'hasUpdate'             => $this->hasUpdate,
            'download_url'          => $this->downloadUrl,
            'changelog'             => $this->changelog,
            'release_date'          => $this->releaseDate,
            'min_php_version'       => $this->minPhpVersion,
            'min_framework_version' => $this->minFrameworkVersion,
            'sha256'                => $this->sha256,
            'file_size'             => $this->fileSize,
            'metadata'              => $this->metadata,
        ];
    }
}
