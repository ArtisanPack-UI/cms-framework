# Plugins

## Overview

The ArtisanPack UI CMS Framework includes a powerful plugin system that allows developers to extend the core functionality of the CMS. Plugins can add new features, modify existing functionality, and integrate with other systems.

## Plugin Architecture

The plugin system is built on a few key components:

1. **Plugin Base Class**: All plugins must extend the abstract `Plugin` class, which provides the basic structure and lifecycle methods.
2. **Plugin Manager**: Manages the installation, activation, deactivation, and uninstallation of plugins.
3. **Plugin Model**: Represents a plugin in the database and provides access to the plugin instance.

## Creating a Plugin

### Basic Structure

A plugin should have the following structure:

```
my-plugin/
├── composer.json
├── src/
│   └── Plugin.php
├── database/
│   └── migrations/
└── resources/
    ├── views/
    └── assets/
```

### Plugin Class

The main plugin class must extend the `ArtisanPackUI\CMSFramework\Features\Plugins\Plugin` abstract class:

```php
<?php

namespace MyVendor\MyPlugin;

use ArtisanPackUI\CMSFramework\Features\Plugins\Plugin as BasePlugin;

class Plugin extends BasePlugin
{
    /**
     * The human-friendly name of the plugin.
     */
    public string $name = 'My Plugin';

    /**
     * The unique, URL-friendly slug of the plugin.
     */
    public string $slug = 'my-plugin';

    /**
     * The current version of the plugin.
     */
    public string $version = '1.0.0';

    /**
     * The author of the plugin.
     */
    public string $author = 'Your Name';

    /**
     * The website of the plugin author or project.
     */
    public string $website = 'https://example.com';

    /**
     * A short description of what the plugin does.
     */
    public string $description = 'This plugin adds awesome functionality.';

    /**
     * Register any plugin-specific services or bindings.
     */
    public function register(): void
    {
        // Register services, bindings, etc.
    }

    /**
     * Bootstrap any plugin-specific services or hooks.
     */
    public function boot(): void
    {
        // Register routes, views, assets, etc.
    }

    /**
     * Define any database migrations for the plugin.
     */
    public function registerMigrations(): array
    {
        return ['database/migrations'];
    }

    /**
     * Define any settings that this plugin introduces.
     */
    public function registerSettings(): array
    {
        return [
            [
                'key' => 'my_plugin.some_option',
                'default' => 'default value',
                'type' => 'string',
                'description' => 'A description of this setting.'
            ]
        ];
    }

    /**
     * Define any permissions this plugin introduces.
     */
    public function registerPermissions(): array
    {
        return [
            'my_plugin.manage_settings' => [
                'label' => 'Manage My Plugin Settings',
                'description' => 'Allows users to manage settings for My Plugin.'
            ]
        ];
    }
}
```

### Composer.json

Your plugin must have a valid `composer.json` file:

```json
{
    "name": "my-vendor/my-plugin",
    "description": "My awesome plugin for ArtisanPack UI CMS",
    "type": "artisanpack-plugin",
    "require": {
        "artisanpack-ui/cms-framework": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "MyVendor\\MyPlugin\\": "src/"
        }
    },
    "extra": {
        "artisanpack": {
            "plugin-class": "MyVendor\\MyPlugin\\Plugin"
        }
    }
}
```

## Plugin Lifecycle

### Installation

Plugins can be installed from a ZIP file or a URL:

```php
// Install from a ZIP file
$pluginManager->installFromZip('/path/to/plugin.zip');

// Install from a URL
$pluginManager->installFromUrl('https://example.com/plugin.zip');
```

During installation, the plugin manager:
1. Extracts the plugin files to the plugins directory
2. Validates the plugin's composer.json
3. Discovers the main plugin class
4. Adds the plugin to the application's composer.json
5. Runs composer dump-autoload
6. Creates a record in the plugins table

### Activation

Activating a plugin:

```php
$pluginManager->activatePlugin('my-plugin');
```

During activation, the plugin manager:
1. Runs the plugin's migrations
2. Calls the plugin's `register()` method
3. Calls the plugin's `boot()` method
4. Registers the plugin's settings
5. Registers the plugin's permissions
6. Updates the plugin's active status in the database

### Deactivation

Deactivating a plugin:

```php
$pluginManager->deactivatePlugin('my-plugin');
```

During deactivation, the plugin manager:
1. Rolls back the plugin's migrations
2. Updates the plugin's active status in the database

### Uninstallation

Uninstalling a plugin:

```php
$pluginManager->uninstallPlugin('my-plugin');
```

During uninstallation, the plugin manager:
1. Deactivates the plugin if it's active
2. Removes the plugin from the application's composer.json
3. Runs composer dump-autoload
4. Deletes the plugin files
5. Removes the plugin record from the database

## Plugin API

### Plugin Base Class Methods

- `register()`: Register any plugin-specific services or bindings
- `boot()`: Bootstrap any plugin-specific services or hooks
- `registerMigrations()`: Define any database migrations for the plugin
- `registerSettings()`: Define any settings that this plugin introduces
- `registerPermissions()`: Define any permissions this plugin introduces

### Plugin Manager Methods

- `getAllInstalled()`: Get all installed plugins
- `getActiveInstance(string $slug)`: Get an active plugin instance
- `installFromZip(string $zipFilePath)`: Install a plugin from a ZIP file
- `installFromUrl(string $url)`: Install a plugin from a URL
- `activatePlugin(string $pluginSlug)`: Activate a plugin
- `deactivatePlugin(string $pluginSlug)`: Deactivate a plugin
- `uninstallPlugin(string $pluginSlug)`: Uninstall a plugin
- `updateFromZip(string $zipFilePath, string $pluginSlug)`: Update a plugin from a ZIP file

## Best Practices

1. **Namespace Your Code**: Use a unique namespace for your plugin to avoid conflicts with other plugins.
2. **Follow Laravel Conventions**: Structure your plugin following Laravel conventions for models, controllers, etc.
3. **Use Dependency Injection**: Leverage Laravel's dependency injection to access services.
4. **Respect Plugin Boundaries**: Don't modify core functionality directly; use hooks and events instead.
5. **Provide Clear Documentation**: Document your plugin's features, settings, and permissions.
6. **Handle Errors Gracefully**: Catch and handle exceptions to prevent breaking the entire application.
7. **Clean Up After Yourself**: Ensure your plugin properly cleans up when deactivated or uninstalled.

## Troubleshooting

### Common Issues

1. **Plugin Not Found**: Ensure the plugin's namespace matches the one in composer.json and the plugin class is correctly named.
2. **Activation Fails**: Check for errors in the plugin's migrations or register/boot methods.
3. **Composer Autoload Issues**: Run `composer dump-autoload` manually if autoloading fails.
4. **Permission Errors**: Ensure the plugins directory is writable by the web server.

### Debugging

Enable debug mode in your Laravel application to see detailed error messages:

```php
// In .env
APP_DEBUG=true
```

Check the Laravel logs for plugin-related errors:

```
storage/logs/laravel.log
```

## Conclusion

The plugin system provides a powerful way to extend the ArtisanPack UI CMS Framework. By following the guidelines in this documentation, you can create plugins that seamlessly integrate with the core system and provide valuable functionality to users.