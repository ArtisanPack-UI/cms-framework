<?php

namespace ArtisanPackUI\CMSFramework\Features\Plugins;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Base class for Artisanpack CMS plugins.
 * All external plugins developed for this framework should extend this class.
 */
abstract class Plugin
{
	/**
	 * The human-friendly name of the plugin (e.g., "SEO Toolkit").
	 * This is displayed in the admin UI.
	 * @var string
	 */
	public string $name;

	/**
	 * The unique, URL-friendly slug of the plugin (e.g., "seo-toolkit").
	 * This must be unique across all plugins and is used internally.
	 * @var string
	 */
	public string $slug;

	/**
	 * The current version of the plugin (e.g., "1.0.0").
	 * @var string
	 */
	public string $version = '1.0.0';

	/**
	 * The author of the plugin.
	 * @var string
	 */
	public string $author = 'Unknown';

	/**
	 * The website of the plugin author or project.
	 * @var string|null
	 */
	public string|null $website;

	/**
	 * A short description of what the plugin does.
	 * @var string|null
	 */
	public string|null $description;

	/**
	 * The Composer package name of the plugin (e.g., 'vendor/package-name').
	 * This is automatically set by the framework's PluginManager during installation/loading.
	 * @internal
	 */
	public string $composerPackageName;

	/**
	 * The directory name of the plugin on disk (relative to the plugins root).
	 * This is automatically set by the framework's PluginManager.
	 * @internal
	 */
	public string $directoryName;

	/**
	 * The fully qualified class name of this plugin instance (e.g., 'ArtisanpackPlugins\Seo\Plugin').
	 * This is automatically set by the framework's PluginManager.
	 * @internal
	 */
	public string $pluginClass;

	/**
	 * Determines if the plugin is currently active in the CMS.
	 * This is automatically set by the framework's PluginManager.
	 * @internal
	 */
	public bool $isActive;

	/**
	 * Plugin constructor.
	 * Ensures required properties are set at instantiation.
	 */
	public function __CONSTRUCT()
	{
		if ( empty( $this->name ) || empty( $this->slug ) ) {
			throw new InvalidArgumentException(
				"Plugin must define a 'name' and 'slug' property in its main Plugin.php class."
			);
		}
		$this->slug = Str::slug( $this->slug ); // Ensure slug is in proper format
	}

	/**
	 * Register any plugin-specific services, bindings, or perform early setup.
	 * This method is called during plugin activation, before boot().
	 * Analogous to a Service Provider's register method.
	 *
	 * @return void
	 */
	public function register(): void
	{
		// Implement in concrete plugin classes to register services
	}

	/**
	 * Bootstrap any plugin-specific services or hooks.
	 * This method is called after all plugins have been registered, during activation.
	 * This is where Eventy hooks, routes, views, etc., are typically loaded.
	 * Analogous to a Service Provider's boot method.
	 *
	 * @return void
	 */
	public function boot(): void
	{
		// Implement in concrete plugin classes to bootstrap functionality
	}

	/**
	 * Define any database migrations for the plugin.
	 * These paths should be relative to the plugin's root directory.
	 * Example: `['database/migrations']`
	 *
	 * @return array An array of paths to migration directories.
	 */
	public function registerMigrations(): array
	{
		return [];
	}

	/**
	 * Define any settings that this plugin introduces.
	 * These settings will be automatically registered with the framework's SettingsManager.
	 *
	 * @return array An array of setting definitions. Each array item should be:
	 * `['key' => 'my_plugin.some_option', 'default' => 'value', 'type' => 'string', 'description' => 'A description.']`
	 */
	public function registerSettings(): array
	{
		return [];
	}

	/**
	 * Define any permissions this plugin introduces.
	 * This could be used by your framework's permission system.
	 * @return array
	 * Example: [
	 * 'my_plugin.manage_settings' => [
	 * 'label' => 'Manage My Plugin Settings',
	 * 'description' => 'Allows users to manage settings for My Plugin.'
	 * ]
	 * ]
	 */
	public function registerPermissions(): array
	{
		return [];
	}

	// You can add more registration methods here as your framework evolves,
	// e.g., registerRoutes(), registerViews(), registerWidgets(), registerCommands().
}