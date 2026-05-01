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

- [Getting Started](Themes-Getting-Started) — Create your first theme
- [Theme Structure](Themes-Theme-Structure) — Directory layout and required files
- [Template Hierarchy](Themes-Template-Hierarchy) — How templates are resolved
- [Theme Manifest](Themes-Theme-Manifest) — The theme.json file format
- [API Reference](Themes-Api-Reference) — REST endpoints and helper functions

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

        // WordPress theme.json schema version used to validate the WP-shape
        // subset of theme.json. Pinned to match the @wordpress/* package
        // versions in artisanpack-ui/visual-editor.
        'wpThemeJsonSchemaVersion' => '3',
    ],
];
```

## Theme Manifest

`theme.json` carries cms-framework metadata plus an optional WordPress-shape subset for site-editor integration.

### cms-framework manifest fields

```json
{
    "name": "Digital Shopfront",
    "slug": "digital-shopfront",
    "version": "1.0.0",
    "description": "A reference theme.",
    "author": "Jacob Martella",
    "screenshot": "screenshot.png"
}
```

These fields are unchanged from earlier versions and are required for theme discovery.

### WordPress theme.json subset

Themes can additionally carry the WordPress `theme.json` top-level keys to drive global styles, custom templates, template parts, and patterns:

```json
{
    "name": "Digital Shopfront",
    "slug": "digital-shopfront",
    "version": "1.0.0",
    "$schema": "https://schemas.wp.org/wp/6.8/theme.json",

    "settings": {
        "color": {
            "palette": [
                { "slug": "primary", "name": "Primary", "color": "#3b82f6" }
            ]
        },
        "typography": {
            "fontSizes": [
                { "slug": "small", "name": "Small", "size": "0.875rem" }
            ]
        }
    },
    "styles": {
        "color": { "background": "#ffffff", "text": "#111827" }
    },
    "customTemplates": [
        { "name": "page-with-sidebar", "title": "Page with sidebar" }
    ],
    "templateParts": [
        { "name": "header", "title": "Header", "area": "header" }
    ],
    "patterns": [ "my-namespace/cta" ]
}
```

The WP-shape subset is validated against the WordPress `theme.json` schema version pinned in `cms.themes.wpThemeJsonSchemaVersion` (default `'3'`, matching WordPress 6.8). Bumping the pinned version requires also updating the bundled schema file at `src/Modules/Themes/Validation/schemas/wp-theme-json-v{N}.json`.

### `menus.locations` extension

cms-framework adds a `menus.locations` extension that overrides the default menu locations configured in `config('cms.menus.locations')`. Theme entries replace app-defined locations by key:

```json
{
    "menus": {
        "locations": {
            "primary": "Primary Menu",
            "footer":  "Footer Menu"
        }
    }
}
```

`menus.locations` must be an object mapping location keys (strings) to display labels (strings). Lists or non-string values are rejected.

### Validation behavior

When `ThemeManager::discoverThemes()` runs, each theme is validated in three stages:

1. The theme directory exists.
2. All `cms.themes.requiredFiles` entries are present.
3. The `theme.json` manifest passes the pinned WP schema (for any WP-shape keys it carries) and the `menus.locations` extension shape.

Themes that fail any stage are skipped from discovery. Schema failures log a warning naming the offending key (e.g. `settings.color.palette`) so theme authors can correct their manifest.

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
