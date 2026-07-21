---
title: Hooks and Events
---

# Hooks and Events

The CMS Framework exposes a small set of WordPress‑style hooks and events to allow packages and applications to extend behavior without tightly coupling code.

This guide documents the available hooks grouped by module.

> **Namespace normalized in 2.5.0** ([#193](https://github.com/ArtisanPack-UI/cms-framework/issues/193), [#194](https://github.com/ArtisanPack-UI/cms-framework/issues/194), [#195](https://github.com/ArtisanPack-UI/cms-framework/issues/195)) — most infrastructure, lifecycle, and ability hooks moved onto the `ap.cmsFramework.*` (or `ap.rbac.*`) namespace. Every pre-2.5.0 name below is registered as a WordPress-style alias, so existing subscribers keep firing; the old names emit a one-per-request deprecation log entry so downstreams can migrate at their own pace.

## Core: Assets

Filters for modifying enqueued assets before retrieval:

- ap.cmsFramework.admin.enqueuedAssets (was `ap.admin.enqueuedAssets`)
- ap.cmsFramework.public.enqueuedAssets (was `ap.public.enqueuedAssets`)
- ap.cmsFramework.auth.enqueuedAssets (was `ap.auth.enqueuedAssets`)

Each filter receives the current associative array of assets and should return the modified array.

```php
addFilter('ap.cmsFramework.admin.enqueuedAssets', function (array $assets) {
    $assets['custom-admin'] = [
        'path' => mix('js/custom-admin.js'),
        'inFooter' => true,
    ];
    return $assets;
});
```

## Users: Settings UI

- ap.users.settings.sections (filter)

Add or modify sections (tabs) for your user settings UI.

```php
apRegisterUserSettingsSection('profile', 'Profile', 10);

// Internally this uses the following filter:
addFilter('ap.users.settings.sections', function (array $sections) {
    $sections['profile'] = ['label' => 'Profile', 'order' => 10];
    return $sections;
});
```

## Users: Model Events

Actions fired when roles or permissions are registered via managers:

- ap.rbac.roleRegistered (action, was `ap.roleRegistered`)
- ap.rbac.permissionRegistered (action, was `ap.permissionRegistered`)

```php
addAction('ap.rbac.roleRegistered', function ($role) {
    // React to role creation/registration
});

addAction('ap.rbac.permissionRegistered', function ($permission) {
    // React to permission creation/registration
});
```

## Content: Lifecycle

> **Added in 2.5.0** ([#196](https://github.com/ArtisanPack-UI/cms-framework/issues/196)).

Every `Post` and `Page` model emits lifecycle actions via the `FiresLifecycleHooks` concern under `src/Modules/ContentTypes/Models/Concerns/`:

- ap.cmsFramework.{post,page}.saving
- ap.cmsFramework.{post,page}.saved
- ap.cmsFramework.{post,page}.published — fires **only on a transition into `ContentStatus::Published`**: first-save-as-published, draft→published, and scheduled→published all count; subsequent saves of an already-published record do not.
- ap.cmsFramework.{post,page}.trashed — soft delete only; force-delete is skipped.
- ap.cmsFramework.{post,page}.restored

## Admin: Dashboard Widgets

> **Added in 2.5.0** ([#196](https://github.com/ArtisanPack-UI/cms-framework/issues/196)).

`AdminWidgetManager::getAvailableWidgetsForUser()` runs its output through `ap.cmsFramework.admin.dashboardWidgets`, passing the resolved user (or `null`) so subscribers can make per-user injections without re-resolving auth.

```php
addFilter('ap.cmsFramework.admin.dashboardWidgets', function (array $widgets, ?User $user) {
    if ($user?->hasRole('editor')) {
        $widgets['editorial-queue'] = new EditorialQueueWidget();
    }
    return $widgets;
}, 10, 2);
```

## Plugins: Hook Registration

> **Added in 2.5.0** ([#196](https://github.com/ArtisanPack-UI/cms-framework/issues/196)).

`PluginManager::activate()` fires `ap.cmsFramework.plugin.hookRegistered` immediately after the plugin's service provider registers, carrying `(string $pluginSlug, array $hooks)`. The hooks array is the optional `hooks` field from the plugin's `plugin.json` (empty array when absent) so observers still get a per-plugin signal.

## Search: Query

> **Added in 2.5.0** ([#196](https://github.com/ArtisanPack-UI/cms-framework/issues/196)).

`HasContentFilters::applySearchFilter()` — used by both `BlogManager::getArchiveQuery()` and `PageManager::getPageQuery()` — runs the assembled search query through `ap.cmsFramework.search.query` with `(Builder $q, string $term, array $context)` where `$context` carries the calling manager class, the queried model class, and the full filter array. Enough for a subscriber to swap in a full-text index or route to an external search service.

## Conventions

- Filters should return the modified value; actions return void.
- Hook names are namespaced with the `ap.` prefix.
- Prefer kebab‑case segments for readability.

## Utilities

The examples above assume the presence of the following global helpers (provided by the artisanpack-ui/hooks package):

- addFilter(string $hook, callable $callback)
- applyFilters(string $hook, mixed $value): mixed
- addAction(string $hook, callable $callback)
- doAction(string $hook, ...$args): void


## Settings: Registered Settings

- ap.settings.registeredSettings (filter)

Provide settings definitions from anywhere. Each item should include:

```php
addFilter('ap.settings.registeredSettings', function (array $settings) {
    $settings['site.title'] = [
        'default'  => 'My Site',
        'type'     => 'string',
        'callback' => fn ($value) => trim((string) $value),
    ];

    return $settings;
});
```

See the Settings module docs for details: [Settings Hooks and Events](Settings-Hooks-And-Events).
