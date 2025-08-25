<?php

declare(strict_types=1);

/**
 * Settings Manager Interface
 *
 * Defines the contract for settings management operations in the CMS framework.
 * This interface provides methods for managing application settings.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Contracts;

use ArtisanPackUI\CMSFramework\Models\Setting;

/**
 * Settings Manager Interface
 *
 * Defines the contract for settings management operations including setting
 * registration, retrieval, updating, and deletion.
 *
 * @since 1.0.0
 */
interface SettingsManagerInterface
{
    /**
     * Load all settings from the database into cache.
     */
    public function loadSettings(): void;

    /**
     * Get all settings as an array.
     *
     * @return array Array of all settings.
     */
    public function all(): array;

    /**
     * Get a specific setting value by key.
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $default  The default value if the setting doesn't exist.
     * @return mixed The setting value or the default value.
     */
    public function get(string $key, mixed $default): mixed;

    /**
     * Delete a setting by its key.
     *
     * @param  string  $key  The setting key to delete.
     * @return bool|null True if deletion was successful, false if failed, null if setting not found.
     */
    public function delete(string $key): ?bool;

    /**
     * Refresh the settings cache by reloading from the database.
     */
    public function refreshSettingsCache(): void;

    /**
     * Register default PWA-related settings.
     */
    public function registerPwaDefaults(): void;

    /**
     * Register a new setting with a default value.
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $defaultValue  The default value for the setting.
     * @param  string|null  $type  The setting type (optional).
     * @param  string|null  $description  The setting description (optional).
     * @return Setting|null The created setting instance if successful, null otherwise.
     */
    public function register(
        string $key,
        mixed $defaultValue,
        ?string $type = null,
        ?string $description = null
    ): ?Setting;

    /**
     * Set a setting value.
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $value  The setting value.
     * @param  string|null  $type  The setting type (optional).
     * @return Setting|null The updated setting instance if successful, null otherwise.
     */
    public function set(string $key, mixed $value, ?string $type = null): ?Setting;
}
