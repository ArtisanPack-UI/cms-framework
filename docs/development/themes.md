---
title: Themes
---

# Themes

## Overview

The ArtisanPack UI CMS Framework includes a powerful theming system that allows developers to customize the appearance and functionality of their CMS. Themes can modify the layout, styling, and presentation of content while also adding theme-specific functionality.

## Theme Architecture

The theme system is built on a few key components:

1. **Theme Base Class**: All themes must extend the abstract `Theme` class, which provides the basic structure and lifecycle methods.
2. **Theme Manager**: Manages the activation, deactivation, and discovery of themes.
3. **Theme Directory Structure**: A standardized way to organize theme files and resources.

## Creating a Theme

### Basic Structure

A theme should have the following structure:

```
my-theme/
├── composer.json
├── src/
│   └── Theme.php
├── database/
│   └── migrations/
└── resources/
    ├── views/
    ├── assets/
    │   ├── css/
    │   ├── js/
    │   └── images/
    └── lang/
```

### Theme Class

The main theme class must extend the `ArtisanPackUI\CMSFramework\Features\Themes\Theme` abstract class:

```php
<?php

namespace App\Themes\MyTheme;

use ArtisanPackUI\CMSFramework\Features\Themes\Theme as BaseTheme;

class Theme extends BaseTheme
{
    /**
     * The human-friendly name of the theme.
     */
    public string $name = 'My Theme';

    /**
     * The unique, URL-friendly slug of the theme.
     */
    public string $slug = 'my-theme';

    /**
     * The current version of the theme.
     */
    public string $version = '1.0.0';

    /**
     * The author of the theme.
     */
    public string $author = 'Your Name';

    /**
     * The website of the theme author or project.
     */
    public string $website = 'https://example.com';

    /**
     * A short description of what the theme does.
     */
    public string $description = 'This theme provides a beautiful design for your CMS.';

    /**
     * Register any theme-specific services or bindings.
     */
    public function register(): void
    {
        // Register services, bindings, etc.
    }

    /**
     * Bootstrap any theme-specific services or hooks.
     */
    public function boot(): void
    {
        // Register routes, views, assets, etc.
    }

    /**
     * Define any database migrations for the theme.
     */
    public function registerMigrations(): array
    {
        return ['database/migrations'];
    }

    /**
     * Define any settings that this theme introduces.
     */
    public function registerSettings(): array
    {
        return [
            [
                'key' => 'my_theme.primary_color',
                'default' => '#3490dc',
                'type' => 'string',
                'description' => 'The primary color used throughout the theme.'
            ]
        ];
    }

    /**
     * Define any permissions this theme introduces.
     */
    public function registerPermissions(): array
    {
        return [
            'my_theme.customize' => [
                'label' => 'Customize Theme',
                'description' => 'Allows users to customize theme settings.'
            ]
        ];
    }
}
```

### Composer.json

Your theme must have a valid `composer.json` file:

```json
{
    "name": "my-vendor/my-theme",
    "description": "My beautiful theme for ArtisanPack UI CMS",
    "type": "artisanpack-theme",
    "require": {
        "artisanpack-ui/cms-framework": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "App\\Themes\\MyTheme\\": "src/"
        }
    }
}
```

## Theme Lifecycle

### Activation

Activating a theme:

```php
$themeManager->activateTheme('my-theme');
```

During activation, the theme manager:
1. Deactivates any currently active theme
2. Sets the new theme as active in the settings
3. Fires the `ap.cms.theme.activated` action hook

### Deactivation

Deactivating a theme:

```php
$themeManager->deactivateTheme();
```

During deactivation, the theme manager:
1. Sets the active theme to null in the settings
2. Fires the `ap.cms.theme.deactivated` action hook

## Theme Discovery

The ThemeManager scans the themes directory to discover available themes:

```php
$themes = $themeManager->scanThemes();
```

This returns an array of theme information, including:
- Name
- Description
- Path
- Status (active/inactive)

## Working with Themes

### Getting the Active Theme

```php
$activeTheme = $themeManager->getActiveTheme();
```

### Checking Theme Status

```php
$status = $themeManager->getThemeStatus('my-theme'); // Returns 'active' or 'inactive'
```

### Loading the Active Theme

```php
$themeInstance = $themeManager->loadActiveThemeClass();
```

This instantiates the Theme class of the active theme and calls its `register()` and `boot()` methods.

## Theme Helper Methods

The Theme base class provides several helper methods:

### Asset URL

Get the URL to a theme asset:

```php
$url = $theme->asset('css/style.css');
```

### Theme Path

Get the file system path to a theme file:

```php
$path = $theme->path('resources/views/template.blade.php');
```

## Best Practices

1. **Namespace Your Code**: Use a unique namespace for your theme to avoid conflicts with other themes or plugins.
2. **Follow Laravel Conventions**: Structure your theme following Laravel conventions for views, assets, etc.
3. **Use Dependency Injection**: Leverage Laravel's dependency injection to access services.
4. **Respect Theme Boundaries**: Don't modify core functionality directly; use hooks and events instead.
5. **Provide Clear Documentation**: Document your theme's features, settings, and customization options.
6. **Handle Errors Gracefully**: Catch and handle exceptions to prevent breaking the entire application.
7. **Make Your Theme Responsive**: Ensure your theme works well on all device sizes.
8. **Optimize Assets**: Minimize CSS and JavaScript files for production.

## Troubleshooting

### Common Issues

1. **Theme Not Found**: Ensure the theme directory exists and has the correct structure.
2. **Activation Fails**: Check for errors in the theme's register/boot methods.
3. **Missing Assets**: Verify that asset paths are correct and the files exist.
4. **Styling Issues**: Use browser developer tools to inspect CSS conflicts or missing styles.

### Debugging

Enable debug mode in your Laravel application to see detailed error messages:

```php
// In .env
APP_DEBUG=true
```

Check the Laravel logs for theme-related errors:

```
storage/logs/laravel.log
```

## Conclusion

The theming system provides a powerful way to customize the appearance and functionality of the ArtisanPack UI CMS Framework. By following the guidelines in this documentation, you can create themes that seamlessly integrate with the core system and provide a unique experience for your users.

For a more detailed, step-by-step guide on implementing themes in your ArtisanPack UI CMS application, including setting up your CMS for themes, developing custom themes, and managing themes through Artisan commands and a dashboard UI, see the [Implementing Themes](implementing-themes.md) guide.
