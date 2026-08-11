<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Managers;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCheckerFactory;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\IncompatiblePluginException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;
use ZipArchive;

class UpdateManager
{
    public function __construct(
        private PluginManager $pluginManager,
    ) {
    }

    /**
     * Check for updates for all active plugins.
     *
     * @return array Array of plugins with available updates
     */
    public function checkForUpdates(): array
    {
        $plugins = Plugin::all();
        $updates = [];

        foreach ( $plugins as $plugin ) {
            $updateInfo = $this->checkPluginUpdate( $plugin->slug );

            if ( $updateInfo ) {
                $updates[ $plugin->slug ] = $updateInfo;
            }
        }

        return $updates;
    }

    /**
     * Check update for specific plugin.
     *
     * Two resolution paths, in priority order:
     *
     * 1. The manifest declares an `update` object — the check runs through
     *    `UpdateCheckerFactory`, so a plugin published on GitHub or GitLab
     *    Releases needs no hosted JSON feed and gets checksum metadata for
     *    free.
     * 2. The manifest declares only the legacy `update_url` — the original
     *    custom-JSON behavior, unchanged.
     *
     * The return shape stays an array keyed `version` / `download_url` in both
     * cases so `GET /api/v1/plugins/updates` is unaffected; source-backed
     * checks add `sha256` and friends alongside those keys.
     *
     * @param  string  $slug  Plugin slug
     *
     * @return array|null Update info or null if no update available
     */
    public function checkPluginUpdate( string $slug ): ?array
    {
        $plugin = Plugin::where( 'slug', sanitizeText( $slug ) )->first();

        if ( ! $plugin ) {
            return null;
        }

        $sourceUrl = $this->resolveUpdateSourceUrl( $plugin );

        if ( null === $sourceUrl && ! isset( $plugin->meta['update_url'] ) ) {
            return null;
        }

        $cacheKey = "plugin.update.{$slug}";

        return Cache::remember( $cacheKey, config( 'cms.plugins.updateCacheTtl' ), function () use ( $plugin, $sourceUrl ) {
            try {
                return null !== $sourceUrl
                    ? $this->checkViaUpdateSource( $plugin )
                    : $this->checkViaCustomFeed( $plugin );
            } catch ( Exception $e ) {
                logger()->error( "Failed to check update for plugin: {$plugin->slug}", [
                    'exception' => $e->getMessage(),
                ] );
            }

            return null;
        } );
    }

    /**
     * Resolve the update-source URL declared by a plugin manifest's `update` key.
     *
     * Accepts either `{"update": {"github": "owner/repo"}}` — normalized to a
     * github.com URL — or `{"update": {"url": "https://..."}}`, which is handed
     * to `UpdateCheckerFactory` verbatim so the GitLab and custom-JSON sources
     * fall out of the same key.
     *
     * The https requirement is re-checked here rather than trusted from
     * install-time validation. `updatePlugin()` refreshes `meta` straight from
     * the manifest inside the downloaded ZIP without re-running
     * `PluginManager::validateManifest()`, so an update can seat a value that
     * never passed validation. A plaintext source is not a cosmetic problem:
     * `CustomJsonUpdateSource` would fetch the update metadata over http, and
     * a network attacker rewriting that response chooses both the download URL
     * and the `sha256` it is checked against — the digest and the archive come
     * from the same document, so verification would confirm the attacker's own
     * archive. Refusing the source is the only safe reading.
     *
     * @param  Plugin  $plugin  Plugin whose manifest to read.
     *
     * @return string|null Absolute https URL, or null when the manifest declares no usable source.
     */
    public function resolveUpdateSourceUrl( Plugin $plugin ): ?string
    {
        $update = $plugin->meta['update'] ?? null;

        if ( ! is_array( $update ) ) {
            return null;
        }

        $url = $this->nonEmptyString( $update['url'] ?? null );

        if ( null !== $url ) {
            return $this->rejectInsecureSource( $url, $plugin->slug );
        }

        $github = $this->nonEmptyString( $update['github'] ?? null );

        if ( null === $github ) {
            return null;
        }

        if ( str_contains( $github, '://' ) ) {
            return $this->rejectInsecureSource( $github, $plugin->slug );
        }

        return 'https://github.com/' . ltrim( $github, '/' );
    }

    /**
     * Update a plugin to latest version.
     *
     * @param  string  $slug  Plugin slug
     *
     * @throws PluginUpdateException On update failure
     *
     * @return bool True on success
     */
    public function updatePlugin( string $slug ): bool
    {
        $plugin = Plugin::where( 'slug', sanitizeText( $slug ) )->first();

        if ( ! $plugin ) {
            throw PluginUpdateException::downloadFailed( $slug );
        }

        $updateInfo = $this->checkPluginUpdate( $slug );

        if ( ! $updateInfo ) {
            return false; // No update available
        }

        $wasActive              = $plugin->is_active;
        $oldVersion             = $plugin->version;
        $oldMeta                = $plugin->meta;
        $oldServiceProvider     = $plugin->service_provider;
        $backupPath             = null;

        doAction( 'ap.cmsFramework.plugin.updating', $slug, $oldVersion, $updateInfo['version'] );

        try {
            // 1. Backup current version
            $backupPath = $this->backupPlugin( $slug );

            // 2. Deactivate if active
            if ( $wasActive ) {
                $this->pluginManager->deactivate( $slug );
            }

            // 3. Download new version
            $zipPath = $this->downloadUpdateArchive( $plugin, $updateInfo );

            // 4. Delete old files
            $pluginPath = base_path( config( 'cms.plugins.directory' ) . '/' . $slug );
            File::deleteDirectory( $pluginPath );

            // 5. Extract new version
            $zip = new ZipArchive;
            $zip->open( $zipPath );
            $zip->extractTo( base_path( config( 'cms.plugins.directory' ) ) );
            $zip->close();

            // 6. Update database
            $manifestPath = $pluginPath . '/plugin.json';
            $manifest     = json_decode( File::get( $manifestPath ), true );

            $plugin->version          = $updateInfo['version'];
            $plugin->meta             = $manifest;
            $plugin->service_provider = $manifest['service_provider'] ?? null;
            $plugin->save();

            // 7. Reactivate if was active
            if ( $wasActive ) {
                $this->pluginManager->activate( $slug );
            }

            // 8. Cleanup
            File::delete( $zipPath );

            doAction( 'ap.cmsFramework.plugin.updated', $slug, $updateInfo['version'] );

            return true;
        } catch ( IncompatiblePluginException $e ) {
            // The DB row was already updated with the new manifest at step 6,
            // but the reactivate at step 7 rejected the new min_host_version.
            // Restore files AND DB so the plugin isn't stranded pointing at a
            // version whose files no longer exist.
            $this->restoreFromBackup( $slug, $backupPath );
            $this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider );

            throw $e;
        } catch ( UpdateException $e ) {
            // A checksum mismatch or an unverifiable archive is not a download
            // failure, and collapsing it into `downloadFailed()` hides the one
            // detail an operator needs to tell a corrupted mirror apart from a
            // release that shipped without a `.sha256` sidecar.
            $this->restoreFromBackup( $slug, $backupPath );
            $this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider );

            throw PluginUpdateException::updateFailed( $slug, $e->getMessage() );
        } catch ( Exception $e ) {
            // Restore from backup on failure
            $this->restoreFromBackup( $slug, $backupPath );
            $this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider );

            throw PluginUpdateException::downloadFailed( $slug );
        }
    }

    /**
     * Pass an update-source URL through only when it is https.
     *
     * @param  string  $url  Declared source URL.
     * @param  string  $slug  Plugin slug, for the log line.
     *
     * @return string|null The URL, or null when it is not https.
     */
    protected function rejectInsecureSource( string $url, string $slug ): ?string
    {
        if ( str_starts_with( strtolower( $url ), 'https://' ) ) {
            return $url;
        }

        logger()->warning( 'Ignoring plugin update source: update sources must use https.', [
            'plugin' => $slug,
            'url'    => $url,
        ] );

        return null;
    }

    /**
     * Check for an update through the shared update-source abstraction.
     *
     * @param  Plugin  $plugin  Plugin to check.
     *
     * @return array|null Normalized update info, or null when already current.
     */
    protected function checkViaUpdateSource( Plugin $plugin ): ?array
    {
        $checker = $this->makeUpdateChecker( $plugin );

        if ( null === $checker ) {
            return null;
        }

        $updateInfo = $checker->checkForUpdate();

        // Deliberately not `UpdateInfo::hasUpdate()`: that method resolves the
        // installed version from `config('app.version')`, which describes the
        // host application and not this plugin.
        if ( ! $this->isUpdateAvailable( $plugin->version, $updateInfo->latestVersion ) ) {
            return null;
        }

        return $this->serializeUpdateInfo( $updateInfo, $plugin->version );
    }

    /**
     * Check for an update through the legacy custom JSON feed.
     *
     * @param  Plugin  $plugin  Plugin to check.
     *
     * @return array|null Raw feed payload, or null when already current.
     */
    protected function checkViaCustomFeed( Plugin $plugin ): ?array
    {
        $response = Http::timeout( config( 'cms.plugins.updateCheckTimeout' ) )
            ->get( $plugin->meta['update_url'] );

        if ( ! $response->successful() ) {
            return null;
        }

        $updateData = $response->json();

        if ( $this->isUpdateAvailable( $plugin->version, $updateData['version'] ?? '' ) ) {
            return $updateData;
        }

        return null;
    }

    /**
     * Build an update checker for a plugin's declared source.
     *
     * @param  Plugin  $plugin  Plugin to build a checker for.
     *
     * @return UpdateChecker|null Configured checker, or null when no source is declared.
     */
    protected function makeUpdateChecker( Plugin $plugin ): ?UpdateChecker
    {
        $sourceUrl = $this->resolveUpdateSourceUrl( $plugin );

        if ( null === $sourceUrl ) {
            return null;
        }

        $checker = UpdateCheckerFactory::buildUpdateChecker(
            $sourceUrl,
            UpdateType::Plugin,
            $plugin->slug,
            $plugin->version,
        );

        $token = $this->resolveUpdateToken( $plugin->slug );

        if ( null !== $token ) {
            $checker->setAuthentication( $token );
        }

        return $checker;
    }

    /**
     * Resolve the access token for a plugin's private update source.
     *
     * Tokens are keyed by plugin slug in config rather than read from the
     * manifest ( which ships inside the distributed ZIP ) and are deliberately
     * not global: a single shared token would be sent to whatever host any
     * installed plugin names in its manifest.
     *
     * @param  string  $slug  Plugin slug.
     *
     * @return string|null Token, or null when the source is public.
     */
    protected function resolveUpdateToken( string $slug ): ?string
    {
        $tokens = config( 'cms.plugins.updateTokens', [] );

        if ( ! is_array( $tokens ) ) {
            return null;
        }

        return $this->nonEmptyString( $tokens[ $slug ] ?? null );
    }

    /**
     * Flatten an `UpdateInfo` onto the array shape the plugin update API has
     * always returned, so normalizing internally does not change the payload at
     * `GET /api/v1/plugins/updates`.
     *
     * @param  UpdateInfo  $updateInfo  Value object from the update source.
     * @param  string  $currentVersion  Installed plugin version.
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
     * Reset the DB row to the pre-update version/manifest/service_provider so
     * a failed update doesn't leave the row pointing at a version whose files
     * were rolled back.
     *
     * @param  array|null  $oldMeta  Prior manifest snapshot.
     */
    protected function revertPluginRow( Plugin $plugin, string $oldVersion, ?array $oldMeta, ?string $oldServiceProvider ): void
    {
        try {
            $plugin->version          = $oldVersion;
            $plugin->meta             = $oldMeta;
            $plugin->service_provider = $oldServiceProvider;
            $plugin->save();
        } catch ( Exception $e ) {
            logger()->error( "Failed reverting plugin row after failed update: {$plugin->slug}", [
                'exception' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Backup plugin before update.
     *
     * @param  string  $slug  Plugin slug
     *
     * @return string Backup path
     */
    protected function backupPlugin( string $slug ): string
    {
        $plugin     = Plugin::where( 'slug', sanitizeText( $slug ) )->first();
        $pluginPath = base_path( config( 'cms.plugins.directory' ) . '/' . $slug );

        $backupDir = storage_path( config( 'cms.plugins.backupPath' ) );
        if ( ! File::exists( $backupDir ) ) {
            File::makeDirectory( $backupDir, 0755, true );
        }

        $backupFile = $backupDir . '/' . $slug . '-' . $plugin->version . '-' . time() . '.zip';

        $zip = new ZipArchive;
        $zip->open( $backupFile, ZipArchive::CREATE );

        $files = File::allFiles( $pluginPath );
        foreach ( $files as $file ) {
            $relativePath = str_replace( $pluginPath . '/', '', $file->getPathname() );
            $zip->addFile( $file->getPathname(), $relativePath );
        }

        $zip->close();

        return $backupFile;
    }

    /**
     * Restore plugin from backup on failure.
     *
     * @param  string  $slug  Plugin slug
     * @param  string|null  $backupPath  Path to backup, or null when the backup step itself failed
     */
    protected function restoreFromBackup( string $slug, ?string $backupPath ): void
    {
        // `backupPlugin()` runs inside the same try block that calls this, so a
        // failure there leaves nothing to restore from. Deleting the plugin
        // directory in that state would destroy a working install.
        if ( null === $backupPath || ! File::exists( $backupPath ) ) {
            return;
        }

        $pluginPath = base_path( config( 'cms.plugins.directory' ) . '/' . $slug );

        // Delete failed update
        if ( File::exists( $pluginPath ) ) {
            File::deleteDirectory( $pluginPath );
        }

        // Extract backup
        $zip = new ZipArchive;
        $zip->open( $backupPath );
        $zip->extractTo( $pluginPath );
        $zip->close();
    }

    /**
     * Download the update archive for a plugin.
     *
     * Source-backed plugins download through the update source itself, which
     * streams the archive to disk via `StreamsDownloadsToDisk` instead of
     * buffering it in memory, enforces https on the download and every
     * redirect, and gives us a digest to verify against. Legacy `update_url`
     * plugins keep the original buffered `Http::get()` path.
     *
     * @param  Plugin  $plugin  Plugin being updated.
     * @param  array  $updateInfo  Update info from `checkPluginUpdate()`.
     *
     * @throws UpdateException When integrity verification fails or is impossible.
     *
     * @return string Path to downloaded file
     */
    protected function downloadUpdateArchive( Plugin $plugin, array $updateInfo ): string
    {
        $checker = $this->makeUpdateChecker( $plugin );

        if ( null === $checker ) {
            return $this->downloadUpdate( $updateInfo['download_url'] );
        }

        $version = (string) $updateInfo['version'];
        $zipPath = $checker->downloadUpdate( $version );

        try {
            $this->verifyArchiveChecksum( $zipPath, $updateInfo['sha256'] ?? null, $version );
        } catch ( Throwable $e ) {
            // The rejected archive is ours to clean up; nothing downstream will
            // ever see this path again.
            File::delete( $zipPath );

            throw $e;
        }

        return $zipPath;
    }

    /**
     * Verify a downloaded archive against the digest its source advertised.
     *
     * Mirrors `ApplicationUpdateManager::maybeVerifyChecksum()`: honors
     * `cms.updates.verify_checksum` and fails closed when no digest is
     * published unless `cms.updates.allow_unverified_updates` is set.
     *
     * Only source-backed updates reach this. Legacy `update_url` feeds have
     * never advertised a digest, so enforcing it there would break every
     * existing custom-feed plugin.
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

        $expected = $this->nonEmptyString( $expectedHash );

        if ( null !== $expected ) {
            $actual = (string) hash_file( 'sha256', $zipPath );

            if ( ! hash_equals( strtolower( $expected ), $actual ) ) {
                throw UpdateException::checksumMismatch( $expected, $actual );
            }

            return;
        }

        if ( ! config( 'cms.updates.allow_unverified_updates', false ) ) {
            throw UpdateException::checksumRequired( $version );
        }

        logger()->warning( 'Skipping plugin update integrity verification: update source did not advertise a SHA-256 checksum.', [
            'version' => $version,
        ] );
    }

    /**
     * Download plugin update from URL.
     *
     * @param  string  $updateUrl  URL to download ZIP
     *
     * @return string Path to downloaded file
     */
    protected function downloadUpdate( string $updateUrl ): string
    {
        $response = Http::timeout( 60 )->get( $updateUrl );

        if ( ! $response->successful() ) {
            throw new Exception( 'Failed to download update' );
        }

        $tempPath = storage_path( 'app/temp-plugin-' . time() . '.zip' );
        File::put( $tempPath, $response->body() );

        return $tempPath;
    }

    /**
     * Compare version numbers.
     *
     * @param  string  $current  Current version
     * @param  string  $available  Available version
     *
     * @return bool True if update available
     */
    protected function isUpdateAvailable( string $current, string $available ): bool
    {
        return version_compare( $available, $current, '>');
    }
}
