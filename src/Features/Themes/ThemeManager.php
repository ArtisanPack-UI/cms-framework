<?php
/**
 * Themes Manager
 *
 * Manages the lifecycle of themes within the ArtisanPack UI CMS Framework,
 * including activation, deactivation, and retrieval of theme information.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Themes\ThemeManager
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Themes;

use ArtisanPackUI\CMSFramework\CMSManager;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use TorMorten\Eventy\Facades\Eventy;

// Import CMSManager to access settings

/**
 * Handles theme discovery, activation, and management.
 *
 * This class provides methods to interact with themes installed in the CMS.
 *
 * @since 1.0.0
 */
class ThemeManager
{
    /**
     * The path to the themes directory.
     *
     * @since 1.0.0
     * @var string
     */
    protected string $themesPath;

    /**
     * Constructor.
     *
     * Initializes the theme manager with the themes directory path.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // Adjust this path if your themes directory is located elsewhere.
        $this->themesPath = base_path( 'themes' );
    }

    /**
     * Activates a theme.
     *
     * Sets the specified theme as active in the application settings
     * and attempts to dynamically register its service provider.
     *
     * @since 1.0.0
     *
     * @param string $themeName The directory name of the theme to activate.
     * @return bool True on successful activation, false on failure.
     *
     * @throws InvalidArgumentException If the specified theme is not found.
     */
    public function activateTheme( string $themeName ): bool
    {
        // Deactivate current theme first to ensure only one is active.
        $this->deactivateTheme();

        // Check if theme exists.
        if ( ! array_key_exists( $themeName, $this->scanThemes() ) ) {
            throw new InvalidArgumentException( sprintf( 'Themes "%s" not found.', $themeName ) );
        }

        try {
            // Store active theme using SettingsManager.
            app( CMSManager::class )->settings()->set( 'theme.active', $themeName );

            // Fire an action after theme activation.
            Eventy::action( 'ap.cms.theme.activated', $themeName );

            return true;
        } catch ( Exception $e ) {
            // Log the error for debugging purposes.
            report( $e );
            return false;
        }
    }

    /**
     * Deactivates the currently active theme.
     *
     * Resets the active theme setting to null or a default fallback.
     *
     * @since 1.0.0
     * @return bool True if a theme was deactivated, false if no theme was active.
     */
    public function deactivateTheme(): bool
    {
        $activeTheme = $this->getActiveTheme();
        if ( null !== $activeTheme ) {
            // Set the active theme to null or a default fallback like 'default-artisanpack-theme'.
            // Setting to null effectively deactivates it.
            app( CMSManager::class )->settings()->set( 'theme.active', null );
            Eventy::action( 'ap.cms.theme.deactivated', $activeTheme );
            return true;
        }
        return false;
    }

    /**
     * Returns the name of the currently active theme.
     *
     * Retrieves the active theme from the application settings.
     *
     * @since 1.0.0
     * @return string|null The active theme's directory name, or null if no theme is active.
     */
    public function getActiveTheme(): ?string
    {
        return app( CMSManager::class )->settings()->get( 'theme.active' );
    }

    /**
     * Scans the themes directory and returns a list of available themes.
     *
     * Themes are identified by a `composer.json` file in their root directory.
     *
     * @since 1.0.0
     * @return array An array of theme data, keyed by theme directory name.
     */
    public function scanThemes(): array
    {
        $themes = [];
        if ( ! File::isDirectory( $this->themesPath ) ) {
            return $themes;
        }

        foreach ( File::directories( $this->themesPath ) as $themeDir ) {
            $themeName         = basename( $themeDir );
            $themeComposerPath = $themeDir . '/composer.json';

            if ( File::exists( $themeComposerPath ) ) {
                $composerData = json_decode( File::get( $themeComposerPath ), true );
                // Basic validation: check for name and description.
                if ( isset( $composerData['name'] ) && isset( $composerData['description'] ) ) {
                    $themes[ $themeName ] = [
                        'name'        => $composerData['name'],
                        'description' => $composerData['description'],
                        'path'        => $themeDir,
                        'status'      => $this->getThemeStatus( $themeName ),
                    ];
                }
            }
        }

        return $themes;
    }

    /**
     * Gets the status of a specific theme (active/inactive).
     *
     * @since 1.0.0
     *
     * @param string $themeName The directory name of the theme.
     * @return string 'active' if the theme is currently active, 'inactive' otherwise.
     */
    public function getThemeStatus( string $themeName ): string
    {
        $activeTheme = $this->getActiveTheme();
        return ( $activeTheme === $themeName ) ? 'active' : 'inactive';
    }

    /**
     * Loads the active theme's `Theme` class.
     *
     * This method is responsible for instantiating the main `Theme` class
     * of the active theme, allowing its hooks and functionalities to be registered.
     * It calls the `register()` and `boot()` methods on the theme instance.
     *
     * @since 1.0.0
     * @return Theme|null The instantiated Theme class, or null if no theme is active
     *                                                      or the class is not found.
     */
    public function loadActiveThemeClass(): ?Theme
    {
        $activeThemeName = $this->getActiveTheme();
        if ( null === $activeThemeName ) {
            return null;
        }

        // Assumes theme's main class is in App\Themes\{ThemeName}\Theme.
        $themeClass = 'App\\Themes\\' . Str::studly( $activeThemeName ) . '\\Theme';

        if ( class_exists( $themeClass ) ) {
            /** @var \ArtisanPackUI\CMSFramework\Theme\Theme $themeInstance */
            $themeInstance = app()->make( $themeClass );

            // Populate internal properties (similar to how PluginManager does for plugins).
            $themeInstance->directoryName       = $activeThemeName;
            $themeInstance->themeClass          = $themeClass;
            $themeInstance->isActive            = true;
            $themeInstance->composerPackageName = $this->getComposerPackageNameForTheme( $activeThemeName ); // New helper needed

            // Call register and boot methods on the theme instance.
            $themeInstance->register();
            $themeInstance->boot();

            return $themeInstance;
        }

        return null;
    }

    /**
     * Retrieves the Composer package name for a given theme.
     *
     * @since 1.0.0
     *
     * @param string $themeName The directory name of the theme.
     * @return string|null The Composer package name, or null if not found.
     */
    protected function getComposerPackageNameForTheme( string $themeName ): ?string
    {
        $themeComposerPath = $this->themesPath . '/' . $themeName . '/composer.json';
        if ( File::exists( $themeComposerPath ) ) {
            $composerData = json_decode( File::get( $themeComposerPath ), true );
            return $composerData['name'] ?? null;
        }
        return null;
    }
}
