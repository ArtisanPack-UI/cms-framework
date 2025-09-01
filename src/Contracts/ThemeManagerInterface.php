<?php

declare(strict_types=1);

/**
 * Theme Manager Interface
 *
 * Defines the contract for theme management operations in the CMS framework.
 * This interface provides methods for managing themes, activation, deactivation, and scanning.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Contracts;

use ArtisanPackUI\CMSFramework\Features\Themes\Theme;

/**
 * Theme Manager Interface
 *
 * Defines the contract for theme management operations including theme activation,
 * deactivation, scanning, and loading theme classes.
 *
 * @since 1.0.0
 */
interface ThemeManagerInterface
{
    /**
     * Activate a specific theme by its name.
     *
     * @param  string  $themeName  The name of the theme to activate.
     * @return bool True if the theme was activated successfully, false otherwise.
     */
    public function activateTheme(string $themeName): bool;

    /**
     * Deactivate the currently active theme.
     *
     * @return bool True if the theme was deactivated successfully, false otherwise.
     */
    public function deactivateTheme(): bool;

    /**
     * Get the name of the currently active theme.
     *
     * @return string|null The active theme name if set, null otherwise.
     */
    public function getActiveTheme(): ?string;

    /**
     * Scan the themes directory and return all available themes.
     *
     * @return array Array of available theme information.
     */
    public function scanThemes(): array;

    /**
     * Get the status of a specific theme.
     *
     * @param  string  $themeName  The name of the theme to check.
     * @return string The theme status (e.g., 'active', 'inactive').
     */
    public function getThemeStatus(string $themeName): string;

    /**
     * Load the active theme's main class.
     *
     * @return Theme|null The loaded theme class instance if successful, null otherwise.
     */
    public function loadActiveThemeClass(): ?Theme;

    /**
     * Get the composer package name for a given theme.
     *
     * @param  string  $themeName  The name of the theme.
     * @return string|null The composer package name if found, null otherwise.
     */
    public function getComposerPackageNameForTheme(string $themeName): ?string;
}
