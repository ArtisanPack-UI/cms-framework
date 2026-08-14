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
- [[themes/Installing From Zip]] — Upload a theme as a ZIP archive *(2.0.0)*
- [[themes/Updating]] — Declare an `update` source and update an installed theme in place *(2.8.0)*
- [[themes/Lifecycle Hooks]] — Listen to `theme.activating`, `theme.activated`, `theme.installing`, `theme.installed` *(2.0.0)*
- [[themes/Theme Base Class]] — Optional `themes/{slug}/Theme.php` for per-request enqueues, image sizes, and REST/block registration *(2.5.0)*
- [[themes/Editor Stylesheet]] — Ship a `themes/{slug}/editor.css` for canvas-only overrides *(2.5.0)*

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

Theme settings live at `config/cms/themes.php` and are read under the
`cms.themes` config key. Publish the file with either the umbrella tag or the
themes-only tag:

```bash
php artisan vendor:publish --tag=cms-framework-config
# or, themes config only:
php artisan vendor:publish --tag=cms-themes-config
```

The published file returns the settings array directly — it is *not* wrapped in
a `themes` key, since the module merges it under `cms.themes` for you:

```php
return [
    // Directory where themes are stored (relative to base_path)
    'directory' => 'themes',

    // Default theme slug — null unless you name one. See below.
    'default' => env( 'CMS_DEFAULT_THEME' ),

    // Required files for theme validation
    'requiredFiles' => [
        'theme.json',
    ],

    // Cache settings
    'cacheEnabled' => env( 'THEMES_CACHE_ENABLED', true ),
    'cacheKey'     => 'cms.themes.discovered',
    'cacheTtl'     => 3600, // 1 hour

    // WordPress theme.json schema version used to validate the WP-shape
    // subset of theme.json. Pinned to match the @wordpress/* package
    // versions in artisanpack-ui/visual-editor.
    'wpThemeJsonSchemaVersion' => '3',
];
```

The published file carries more keys than are shown here — upload limits,
update settings and asset caching among them. See the shipped
`src/Modules/Themes/config/themes.php` for the annotated full list.

Since *2.8.0*, `cms.themes.maxUncompressedSize` (100MB default) is an
uncompressed-size ceiling enforced *before* a theme archive is extracted — a
zip-bomb guard that rejects an archive whose declared uncompressed size exceeds
the limit, independently of the download and upload size ceilings. The plugin
module has the equivalent `cms.plugins.maxUncompressedSize`.

### The default theme (`CMS_DEFAULT_THEME`)

`cms.themes.default` is the slug the framework falls back to when the
`themes.activeTheme` setting has never been written — a fresh install, or one
whose settings table has not been seeded. It ships as `null`.

The framework bundles no themes of its own, so it has no slug it could name
here that would be right for every consumer. Left null, an install with no
activated theme resolves cleanly to "no active theme":

- `ThemeManager::getActiveTheme()` returns `null`.
- `registerThemeViewPath()` early-returns, leaving the host application's own
  view paths in place.
- `markActiveTheme()` flags every discovered theme `is_active => false`, so
  `GET /v1/themes` lists them all with nothing selected.
- The site-editor resolvers (templates, template parts, menus, patterns,
  global styles) each fall back to their theme-less path.

Name a default by setting the env var:

```dotenv
CMS_DEFAULT_THEME=my-theme
```

Or by editing the published config directly. Either way the slug is only a
fallback — the moment a theme is activated through
`ThemeManager::activateTheme()` or `POST /v1/themes/{slug}/activate`, the
stored `themes.activeTheme` setting wins and the default is no longer
consulted.

> **If you cache your config**, `env()` is evaluated once when the cache is
> written and the result is baked in. Changing `CMS_DEFAULT_THEME` in `.env`
> afterwards has no effect until you re-run `php artisan config:cache`. This is
> standard Laravel behavior for every `env()` call in a config file, but it is
> easy to miss here because the symptom — no active theme — looks identical to
> not having set the variable at all.

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

### Strict install validation

`ThemeManager::installFromZip()` runs an additional `validateManifest()` check after extraction. This stricter pass is the contract every uploadable theme must satisfy. It is not applied to themes already on disk so existing installations are not broken by tightened rules.

**Required fields:**

- `slug` — alphanumeric, hyphens, and underscores only (`/^[a-zA-Z0-9_-]+$/`).
- `name` — non-empty string.
- `version` — anchored semver `MAJOR.MINOR.PATCH` (`/^\d+\.\d+\.\d+$/`). Anchoring is deliberate; it prevents injection suffixes such as `1.0.0'; DROP TABLE`.

**Optional fields, validated when present:**

- `screenshot` — basename only, no path separators (`/` or `\`), with an allowlisted image extension (`png`, `jpg`, `jpeg`, `webp`).
- `requires` — anchored semver, same shape as `version`.
- `templates.layouts`, `templates.pages`, `templates.partials` — arrays of strings.
- `supports.*` — booleans (e.g. `supports.menus`, `supports.widgets`).

If validation fails, the freshly extracted theme directory is removed and a `ThemeValidationException` is thrown.

### Reserved `keystone` namespace

The `keystone` top-level key is reserved for consumer-specific install hints (e.g. `keystone.installer`, `keystone.seed.pages[]`). cms-framework treats this namespace as opaque — it is preserved through parsing but not interpreted. Downstream CMSes can layer their own installer/seed contracts under `keystone` without forking the manifest spec.

## Template Hierarchy

The theme system implements a WordPress‑style template hierarchy for resolving templates:

1. `single-{contentType}-{slug}.blade.php` — Specific content item
2. `single-{contentType}.blade.php` — Content type archive
3. `single.blade.php` — Generic single template
4. `index.blade.php` — Fallback template

This allows themes to provide increasingly specific templates for different content types and items.

## REST API Endpoints

All endpoints require authentication via Laravel Sanctum and are prefixed with `/v1`. Since *2.8.0*, the mutating routes additionally require the `manage-themes` permission (deny-by-default, seeded to the `admin` role); the `GET` routes stay auth-only:

- `GET /themes` — List all available themes
- `POST /themes` — Upload and install a theme from a ZIP *(requires `manage-themes`)*
- `GET /themes/updates` — List themes with an update available *(2.8.0)*
- `GET /themes/{slug}` — Get specific theme details
- `POST /themes/{slug}/activate` — Activate a theme *(requires `manage-themes`)*
- `POST /themes/{slug}/update` — Update a theme in place *(2.8.0, requires `manage-themes`)*

### Error shape

Action endpoints (`POST /themes`, `POST /themes/{slug}/activate`, `POST /themes/{slug}/update`) surface failures as a Laravel `ValidationException` — `422` with an `errors` bag keyed by the field the failure belongs to (`theme_zip` for uploads, `slug` for actions taken against an installed theme). That is the shape Inertia's `usePage().props.errors` and `useForm().errors` read, so an admin UI can render field-level messages without an error-shape adapter, and a pure-API client gets a parseable `errors` object rather than a bare `message`.

Since *2.8.0*, an unknown slug on `POST /themes/{slug}/activate` and `POST /themes/{slug}/update` is one of those `422` responses rather than a `404` — the slug is form input there, not a resource path. `GET /themes/{slug}` still answers `404`, because there the slug *is* the resource path.

The `422` covers request validation and the manager's own named rejections. An *unexpected* server fault is reported and returns `500` on every endpoint above.

## Service Registration

The `ThemesServiceProvider` automatically:

- Registers the `ThemeManager` and `UpdateManager` as singletons
- Merges theme configuration
- Registers the active theme's view path with Laravel
- Loads theme API routes
- Registers the `themes.activeTheme` setting

See the guides above for detailed usage and patterns.
