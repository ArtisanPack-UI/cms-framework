---
title: Hooks and Events
---

# Hooks and Events

The CMS Framework exposes a small set of WordPress‑style hooks and events to allow packages and applications to extend behavior without tightly coupling code.

This guide documents the available hooks grouped by module.

## Core: Assets

Filters for modifying enqueued assets before retrieval:

- ap.admin.enqueuedAssets
- ap.public.enqueuedAssets
- ap.auth.enqueuedAssets

Each filter receives the current associative array of assets and should return the modified array.

```php
addFilter('ap.admin.enqueuedAssets', function (array $assets) {
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

- ap.roleRegistered (action)
- ap.permissionRegistered (action)

```php
addAction('ap.roleRegistered', function ($role) {
    // React to role creation/registration
});

addAction('ap.permissionRegistered', function ($permission) {
    // React to permission creation/registration
});
```

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

See the Settings module docs for details: [Settings Hooks and Events](settings/Hooks-and-Events.md).
