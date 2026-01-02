<?php

/**
 * Themes Service Provider
 *
 * Registers and bootstraps the themes module functionality.
 *
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Providers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\ServiceProvider;

/**
 * Themes Service Provider class.
 *
 * Handles registration and bootstrapping of theme-related services:
 * - Registers ThemeManager as a singleton
 * - Merges theme configuration
 * - Registers theme view paths
 * - Loads theme API routes
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
        // Register ThemeManager as singleton
        $this->app->singleton(ThemeManager::class, function ($app) {
            return new ThemeManager(
                $app->make(SettingsManager::class),
            );
        });

        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/themes.php',
            'cms.themes',
        );
    }

    /**
     * Bootstraps theme services.
     *
     * Performs the following bootstrap operations:
     * - Registers the active theme's view path with Laravel's view finder
     * - Loads theme API routes
     * - Registers the default 'themes.activeTheme' setting with sanitization
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/themes.php' => config_path('cms/themes.php'),
        ], 'cms-themes-config');

        // Register theme view paths early in the boot cycle
        $themeManager = $this->app->make(ThemeManager::class);
        $themeManager->registerThemeViewPath();

        // Load API routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Register default setting
        $settingsManager = $this->app->make(SettingsManager::class);
        $settingsManager->registerSetting(
            'themes.activeTheme',
            config('cms.themes.default', 'digital-shopfront'),
            fn ($value) => is_string($value) ? sanitizeText($value) : '',
            'string',
        );
    }
}
