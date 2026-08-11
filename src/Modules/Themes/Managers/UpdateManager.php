<?php

/**
 * Theme Update Manager
 *
 * Checks for and installs in-place updates of installed themes.
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Managers;

use Artisan;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCheckerFactory;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeUpdateException;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Throwable;
use ZipArchive;

/**
 * Theme Update Manager class.
 *
 * Gives themes the update path plugins already have — check, download, verify,
 * install, roll back — built on `UpdateSourceInterface` from the start, so a
 * theme published on GitHub or GitLab Releases needs no hosted JSON feed and a
 * repo-less host can update without an SFTP session.
 *
 * ### Active-theme safety
 *
 * A theme can be *active* while it is being replaced, and every request in
 * flight resolves templates out of its directory. Rather than dropping the site
 * into maintenance mode — the application updater's approach, which is
 * warranted there because it also runs migrations and `composer install` — the
 * update is staged: the archive is downloaded, checksum-verified, extracted and
 * fully validated in `{themes}/.updates/` before anything touches the live
 * directory. The swap itself is two `rename()` calls (see
 * {@see ThemeManager::swapStagedTheme()}), so the window during which the
 * active theme's directory does not exist is a single syscall wide. A bad
 * archive can never delete a working active theme, because it is rejected
 * before the swap is reached.
 *
 * @since 2.8.0
 */
class UpdateManager
{
    /**
     * Constructs the UpdateManager instance.
     *
     * @since 2.8.0
     *
     * @param  ThemeManager  $themeManager  Used to read manifests and to stage and swap theme directories.
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * Check for updates for every installed theme.
     *
     * A theme whose source cannot be reached is logged and skipped rather than
     * failing the whole listing — one unreachable repository must not hide
     * every other theme's available update.
     *
     * @since 2.8.0
     *
     * @return array<string, array> Update info keyed by theme slug; themes with no update are omitted.
     */
    public function checkForUpdates(): array
    {
        $updates = [];

        foreach ( $this->themeManager->discoverThemes() as $theme ) {
            $slug = $theme['slug'] ?? null;

            if ( ! is_string( $slug ) || '' === $slug ) {
                continue;
            }

            try {
                $updateInfo = $this->checkThemeUpdate( $slug );
            } catch ( Exception $e ) {
                logger()->error( "Failed to check update for theme: {$slug}", [
                    'exception' => $e->getMessage(),
                ] );

                continue;
            }

            if ( null !== $updateInfo ) {
                $updates[ $slug ] = $updateInfo;
            }
        }

        return $updates;
    }

    /**
     * Check for an update to a specific theme.
     *
     * Unlike the plugin equivalent there is no legacy custom-feed path to keep
     * working: themes have never had an update mechanism, so every check runs
     * through `UpdateCheckerFactory` and gets checksum metadata for free.
     *
     * A failed check throws rather than returning null. "The source said there
     * is nothing newer" and "we never reached the source" are different
     * answers, and collapsing them makes `updateTheme()` report a
     * rate-limited or timed-out check as "already up to date" — telling an
     * operator their site is current when nothing was ever checked.
     * `checkForUpdates()` catches per theme so the listing still degrades
     * gracefully.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug.
     *
     * @throws UpdateException If the update source could not be reached or returned an unusable response.
     *
     * @return array|null Normalized update info, or null when the theme declares no source, is missing, or is current.
     */
    public function checkThemeUpdate( string $slug ): ?array
    {
        $manifest = $this->themeManager->getTheme( $slug );

        if ( null === $manifest ) {
            return null;
        }

        if ( null === $this->resolveUpdateSourceUrl( $manifest, $slug ) ) {
            return null;
        }

        $cacheKey = $this->updateCacheKey( $slug );
        $cached   = Cache::get( $cacheKey );

        // Deliberately not `Cache::remember()`: it treats a null return as a
        // miss and re-runs the closure, so the overwhelmingly common
        // "no update available" answer would be written and never read back,
        // and `cms.themes.updateCacheTtl` would do nothing. An empty array is
        // stored for that answer instead, which caches like any other value.
        if ( null !== $cached ) {
            return is_array( $cached ) && [] !== $cached ? $cached : null;
        }

        $updateInfo = $this->checkViaUpdateSource( $manifest, $slug );

        // Only reached when the check actually completed — a thrown failure
        // caches nothing, so the next call retries rather than serving
        // "no update" for the next twelve hours.
        Cache::put( $cacheKey, $updateInfo ?? [], config( 'cms.themes.updateCacheTtl', 43200 ) );

        return $updateInfo;
    }

    /**
     * Resolve the update-source URL declared by a theme manifest's `update` key.
     *
     * Accepts either `{"update": {"github": "owner/repo"}}` — normalized to a
     * github.com URL — or `{"update": {"url": "https://..."}}`, which is handed
     * to `UpdateCheckerFactory` verbatim so the GitLab and custom-JSON sources
     * fall out of the same key.
     *
     * The https requirement is re-checked here rather than trusted from
     * install-time validation, because an update seats a new manifest on disk
     * and the next check reads it back. A plaintext source is not a cosmetic
     * problem: `CustomJsonUpdateSource` would fetch the update metadata over
     * http, and a network attacker rewriting that response chooses both the
     * download URL and the `sha256` it is checked against — the digest and the
     * archive come from the same document, so verification would confirm the
     * attacker's own archive.
     *
     * @since 2.8.0
     *
     * @param  array  $manifest  Parsed theme.json contents.
     * @param  string  $slug  Theme slug, for the log line.
     *
     * @return string|null Absolute https URL, or null when the manifest declares no usable source.
     */
    public function resolveUpdateSourceUrl( array $manifest, string $slug ): ?string
    {
        $update = $manifest['update'] ?? null;

        if ( ! is_array( $update ) ) {
            return null;
        }

        // Re-run the manifest rules rather than assume they ever ran. Only
        // themes installed through the ZIP paths have been strictly validated;
        // a theme copied into `themes/` by hand has not, so without this an
        // `update.github` of `../../x` would be interpolated into a github.com
        // URL and reach `GitHubUpdateSource::parseUrl()` as an owner/repo pair.
        if ( ! $this->themeManager->isUsableUpdateSource( $update ) ) {
            logger()->warning( 'Ignoring theme update source: malformed `update` key in theme.json.', [
                'theme' => $slug,
            ] );

            return null;
        }

        $url = $this->nonEmptyString( $update['url'] ?? null );

        if ( null !== $url ) {
            return $this->rejectInsecureSource( $url, $slug );
        }

        $github = $this->nonEmptyString( $update['github'] ?? null );

        if ( null === $github ) {
            return null;
        }

        if ( str_contains( $github, '://' ) ) {
            return $this->rejectInsecureSource( $github, $slug );
        }

        return 'https://github.com/' . ltrim( $github, '/' );
    }

    /**
     * Update an installed theme to the latest published version.
     *
     * Ordering matters more here than it does for plugins, because the theme
     * may be active while it is replaced:
     *
     * 1. Fire `ap.cmsFramework.theme.updating`; listeners may veto by throwing.
     *    Nothing has changed at this point, so a veto costs nothing.
     * 2. Download the archive and verify its checksum.
     * 3. Extract and fully validate into a staging directory.
     * 4. Back up the installed directory to `cms.themes.backupPath`.
     * 5. Swap the staged directory into place.
     * 6. Forget the discovery and update caches, re-register view paths.
     * 7. Fire `ap.cmsFramework.theme.updated`.
     *
     * A failure at or after step 5 restores from the backup and forgets the
     * caches again. The restore deletes the live directory outright before
     * re-extracting the backup, so files the update *added* are removed rather
     * than left orphaned alongside the restored ones (#272).
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug.
     *
     * @throws ThemeNotFoundException If the theme is not installed.
     * @throws ThemeUpdateException On update failure.
     *
     * @return bool True when an update was installed, false when the theme is already current.
     */
    public function updateTheme( string $slug ): bool
    {
        $manifest = $this->themeManager->getTheme( $slug );

        if ( null === $manifest ) {
            throw ThemeNotFoundException::forSlug( $slug );
        }

        $updateInfo = $this->checkThemeUpdate( $slug );

        if ( null === $updateInfo ) {
            return false;
        }

        $oldVersion = (string) ( $manifest['version'] ?? '0.0.0' );
        $newVersion = (string) $updateInfo['version'];

        doAction( 'ap.cmsFramework.theme.updating', $slug, $oldVersion, $newVersion );

        $zipPath       = null;
        $stagingPath   = null;
        $backupPath    = null;
        $swapAttempted = false;
        $newManifest   = [];

        try {
            $zipPath = $this->downloadUpdateArchive( $manifest, $slug, $updateInfo );

            $staged      = $this->themeManager->stageThemeFromZip( $zipPath, $slug );
            $stagingPath = $staged['stagingPath'];
            $newManifest = $staged['manifest'];

            // Backed up only once there is a validated replacement to swap in.
            // Backing up before the download would write a full copy of the
            // theme on every failed download — and a release published without
            // a checksum fails on every retry, so that copy would be written
            // again on each one.
            $backupPath = $this->backupTheme( $slug, $oldVersion );

            // From here the live directory may be disturbed, so every failure
            // past this point has to try the backup. `swapStagedTheme()` undoes
            // its own first rename when the second fails, but it cannot recover
            // when that undo *also* fails — which is exactly when the backup is
            // the only thing standing between a failed update and a site with
            // no theme directory at all.
            $swapAttempted = true;

            $this->themeManager->swapStagedTheme( $slug, $staged['path'] );

            $this->refreshThemeState( $slug );
        } catch ( Throwable $e ) {
            if ( $swapAttempted ) {
                $this->restoreFromBackup( $slug, $backupPath );
                $this->refreshThemeState( $slug );
            }

            throw $this->wrapUpdateFailure( $slug, $e );
        } finally {
            if ( null !== $zipPath && File::exists( $zipPath ) ) {
                File::delete( $zipPath );
            }

            if ( null !== $stagingPath && File::isDirectory( $stagingPath ) ) {
                File::deleteDirectory( $stagingPath );
            }
        }

        // Deliberately outside the try: by this point the new files are swapped
        // in and validated, so a listener throwing here must not revert a
        // completed update. `theme.installing` is the veto hook and
        // `theme.updating` above is its update-path equivalent; the post hooks
        // are notifications, and `installFromZip()` fires `theme.installed`
        // the same way.
        doAction( 'ap.cmsFramework.theme.updated', $slug, $newVersion, $newManifest );

        return true;
    }

    /**
     * Forget the cached update check for a theme.
     *
     * Forgets both this manager's normalized entry and the `UpdateChecker`'s
     * own raw entry, so a check immediately after an update sees disk rather
     * than the pre-update answer.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug.
     */
    public function forgetUpdateCache( string $slug ): void
    {
        Cache::forget( $this->updateCacheKey( $slug ) );
        Cache::forget( 'cms.' . UpdateType::Theme->value . ".{$slug}.update_check" );
    }

    /**
     * Translate an arbitrary update failure into a theme-namespaced exception.
     *
     * A checksum mismatch or an unverifiable archive is not a download failure,
     * and collapsing it into `downloadFailed()` hides the one detail an
     * operator needs to tell a corrupted mirror apart from a release that
     * shipped without a `.sha256` sidecar.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug.
     * @param  Throwable  $e  The original failure.
     *
     * @return Throwable The exception to surface.
     */
    protected function wrapUpdateFailure( string $slug, Throwable $e ): Throwable
    {
        if ( $e instanceof ThemeUpdateException ) {
            return $e;
        }

        if ( $e instanceof UpdateException ) {
            return ThemeUpdateException::updateFailed( $slug, $e->getMessage() );
        }

        if ( $e instanceof Exception ) {
            return ThemeUpdateException::updateFailed( $slug, $e->getMessage() );
        }

        return $e;
    }

    /**
     * Re-sync the framework's view of the themes directory after its contents
     * changed underneath it.
     *
     * Never throws. It is called from both the success path and the rollback
     * path, and on the rollback path a throw would replace the exception
     * describing why the update actually failed with whatever went wrong while
     * clearing a cache. On the success path a throw would trigger a rollback of
     * an update whose files are already correctly in place. Neither trade is
     * worth making for a cache-clearing step, so failures are logged instead.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug whose files changed.
     */
    protected function refreshThemeState( string $slug ): void
    {
        try {
            Cache::forget( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ) );
            $this->forgetUpdateCache( $slug );

            // The view finder memoizes resolved template paths. An update that
            // removed a template would otherwise keep resolving it to a file
            // that no longer exists for the rest of the request.
            View::getFinder()->flush();

            $this->themeManager->registerThemeViewPath();

            $activeTheme = $this->themeManager->getActiveTheme();

            if ( ( $activeTheme['slug'] ?? null ) !== $slug ) {
                return;
            }

            // Templates were replaced at paths Blade has already compiled.
            // Laravel recompiles on mtime, so this is belt-and-suspenders — but
            // a theme whose archive preserved older mtimes would otherwise
            // serve stale compiled views until the next deploy.
            // `activateTheme()` clears the same cache for the same reason.
            Artisan::call( 'view:clear' );
        } catch ( Throwable $e ) {
            logger()->warning( 'Failed to refresh theme state after a theme update', [
                'theme' => $slug,
                'error' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Pass an update-source URL through only when it is https.
     *
     * @since 2.8.0
     *
     * @param  string  $url  Declared source URL.
     * @param  string  $slug  Theme slug, for the log line.
     *
     * @return string|null The URL, or null when it is not https.
     */
    protected function rejectInsecureSource( string $url, string $slug ): ?string
    {
        if ( str_starts_with( strtolower( $url ), 'https://' ) ) {
            return $url;
        }

        logger()->warning( 'Ignoring theme update source: update sources must use https.', [
            'theme' => $slug,
            'url'   => $url,
        ] );

        return null;
    }

    /**
     * Check for an update through the shared update-source abstraction.
     *
     * @since 2.8.0
     *
     * @param  array  $manifest  Parsed theme.json contents.
     * @param  string  $slug  Theme slug.
     *
     * @return array|null Normalized update info, or null when already current.
     */
    protected function checkViaUpdateSource( array $manifest, string $slug ): ?array
    {
        $checker = $this->makeUpdateChecker( $manifest, $slug );

        if ( null === $checker ) {
            return null;
        }

        $currentVersion = (string) ( $manifest['version'] ?? '0.0.0' );
        $updateInfo     = $checker->checkForUpdate();

        // Deliberately not `UpdateInfo::hasUpdate()`: that method resolves the
        // installed version from `config('app.version')`, which describes the
        // host application and not this theme.
        if ( ! $this->isUpdateAvailable( $currentVersion, $updateInfo->latestVersion ) ) {
            return null;
        }

        return $this->serializeUpdateInfo( $updateInfo, $currentVersion );
    }

    /**
     * Build an update checker for a theme's declared source.
     *
     * @since 2.8.0
     *
     * @param  array  $manifest  Parsed theme.json contents.
     * @param  string  $slug  Theme slug.
     *
     * @return UpdateChecker|null Configured checker, or null when no usable source is declared.
     */
    protected function makeUpdateChecker( array $manifest, string $slug ): ?UpdateChecker
    {
        $sourceUrl = $this->resolveUpdateSourceUrl( $manifest, $slug );

        if ( null === $sourceUrl ) {
            return null;
        }

        $checker = UpdateCheckerFactory::buildUpdateChecker(
            $sourceUrl,
            UpdateType::Theme,
            $slug,
            (string) ( $manifest['version'] ?? '0.0.0' ),
        );

        $token = $this->resolveUpdateToken( $slug );

        if ( null !== $token ) {
            $checker->setAuthentication( $token );
        }

        return $checker;
    }

    /**
     * Resolve the access token for a theme's private update source.
     *
     * Tokens are keyed by theme slug in config rather than read from the
     * manifest ( which ships inside the distributed ZIP ) and are deliberately
     * not global: a single shared token would be sent to whatever host any
     * installed theme names in its manifest.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug.
     *
     * @return string|null Token, or null when the source is public.
     */
    protected function resolveUpdateToken( string $slug ): ?string
    {
        $tokens = config( 'cms.themes.updateTokens', [] );

        if ( ! is_array( $tokens ) ) {
            return null;
        }

        return $this->nonEmptyString( $tokens[ $slug ] ?? null );
    }

    /**
     * Flatten an `UpdateInfo` onto the array shape the themes update API
     * returns.
     *
     * Keys match the plugin endpoint's payload so a host admin UI can render
     * both extension types through one component.
     *
     * @since 2.8.0
     *
     * @param  UpdateInfo  $updateInfo  Value object from the update source.
     * @param  string  $currentVersion  Installed theme version.
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
     * Back up an installed theme directory before it is replaced.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug.
     * @param  string  $version  Installed version, used in the backup filename.
     *
     * @throws ThemeUpdateException If the backup archive could not be written.
     *
     * @return string Absolute path to the backup archive.
     */
    protected function backupTheme( string $slug, string $version ): string
    {
        $themePath = $this->themeManager->getThemesPath() . '/' . $slug;
        $backupDir = storage_path( config( 'cms.themes.backupPath', 'theme-backups' ) );

        File::ensureDirectoryExists( $backupDir );

        $backupFile = $backupDir . '/' . $slug . '-' . $version . '-' . time() . '.zip';

        $zip = new ZipArchive;

        if ( true !== $zip->open( $backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            throw ThemeUpdateException::backupFailed( $slug );
        }

        foreach ( File::allFiles( $themePath, true ) as $file ) {
            $relativePath = str_replace( $themePath . '/', '', $file->getPathname() );
            $zip->addFile( $file->getPathname(), $relativePath );
        }

        if ( ! $zip->close() ) {
            throw ThemeUpdateException::backupFailed( $slug );
        }

        return $backupFile;
    }

    /**
     * Restore a theme from its pre-update backup.
     *
     * The live directory is deleted outright before the backup is extracted, so
     * files the failed update *added* do not survive the rollback alongside the
     * restored ones — the orphaned-file defect fixed for the application
     * updater in #272.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug.
     * @param  string|null  $backupPath  Path to the backup archive, or null when the backup step never ran.
     */
    protected function restoreFromBackup( string $slug, ?string $backupPath ): void
    {
        if ( null === $backupPath || ! File::exists( $backupPath ) ) {
            logger()->critical( 'Theme update failed and no backup was available to restore.', [
                'theme'  => $slug,
                'backup' => $backupPath,
            ] );

            return;
        }

        $themePath = $this->themeManager->getThemesPath() . '/' . $slug;

        try {
            $zip = new ZipArchive;

            // Opened before the live directory is removed: a backup that cannot
            // be read is a reason to leave the failed update in place, not to
            // delete it and have nothing to put back.
            if ( true !== $zip->open( $backupPath ) ) {
                logger()->critical( 'Theme update failed and its backup archive could not be opened. Manual intervention required.', [
                    'theme'  => $slug,
                    'backup' => $backupPath,
                ] );

                return;
            }

            if ( File::exists( $themePath ) ) {
                File::deleteDirectory( $themePath );
            }

            $zip->extractTo( $themePath );
            $zip->close();
        } catch ( Exception $e ) {
            logger()->critical( 'Failed to restore theme from backup after a failed update. Manual intervention required.', [
                'theme'     => $slug,
                'backup'    => $backupPath,
                'exception' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Download the update archive for a theme.
     *
     * Downloads through the update source itself, which streams the archive to
     * disk via `StreamsDownloadsToDisk` instead of buffering it in memory and
     * enforces https on the download and every redirect.
     *
     * @since 2.8.0
     *
     * @param  array  $manifest  Parsed theme.json contents.
     * @param  string  $slug  Theme slug.
     * @param  array  $updateInfo  Update info from `checkThemeUpdate()`.
     *
     * @throws ThemeUpdateException When the theme declares no usable update source.
     * @throws UpdateException When integrity verification fails or is impossible.
     *
     * @return string Path to the downloaded archive.
     */
    protected function downloadUpdateArchive( array $manifest, string $slug, array $updateInfo ): string
    {
        $checker = $this->makeUpdateChecker( $manifest, $slug );

        if ( null === $checker ) {
            throw ThemeUpdateException::noUpdateSource( $slug );
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

        logger()->warning( 'Skipping theme update integrity verification: update source did not advertise a SHA-256 checksum.', [
            'version' => $version,
        ] );
    }

    /**
     * The cache key holding a theme's normalized update-check result.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug.
     *
     * @return string Cache key.
     */
    protected function updateCacheKey( string $slug ): string
    {
        return "theme.update.{$slug}";
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
}
