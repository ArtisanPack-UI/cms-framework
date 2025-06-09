<?php
/**
 * Settings Manager
 *
 * Manages CRUD operations and event filters for application settings.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Settings
 * @since      1.0.0
 *
 */

namespace ArtisanPackUI\CMSFramework\Features\Settings;

use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Class for managing application settings
 *
 * Provides functionality to manage application settings, including
 * registering, adding, updating, retrieving, and deleting settings.
 *
 * @since 1.0.0
 */
class SettingsManager
{
    /**
     * Merged settings from config and database
     *
     * @since 1.0.0
     * @var array
     */
    protected array $mergedSettings = [];

    /**
     * Cache key for storing settings
     *
     * @since 1.0.0
     * @var string
     */
    protected string $cacheKey = 'cms.settings.resolved';

    /**
     * Cache time-to-live in minutes (60 * 24 = 1 day)
     *
     * @since 1.0.0
     * @var int
     */
    protected int $cacheTtl = 60 * 24;

    /**
     * Constructor
     *
     * Initializes the settings manager by loading settings from config and database.
     *
     * @since 1.0.0
     */
    public function __CONSTRUCT()
    {
        $this->loadSettings();
    }

    /**
     * Load settings from config and database
     *
     * Merges default settings from config with overrides from database.
     *
     * @since 1.0.0
     * @return void
     */
    protected function loadSettings(): void
    {
        $configDefaults       = config( 'cms', [] );
        $dbOverrides          = Cache::remember( $this->cacheKey, $this->cacheTtl, function () {
            return Setting::all()->keyBy( 'key' )->map->value->toArray();
        } );
        $this->mergedSettings = array_replace_recursive( $configDefaults, Arr::undot( $dbOverrides ) );
    }

    /**
     * Get all currently active settings
     *
     * Returns all settings merged from config and database.
     *
     * @since 1.0.0
     * @return array Array of all settings
     */
    public function all(): array
    {
        return $this->mergedSettings;
    }

    /**
     * Get a setting value
     *
     * Retrieves a setting value from the resolved configuration.
     *
     * @since 1.0.0
     * @param string $key     The setting key to retrieve
     * @param mixed  $default Default value if setting doesn't exist
     * @return mixed The setting value or default if not found
     */
    public function get( string $key, mixed $default = null ): mixed
    {
        return Arr::get( $this->mergedSettings, $key, $default );
    }

    /**
     * Register a default setting programmatically
     *
     * Registers a setting with a default value. If the setting already exists
     * in the database, it will NOT be overwritten. This is useful for
     * modules/plugins to set their initial defaults.
     *
     * @since 1.0.0
     * @param string      $key          The setting key
     * @param mixed       $defaultValue The default value for the setting
     * @param string|null $type         Optional. Explicit type of the setting. Default null.
     * @param string|null $description  Optional. A description for the setting (useful for UI). Default null.
     * @return Setting|null The created setting model or null if already exists
     */
    public function register( string $key, mixed $defaultValue, ?string $type = null, ?string $description = null ): ?Setting
    {
        // Check if the setting already exists in the database
        $existingSetting = Setting::where( 'key', sanitizeText( $key ) )->first();

        if ( $existingSetting ) {
            return $existingSetting; // Setting already exists in DB, do not overwrite
        }

        // If it doesn't exist, use the set method to store it with the default value
        // The set method will handle type detection if $type is null
        $setting = $this->set( $key, $defaultValue, $type );

        // Optionally, if you have a 'description' column in your settings table
        // and it's not handled by the set() method, you'd add it here:
        if ( null !== $description && $setting->description !== $description ) {
            $setting->description = $description;
            $setting->save();              // Save again if description was updated
            $this->refreshSettingsCache(); // Re-refresh if description was changed
        }

        return $setting;
    }

    /**
     * Set a setting value
     *
     * Sets a setting value in the database and refreshes the cached settings.
     * This method is used for user-initiated changes, always writing to DB.
     *
     * @since 1.0.0
     * @param string      $key   The setting key (dot-notation supported)
     * @param mixed       $value The value to store
     * @param string|null $type  Optional. Explicit type ('string', 'boolean', 'integer', 'json').
     *                           Auto-detected if null. Default null.
     * @return Setting The setting model instance
     */
    public function set( string $key, mixed $value, ?string $type = null ): Setting
    {
        // Determine type if not explicitly provided (same logic as before)
        if ( null === $type ) {
            if ( is_bool( $value ) ) {
                $type = 'boolean';
            } else if ( is_int( $value ) ) {
                $type = 'integer';
            } else if ( is_array( $value ) || is_object( $value ) ) {
                $type = 'json';
            } else {
                $type = 'string';
            }
        }

        $setting = Setting::updateOrCreate(
            [
                'key' => $key
            ],
            [
                'value' => $value,
                'type'  => $type,
            ]
        );

        $this->refreshSettingsCache();

        return $setting;
    }

    /**
     * Refresh settings cache
     *
     * Clears the settings cache and forces a reload of the merged settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function refreshSettingsCache(): void
    {
        Cache::forget( $this->cacheKey );
        $this->loadSettings(); // Reloads merged settings from fresh sources
    }

    /**
     * Delete a setting
     *
     * Deletes a setting from the database and refreshes the cached settings.
     *
     * @since 1.0.0
     * @param string $key The setting key to delete
     * @return bool|null True if deleted, false if not found, null on error
     */
    public function delete( string $key ): ?bool
    {
        $deleted = Setting::where( 'key', sanitizeText( $key ) )->delete();
        if ( $deleted ) {
            $this->refreshSettingsCache();
        }
        return $deleted;
    }
}
