<?php

declare(strict_types=1);

/**
 * Plugin Manager Interface
 *
 * Defines the contract for plugin management operations in the CMS framework.
 * This interface provides methods for installing, activating, deactivating, and managing plugins.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Contracts;

use ArtisanPackUI\CMSFramework\Features\Plugins\Plugin as BasePlugin;
use ArtisanPackUI\CMSFramework\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;

/**
 * Plugin Manager Interface
 *
 * Defines the contract for plugin management operations including plugin installation,
 * activation, deactivation, updating, and uninstallation.
 *
 * @since 1.0.0
 */
interface PluginManagerInterface
{
    /**
     * Initialize all active plugins in the system.
     */
    public function initializeActivePlugins(): void;

    /**
     * Get all installed plugins from the database.
     *
     * @return Collection<Plugin> Collection of all installed plugins.
     */
    public function getAllInstalled(): Collection;

    /**
     * Get the active instance of a specific plugin by its slug.
     *
     * @param  string  $slug  The plugin slug.
     * @return BasePlugin|null The plugin instance if active, null otherwise.
     */
    public function getActiveInstance(string $slug): ?BasePlugin;

    /**
     * Install a plugin from a remote URL.
     *
     * @param  string  $url  The URL to download the plugin from.
     * @return Plugin The installed plugin model instance.
     */
    public function installFromUrl(string $url): Plugin;

    /**
     * Install a plugin from a local ZIP file.
     *
     * @param  string  $zipFilePath  The path to the ZIP file.
     * @return Plugin The installed plugin model instance.
     */
    public function installFromZip(string $zipFilePath): Plugin;

    /**
     * Remove a plugin from the application's composer configuration.
     *
     * @param  string  $packageName  The plugin package name.
     * @param  string  $pluginPath  The plugin path.
     */
    public function removePluginFromAppComposer(string $packageName, string $pluginPath): void;

    /**
     * Update a plugin from a ZIP file.
     *
     * @param  string  $zipFilePath  The path to the updated plugin ZIP file.
     * @param  string  $pluginSlug  The slug of the plugin to update.
     * @return Plugin The updated plugin model instance.
     */
    public function updateFromZip(string $zipFilePath, string $pluginSlug): Plugin;

    /**
     * Deactivate a plugin by its slug.
     *
     * @param  string  $pluginSlug  The plugin slug.
     * @return Plugin The deactivated plugin model instance.
     */
    public function deactivatePlugin(string $pluginSlug): Plugin;

    /**
     * Activate a plugin by its slug.
     *
     * @param  string  $pluginSlug  The plugin slug.
     * @return Plugin The activated plugin model instance.
     */
    public function activatePlugin(string $pluginSlug): Plugin;

    /**
     * Completely uninstall a plugin from the system.
     *
     * @param  string  $pluginSlug  The plugin slug.
     */
    public function uninstallPlugin(string $pluginSlug): void;
}
