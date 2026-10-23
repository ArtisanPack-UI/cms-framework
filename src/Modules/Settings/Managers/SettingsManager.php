<?php

declare( strict_types=1 );

/**
 * Settings Manager
 *
 * Provides a simple API for registering, retrieving, and updating application settings
 * with sanitization callbacks and storage in the database. Exposes a filter hook to
 * allow third-parties to register settings.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Managers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;
use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Manages registration, retrieval, and updates of settings.
 *
 * @since 1.0.0
 */
class SettingsManager
{
    /**
     * Cache key holding the raw attributes of every stored setting.
     *
     * @since 2.15.4
     *
     * @var string
     */
    public const CACHE_KEY = 'cms.settings.all';

    /**
     * Lifetime of the shared cache entry, in seconds.
     *
     * Invalidation is event-driven; the TTL only bounds how long stale rows
     * can survive when a flush is lost, e.g. another process caching rows it
     * read just before a write flushed the entry.
     *
     * @since 2.15.4
     *
     * @var int
     */
    public const CACHE_TTL = 3600;

    /**
     * Stored settings loaded for the current request, keyed by setting key.
     *
     * Null until the first read; reset by `flushCache()` and `forgetLoadedSettings()`.
     *
     * @since 2.15.4
     *
     * @var array<string, Setting>|null
     */
    protected ?array $loadedSettings = null;

    /**
     * Whether the settings table has been confirmed to exist.
     *
     * Only a positive result is remembered so a table created later in the
     * same process (e.g. during installation) is still picked up.
     *
     * @since 2.15.4
     *
     * @var bool
     */
    protected bool $settingsTableExists = false;

    /**
     * Registers a setting definition via a filter so it can be discovered globally.
     *
     * The setting is exposed through the `ap.settings.registeredSettings` filter. Consumers
     * should pass a sanitization callback which will be used when updating the setting value.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Unique key for the setting.
     * @param  mixed  $defaultValue  Default value returned when no stored value exists.
     * @param  callable  $callback  Sanitization callback used to clean values on update.
     * @param  SettingType  $type  Data type of the setting.
     */
    public function registerSetting( string $key, mixed $defaultValue, callable $callback, SettingType $type = SettingType::String ): void
    {
        /**
         * Filters the array of registered settings to add or modify items.
         *
         * This filter is used to register settings across the application. Each callback
         * should return the augmented `$settings` array including its setting definition.
         *
         * @since 1.0.0
         *
         * @hook  ap.settings.registeredSettings
         *
         * @param  array  $settings  Associative array of registered settings keyed by setting key. Each item
         *                           contains: 'default' (mixed), 'type' (SettingType), and 'callback' (callable).
         *
         * @return array Filtered settings array.
         */
        addFilter( 'ap.settings.registeredSettings', function ( $settings ) use ( $key, $defaultValue, $type, $callback ) {
            $settings[ $key ] = [
                'default'  => $defaultValue,
                'type'     => $type,
                'callback' => $callback,
            ];

            return $settings;
        } );
    }

    /**
     * Retrieves a setting value, falling back to a default when not stored.
     *
     * Checks the stored settings first and if no record exists, falls back to the
     * registered default value from the `ap.settings.registeredSettings` filter
     * or the provided `$default` parameter. Stored settings are loaded once per
     * request (see `loadSettings()`), so repeated calls do not query the database.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Unique key for the setting.
     * @param  mixed  $default  Optional default value to use if no stored or registered default exists.
     *
     * @return mixed The setting value.
     */
    public function getSetting( string $key, mixed $default = null ): mixed
    {
        $setting = $this->loadSettings()[ sanitizeText( $key ) ] ?? null;

        if ( $setting ) {
            return $setting->value;
        }

        /**
         * Filters the array of registered settings to allow discovery of defaults.
         *
         * @since 1.0.0
         *
         * @hook  ap.settings.registeredSettings
         *
         * @param  array  $settings  Associative array of registered settings keyed by setting key.
         *
         * @return array Filtered settings array.
         */
        $settings          = applyFilters( 'ap.settings.registeredSettings', [] );
        $registeredDefault = $settings[ $key ]['default'] ?? null;

        return $default ?? $registeredDefault;
    }

    /**
     * Updates a setting value after sanitizing it using the registered callback.
     *
     * Creates or updates the record in the database and stores the sanitized value.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Unique key for the setting.
     * @param  mixed  $value  New value to store for the setting (will be sanitized first).
     */
    public function updateSetting( string $key, mixed $value ): void
    {
        if ( ! $this->settingsTableExists() ) {
            return;
        }

        /**
         * Filters the array of registered settings to retrieve type and sanitizer.
         *
         * @since 1.0.0
         *
         * @param  array  $settings  Associative array of registered settings keyed by setting key.
         *
         * @return array Filtered settings array.
         */
        $settings  = applyFilters( 'ap.settings.registeredSettings', [] );
        $def       = $settings[ $key ] ?? null;
        $sanitized = is_array( $def ) && isset( $def['callback'] ) && is_callable( $def['callback'] )
            ? call_user_func( $def['callback'], $value )
            : $value;

        $registeredType = ( $def['type'] ?? null ) instanceof SettingType ? $def['type'] : null;
        $currentSetting = Setting::where( 'key', sanitizeText( $key ) )->first();

        if ( $currentSetting ) {
            $currentSetting->value = $sanitized;

            if ( $registeredType ) {
                $currentSetting->type = $registeredType->value;
            }

            $currentSetting->save();
        } else {
            $attributes = [
                'key'   => $key,
                'value' => $sanitized,
            ];

            if ( $registeredType ) {
                $attributes['type'] = $registeredType->value;
            }

            Setting::create( $attributes );
        }
    }

    /**
     * Clears the request memo and the shared cache of stored settings.
     *
     * Called automatically whenever a `Setting` model is saved or deleted.
     * Writes that bypass Eloquent events (raw query builder updates) must
     * call this manually.
     *
     * @since 2.15.4
     */
    public function flushCache(): void
    {
        $this->forgetLoadedSettings();

        Cache::forget( self::CACHE_KEY );
    }

    /**
     * Clears the request memo only, leaving the shared cache intact.
     *
     * The manager is a singleton, so long-lived processes (queue workers,
     * Octane) call this at each job/request boundary to pick up writes made
     * by other processes.
     *
     * @since 2.15.4
     */
    public function forgetLoadedSettings(): void
    {
        $this->loadedSettings = null;
    }

    /**
     * Loads every stored setting, memoized for the lifetime of this instance.
     *
     * Reads from the shared cache when possible and falls back to a single
     * query. Inside a database transaction both the memo and the shared cache
     * are bypassed, so uncommitted values never outlive a rollback or leak to
     * other requests.
     *
     * @since 2.15.4
     *
     * @return array<string, Setting> Stored settings keyed by setting key.
     */
    protected function loadSettings(): array
    {
        $inTransaction = ( new Setting )->getConnection()->transactionLevel() > 0;

        if ( ! $inTransaction && null !== $this->loadedSettings ) {
            return $this->loadedSettings;
        }

        if ( ! $this->settingsTableExists() ) {
            return [];
        }

        $rows = $inTransaction ? null : $this->readCachedRows();

        if ( ! is_array( $rows ) ) {
            $rows = Setting::query()
                ->toBase()
                ->get( [ 'key', 'value', 'type' ] )
                ->map( fn ( $row ): array => (array) $row )
                ->all();

            if ( $inTransaction ) {
                return Setting::hydrate( $rows )->keyBy( 'key' )->all();
            }

            $this->writeCachedRows( $rows );
        }

        return $this->loadedSettings = Setting::hydrate( $rows )->keyBy( 'key' )->all();
    }

    /**
     * Reads the shared cache entry, treating an unavailable cache store as a miss.
     *
     * The `database` cache store throws when its table hasn't been migrated
     * yet, which would otherwise break app boot (e.g. `package:discover`)
     * before `migrate` has run.
     *
     * @since 2.15.5
     *
     * @return mixed The cached rows, or null on a miss or an unavailable store.
     */
    protected function readCachedRows(): mixed
    {
        try {
            return Cache::get( self::CACHE_KEY );
        } catch ( QueryException ) {
            return null;
        }
    }

    /**
     * Writes the shared cache entry, skipping it when the cache store is unavailable.
     *
     * @since 2.15.5
     *
     * @param  array<int, array<string, mixed>>  $rows  Raw setting rows to cache.
     */
    protected function writeCachedRows( array $rows ): void
    {
        try {
            Cache::put( self::CACHE_KEY, $rows, self::CACHE_TTL );
        } catch ( QueryException ) {
            // The store is unavailable; the rows are still memoized for this request.
        }
    }

    /**
     * Determines whether the settings table exists, remembering a positive result.
     *
     * @since 2.15.4
     *
     * @return bool True when the settings table exists.
     */
    protected function settingsTableExists(): bool
    {
        if ( ! $this->settingsTableExists ) {
            $this->settingsTableExists = Schema::hasTable( 'settings' );
        }

        return $this->settingsTableExists;
    }
}
