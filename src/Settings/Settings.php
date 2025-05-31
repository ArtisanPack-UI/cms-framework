<?php
/**
 * Represents the settings module for the ArtisanPackUI CMS Framework.
 *
 * This class handles registration, retrieval, updating, and deletion of settings.
 * Additionally, it adds custom migration directories for settings in the system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Settings\Settings
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Settings;

use ArtisanPackUI\CMSFramework\Settings\Models\Setting;
use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Represents the settings module for the ArtisanPackUI CMS Framework.
 *
 * Implements the behavior and functionality required for managing settings
 * within the application. This includes functionality for registering,
 * retrieving, updating, and deleting settings, as well as managing related
 * migrations.
 *
 * @since 1.0.0
 */
class Settings implements Module
{

    /**
     * Returns the slug for the module.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @return string The slug for the module.
     */
    public function getSlug(): string
    {
        return 'settings';
    }

    /**
     * Returns an array of functions to be registered with the CMSFramework.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @return array List of functions to register.
     */
    public function functions(): array
    {
        return [
            'registerSetting' => [ $this, 'registerSetting' ],
            'getSetting'      => [ $this, 'getSetting' ],
            'updateSetting'   => [ $this, 'updateSetting' ],
            'getSettings'     => [ $this, 'getSettings' ],
            'deleteSetting'   => [ $this, 'deleteSetting' ],
            'addSetting'      => [ $this, 'addSetting' ],
        ];
    }

    /**
     * Initializes the module.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     */
    public function init(): void
    {
        Eventy::addFilter( 'ap.migrations.directories', [ $this, 'settingsMigrations' ] );
    }

    /**
     * Registers a setting with the CMSFramework.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @param string   $name     The name of the setting.
     * @param string   $value    The value of the setting.
     * @param callable $callback The callback to use for the setting.
     * @param string   $category The category for the setting.
     *
     */
    public function registerSetting( string $name, string $value, callable $callback, string $category = '' ): void
    {
        if ( ! Setting::where( 'key', $name )->exists() ) {
            $this->addSetting( $name, $value, $category );
        }

        Eventy::addFilter( 'ap.settings.settings', function ( array $settings ) use ( $name, $callback ) {
            $settings[ $name ] = $callback;
            return $settings;
        } );
    }

    /**
     * Adds a setting to the database.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @param string $setting The name of the setting to add.
     * @param string $value   The value of the setting to add.
     */
    public function addSetting( string $setting, string $value, string $category = '' ): void
    {
        Setting::create( [
                             'key'      => $setting,
                             'value'    => $value,
                             'category' => $category,
                         ] );
    }

    /**
     * Retrieves all settings from the database.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @param array $args The arguments to filter the settings by.
     * @return array The list of settings.
     */
    public function getSettings( array $args = [] )
    {
        if ( ! empty( $args['category'] ) ) {
            return Setting::where( 'category', $args['category'] )->get();
        }
        return Setting::all();
    }

    /**
     * Retrieves a setting from the database.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @param string $setting The name of the setting to retrieve.
     * @param string $default The default value to return if the setting is not found.
     * @return string The value of the setting, or the default value if not found.
     */
    public function getSetting( string $setting, string $default = '' ): string
    {
        $setting = Setting::where( 'key', $setting )->first();
        if ( $setting ) {
            return $setting->value;
        }
        return $default;
    }

    /**
     * Updates a setting in the database.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @param string $setting The name of the setting to update.
     * @param string $value   The new value for the setting.
     * @return Setting|bool The updated setting, or false if the setting is not found.
     */
    public function updateSetting( string $setting, string $value ): Setting | bool
    {
        $settings = Eventy::filter( 'ap.settings.settings', [] );
        if ( ! isset( $settings[ $setting ] ) ) {
            return false;
        }
        $updatedValue = call_user_func_array( $settings[ $setting ], [ $value ] );
        return Setting::where( 'key', $setting )->update( [ 'value' => $updatedValue ] );
    }

    /**
     * Deletes a setting from the database.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @param string $setting The name of the setting to delete.
     * @return bool|int True if the setting was deleted, or the number of rows deleted if the setting was not found.
     */
    public function deleteSetting( string $setting ): bool | int
    {
        if ( ! Setting::where( 'key', $setting )->exists() ) {
            return false;
        }

        return Setting::destroy( Setting::where( 'key', $setting )->first()->id );
    }

    /**
     * Adds custom migration directories for settings.
     *
     * @since 1.0.0
     *
     * @see   CMSFramework
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @param array $directories The array of migration directories.
     * @return array The array of migration directories.
     */
    public function settingsMigrations( array $directories ): array
    {
        $directories[] = __DIR__ . '/Migrations';
        return $directories;
    }
}
