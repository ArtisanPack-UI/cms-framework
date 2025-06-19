<?php
/**
 * Base class for ArtisanPack UI CMS themes.
 *
 * All themes developed for this framework should extend this abstract class.
 * It provides a structured way to define theme metadata, register hooks,
 * and manage theme-specific resources.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Theme
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Themes;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Abstract Theme class for ArtisanPack UI CMS themes.
 *
 * This class defines the foundational structure and methods that all concrete
 * theme implementations must adhere to. It ensures consistent handling of
 * theme properties and lifecycle events.
 *
 * @since 1.0.0
 */
abstract class Theme
{
    /**
     * The human-friendly name of the theme (e.g., "ArtisanBlog Theme").
     * This is displayed in the admin UI.
     *
     * @since 1.0.0
     * @var string
     */
    public string $name;

    /**
     * The unique, URL-friendly slug of the theme (e.g., "artisan-blog").
     * This must be unique across all themes and is used internally for identification.
     *
     * @since 1.0.0
     * @var string
     */
    public string $slug;

    /**
     * The current version of the theme (e.g., "1.0.0").
     *
     * @since 1.0.0
     * @var string
     */
    public string $version = '1.0.0';

    /**
     * The author of the theme.
     *
     * @since 1.0.0
     * @var string
     */
    public string $author = 'Unknown';

    /**
     * The website of the theme author or project.
     *
     * @since 1.0.0
     * @var string|null
     */
    public string | null $website;

    /**
     * A short description of what the theme does.
     *
     * @since 1.0.0
     * @var string|null
     */
    public string | null $description;

    /**
     * The Composer package name of the theme (e.g., 'vendor/theme-name').
     * This is automatically set by the framework's ThemeManager during installation/loading.
     *
     * @since 1.0.0
     * @internal
     * @var string
     */
    public string $composerPackageName;

    /**
     * The directory name of the theme on disk (relative to the themes root).
     * This is automatically set by the framework's ThemeManager.
     *
     * @since 1.0.0
     * @internal
     * @var string
     */
    public string $directoryName;

    /**
     * The fully qualified class name of this theme instance.
     * This is automatically set by the framework's ThemeManager.
     *
     * @since 1.0.0
     * @internal
     * @var string
     */
    public string $themeClass;

    /**
     * Determines if the theme is currently active in the CMS.
     * This is automatically set by the framework's ThemeManager.
     *
     * @since 1.0.0
     * @internal
     * @var bool
     */
    public bool $isActive;

    /**
     * Theme constructor.
     *
     * Ensures required properties are set at instantiation and standardizes the slug.
     *
     * @since 1.0.0
     *
     * @throws InvalidArgumentException If the 'name' or 'slug' properties are not defined.
     */
    public function __CONSTRUCT()
    {
        if ( empty( $this->name ) || empty( $this->slug ) ) {
            throw new InvalidArgumentException(
                "Theme must define a 'name' and 'slug' property in its main Theme.php class."
            );
        }
        // Ensure slug is in proper format.
        $this->slug = Str::slug( $this->slug );
    }

    /**
     * Register any theme-specific services, bindings, or perform early setup.
     *
     * This method is called during theme activation, before `boot()`.
     * It is analogous to a Service Provider's `register` method, allowing themes
     * to bind their own components to the Laravel container.
     *
     * @since 1.0.0
     * @return void
     */
    public function register(): void
    {
        // Implement in concrete theme classes to register services.
    }

    /**
     * Bootstrap any theme-specific services or hooks.
     *
     * This method is called after all themes (and plugins) have been registered,
     * during activation. This is where Eventy hooks, routes, views, etc.,
     * are typically loaded. It is analogous to a Service Provider's `boot` method.
     *
     * @since 1.0.0
     * @return void
     */
    public function boot(): void
    {
        // Implement in concrete theme classes to bootstrap functionality.
    }

    /**
     * Define any database migrations for the theme.
     *
     * These paths should be relative to the theme's root directory.
     * Example: `['database/migrations']`
     *
     * @since 1.0.0
     * @return array An array of paths to migration directories.
     */
    public function registerMigrations(): array
    {
        return [];
    }

    /**
     * Define any settings that this theme introduces.
     *
     * These settings will be automatically registered with the framework's SettingsManager.
     * This is useful for theme-specific options that can be configured via the CMS.
     *
     * @since 1.0.0
     * @return array An array of setting definitions. Each array item should be:
     * `[ 'key' => 'my_theme.some_option', 'default' => 'value', 'type' => 'string', 'description' => 'A description.'
     * ]`
     */
    public function registerSettings(): array
    {
        return [];
    }

    /**
     * Define any permissions this theme introduces.
     *
     * This could be used by your framework's permission system to grant
     * granular control over theme-specific functionalities.
     *
     * @since 1.0.0
     * @return array An array of permission definitions. Example:
     * `[ 'my_theme.manage_options' => [ 'label' => 'Manage My Theme Options', 'description' => 'Allows users to manage
     * options for My Theme.' ] ]`
     */
    public function registerPermissions(): array
    {
        return [];
    }

    /**
     * Returns the base URL for theme assets.
     *
     * This helper function provides the correct base URL for assets within the theme's
     * directory, considering the current application environment.
     *
     * @since 1.0.0
     *
     * @param string $path Optional. Path to the asset relative to the theme's root public directory. Default empty.
     * @return string The full URL to the theme asset.
     */
    public function asset( string $path = '' ): string
    {
        // This assumes your themes are directly accessible under a 'themes' public path.
        // Adjust 'themes' if your public theme assets are served from a different path.
        return asset( 'themes/' . $this->directoryName . '/' . ltrim( $path, '/' ) );
    }

    /**
     * Returns the full path to a file within the theme's directory.
     *
     * This helper function provides the absolute file system path to files
     * within the theme, useful for includes or other file operations.
     *
     * @since 1.0.0
     *
     * @param string $path Optional. Path to the file relative to the theme's root directory. Default empty.
     * @return string The full file system path to the theme file.
     */
    public function path( string $path = '' ): string
    {
        // This assumes your Theme class can resolve its own root directory.
        // If not, ThemeManager would need to inject the theme's root path.
        $reflection = new \ReflectionClass( $this );
        $themeRoot  = dirname( $reflection->getFileName(), 2 ); // Go up two levels from src/Theme.php

        return rtrim( $themeRoot, '/' ) . '/' . ltrim( $path, '/' );
    }
}
