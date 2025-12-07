<?php

/**
 * Theme Manager
 *
 * Manages theme discovery, activation, and template resolution for the CMS.
 *
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Managers;

use Artisan;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeNotFoundException;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

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
    /**
     * Constructs the ThemeManager instance.
     *
     * @since 1.0.0
     *
     * @param  SettingsManager  $settingsManager  Settings manager instance.
     */
    public function __construct(
        private SettingsManager $settingsManager,
    ) {}

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
        $cacheEnabled = config('cms.themes.cacheEnabled', true);
        $cacheKey     = config('cms.themes.cacheKey', 'cms.themes.discovered');
        $cacheTtl     = config('cms.themes.cacheTtl', 3600);

        if ($cacheEnabled) {
            $themes = Cache::get($cacheKey);

            if (null !== $themes) {
                return $this->markActiveTheme($themes);
            }
        }

        $themesPath = $this->getThemesPath();
        $themes     = [];

        if (! File::isDirectory($themesPath)) {
            return $themes;
        }

        $directories = File::directories($themesPath);

        foreach ($directories as $directory) {
            if ($this->validateTheme($directory)) {
                $manifestPath = $directory.'/theme.json';
                $manifest     = $this->parseManifest($manifestPath);

                if (! empty($manifest)) {
                    $themes[] = $manifest;
                }
            }
        }

        if ($cacheEnabled) {
            Cache::put($cacheKey, $themes, $cacheTtl);
        }

        return $this->markActiveTheme($themes);
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
            config('cms.themes.default', 'digital-shopfront'),
        );

        if (empty($activeSlug)) {
            return null;
        }

        return $this->getTheme($activeSlug);
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
    public function activateTheme(string $slug): bool
    {
        $theme = $this->getTheme($slug);

        if (null === $theme) {
            throw ThemeNotFoundException::forSlug($slug);
        }

        $this->settingsManager->updateSetting('themes.activeTheme', $slug);

        // Clear theme cache
        $cacheKey = config('cms.themes.cacheKey', 'cms.themes.discovered');
        Cache::forget($cacheKey);

        // Clear view cache
        try {
            Artisan::call('view:clear');
        } catch (Exception $e) {
            // Log the error but don't fail activation
            if (function_exists('logger')) {
                logger()->warning('Failed to clear view cache during theme activation', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return true;
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
    public function getTheme(string $slug): ?array
    {
        // Validate slug to prevent path traversal attacks
        // Only allow alphanumeric characters, hyphens, and underscores
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            return null;
        }

        $themesBasePath = $this->getThemesPath();
        $themePath      = $themesBasePath.'/'.$slug;

        // Resolve real path and verify it's within the themes directory
        $realThemePath = realpath($themePath);

        if (false === $realThemePath) {
            return null;
        }

        $realBasePath = realpath($themesBasePath);

        if (false === $realBasePath || 0 !== strpos($realThemePath, $realBasePath.DIRECTORY_SEPARATOR)) {
            return null;
        }

        // Now safe to proceed with validation and manifest parsing
        $manifestPath = $realThemePath.'/theme.json';

        if (! $this->validateTheme($realThemePath)) {
            return null;
        }

        return $this->parseManifest($manifestPath);
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

        if (null === $activeTheme) {
            return;
        }

        // Defensive check: ensure slug key exists in the manifest
        if (! is_array($activeTheme) || empty($activeTheme['slug'])) {
            return;
        }

        $themePath = $this->getThemesPath().'/'.$activeTheme['slug'];

        if (File::isDirectory($themePath)) {
            // Prepend the theme path to give it priority
            View::getFinder()->prependLocation($themePath);
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
    public function resolveTemplate(string $contentType, ?string $slug = null): string
    {
        // Sanitize inputs to prevent path traversal
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $contentType)) {
            return 'index';
        }

        if (null !== $slug && ! preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            $slug = null;
        }

        $templates = [];

        if (null !== $slug) {
            $templates[] = "single-{$contentType}-{$slug}";
        }

        $templates[] = "single-{$contentType}";
        $templates[] = 'single';
        $templates[] = 'index';

        foreach ($templates as $template) {
            if ($this->templateExists($template)) {
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
    public function templateExists(string $template): bool
    {
        $activeTheme = $this->getActiveTheme();

        if (null === $activeTheme) {
            return false;
        }

        $themePath    = $this->getThemesPath().'/'.$activeTheme['slug'];
        $templatePath = $themePath.'/'.$template.'.blade.php';

        return File::exists($templatePath);
    }

    /**
     * Validates a theme's structure and manifest.
     *
     * Checks that the theme directory exists and contains all required files
     * as specified in the cms.themes.requiredFiles configuration.
     *
     * @since 1.0.0
     *
     * @param  string  $themePath  Absolute path to theme directory.
     *
     * @return bool True if theme is valid, false otherwise.
     */
    protected function validateTheme(string $themePath): bool
    {
        if (! File::isDirectory($themePath)) {
            return false;
        }

        $requiredFiles = config('cms.themes.requiredFiles', ['theme.json']);

        foreach ($requiredFiles as $file) {
            if (! File::exists($themePath.'/'.$file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parses a theme.json manifest file.
     *
     * Reads and decodes the theme manifest JSON file, returning an empty array
     * if the file doesn't exist or contains invalid JSON.
     *
     * @since 1.0.0
     *
     * @param  string  $manifestPath  Absolute path to theme.json file.
     *
     * @return array Parsed manifest data, or empty array on error.
     */
    protected function parseManifest(string $manifestPath): array
    {
        if (! File::exists($manifestPath)) {
            return [];
        }

        $content = File::get($manifestPath);
        $data    = json_decode($content, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return [];
        }

        return $data;
    }

    /**
     * Gets the themes base directory path.
     *
     * Returns the absolute path to the themes directory based on the
     * cms.themes.directory configuration value.
     *
     * @since 1.0.0
     *
     * @return string Absolute path to themes directory.
     */
    protected function getThemesPath(): string
    {
        $directory = config('cms.themes.directory', 'themes');

        return base_path($directory);
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
    protected function markActiveTheme(array $themes): array
    {
        $activeSlug = $this->settingsManager->getSetting(
            'themes.activeTheme',
            config('cms.themes.default', 'digital-shopfront'),
        );

        return array_map(function ($theme) use ($activeSlug) {
            // Defensive check: ensure slug key exists before comparing
            $theme['is_active'] = isset($theme['slug']) && $theme['slug'] === $activeSlug;

            return $theme;
        }, $themes);
    }
}
