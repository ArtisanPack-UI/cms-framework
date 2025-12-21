<?php

declare( strict_types = 1 );

/**
 * Helper functions for the CMS Framework Settings Module.
 *
 * Provides global helper functions to register, retrieve, and update settings.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;

if ( ! function_exists( 'apGetSetting' ) ) {
    /**
     * Retrieve a setting value with optional default fallback.
     *
     * Looks up the value from the database, or falls back to the default registered
     * via the `ap.settings.registeredSettings` filter, or the provided `$default`.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Unique key for the setting.
     * @param  mixed  $default  Optional explicit default to return if no value is stored or registered.
     *
     * @return mixed The setting value.
     */
    function apGetSetting( string $key, mixed $default = null ): mixed
    {
        return app( SettingsManager::class )->getSetting( $key, $default );
    }
}

if ( ! function_exists( 'apRegisterSetting' ) ) {
    /**
     * Register a setting definition that includes type and sanitization callback.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Unique key for the setting.
     * @param  mixed  $defaultValue  Default value if none stored.
     * @param  callable  $callback  Sanitization callback to clean values on update.
     * @param  string  $type  Data type of the setting.
     */
    function apRegisterSetting( string $key, mixed $defaultValue, callable $callback, string $type = 'string' ): void
    {
        app( SettingsManager::class )->registerSetting( $key, $defaultValue, $callback, $type );
    }
}

if ( ! function_exists( 'apUpdateSetting' ) ) {
    /**
     * Update a setting value after sanitizing it using the registered callback.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Unique key for the setting.
     * @param  mixed  $value  The new value to store.
     */
    function apUpdateSetting( string $key, mixed $value ): void
    {
        app( SettingsManager::class )->updateSetting( $key, $value );
    }
}
