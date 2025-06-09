<?php
/**
 * Settings Service Provider
 *
 * Provides the service registration and bootstrapping for the settings feature
 * of the CMS framework. This service provider is responsible for defining
 * the registration and bootstrapping process related to the settings functionality.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Settings
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Settings;

use Illuminate\Support\ServiceProvider;

/**
 * Class for providing settings services
 *
 * Provides the necessary methods to register and boot the settings services within the application.
 *
 * @since 1.0.0
 * @see   ServiceProvider
 */
class SettingsServiceProvider extends ServiceProvider
{

    /**
     * Register settings services
     *
     * Registers the SettingsManager as a singleton service in the application container.
     *
     * @since 1.0.0
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton( SettingsManager::class, function ( $app ) {
            return new SettingsManager();
        } );
    }

    /**
     * Boot settings services
     *
     * Configures the cache prefix for settings to avoid conflicts with other cached data.
     *
     * @since 1.0.0
     * @return void
     */
    public function boot(): void
    {
        config( [ 'cache.stores.file.prefix' => config( 'cache.stores.file.prefix' ) . '.settings_cache' ] );
    }
}
