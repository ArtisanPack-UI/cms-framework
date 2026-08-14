<?php

/**
 * Manages Extension Updates
 *
 * The shared body of the theme and plugin `UpdateManager`s. Both were added in
 * the same release as near-verbatim copies and had already begun to diverge;
 * this trait is their single home for the source-resolution, token, checksum,
 * and version-comparison helpers, parameterized by the two hooks each concrete
 * manager implements. Each manager keeps only its storage-specific glue (how it
 * reads the installed version and manifest, backs up, and restores).
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\ArchiveChecksum;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;

/**
 * Shared update-pipeline helpers for extension (theme / plugin) managers.
 *
 * @since 2.8.0
 */
trait ManagesExtensionUpdates
{
    /**
     * The lowercase noun used in this manager's log lines (e.g. `theme`).
     *
     * @since 2.8.0
     *
     * @return string The log noun.
     */
    abstract protected function updateLogNoun(): string;

    /**
     * The config prefix for this extension type (e.g. `cms.themes`).
     *
     * @since 2.8.0
     *
     * @return string The config prefix.
     */
    abstract protected function updateConfigPrefix(): string;

    /**
     * Pass an update-source URL through only when it is https.
     *
     * A plaintext source is not cosmetic: the custom-JSON source would fetch
     * update metadata over http, and a network attacker rewriting that
     * response chooses both the download URL and the `sha256` it is checked
     * against — the digest and the archive come from the same document, so
     * verification would confirm the attacker's own archive.
     *
     * @since 2.8.0
     *
     * @param  string  $url  Declared source URL.
     * @param  string  $slug  Extension slug, for the log line.
     *
     * @return string|null The URL, or null when it is not https.
     */
    protected function rejectInsecureSource( string $url, string $slug ): ?string
    {
        if ( str_starts_with( strtolower( $url ), 'https://' ) ) {
            return $url;
        }

        logger()->warning( 'Ignoring ' . $this->updateLogNoun() . ' update source: update sources must use https.', [
            $this->updateLogNoun() => $slug,
            'url'                  => $url,
        ] );

        return null;
    }

    /**
     * Resolve the access token for an extension's private update source.
     *
     * Tokens are keyed by slug in config rather than read from the manifest
     * ( which ships inside the distributed ZIP ) and are deliberately not
     * global: a single shared token would be sent to whatever host any
     * installed extension names in its manifest.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Extension slug.
     *
     * @return string|null Token, or null when the source is public.
     */
    protected function resolveUpdateToken( string $slug ): ?string
    {
        $tokens = config( $this->updateConfigPrefix() . '.updateTokens', [] );

        if ( ! is_array( $tokens ) ) {
            return null;
        }

        return $this->nonEmptyString( $tokens[ $slug ] ?? null );
    }

    /**
     * Flatten an `UpdateInfo` onto the array shape the update API returns.
     *
     * Keys match across the theme and plugin endpoints so a host admin UI can
     * render both extension types through one component.
     *
     * @since 2.8.0
     *
     * @param  UpdateInfo  $updateInfo  Value object from the update source.
     * @param  string  $currentVersion  Installed extension version.
     *
     * @return array<string, mixed> Serialized update info.
     */
    protected function serializeUpdateInfo( UpdateInfo $updateInfo, string $currentVersion ): array
    {
        return [
            'version'      => $updateInfo->latestVersion,
            'download_url' => $updateInfo->downloadUrl,
            'current'      => $currentVersion,
            'changelog'    => $updateInfo->changelog,
            'release_date' => $updateInfo->releaseDate,
            'sha256'       => $updateInfo->sha256,
            'file_size'    => $updateInfo->fileSize,
            'metadata'     => $updateInfo->metadata,
        ];
    }

    /**
     * Narrow a mixed manifest/config value to a trimmed non-empty string.
     *
     * @since 2.8.0
     *
     * @param  mixed  $value  Value to narrow.
     *
     * @return string|null Trimmed string, or null when unusable.
     */
    protected function nonEmptyString( mixed $value ): ?string
    {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $trimmed = trim( $value );

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * Compare version numbers.
     *
     * @since 2.8.0
     *
     * @param  string  $current  Current version.
     * @param  string  $available  Available version.
     *
     * @return bool True if an update is available.
     */
    protected function isUpdateAvailable( string $current, string $available ): bool
    {
        return version_compare( $available, $current, '>' );
    }

    /**
     * Verify a downloaded archive against the digest its source advertised.
     *
     * Honors `cms.updates.verify_checksum` and fails closed when no digest is
     * published unless `cms.updates.allow_unverified_updates` is set. The
     * digest is normalized through {@see ArchiveChecksum} so an uppercase or
     * padded value verifies and an unusable one fails closed.
     *
     * @since 2.8.0
     *
     * @param  string  $zipPath  Path to the downloaded archive.
     * @param  mixed  $expectedHash  Digest advertised by the source, if any.
     * @param  string  $version  Version being installed.
     *
     * @throws UpdateException On mismatch, or when no digest is available and unverified updates are disallowed.
     */
    protected function verifyArchiveChecksum( string $zipPath, mixed $expectedHash, string $version ): void
    {
        if ( ! config( 'cms.updates.verify_checksum', true ) ) {
            return;
        }

        $expected = ArchiveChecksum::normalize( $expectedHash );

        if ( null !== $expected ) {
            if ( ! ArchiveChecksum::fileMatches( $zipPath, $expected ) ) {
                throw UpdateException::checksumMismatch( $expected, (string) hash_file( 'sha256', $zipPath ) );
            }

            return;
        }

        if ( ! config( 'cms.updates.allow_unverified_updates', false ) ) {
            throw UpdateException::checksumRequired( $version );
        }

        logger()->warning( 'Skipping ' . $this->updateLogNoun() . ' update integrity verification: update source did not advertise a SHA-256 checksum.', [
            'version' => $version,
        ] );
    }
}
