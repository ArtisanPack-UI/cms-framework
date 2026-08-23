<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Managers;

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns\ManagesExtensionUpdates;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\ExtensionArchive;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCheckerFactory;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\ComposerDependencyNotSatisfiedException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\DependencyNotSatisfiedException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\IncompatiblePluginException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginConflictException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginValidationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;
use ZipArchive;

class UpdateManager
{
    use ManagesExtensionUpdates;

    /**
     * @param  PluginManager  $pluginManager  Used to deactivate and reactivate a plugin around an update.
     */
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

        $cacheKey = $this->updateCacheKey( $slug );
        $cached   = Cache::get( $cacheKey );

        // Deliberately not `Cache::remember()`: it treats a null return as a
        // miss and re-runs the closure, so the common "no update available"
        // answer would never be cached, and — worse — a *failed* check (a
        // rate-limited or 5xx source) that returned null would be re-cached and
        // served as "no update" for the whole TTL. An empty array caches the
        // genuine "no update" answer; a thrown check caches nothing and retries.
        if ( null !== $cached ) {
            return is_array( $cached ) && [] !== $cached ? $cached : null;
        }

        try {
            $updateInfo = null !== $sourceUrl
                ? $this->checkViaUpdateSource( $plugin )
                : $this->checkViaCustomFeed( $plugin );
        } catch ( Exception $e ) {
            // A transient failure must not be cached as "no update" — leaving
            // it uncached means the next call retries rather than hiding a real
            // update for twelve hours.
            logger()->error( "Failed to check update for plugin: {$plugin->slug}", [
                'exception' => $e->getMessage(),
            ] );

            return null;
        }

        Cache::put( $cacheKey, $updateInfo ?? [], config( 'cms.plugins.updateCacheTtl' ) );

        return $updateInfo;
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
     * install-time validation. `updatePlugin()` now re-runs
     * `PluginManager::assertManifestValid()` before seating a new manifest
     * (#283), but this method also reads `meta` seated by a pre-#283 framework
     * version or edited in place on disk (which `discoverPlugins()` still loads
     * unvalidated), so a value that never passed validation can still reach
     * here. A plaintext source is not a cosmetic problem:
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

        // Re-run the shared manifest rules rather than assume they ever ran.
        // `updatePlugin()` re-validates a new manifest before seating it (#283),
        // but a value seated by a pre-#283 version or edited in place on disk
        // can still reach here, so without this an `update.github` of `../../x`
        // would be interpolated into a github.com URL and reach
        // `GitHubUpdateSource::parseUrl()` as an owner/repo pair. Mirrors
        // `ThemeManager::resolveUpdateSourceUrl()`.
        if ( ! $this->pluginManager->isUsableUpdateSource( $update ) ) {
            logger()->warning( 'Ignoring plugin update source: malformed `update` key in plugin.json.', [
                'plugin' => $plugin->slug,
            ] );

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

        // Re-compare against the *current* installed version. `checkPluginUpdate`
        // is cached for 12h, so straight after an update the cache still
        // advertises the version just installed; without this guard a second
        // `POST /{slug}/update` would re-run the full backup → download →
        // delete → extract → reactivate cycle for the already-installed
        // version and fire `plugin.updating`/`plugin.updated` needlessly.
        if ( ! $this->isUpdateAvailable( $plugin->version, (string) $updateInfo['version'] ) ) {
            return false;
        }

        $wasActive              = $plugin->is_active;
        $oldVersion             = $plugin->version;
        $oldMeta                = $plugin->meta;
        $oldServiceProvider     = $plugin->service_provider;
        $backupPath             = null;
        $zipPath                = null;

        doAction( 'ap.cmsFramework.plugin.updating', $slug, $oldVersion, $updateInfo['version'] );

        try {
            // 1. Backup current version
            $backupPath = $this->backupPlugin( $slug );

            // 2. Deactivate if active. Forced past the dependents guard (#45):
            //    this is a temporary deactivate/reactivate around an in-place
            //    update, not a teardown, so active dependents must not abort it.
            if ( $wasActive ) {
                $this->pluginManager->deactivate( $slug, true );
            }

            // 3. Download new version
            $zipPath = $this->downloadUpdateArchive( $plugin, $updateInfo );

            // 4. Extract the validated archive over the old files. The archive
            //    is opened and guarded *before* the live directory is removed,
            //    so a rejected update never destroys the installed plugin.
            $pluginsRoot = base_path( config( 'cms.plugins.directory' ) );
            // Build filesystem paths from the trusted database slug, not the raw
            // route parameter, so a value that only matches a row after
            // sanitization can never point extraction at an unvalidated path.
            $pluginPath = $pluginsRoot . '/' . $plugin->slug;

            $this->extractUpdateArchive( $zipPath, $pluginsRoot, $pluginPath, $plugin->slug );

            // 5. Re-validate the new manifest before trusting it. Step 6 seats
            //    `meta` straight from the manifest inside the downloaded ZIP, so
            //    without this an update could install a manifest that would have
            //    been refused at install — a `migrations_path` traversal, an
            //    unprefixed permission, a malformed `update` source (#283). A
            //    rejected manifest throws here and unwinds through the
            //    backup-restore path below rather than leaving the value seated.
            $manifestPath = $pluginPath . '/plugin.json';
            $manifest     = json_decode( File::get( $manifestPath ), true );

            if ( ! is_array( $manifest ) ) {
                throw PluginValidationException::invalidManifest( 'The update archive manifest could not be parsed.' );
            }

            $this->pluginManager->assertManifestValid( $manifest );

            // The update archive must be an update *of this plugin*. Step 6
            // seats `meta` straight from the manifest, and `meta['slug']` (with
            // its permission namespace) is trusted downstream — so a manifest
            // that declared a different `slug` than the plugin being updated
            // would let it claim, and later remove, another plugin's permission
            // rows. `assertManifestValid()` only checks the slug's *format*;
            // this asserts its *identity*, the way the Themes module does after
            // extraction. A mismatch unwinds through the backup-restore path.
            if ( $manifest['slug'] !== $slug ) {
                throw PluginValidationException::invalidManifest(
                    "Update archive manifest declares slug '{$manifest['slug']}', but plugin '{$slug}' is being updated.",
                );
            }

            // 6. Update database
            $plugin->version          = $updateInfo['version'];
            $plugin->meta             = $manifest;
            $plugin->service_provider = $manifest['service_provider'] ?? null;
            $plugin->save();

            // 7. Reactivate if was active
            if ( $wasActive ) {
                $this->pluginManager->activate( $slug );
            }

            // 8. Cleanup. Forget the cached check so it no longer advertises
            //    the version just installed for the rest of the TTL. The
            //    downloaded archive is removed in the `finally` below, which
            //    also covers every rollback path.
            Cache::forget( $this->updateCacheKey( $slug ) );

            doAction( 'ap.cmsFramework.plugin.updated', $slug, $updateInfo['version'] );

            return true;
        } catch ( IncompatiblePluginException $e ) {
            // The DB row was already updated with the new manifest at step 6,
            // but the reactivate at step 7 rejected the new min_host_version.
            // Restore files AND DB so the plugin isn't stranded pointing at a
            // version whose files no longer exist.
            $this->restoreFromBackup( $plugin->slug, $backupPath );
            $this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider, $wasActive );

            throw $e;
        } catch ( DependencyNotSatisfiedException | PluginConflictException | ComposerDependencyNotSatisfiedException $e ) {
            // The reactivate at step 7 rejected the new manifest's `requires` /
            // `conflicts` declaration (#45) or could not satisfy its `composer`
            // block (#323). Unwind exactly like the other post-extraction
            // failures — restore files and revert the row — and surface the
            // dependency reason instead of collapsing it into the generic
            // `downloadFailed()`, which would misreport a satisfiable-dependency
            // problem as a network error.
            $this->restoreFromBackup( $plugin->slug, $backupPath );
            $this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider, $wasActive );

            throw PluginUpdateException::updateFailed( $slug, $e->getMessage() );
        } catch ( UpdateException $e ) {
            // A checksum mismatch or an unverifiable archive is not a download
            // failure, and collapsing it into `downloadFailed()` hides the one
            // detail an operator needs to tell a corrupted mirror apart from a
            // release that shipped without a `.sha256` sidecar.
            $this->restoreFromBackup( $plugin->slug, $backupPath );
            $this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider, $wasActive );

            throw PluginUpdateException::updateFailed( $slug, $e->getMessage() );
        } catch ( PluginValidationException $e ) {
            // The new manifest failed re-validation (#283), so it is refused
            // rather than seated. Unwind like any other post-extraction failure
            // — restore the old files and revert the row — and surface the
            // reason through the type the controller renders verbatim, instead
            // of collapsing it into the generic `downloadFailed()`.
            $this->restoreFromBackup( $plugin->slug, $backupPath );
            $this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider, $wasActive );

            throw PluginUpdateException::updateFailed( $slug, $e->getMessage() );
        } catch ( Exception $e ) {
            // Restore from backup on failure
            $this->restoreFromBackup( $plugin->slug, $backupPath );
            $this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider, $wasActive );

            throw PluginUpdateException::downloadFailed( $slug );
        } finally {
            // Remove the downloaded archive on every outcome — success and each
            // rollback path — so repeated failed updates don't accumulate ZIPs
            // in storage.
            if ( null !== $zipPath ) {
                File::delete( $zipPath );
            }
        }
    }

    /**
     * The log noun for plugin update messages.
     *
     * @since 2.8.0
     *
     * @return string Always `plugin`.
     */
    protected function updateLogNoun(): string
    {
        return 'plugin';
    }

    /**
     * The config prefix for the plugins module.
     *
     * @since 2.8.0
     *
     * @return string Always `cms.plugins`.
     */
    protected function updateConfigPrefix(): string
    {
        return 'cms.plugins';
    }

    /**
     * The cache key holding a plugin's normalized update-check result.
     *
     * @param  string  $slug  Plugin slug.
     *
     * @return string Cache key.
     */
    protected function updateCacheKey( string $slug ): string
    {
        return "plugin.update.{$slug}";
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
        // Enforce https on the feed URL before fetching. The feed response
        // carries both the download URL and the `sha256` it is verified
        // against, so a plaintext feed lets a network attacker choose both —
        // verification would confirm the attacker's own archive. Same reason
        // the source-backed and legacy download paths require https.
        $feedUrl = $this->nonEmptyString( $plugin->meta['update_url'] ?? null );

        if ( null === $feedUrl || null === $this->rejectInsecureSource( $feedUrl, $plugin->slug ) ) {
            return null;
        }

        $response = Http::timeout( config( 'cms.plugins.updateCheckTimeout' ) )
            ->get( $feedUrl );

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
     * Reset the DB row to the pre-update version/manifest/service_provider so
     * a failed update doesn't leave the row pointing at a version whose files
     * were rolled back.
     *
     * @param  array|null  $oldMeta  Prior manifest snapshot.
     * @param  bool  $wasActive  Activation state before the update began.
     */
    protected function revertPluginRow( Plugin $plugin, string $oldVersion, ?array $oldMeta, ?string $oldServiceProvider, bool $wasActive ): void
    {
        try {
            // Reload from the database first. Step 2's deactivate() flipped
            // is_active to false on a *separate* model instance, so this
            // instance's is_active is stale and save() would not write the
            // restore. Refreshing gives save() an accurate baseline so setting
            // is_active back to $wasActive is seen as dirty and persisted.
            $plugin->refresh();

            $plugin->version          = $oldVersion;
            $plugin->meta             = $oldMeta;
            $plugin->service_provider = $oldServiceProvider;
            $plugin->is_active        = $wasActive;
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

        if ( true !== $zip->open( $backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            throw PluginUpdateException::updateFailed( $slug, 'The pre-update backup archive could not be created.' );
        }

        // `allFiles( $path, true )` includes dotfiles (`.env.example`,
        // `.htaccess`); without the flag a plugin shipping them would lose them
        // on rollback.
        foreach ( File::allFiles( $pluginPath, true ) as $file ) {
            $relativePath = str_replace( $pluginPath . '/', '', $file->getPathname() );
            $zip->addFile( $file->getPathname(), $relativePath );
        }

        if ( ! $zip->close() ) {
            throw PluginUpdateException::updateFailed( $slug, 'The pre-update backup archive could not be written.' );
        }

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

        $zip = new ZipArchive;

        // Opened before the live directory is removed: a backup that cannot be
        // read is a reason to leave the failed update in place, not to delete
        // it and have nothing to put back.
        if ( true !== $zip->open( $backupPath ) ) {
            logger()->critical( 'Plugin update failed and its backup archive could not be opened. Manual intervention required.', [
                'plugin' => $slug,
                'backup' => $backupPath,
            ] );

            return;
        }

        // A tampered backup carrying an absolute or `..` path is a reason to
        // abort the restore rather than write it over the plugins directory.
        if ( null !== ExtensionArchive::firstUnsafeEntry( $zip ) ) {
            $zip->close();

            logger()->critical( 'Plugin backup archive contains an unsafe path; refusing to restore. Manual intervention required.', [
                'plugin' => $slug,
                'backup' => $backupPath,
            ] );

            return;
        }

        // Delete failed update
        if ( File::exists( $pluginPath ) ) {
            File::deleteDirectory( $pluginPath );
        }

        if ( true !== $zip->extractTo( $pluginPath ) ) {
            $zip->close();

            logger()->critical( 'Plugin backup extraction failed partway; the plugin directory may be incomplete. Manual intervention required.', [
                'plugin' => $slug,
                'backup' => $backupPath,
            ] );

            return;
        }

        $zip->close();
    }

    /**
     * Extract a validated update archive over an installed plugin's files.
     *
     * The archive is opened and fully guarded — zip-slip rejection, a required
     * slug-directory top segment so a sibling folder cannot overwrite a
     * different trusted plugin, and an uncompressed-size ceiling — *before* the
     * live directory is removed, so a rejected update never destroys the
     * installed plugin.
     *
     * @since 2.8.0
     *
     * @param  string  $zipPath  Path to the downloaded archive.
     * @param  string  $pluginsRoot  Absolute path of the plugins directory.
     * @param  string  $pluginPath  Absolute path of the plugin's own directory.
     * @param  string  $slug  Plugin slug; every archive entry must live under it.
     *
     * @throws UpdateException When the archive is unopenable, unsafe, oversized, or extraction fails.
     */
    protected function extractUpdateArchive( string $zipPath, string $pluginsRoot, string $pluginPath, string $slug ): void
    {
        $zip = new ZipArchive;

        if ( true !== $zip->open( $zipPath ) ) {
            throw new UpdateException( 'The downloaded plugin archive could not be opened.' );
        }

        $realPluginsRoot = realpath( $pluginsRoot );

        if ( false === $realPluginsRoot ) {
            $zip->close();

            throw new UpdateException( 'The plugins directory could not be resolved.' );
        }

        $unsafe = ExtensionArchive::firstUnsafeEntry( $zip, $realPluginsRoot, $slug );

        if ( null !== $unsafe ) {
            $zip->close();

            throw new UpdateException( "The update archive contains an unsafe path: {$unsafe}." );
        }

        $maxUncompressed = (int) config( 'cms.plugins.maxUncompressedSize', 100 * 1024 * 1024 );

        if ( ExtensionArchive::uncompressedSize( $zip ) > $maxUncompressed ) {
            $zip->close();

            throw new UpdateException( 'The update archive expands beyond the permitted uncompressed size.' );
        }

        if ( File::exists( $pluginPath ) ) {
            File::deleteDirectory( $pluginPath );
        }

        if ( true !== $zip->extractTo( $pluginsRoot ) ) {
            $zip->close();

            throw new UpdateException( 'Extraction of the update archive failed.' );
        }

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
        $version = (string) ( $updateInfo['version'] ?? '' );

        if ( null === $checker ) {
            // Legacy `update_url` feed. It bypasses the source abstraction, so
            // the https and integrity guarantees have to be enforced here: an
            // http download URL lets a network attacker choose the archive, and
            // an unverified archive is extracted and its provider re-registered
            // (arbitrary PHP execution). Require https up front, then run the
            // same checksum gate as the source-backed path — a feed that
            // advertises no digest is refused unless `allow_unverified_updates`.
            $downloadUrl = $this->nonEmptyString( $updateInfo['download_url'] ?? null );

            if ( null === $downloadUrl || null === $this->rejectInsecureSource( $downloadUrl, $plugin->slug ) ) {
                throw PluginUpdateException::downloadFailed( $plugin->slug );
            }

            $zipPath = $this->downloadUpdate( $downloadUrl );

            try {
                $this->verifyArchiveChecksum( $zipPath, $updateInfo['sha256'] ?? null, $version );
            } catch ( Throwable $e ) {
                File::delete( $zipPath );

                throw $e;
            }

            return $zipPath;
        }

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
}
