# ArtisanPack UI CMS Framework

[![Latest Version on Packagist](https://img.shields.io/packagist/v/artisanpack-ui/cms-framework.svg?style=flat-square)](https://packagist.org/packages/artisanpack-ui/cms-framework)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/artisanpack-ui/cms-framework/run-tests?label=tests)](https://github.com/artisanpack-ui/cms-framework/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/artisanpack-ui/cms-framework/Check%20&%20fix%20styling?label=code%20style)](https://github.com/artisanpack-ui/cms-framework/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/artisanpack-ui/cms-framework.svg?style=flat-square)](https://packagist.org/packages/artisanpack-ui/cms-framework)

A comprehensive Laravel package that provides back-end support for building a CMS with any front-end framework. This package offers a complete set of features for content management, user management, authentication, and more.

## Features

- **Content Management**: Content types, taxonomies, and media management
- **Admin Interface**: Admin pages, dashboard widgets, and settings management
- **User Management**: User roles, permissions, and profiles (powered by `artisanpack-ui/rbac` in 2.0.0)
- **Authentication**: Two-factor authentication with Laravel Sanctum integration
- **Notifications**: Comprehensive notification system
- **Site Editor** *(new in 2.0.0)*: WordPress-style templates, template parts, patterns, global styles, and menus
- **Visual Editor Integration** *(new in 2.0.0)*: Opt-in bridge to the optional `artisanpack-ui/visual-editor` package
- **Blog Comments** *(new in 2.1.0)*: Threaded post comments with public read / guest submit, rate-limited public submission, and auth-gated moderation
- **Themes**: Discovery, activation, ZIP upload, and lifecycle hooks
- **Plugins**: Plugin system ⚠️ **Experimental**
- **Core Updates**: Automatic update checking and management with rollback support
- **PWA Support**: Progressive Web App features
- **Audit Logging**: Track changes and user actions

## Requirements

- PHP 8.2 or higher
- Laravel 12.0 or higher
- Laravel Sanctum 4.1 or higher

## Quick Installation

You can install the CMS Framework package by running the following composer command:

```bash
composer require artisanpack-ui/cms-framework
```

After installation, publish the configuration file:

```bash
php artisan vendor:publish --tag=cms-framework-config
```

Run the migrations to set up the database tables:

```bash
php artisan migrate
```

## Documentation

📚 **[Complete Documentation](docs/)**

- **[Installation Guide](docs/installation.md)** - Detailed installation and setup instructions
- **[Configuration](docs/configuration.md)** - Configuration options and environment setup
- **[Usage Guide](docs/usage.md)** - Comprehensive usage examples and tutorials
- **[API Documentation](docs/api.md)** - Complete REST API reference
- **[Migration Guide](docs/migration.md)** - Migrating from other CMS frameworks
- **[Testing](docs/testing.md)** - Testing strategies and examples
- **[Performance & Troubleshooting](docs/performance.md)** - Optimization and common issues
- **[Contributing](docs/contributing.md)** - Development and contribution guidelines

## Quick Start

### Content Types

Register a custom content type:

```php
use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;

app(ContentTypeManager::class)->register('product', [
    'name' => 'Product',
    'plural' => 'Products',
    'description' => 'Products for the store',
    'supports' => ['title', 'editor', 'thumbnail'],
]);
```

### Admin Pages

Register a custom admin page:

```php
use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesManager;

app(AdminPagesManager::class)->addPage([
    'title' => 'Custom Settings',
    'slug' => 'custom-settings',
    'callback' => function() {
        return view('custom.settings');
    }
]);
```

### Settings

Register and retrieve settings:

```php
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;

// Register a setting
app(SettingsManager::class)->register('site_name', 'My Awesome Site');

// Get a setting
$siteName = app(SettingsManager::class)->get('site_name');
```

### Customization with Hooks

The CMS Framework uses hooks and filters for extensive customization:

```php
// addFilter and addAction are global helper functions provided by the framework

// Add a filter
addFilter('ap.cms.migrations.directories', function($directories) {
    $directories[] = __DIR__ . '/database/migrations';
    return $directories;
});

// Add an action
addAction('ap.cms.after_content_save', function($content) {
    // Do something after content is saved
});
```

## Using with the visual editor

cms-framework is fully usable standalone, but pairs with [`artisanpack-ui/visual-editor`](https://github.com/ArtisanPack-UI/visual-editor) to expose its `Post` and `Page` content to a Gutenberg-style block editor, back `core/site-*` blocks with real site settings, and seed `visual_editor.*` permissions into the RBAC tables. The full integration contract lives in visual-editor's [`docs/plans/12-cms-framework-integration.md`](https://github.com/ArtisanPack-UI/visual-editor/blob/release/1.0/docs/plans/12-cms-framework-integration.md); the reciprocal narrative is visual-editor's [Using with cms-framework](https://github.com/ArtisanPack-UI/visual-editor/blob/release/1.0/README.md#using-with-cms-framework) section.

### Install both packages

```bash
composer require artisanpack-ui/cms-framework artisanpack-ui/visual-editor
```

Both packages are loosely coupled — every cms-framework hook into the editor is guarded by `class_exists(\ArtisanPackUI\VisualEditor\VisualEditor::class)`, so cms-framework continues to work standalone if visual-editor is not installed.

### Run migrations

```bash
php artisan migrate
```

The cms-framework migration set adds a `block_content json nullable` column to the `posts` and `pages` tables (alongside the preserved legacy `content` longText column, which keeps powering search / excerpt / backwards-compatibility). Editing a legacy post in the visual editor populates `block_content` on first save; the legacy column is left untouched. See plan 12 [§4.2 Block content storage](https://github.com/ArtisanPack-UI/visual-editor/blob/release/1.0/docs/plans/12-cms-framework-integration.md#42-block-content-storage) for the dual-state guidance.

### Resource map (`ap.visual-editor.resources`)

When visual-editor is detected, cms-framework's service provider auto-registers `Post` and `Page` into the editor's `ap.visual-editor.resources` filter:

```php
// CMSFrameworkServiceProvider::boot()
if ( class_exists( \ArtisanPackUI\VisualEditor\VisualEditor::class ) ) {
    addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
        return array_merge( [
            'posts' => \ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post::class,
            'pages' => \ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page::class,
        ], $resources );
    } );
}
```

Host-app overrides in `config/artisanpack/visual-editor.php` always win on key collision, so swapping `posts` to a custom `App\Models\Post` is just a config edit. Full filter contract (input/output shape, collision behavior, validation guarantees, contributor timing): plan 12 [§4.1 Resource filter contract](https://github.com/ArtisanPack-UI/visual-editor/blob/release/1.0/docs/plans/12-cms-framework-integration.md#41-resource-filter-contract).

### PostResolver accessors

`Post` and `Page` expose a handful of accessors that visual-editor's `PostResolver` reads to stamp `_resolved*` attributes on `core/post-*` and `core/navigation` blocks:

| Accessor | Type | Source | Used by |
|---|---|---|---|
| `rendered_content` | `string` | `HasRenderedBlockContent` concern — renders `block_content` through the visual editor's server-side renderer, falling back to the legacy `content` column when `block_content` is null | `_resolvedContent` on `core/post-content` |
| `previous_post` | `?Post` | adjacent post ordered by `published_at` (ties broken by `id`) | `_resolvedPreviousPost` on `core/post-navigation-link` |
| `next_post` | `?Post` | adjacent post ordered by `published_at` (ties broken by `id`) | `_resolvedNextPost` on `core/post-navigation-link` |
| `comments_count` | `int` | approved comments only | `_resolvedCommentsCount` on `core/post-comments-count` |
| `comments_url` | `string` | canonical `#comments` anchor on the post URL | `_resolvedCommentsUrl` on `core/post-comments-link` |

All accessors are eager-load friendly and safe to call on detached models — the adjacency lookup short-circuits to `null` for unsaved or unpublished posts.

### Site-meta bridge

cms-framework registers the `site.*` setting family via `SettingsManager` and exposes a WordPress-shape envelope at `GET /api/settings/site` that the editor's `core/site-*` blocks consume:

```php
$settings->registerSetting( 'site.title',    config( 'app.name', '' ),  'sanitizeText',  SettingType::String );
$settings->registerSetting( 'site.tagline',  '',                         'sanitizeText',  SettingType::String );
$settings->registerSetting( 'site.url',      config( 'app.url', '' ),    'sanitizeUrl',   SettingType::String );
$settings->registerSetting( 'site.logo_id',  null,                       'sanitizeInt',   SettingType::Integer );
$settings->registerSetting( 'site.icon_id',  null,                       'sanitizeInt',   SettingType::Integer );
```

```json
GET /api/settings/site
{
    "title": "ArtisanPack UI Demo",
    "description": "A Laravel CMS",
    "url": "https://example.test",
    "site_logo": 42,
    "site_icon": 17
}
```

Mutating endpoints (`PUT /api/settings/site`) update the matching `Setting` rows through `SettingsManager`. See plan 12 [§4.3 Site-meta REST shape](https://github.com/ArtisanPack-UI/visual-editor/blob/release/1.0/docs/plans/12-cms-framework-integration.md#43-site-meta-rest-shape).

### Permissions seed (`visual_editor.*`)

When visual-editor is detected, cms-framework seeds the editor's permission family into its RBAC tables so host apps can grant them on roles immediately:

```text
visual_editor.access
visual_editor.posts.edit
visual_editor.pages.edit
visual_editor.templates.edit
visual_editor.template-parts.edit
visual_editor.patterns.edit
visual_editor.global-styles.edit
visual_editor.navigation.edit
```

Permissions are registered but not yet enforced by visual-editor's policies — that flips in a future release behind `artisanpack.visual-editor.authorization.delegate_to_cms_framework`. See plan 12 [§4.6 Permissions seed (G5)](https://github.com/ArtisanPack-UI/visual-editor/blob/release/1.0/docs/plans/12-cms-framework-integration.md#46-permissions-seed-g5).

### Version pairing

cms-framework and visual-editor ship as a version pair — the supported combinations are tracked in visual-editor's [Version compatibility](https://github.com/ArtisanPack-UI/visual-editor/blob/release/1.0/README.md#version-compatibility) matrix. Bumping the major on either package without bumping the partner is unsupported.

## Experimental Features

The following features are **experimental** in the 1.0.0 release and should be used with caution in production environments:

### Plugin System

The plugin system provides a foundation for extending the CMS with custom functionality.

**What Works:**
- Plugin model with activation/deactivation tracking
- Plugin manager for lifecycle management
- Plugin installation and validation
- Plugin update manager integration

**Known Limitations:**
- Plugin lifecycle hooks not fully implemented
- No plugin dependency management
- Limited plugin configuration API
- No plugin marketplace integration

**Recommendation:** Use for testing and development. Not recommended for production until full lifecycle support is added in a future release.

### Theme System

The theme system allows customization of the CMS appearance.

**What Works:**
- Theme manager with theme discovery
- Theme activation mechanism
- JSON manifest validation
- Basic theme structure
- Lifecycle hooks for install/activate (see below)

**Known Limitations:**
- Asset compilation not implemented
- No child theme support
- Limited theme customization API
- No theme preview functionality

**Theme Lifecycle Hooks**

The Themes module fires `doAction()` callbacks around `installFromZip()` and `activateTheme()` so host applications can subscribe listeners (seed content, register theme-supplied service providers, etc.) without forking the framework.

| Hook | Fires | Payload | Throwing semantics |
|------|-------|---------|--------------------|
| `theme.installing` | After the ZIP is extracted and the manifest is validated, before the install is finalized. | `string $slug, array $manifest` | Throwing aborts the install; the extracted directory is rolled back. |
| `theme.installed` | After the install completes and the discovery cache is cleared. | `string $slug, array $manifest` | Throwing propagates to the caller (install is already complete). |
| `theme.activating` | After the target theme is resolved, before `themes.activeTheme` is updated. | `string $slug, array $manifest` | Throwing aborts activation; the active theme setting is not changed. |
| `theme.activated` | After `themes.activeTheme` is updated and cache invalidation is attempted (including `view:clear`, which is logged-and-continued on failure). | `string $slug, array $manifest` | Throwing propagates to the caller (activation is already complete). |

```php
addAction('theme.activated', function (string $slug, array $manifest): void {
    // Seed pages, register navigation entries, etc.
});
```

**Recommendation:** Use for testing and development. Full theme support including asset compilation and child themes will be added in a future release.

### Reporting Issues

If you encounter issues with experimental features, please report them on our [issue tracker](https://github.com/ArtisanPack-UI/cms-framework/issues) with the `experimental` label.

## Contributing

We welcome contributions! Please see **[Contributing Guide](docs/contributing.md)** for details on:

- Development setup
- Code style guidelines
- Testing requirements
- Submission process

## Security

If you discover a security vulnerability, please send an email to security@artisanpack.com. All security vulnerabilities will be promptly addressed.

## Credits

- [Jacob Martella](https://github.com/jacobmartella)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Changelog

Please see **[CHANGELOG.md](CHANGELOG.md)** for more information on what has changed recently.
