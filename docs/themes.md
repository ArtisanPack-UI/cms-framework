---
title: Themes
---

# Themes Module

The Themes module provides a flexible, WordPress‑inspired theme system with:
- Automatic theme discovery from a configured directory
- Theme activation with cache management
- WordPress‑style template hierarchy for content types
- View path registration for Laravel's Blade engine
- JSON‑based theme manifests for metadata
- RESTful API endpoints for theme management

## Theme Guides

- [Getting Started](themes/Getting-Started.md) — Create your first theme
- [Theme Structure](themes/Theme-Structure.md) — Directory layout and required files
- [Template Hierarchy](themes/Template-Hierarchy.md) — How templates are resolved
- [Theme Manifest](themes/Theme-Manifest.md) — The theme.json file format
- [API Reference](themes/API-Reference.md) — REST endpoints and helper functions

## Overview

Themes are discovered from a directory (default: `themes/`), validated, and can be activated to control the site's appearance. Each theme contains a `theme.json` manifest and Blade templates following a hierarchical naming convention.

### Quick Example

```php
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;

// Get the theme manager instance
$themeManager = app(ThemeManager::class);

// Discover all available themes
$themes = $themeManager->discoverThemes();

// Get the currently active theme
$activeTheme = $themeManager->getActiveTheme();

// Activate a theme by slug
$themeManager->activateTheme('my-custom-theme');

// Resolve template for content type
$template = $themeManager->resolveTemplate('post', 'welcome');
// Returns: 'single-post-welcome' (if exists), 'single-post', 'single', or 'index'

// Check if a specific template exists
if ($themeManager->templateExists('single-post')) {
    // Template exists in active theme
}
```

## Configuration

Configure themes in `config/cms.php` under the `themes` key:

```php
return [
    'themes' => [
        // Directory where themes are stored (relative to base_path)
        'directory' => 'themes',

        // Default theme slug
        'default' => 'digital-shopfront',

        // Required files for theme validation
        'requiredFiles' => [
            'theme.json',
        ],

        // Cache settings
        'cacheEnabled' => env('THEMES_CACHE_ENABLED', true),
        'cacheKey' => 'cms.themes.discovered',
        'cacheTtl' => 3600, // 1 hour
    ],
];
```

## Template Hierarchy

The theme system implements a WordPress‑style template hierarchy for resolving templates:

1. `single-{contentType}-{slug}.blade.php` — Specific content item
2. `single-{contentType}.blade.php` — Content type archive
3. `single.blade.php` — Generic single template
4. `index.blade.php` — Fallback template

This allows themes to provide increasingly specific templates for different content types and items.

## REST API Endpoints

All endpoints require authentication via Laravel Sanctum and are prefixed with `/api/v1`:

- `GET /themes` — List all available themes
- `GET /themes/{slug}` — Get specific theme details
- `POST /themes/{slug}/activate` — Activate a theme

## Service Registration

The `ThemesServiceProvider` automatically:

- Registers the `ThemeManager` as a singleton
- Merges theme configuration
- Registers the active theme's view path with Laravel
- Loads theme API routes
- Registers the `themes.activeTheme` setting

See the guides above for detailed usage and patterns.
