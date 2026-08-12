<?php

/**
 * Theme Manager
 *
 * Manages theme discovery, activation, and template resolution for the CMS.
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Managers;

use Artisan;
use ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns\HasManifestParsing;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeInstallationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeValidationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Validation\WpThemeJsonValidator;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Throwable;
use ZipArchive;

/**
 * Theme Manager class.
 *
 * Provides core functionality for managing themes including:
 * - Theme discovery and validation
 * - Theme activation and deactivation
 * - Template hierarchy resolution
 * - View path registration
 * - Theme caching
 *
 * @since 1.0.0
 */
class ThemeManager
{
    use HasManifestParsing;

    /**
     * Constructs the ThemeManager instance.
     *
     * @since 1.0.0
     *
     * @param  SettingsManager  $settingsManager  Settings manager instance.
     * @param  WpThemeJsonValidator  $wpThemeJsonValidator  Validator for the WP-shape subset of theme.json.
     */
    public function __construct(
        private SettingsManager $settingsManager,
        private WpThemeJsonValidator $wpThemeJsonValidator,
    ) {
    }

    /**
     * Discovers all themes in the themes directory.
     *
     * Scans the configured themes directory for valid theme installations,
     * validates each theme, parses their manifest files, and caches the results.
     *
     * @since 1.0.0
     *
     * @return array Array of theme manifests with active theme marked.
     */
    public function discoverThemes(): array
    {
        $cacheEnabled = config( 'cms.themes.cacheEnabled', true );
        $cacheKey     = config( 'cms.themes.cacheKey', 'cms.themes.discovered' );
        $cacheTtl     = config( 'cms.themes.cacheTtl', 3600 );

        if ( $cacheEnabled ) {
            $themes = Cache::get( $cacheKey );

            if ( null !== $themes ) {
                return $this->markActiveTheme( $themes );
            }
        }

        $themesPath = $this->getThemesPath();
        $themes     = [];

        if ( ! File::isDirectory( $themesPath ) ) {
            return $themes;
        }

        $directories = File::directories( $themesPath );

        foreach ( $directories as $directory ) {
            if ( $this->validateTheme( $directory ) ) {
                $manifestPath = $directory . '/theme.json';
                $manifest     = $this->parseManifest( $manifestPath );

                if ( null !== $manifest ) {
                    $themes[] = $manifest;
                }
            }
        }

        if ( $cacheEnabled ) {
            Cache::put( $cacheKey, $themes, $cacheTtl );
        }

        return $this->markActiveTheme( $themes );
    }

    /**
     * Gets the currently active theme.
     *
     * Retrieves the active theme slug from settings and returns its manifest data.
     * Falls back to `cms.themes.default` when no theme has been activated, which
     * is itself null unless the host application configures one — so an
     * unconfigured install resolves to null rather than to a guessed slug.
     *
     * @since 1.0.0
     *
     * @return array|null Theme manifest array, or null if no theme is active.
     */
    public function getActiveTheme(): ?array
    {
        $activeSlug = $this->settingsManager->getSetting(
            'themes.activeTheme',
            config( 'cms.themes.default' ),
        );

        if ( empty( $activeSlug ) ) {
            return null;
        }

        return $this->getTheme( $activeSlug );
    }

    /**
     * Activates a theme by its slug.
     *
     * Sets the specified theme as active in the settings, clears theme and view caches,
     * and validates that the theme exists before activation.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Theme slug identifier.
     *
     * @throws ThemeNotFoundException If the theme does not exist.
     *
     * @return bool True on successful activation.
     */
    public function activateTheme( string $slug ): bool
    {
        $theme = $this->getTheme( $slug );

        if ( null === $theme ) {
            throw ThemeNotFoundException::forSlug( $slug );
        }

        // Pre-activation hook: listeners may throw to short-circuit activation
        // before any persistent state changes.
        doAction( 'ap.cmsFramework.theme.activating', $slug, $theme );

        $this->settingsManager->updateSetting( 'themes.activeTheme', $slug );

        // Clear theme cache
        $cacheKey = config( 'cms.themes.cacheKey', 'cms.themes.discovered' );
        Cache::forget( $cacheKey );

        // Clear view cache
        try {
            Artisan::call( 'view:clear' );
        } catch ( Exception $e ) {
            // Log the error but don't fail activation
            if ( function_exists( 'logger' ) ) {
                logger()->warning( 'Failed to clear view cache during theme activation', [
                    'error' => $e->getMessage(),
                ] );
            }
        }

        doAction( 'ap.cmsFramework.theme.activated', $slug, $theme );

        return true;
    }

    /**
     * Installs a theme from an uploaded ZIP archive.
     *
     * Process:
     * 1. Validate the ZIP (file exists, MIME type, size, integrity, contains theme.json).
     * 2. Extract the ZIP into the themes directory, guarding against ZIP-slip /
     *    path-traversal attempts by resolving each entry's destination against the
     *    themes base directory.
     * 3. Validate the extracted theme's manifest against the WP theme.json schema.
     *    If validation fails, the extracted directory is removed before throwing.
     * 4. Invalidate the discovery cache so the new theme is picked up immediately.
     *
     * The first top-level directory in the ZIP becomes the theme slug, matching
     * the Plugins module convention.
     *
     * @since 2.0.0
     *
     * @param  string  $zipPath  Absolute path to the uploaded ZIP file.
     *
     * @throws ThemeValidationException If the ZIP or extracted manifest fails validation.
     * @throws ThemeInstallationException If extraction fails or the slug is already installed.
     *
     * @return array The parsed manifest of the installed theme.
     */
    public function installFromZip( string $zipPath ): array
    {
        $this->validateZip( $zipPath );

        $slug = $this->extractZip( $zipPath );

        $themePath = $this->getThemesPath() . '/' . $slug;

        if ( ! $this->validateTheme( $themePath ) ) {
            // Rollback: remove the freshly extracted directory so we don't leave
            // a half-installed theme on disk.
            File::deleteDirectory( $themePath );

            throw ThemeValidationException::invalidManifest( "Theme '{$slug}' failed schema validation after extraction." );
        }

        $manifest = $this->parseManifest( $themePath . '/theme.json' );

        if ( null === $manifest ) {
            File::deleteDirectory( $themePath );

            throw ThemeValidationException::invalidManifest( "Theme '{$slug}' manifest could not be parsed after extraction." );
        }

        try {
            $this->validateManifest( $manifest );
        } catch ( ThemeValidationException $e ) {
            File::deleteDirectory( $themePath );

            throw $e;
        }

        // `validateManifest()` already enforced that a non-empty,
        // well-formed `slug` is present. Now make sure that slug matches
        // the extracted directory name — `getTheme()`, view-path
        // registration, and `activateTheme()` all key off the directory,
        // so a mismatch would leave the theme installed-but-unreachable.
        if ( $manifest['slug'] !== $slug ) {
            File::deleteDirectory( $themePath );

            throw ThemeValidationException::invalidManifest(
                "Manifest slug '{$manifest['slug']}' must match extracted directory slug '{$slug}'.",
            );
        }

        // Pre-install hook: listeners may throw to abort the install. The
        // extracted directory is rolled back so we never leave a half-installed
        // theme on disk after a vetoed install. We catch Throwable (not just
        // Exception) so that Error/TypeError thrown from listener callbacks
        // also trigger rollback before propagating.
        try {
            doAction( 'ap.cmsFramework.theme.installing', $slug, $manifest );
        } catch ( Throwable $e ) {
            File::deleteDirectory( $themePath );

            throw $e;
        }

        Cache::forget( config( 'cms.themes.cacheKey', 'cms.themes.discovered' ) );

        doAction( 'ap.cmsFramework.theme.installed', $slug, $manifest );

        return $manifest;
    }

    /**
     * Extracts and fully validates an update archive into a staging directory,
     * without touching the installed theme.
     *
     * This is the half of {@see installFromZip()} that an in-place update needs
     * and cannot get from it: the same ZIP validation, ZIP-slip guard, schema
     * validation, strict manifest validation and slug/directory-match assertion,
     * applied to a directory the live site is not serving from. A corrupt or
     * mismatched archive is therefore rejected *before* anything replaces a
     * working theme — the caller only has to swap once this method has returned.
     *
     * The staging root lives inside the themes directory
     * (`{themes}/.updates/{slug}-{uniqid}`) rather than under `storage/`, so
     * {@see swapStagedTheme()} can complete the swap with `rename()` — the two
     * paths are guaranteed to be on the same filesystem even when
     * `cms.themes.directory` points at an external mount. It is skipped by
     * discovery, which requires a `theme.json` at the top level of each
     * directory it scans.
     *
     * The staging directory is removed on any failure. On success the caller
     * owns it and must delete it once the swap is done.
     *
     * @since 2.8.0
     *
     * @param  string  $zipPath  Absolute path to the downloaded update archive.
     * @param  string  $expectedSlug  Slug of the installed theme being updated.
     *
     * @throws ThemeValidationException If the ZIP or extracted manifest fails validation.
     * @throws ThemeInstallationException If extraction fails or a ZIP-slip attempt is detected.
     *
     * @return array{path: string, stagingPath: string, manifest: array} Staged theme path, staging root, and parsed manifest.
     */
    public function stageThemeFromZip( string $zipPath, string $expectedSlug ): array
    {
        // `$expectedSlug` is interpolated into a directory this method creates.
        // The in-tree caller only reaches here after `getTheme()` has already
        // validated the slug, but this is a public entry point on a path that
        // writes to disk — it re-checks rather than inheriting a caller's
        // diligence.
        if ( ! $this->validateSlug( $expectedSlug ) ) {
            throw ThemeValidationException::invalidManifest(
                'Invalid slug format. Use alphanumeric, hyphens, and underscores only.',
            );
        }

        // `cms.themes.maxUploadSize` is an abuse control on the *upload*
        // endpoint, where the archive arrives from whoever is logged in. An
        // update archive comes from the source the theme itself names, and a
        // theme with images and fonts clears 10 MB easily — gating updates on
        // the upload ceiling would make such a theme permanently un-updatable,
        // which is the exact situation this feature exists to fix. Hence a
        // separate, larger ceiling.
        $this->validateZip( $zipPath, (int) config( 'cms.themes.maxUpdateSize', 50 * 1024 * 1024 ) );

        $stagingPath = $this->getStagingPath() . '/' . $expectedSlug . '-' . uniqid();

        File::ensureDirectoryExists( $stagingPath );

        try {
            $slug = $this->extractZipTo( $zipPath, $stagingPath );

            $stagedThemePath = $stagingPath . '/' . $slug;

            if ( ! $this->validateTheme( $stagedThemePath ) ) {
                throw ThemeValidationException::invalidManifest( "Theme '{$slug}' failed schema validation after extraction." );
            }

            $manifest = $this->parseManifest( $stagedThemePath . '/theme.json' );

            if ( null === $manifest ) {
                throw ThemeValidationException::invalidManifest( "Theme '{$slug}' manifest could not be parsed after extraction." );
            }

            $this->validateManifest( $manifest );

            if ( $manifest['slug'] !== $slug ) {
                throw ThemeValidationException::invalidManifest(
                    "Manifest slug '{$manifest['slug']}' must match extracted directory slug '{$slug}'.",
                );
            }

            // The archive must be an update *of this theme*. Without this an
            // update source that starts serving a different theme's ZIP would
            // overwrite the installed theme's directory with a theme whose slug
            // no longer matches the directory it lives in — leaving it
            // installed but unreachable, exactly the failure the
            // slug/directory assertion above exists to prevent.
            if ( $slug !== $expectedSlug ) {
                throw ThemeValidationException::invalidManifest(
                    "Update archive contains theme '{$slug}', but theme '{$expectedSlug}' is being updated.",
                );
            }
        } catch ( Throwable $e ) {
            File::deleteDirectory( $stagingPath );

            throw $e;
        }

        return [
            'path'        => $stagedThemePath,
            'stagingPath' => $stagingPath,
            'manifest'    => $manifest,
        ];
    }

    /**
     * Moves a staged theme directory into place over the installed one.
     *
     * The swap is two `rename()` calls — the installed directory is moved
     * aside, then the staged directory takes its name. Both are metadata-only
     * operations on the same filesystem, so the window during which the active
     * theme's directory does not exist is a single syscall wide rather than
     * however long it takes to delete and re-extract a theme's worth of files.
     * That is what lets an *active* theme be updated without dropping the site
     * into maintenance mode; see the theme-updating guide for the reasoning.
     *
     * If the second rename fails, the first is undone so the installed theme is
     * left exactly as it was.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Slug of the installed theme being replaced.
     * @param  string  $stagedThemePath  Absolute path to the validated staged theme directory.
     *
     * @throws ThemeUpdateException If either rename fails.
     */
    public function swapStagedTheme( string $slug, string $stagedThemePath ): void
    {
        // Both arguments become operands of a `rename()`. Re-checked here for
        // the same reason as in `stageThemeFromZip()`: this is a public entry
        // point, and a slug or path that escaped the themes directory would
        // move a directory the caller never named.
        if ( ! $this->validateSlug( $slug ) ) {
            throw ThemeUpdateException::swapFailed( $slug );
        }

        $stagingRoot     = $this->getStagingPath();
        $realStagedPath  = realpath( $stagedThemePath );
        $realStagingRoot = realpath( $stagingRoot );

        // Strictly inside, not equal: the staging root itself is not a staged
        // theme, and swapping it into place would move every in-flight update
        // directory into the themes tree under one theme's name.
        if ( false === $realStagedPath
            || false === $realStagingRoot
            || $realStagedPath === $realStagingRoot
            || ! str_starts_with( $realStagedPath . '/', $realStagingRoot . '/' ) ) {
            throw ThemeUpdateException::swapFailed( $slug );
        }

        $livePath     = $this->getThemesPath() . '/' . $slug;
        $previousPath = $stagingRoot . '/previous-' . $slug . '-' . uniqid();

        $hadPrevious = File::isDirectory( $livePath );

        if ( $hadPrevious && ! @rename( $livePath, $previousPath ) ) {
            throw ThemeUpdateException::swapFailed( $slug );
        }

        // The resolved path rather than the argument: renaming a symlink would
        // move the link and leave the real staged directory behind.
        if ( ! @rename( $realStagedPath, $livePath ) ) {
            if ( $hadPrevious ) {
                @rename( $previousPath, $livePath );
            }

            throw ThemeUpdateException::swapFailed( $slug );
        }

        if ( $hadPrevious ) {
            File::deleteDirectory( $previousPath );
        }
    }

    /**
     * Gets the staging directory used by in-place theme updates.
     *
     * Created on demand so a host that never updates a theme never grows the
     * directory.
     *
     * @since 2.8.0
     *
     * @return string Absolute path to the staging root.
     */
    public function getStagingPath(): string
    {
        $path = $this->getThemesPath() . '/.updates';

        File::ensureDirectoryExists( $path );

        return $path;
    }

    /**
     * Gets a specific theme by slug.
     *
     * Locates a theme by its slug identifier, validates its structure,
     * and returns the parsed manifest data. Includes security checks to
     * prevent path traversal attacks.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Theme slug identifier (alphanumeric, hyphens, underscores only).
     *
     * @return array|null Theme manifest array, or null if not found, invalid, or contains invalid characters.
     */
    public function getTheme( string $slug ): ?array
    {
        // Validate slug to prevent path traversal attacks
        if ( ! $this->validateSlug( $slug ) ) {
            return null;
        }

        // Resolve and validate path within themes directory
        $themesBasePath = $this->getThemesPath();
        $realThemePath  = $this->resolveSecurePath( $themesBasePath . '/' . $slug, $themesBasePath );

        if ( null === $realThemePath ) {
            return null;
        }

        // Now safe to proceed with validation and manifest parsing
        $manifestPath = $realThemePath . '/theme.json';

        if ( ! $this->validateTheme( $realThemePath ) ) {
            return null;
        }

        return $this->parseManifest( $manifestPath );
    }

    /**
     * Registers the active theme's view path.
     *
     * Called during application boot to prepend the active theme's directory
     * to Laravel's view finder, giving theme templates priority over default views.
     *
     * @since 1.0.0
     */
    public function registerThemeViewPath(): void
    {
        $activeTheme = $this->getActiveTheme();

        if ( null === $activeTheme ) {
            return;
        }

        // Defensive check: ensure slug key exists in the manifest
        if ( ! is_array( $activeTheme ) || empty( $activeTheme['slug'] ) ) {
            return;
        }

        $themePath = $this->getThemesPath() . '/' . $activeTheme['slug'];

        if ( File::isDirectory( $themePath ) ) {
            // Prepend the theme path to give it priority
            View::getFinder()->prependLocation( $themePath );
        }
    }

    /**
     * Resolves the template to use for a given content item.
     *
     * Implements WordPress-style template hierarchy, checking for templates
     * in the following order:
     * 1. single-{contentType}-{slug}.blade.php
     * 2. single-{contentType}.blade.php
     * 3. single.blade.php
     * 4. index.blade.php
     *
     * Includes path traversal protection by validating content type and slug
     * against a whitelist pattern.
     *
     * @since 1.0.0
     *
     * @param  string  $contentType  Content type slug (alphanumeric, hyphens, underscores only).
     * @param  string|null  $slug  Optional. Content slug for specific templates (alphanumeric, hyphens, underscores only). Default null.
     *
     * @return string Template name without .blade.php extension.
     */
    public function resolveTemplate( string $contentType, ?string $slug = null ): string
    {
        // Sanitize inputs to prevent path traversal
        if ( ! $this->validateSlug( $contentType ) ) {
            return 'index';
        }

        if ( null !== $slug && ! $this->validateSlug( $slug ) ) {
            $slug = null;
        }

        $templates = [];

        if ( null !== $slug ) {
            $templates[] = "single-{$contentType}-{$slug}";
        }

        $templates[] = "single-{$contentType}";
        $templates[] = 'single';
        $templates[] = 'index';

        foreach ( $templates as $template ) {
            if ( $this->templateExists( $template ) ) {
                return $template;
            }
        }

        return 'index';
    }

    /**
     * Checks if a template exists in the active theme.
     *
     * Verifies the existence of a template file in the active theme's directory.
     *
     * @since 1.0.0
     *
     * @param  string  $template  Template name without .blade.php extension.
     *
     * @return bool True if template exists, false otherwise.
     */
    public function templateExists( string $template ): bool
    {
        // Validate template name to prevent path traversal
        if ( ! $this->validateSlug( $template ) ) {
            return false;
        }

        $activeTheme = $this->getActiveTheme();

        if ( null === $activeTheme ) {
            return false;
        }

        $themePath    = $this->getThemesPath() . '/' . $activeTheme['slug'];
        $templatePath = $themePath . '/' . $template . '.blade.php';

        return File::exists( $templatePath );
    }

    /**
     * Gets the themes base directory path.
     *
     * Returns the absolute path to the themes directory based on the
     * cms.themes.directory configuration value. Any downstream helper that
     * needs to resolve a theme-relative path (asset controllers, stylesheet
     * readers, template resolvers) should call this method so the whole
     * subsystem stays anchored to the same root — see
     * {@see \ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeStylesheetReader}
     * for one such consumer.
     *
     * @since 1.0.0
     *
     * @return string Absolute path to themes directory.
     */
    public function getThemesPath(): string
    {
        $directory = config( 'cms.themes.directory', 'themes' );

        // Guard against a misconfigured null/empty value collapsing the
        // themes root to the app base — `base_path('')` returns the app
        // root itself, and any valid slug (e.g. `app`, `vendor`) would
        // then bypass the intended themes-directory containment guard.
        if ( ! is_string( $directory ) || '' === $directory ) {
            $directory = 'themes';
        }

        // Honor absolute paths verbatim so a host that mounts themes at
        // an external location (`/opt/themes`, `D:\shared\themes`) points
        // the whole subsystem — templates, patterns, assets, stylesheet
        // reads — at the same directory. `base_path()` would otherwise
        // prepend the app base and produce a non-existent path.
        if ( '/' === $directory[0] || preg_match( '#^[A-Za-z]:[\\\\/]#', $directory ) ) {
            return $directory;
        }

        return base_path( $directory );
    }

    /**
     * Whether a manifest's `update` value is a well-formed update source.
     *
     * The same rules `validateManifest()` enforces, exposed as a predicate for
     * the *read* path. Only themes that arrived through `installFromZip()` or
     * `stageThemeFromZip()` have ever had their manifest strictly validated —
     * a theme copied into `themes/` by hand, which is the deployment style
     * this feature exists to replace, never has. `UpdateManager` therefore
     * cannot assume the `update` key it reads back off disk was ever checked,
     * and asks here rather than re-deriving a weaker rule of its own.
     *
     * @since 2.8.0
     *
     * @param  mixed  $update  Raw `update` value from the manifest.
     *
     * @return bool True when the value is a usable update source declaration.
     */
    public function isUsableUpdateSource( mixed $update ): bool
    {
        return null === $this->checkUpdateSourceManifestField( $update );
    }

    /**
     * Validate a theme ZIP file before extraction.
     *
     * Mirrors the Plugins module's pre-extraction validation: confirms the file
     * exists, has an allowed MIME type, is within the configured size limit,
     * opens as a valid ZIP archive, and contains a theme.json manifest.
     *
     * @since 2.0.0
     *
     * @param  string  $zipPath  Absolute path to the ZIP file.
     * @param  int|null  $maxSize  Size ceiling in bytes. Defaults to `cms.themes.maxUploadSize`.
     *
     * @throws ThemeValidationException If the ZIP is invalid or fails any check.
     */
    protected function validateZip( string $zipPath, ?int $maxSize = null ): void
    {
        if ( ! File::exists( $zipPath ) ) {
            throw ThemeValidationException::invalidZip( 'ZIP file not found.' );
        }

        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        if ( false === $finfo ) {
            throw ThemeValidationException::invalidZip( 'Unable to determine file type.' );
        }

        $mimeType = finfo_file( $finfo, $zipPath );
        finfo_close( $finfo );

        if ( false === $mimeType ) {
            throw ThemeValidationException::invalidZip( 'Unable to read file type information.' );
        }

        $allowedTypes = config( 'cms.themes.allowedMimeTypes', [
            'application/zip',
            'application/x-zip-compressed',
        ] );

        if ( ! in_array( $mimeType, $allowedTypes, true ) ) {
            throw ThemeValidationException::invalidZip( 'Invalid file type. Must be a ZIP file.' );
        }

        $maxSize ??= (int) config( 'cms.themes.maxUploadSize', 10 * 1024 * 1024 );

        if ( filesize( $zipPath ) > $maxSize ) {
            throw ThemeValidationException::invalidZip( 'File size exceeds maximum allowed size.' );
        }

        $zip = new ZipArchive;
        if ( true !== $zip->open( $zipPath ) ) {
            throw ThemeValidationException::invalidZip( 'Invalid or corrupted ZIP file.' );
        }

        $manifestFound = false;
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $filename = $zip->getNameIndex( $i );
            if ( str_ends_with( $filename, 'theme.json' ) ) {
                $manifestFound = true;
                break;
            }
        }

        $zip->close();

        if ( ! $manifestFound ) {
            throw ThemeValidationException::invalidZip( 'Theme manifest (theme.json) not found in ZIP.' );
        }
    }

    /**
     * Extract a validated theme ZIP into the themes directory.
     *
     * The first ZIP entry's top-level directory becomes the theme slug. Each
     * entry is checked for ZIP-slip / path-traversal: any entry whose resolved
     * destination falls outside the themes base directory causes extraction to
     * abort and the partial directory to be removed.
     *
     * @since 2.0.0
     *
     * @param  string  $zipPath  Absolute path to the ZIP file.
     *
     * @throws ThemeInstallationException If extraction fails, the slug already exists, or a ZIP-slip attempt is detected.
     *
     * @return string The extracted theme slug.
     */
    protected function extractZip( string $zipPath ): string
    {
        return $this->extractZipTo( $zipPath, $this->getThemesPath() );
    }

    /**
     * Extract a validated theme ZIP into an arbitrary base directory.
     *
     * Carries the whole of {@see extractZip()}'s guard set — slug derivation
     * from the first entry, rejection of absolute paths and `..` segments,
     * rejection of any entry outside the derived slug directory, and an
     * anchored resolve of each destination against the base directory — with
     * the base directory as a parameter so in-place updates can extract into a
     * staging root instead of over the live themes directory.
     *
     * @since 2.8.0
     *
     * @param  string  $zipPath  Absolute path to the ZIP file.
     * @param  string  $baseDir  Absolute path of the existing directory to extract into.
     *
     * @throws ThemeInstallationException If extraction fails, the slug already exists in the base directory, or a ZIP-slip attempt is detected.
     *
     * @return string The extracted theme slug.
     */
    protected function extractZipTo( string $zipPath, string $baseDir ): string
    {
        $zip = new ZipArchive;
        if ( true !== $zip->open( $zipPath ) ) {
            throw ThemeInstallationException::extractionFailed( 'unknown' );
        }

        $firstEntry = $zip->getNameIndex( 0 );
        if ( false === $firstEntry ) {
            $zip->close();
            throw ThemeInstallationException::extractionFailed( 'unknown' );
        }

        // The first entry must live under a top-level directory (e.g. "my-theme/")
        // so we can derive the slug. Reject ZIPs whose first entry is a bare file.
        if ( ! str_contains( $firstEntry, '/' ) ) {
            $zip->close();
            throw ThemeInstallationException::extractionFailed( 'unknown' );
        }

        $slug = explode( '/', $firstEntry )[0];

        $realBasePath = realpath( $baseDir );

        if ( false === $realBasePath ) {
            $zip->close();
            throw ThemeInstallationException::extractionFailed( $slug );
        }

        // Reject if the slug directory already exists.
        if ( File::exists( $baseDir . '/' . $slug ) ) {
            $zip->close();
            throw ThemeInstallationException::alreadyInstalled( $slug );
        }

        // ZIP-slip guard: anchor every entry's intended destination to the
        // themes base directory. We resolve the *parent* of each destination
        // because the entry itself does not yet exist on disk.
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $entry = $zip->getNameIndex( $i );
            if ( false === $entry ) {
                continue;
            }

            $normalized = str_replace( '\\', '/', $entry );

            // Reject absolute paths and any entry containing traversal segments.
            if ( str_starts_with( $normalized, '/' )
                || preg_match( '#(^|/)\.\.(/|$)#', $normalized ) ) {
                $zip->close();
                throw ThemeInstallationException::pathTraversal( $entry );
            }

            // Every entry must live under the derived slug directory. Without
            // this, a malicious ZIP could ship a second top-level folder that
            // would escape rollback (which only removes the slug directory).
            $topSegment = explode( '/', $normalized, 2 )[0];
            if ( $topSegment !== $slug ) {
                $zip->close();
                throw ThemeInstallationException::pathTraversal( $entry );
            }

            $destination = $realBasePath . '/' . ltrim( $normalized, '/' );

            // Walk up the destination path until we find an existing ancestor,
            // then verify that ancestor sits inside the themes base directory.
            $ancestor = dirname( $destination );
            while ( '' !== $ancestor && '/' !== $ancestor && ! File::exists( $ancestor ) ) {
                $ancestor = dirname( $ancestor );
            }

            $realAncestor = realpath( $ancestor );
            if ( false === $realAncestor || ! str_starts_with( $realAncestor . '/', $realBasePath . '/' ) ) {
                $zip->close();
                throw ThemeInstallationException::pathTraversal( $entry );
            }
        }

        if ( ! $zip->extractTo( $baseDir ) ) {
            $zip->close();

            // Clean up any partial files extractTo() may have written before failing.
            $partialPath = $baseDir . '/' . $slug;
            if ( File::exists( $partialPath ) ) {
                File::deleteDirectory( $partialPath );
            }

            throw ThemeInstallationException::extractionFailed( $slug );
        }

        $zip->close();

        return $slug;
    }

    /**
     * Validates a theme's structure and manifest.
     *
     * Performs three checks:
     * 1. The theme directory exists.
     * 2. All `cms.themes.requiredFiles` entries are present.
     * 3. The `theme.json` manifest passes the pinned WP theme.json schema
     *    (for any WP-shape keys it carries) and the cms-framework
     *    `menus.locations` extension shape.
     *
     * Manifests that fail schema validation are skipped from discovery and
     * a warning is logged naming the offending key.
     *
     * @since 1.0.0
     *
     * @param  string  $themePath  Absolute path to theme directory.
     *
     * @return bool True if theme is valid, false otherwise.
     */
    protected function validateTheme( string $themePath ): bool
    {
        if ( ! File::isDirectory( $themePath ) ) {
            return false;
        }

        $requiredFiles = config( 'cms.themes.requiredFiles', ['theme.json'] );

        foreach ( $requiredFiles as $file ) {
            if ( ! File::exists( $themePath . '/' . $file ) ) {
                return false;
            }
        }

        $manifest = $this->parseManifest( $themePath . '/theme.json' );

        if ( null === $manifest ) {
            Log::warning( 'Theme has invalid theme.json (parse failed).', [
                'path' => $themePath,
            ] );

            return false;
        }

        $result = $this->wpThemeJsonValidator->validate( $manifest );

        if ( ! $result->valid ) {
            Log::warning( 'Theme rejected: theme.json failed schema validation.', [
                'path'         => $themePath,
                'offendingKey' => $result->offendingKey,
                'message'      => $result->message,
            ] );

            return false;
        }

        return true;
    }

    /**
     * Validate a parsed theme.json manifest against the strict schema.
     *
     * Mirrors the Plugins module's required-field enforcement and adds
     * screenshot-path safety and optional-field type checks. Intended for
     * the upload/install path: `discoverThemes()` keeps using the looser
     * WP-shape validator so existing on-disk themes are not broken by
     * stricter rules.
     *
     * Required fields:
     *  - `slug` — alphanumeric, hyphens, underscores (matches Plugins).
     *  - `name` — non-empty string.
     *  - `version` — anchored semver `MAJOR.MINOR.PATCH` (matches Plugins).
     *
     * Optional fields, validated when present:
     *  - `screenshot` — basename only (no path separators) with an
     *    allowlisted image extension (png, jpg, jpeg, webp).
     *  - `requires` — anchored semver.
     *  - `templates.layouts|pages|partials` — arrays of strings.
     *  - `supports.*` — booleans.
     *
     * The `keystone` namespace is reserved for consumer-specific install
     * hints (e.g. `keystone.installer`, `keystone.seed.pages[]`) and is
     * intentionally opaque to the framework.
     *
     * @since 2.0.0
     *
     * @param  array  $manifest  Parsed theme.json contents.
     *
     * @throws ThemeValidationException If any required or optional field fails its check.
     */
    protected function validateManifest( array $manifest ): void
    {
        $required = ['slug', 'name', 'version'];

        foreach ( $required as $field ) {
            $value = $manifest[ $field ] ?? null;

            if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
                throw ThemeValidationException::invalidManifest( "Missing required field: {$field}" );
            }

            if ( ! is_string( $value ) ) {
                throw ThemeValidationException::invalidManifest( "Field '{$field}' must be a string." );
            }
        }

        if ( ! $this->validateSlug( $manifest['slug'] ) ) {
            throw ThemeValidationException::invalidManifest(
                'Invalid slug format. Use alphanumeric, hyphens, and underscores only.',
            );
        }

        // Anchored at end to prevent injection attempts like "1.0.0'; DROP TABLE".
        if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $manifest['version'] ) ) {
            throw ThemeValidationException::invalidManifest(
                'Invalid version format. Use semantic versioning (e.g., 1.0.0).',
            );
        }

        if ( array_key_exists( 'screenshot', $manifest ) ) {
            $screenshot = $manifest['screenshot'];

            if ( ! is_string( $screenshot ) || '' === $screenshot ) {
                throw ThemeValidationException::invalidManifest(
                    "Field 'screenshot' must be a non-empty string.",
                );
            }

            if ( str_contains( $screenshot, '/' ) || str_contains( $screenshot, '\\' ) ) {
                throw ThemeValidationException::invalidManifest(
                    "Field 'screenshot' must be a filename, not a path.",
                );
            }

            $extension         = strtolower( pathinfo( $screenshot, PATHINFO_EXTENSION ) );
            $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];

            if ( ! in_array( $extension, $allowedExtensions, true ) ) {
                throw ThemeValidationException::invalidManifest(
                    "Field 'screenshot' must have an allowed extension (png, jpg, jpeg, webp).",
                );
            }
        }

        if ( array_key_exists( 'requires', $manifest ) ) {
            $requires = $manifest['requires'];

            if ( ! is_string( $requires ) || ! preg_match( '/^\d+\.\d+\.\d+$/', $requires ) ) {
                throw ThemeValidationException::invalidManifest(
                    "Field 'requires' must be a semver string (e.g., 1.0.0).",
                );
            }
        }

        if ( array_key_exists( 'templates', $manifest ) ) {
            if ( ! is_array( $manifest['templates'] ) ) {
                throw ThemeValidationException::invalidManifest(
                    "Field 'templates' must be an object.",
                );
            }

            foreach ( ['layouts', 'pages', 'partials'] as $bucket ) {
                if ( ! array_key_exists( $bucket, $manifest['templates'] ) ) {
                    continue;
                }

                $entries = $manifest['templates'][ $bucket ];

                if ( ! is_array( $entries ) ) {
                    throw ThemeValidationException::invalidManifest(
                        "Field 'templates.{$bucket}' must be an array of strings.",
                    );
                }

                foreach ( $entries as $entry ) {
                    if ( ! is_string( $entry ) ) {
                        throw ThemeValidationException::invalidManifest(
                            "Field 'templates.{$bucket}' must contain strings only.",
                        );
                    }
                }
            }
        }

        if ( array_key_exists( 'supports', $manifest ) ) {
            if ( ! is_array( $manifest['supports'] ) ) {
                throw ThemeValidationException::invalidManifest(
                    "Field 'supports' must be an object.",
                );
            }

            foreach ( $manifest['supports'] as $feature => $value ) {
                if ( ! is_bool( $value ) ) {
                    throw ThemeValidationException::invalidManifest(
                        "Field 'supports.{$feature}' must be a boolean.",
                    );
                }
            }
        }

        if ( array_key_exists( 'update', $manifest ) ) {
            $this->validateUpdateSourceManifestField( $manifest['update'] );
        }

        // Manifest override for the Theme base-class discovery
        // (issue #198). ThemeLoader also runs a runtime
        // reflection-based provenance check to prove the resolved
        // class actually lives in the theme's own Theme.php; the
        // pattern check here rejects obviously malformed values at
        // install time so a bad upload never reaches disk.
        if ( array_key_exists( 'themeClass', $manifest ) ) {
            $themeClass = $manifest['themeClass'];

            if ( ! is_string( $themeClass )
                || 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', ltrim( $themeClass, '\\' ) ) ) {
                throw ThemeValidationException::invalidManifest(
                    "Field 'themeClass' must be a fully-qualified PHP class name.",
                );
            }
        }
    }

    /**
     * Validate the optional `update` manifest key, which declares the source
     * the theme `UpdateManager` resolves updates from.
     *
     * The rules themselves live in `HasManifestParsing` so plugins and themes
     * cannot drift on a key they deliberately spell identically; this wrapper
     * only turns the shared failure reason into a theme-namespaced exception.
     *
     * @since 2.8.0
     *
     * @param  mixed  $update  Raw `update` value from the manifest.
     *
     * @throws ThemeValidationException If the key is malformed.
     */
    protected function validateUpdateSourceManifestField( mixed $update ): void
    {
        $reason = $this->checkUpdateSourceManifestField( $update );

        if ( null !== $reason ) {
            throw ThemeValidationException::invalidManifest( $reason );
        }
    }

    /**
     * Marks the active theme in the themes array.
     *
     * Adds an 'is_active' boolean flag to each theme manifest indicating
     * whether it is the currently active theme. Safely handles themes that
     * may be missing the 'slug' key. When no theme has been activated and
     * `cms.themes.default` is unconfigured, every theme is flagged inactive.
     *
     * @since 1.0.0
     *
     * @param  array  $themes  Array of theme manifests.
     *
     * @return array Themes array with is_active flag added to each theme.
     */
    protected function markActiveTheme( array $themes ): array
    {
        $activeSlug = $this->settingsManager->getSetting(
            'themes.activeTheme',
            config( 'cms.themes.default' ),
        );

        return array_map( function ( $theme ) use ( $activeSlug ) {
            // Defensive check: ensure slug key exists before comparing
            $theme['is_active'] = isset( $theme['slug'] ) && $theme['slug'] === $activeSlug;

            return $theme;
        }, $themes);
    }
}
