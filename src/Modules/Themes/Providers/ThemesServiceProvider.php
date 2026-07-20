<?php

/**
 * Themes Service Provider
 *
 * Registers and bootstraps the themes module functionality.
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Providers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Support\EnqueuedAssets;
use ArtisanPackUI\CMSFramework\Modules\Themes\Support\ThemeLoader;
use ArtisanPackUI\CMSFramework\Modules\Themes\Validation\WpThemeJsonValidator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Themes Service Provider class.
 *
 * Handles registration and bootstrapping of theme-related services:
 * - Registers ThemeManager as a singleton
 * - Merges theme configuration
 * - Registers theme view paths
 * - Loads theme API + web routes
 * - Boots the active theme's optional `Theme.php` (per-request lifecycle)
 * - Registers theme-asset Blade directives
 * - Registers default theme settings
 *
 * @since 1.0.0
 */
class ThemesServiceProvider extends ServiceProvider
{
    /**
     * Registers theme services in the container.
     *
     * Binds the ThemeManager as a singleton and merges the theme
     * configuration file into the application's config.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        // Register WP theme.json validator as singleton
        $this->app->singleton( WpThemeJsonValidator::class );

        // Register ThemeManager as singleton
        $this->app->singleton( ThemeManager::class, function ( $app ) {
            return new ThemeManager(
                $app->make( SettingsManager::class ),
                $app->make( WpThemeJsonValidator::class ),
            );
        } );

        // Register ThemeLoader as singleton — one per-request boot pass.
        $this->app->singleton( ThemeLoader::class, function ( $app ) {
            return new ThemeLoader( $app->make( ThemeManager::class ) );
        } );

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/themes.php',
            'cms.themes',
        );
    }

    /**
     * Bootstraps theme services.
     *
     * Performs the following bootstrap operations:
     * - Registers the active theme's view path with Laravel's view finder
     * - Loads theme API + web routes
     * - Boots the active theme's Theme.php (if present)
     * - Registers the theme-asset Blade directives
     * - Registers the default 'themes.activeTheme' setting with sanitization
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes( [
            __DIR__ . '/../config/themes.php' => config_path( 'cms/themes.php' ),
        ], 'cms-themes-config' );

        // Register theme view paths early in the boot cycle
        $themeManager = $this->app->make( ThemeManager::class );
        $themeManager->registerThemeViewPath();

        // Load routes
        $this->loadRoutesFrom( __DIR__ . '/../routes/api.php' );
        $this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );

        // Boot the active theme's Theme.php once per request. `activeTheme()`
        // caches the result, so downstream Blade directives that resolve
        // ThemeLoader hit the cached instance instead of re-running boot().
        $this->app->make( ThemeLoader::class )->activeTheme();

        $this->registerThemeAssetBladeDirectives();

        // Register default setting
        $settingsManager = $this->app->make( SettingsManager::class );
        $settingsManager->registerSetting(
            'themes.activeTheme',
            config( 'cms.themes.default', 'digital-shopfront' ),
            fn ( $value ) => is_string( $value ) ? sanitizeText( $value ) : '',
            SettingType::String,
        );
    }

    /**
     * Render the `@themeFrontendStyles` directive output.
     *
     * @since 2.5.0
     *
     * @internal Blade directive callback; not part of the public API.
     */
    public static function renderFrontendStyles(): string
    {
        [$slug, $entries] = self::resolveEntries( 'frontendStyles' );

        if ( null === $slug ) {
            return '';
        }

        $entries = applyFilters( 'ap.themes.frontendStyles', $entries, $slug );

        if ( ! is_array( $entries ) ) {
            return '';
        }

        return EnqueuedAssets::renderStyles( $slug, $entries );
    }

    /**
     * Render the `@themeEditorStyles` directive output.
     *
     * @since 2.5.0
     *
     * @internal Blade directive callback; not part of the public API.
     */
    public static function renderEditorStyles(): string
    {
        [$slug, $entries] = self::resolveEntries( 'editorStyles' );

        if ( null === $slug ) {
            return '';
        }

        $entries = applyFilters( 'ap.themes.editorStyles', $entries, $slug );

        if ( ! is_array( $entries ) ) {
            return '';
        }

        return EnqueuedAssets::renderStyles( $slug, $entries );
    }

    /**
     * Render the `@themeFrontendScripts` directive output.
     *
     * @since 2.5.0
     *
     * @internal Blade directive callback; not part of the public API.
     */
    public static function renderFrontendScripts(): string
    {
        [$slug, $entries] = self::resolveEntries( 'frontendScripts' );

        if ( null === $slug ) {
            return '';
        }

        $entries = applyFilters( 'ap.themes.frontendScripts', $entries, $slug );

        if ( ! is_array( $entries ) ) {
            return '';
        }

        return EnqueuedAssets::renderScripts( $slug, $entries );
    }

    /**
     * Register the theme-asset Blade directives.
     *
     * All directives no-op when no active theme is loaded or the loaded
     * theme did not extend the Theme base class. Filters:
     *
     *  - `ap.themes.frontendStyles` — mutate the front-end style list
     *  - `ap.themes.editorStyles`   — mutate the editor style list
     *  - `ap.themes.frontendScripts` — mutate the front-end script list
     *
     * All three filters receive `(array $entries, string $slug)`. Third
     * parties may add packages of styles/scripts by hooking these
     * filters without needing to subclass Theme.
     *
     * @since 2.5.0
     */
    protected function registerThemeAssetBladeDirectives(): void
    {
        Blade::directive( 'themeFrontendStyles', function (): string {
            return '<?php echo \\' . self::class . '::renderFrontendStyles(); ?>';
        } );

        Blade::directive( 'themeEditorStyles', function (): string {
            return '<?php echo \\' . self::class . '::renderEditorStyles(); ?>';
        } );

        Blade::directive( 'themeFrontendScripts', function (): string {
            return '<?php echo \\' . self::class . '::renderFrontendScripts(); ?>';
        } );
    }

    /**
     * Resolve the active theme's slug + one of its enqueue lists.
     *
     * Returns `[null, []]` when no theme is loaded so the calling
     * directive can silently emit an empty string instead of crashing
     * a Blade render on the front-end.
     *
     * @since 2.5.0
     *
     * @param  string  $method  Theme method to invoke (`frontendStyles`, etc).
     *
     * @return array{0: string|null, 1: array<int|string, mixed>}
     */
    protected static function resolveEntries( string $method ): array
    {
        $theme = app( ThemeLoader::class )->activeTheme();

        if ( null === $theme ) {
            return [null, []];
        }

        $entries = $theme->{$method}();

        if ( ! is_array( $entries)) {
            $entries = [];
        }

        return [$theme->slug(), $entries];
    }
}
