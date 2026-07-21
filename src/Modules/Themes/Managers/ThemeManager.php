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
     * Falls back to the default theme configured in cms.themes.default if no theme is set.
     *
     * @since 1.0.0
     *
     * @return array|null Theme manifest array, or null if no theme is active.
     */
    public function getActiveTheme(): ?array
    {
        $activeSlug = $this->settingsManager->getSetting(
            'themes.activeTheme',
            config( 'cms.themes.default', 'digital-shopfront' ),
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
     * Validate a theme ZIP file before extraction.
     *
     * Mirrors the Plugins module's pre-extraction validation: confirms the file
     * exists, has an allowed MIME type, is within the configured size limit,
     * opens as a valid ZIP archive, and contains a theme.json manifest.
     *
     * @since 2.0.0
     *
     * @param  string  $zipPath  Absolute path to the ZIP file.
     *
     * @throws ThemeValidationException If the ZIP is invalid or fails any check.
     */
    protected function validateZip( string $zipPath ): void
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

        $maxSize = config( 'cms.themes.maxUploadSize', 10 * 1024 * 1024 );
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

        $themesBasePath = $this->getThemesPath();
        $realBasePath   = realpath( $themesBasePath );

        if ( false === $realBasePath ) {
            $zip->close();
            throw ThemeInstallationException::extractionFailed( $slug );
        }

        // Reject if the slug directory already exists.
        if ( File::exists( $themesBasePath . '/' . $slug ) ) {
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

        if ( ! $zip->extractTo( $themesBasePath ) ) {
            $zip->close();

            // Clean up any partial files extractTo() may have written before failing.
            $partialPath = $themesBasePath . '/' . $slug;
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
     * Marks the active theme in the themes array.
     *
     * Adds an 'is_active' boolean flag to each theme manifest indicating
     * whether it is the currently active theme. Safely handles themes that
     * may be missing the 'slug' key.
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
            config( 'cms.themes.default', 'digital-shopfront' ),
        );

        return array_map( function ( $theme ) use ( $activeSlug ) {
            // Defensive check: ensure slug key exists before comparing
            $theme['is_active'] = isset( $theme['slug'] ) && $theme['slug'] === $activeSlug;

            return $theme;
        }, $themes);
    }
}
