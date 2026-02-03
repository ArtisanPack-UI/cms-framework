---
title: Registering Settings
---

# Registering Settings

Register settings during application boot so they are discoverable anywhere in your app or packages. Registration defines three things:
- A default value
- A type identifier (string, boolean, integer, float, json)
- A sanitization callback used when values are updated

You can register using the helper or the manager service.

## Using the Helper

```php
use function apRegisterSetting;

apRegisterSetting(
    key: 'site.title',
    defaultValue: 'My Site',
    callback: fn ($value) => trim((string) $value),
    type: 'string'
);

apRegisterSetting(
    key: 'site.is_private',
    defaultValue: false,
    callback: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
    type: 'boolean'
);
```

## Using the Manager

```php
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;

public function boot(SettingsManager $settings): void
{
    $settings->registerSetting(
        key: 'site.items_per_page',
        defaultValue: 10,
        callback: fn ($value) => max(1, (int) $value),
        type: 'integer'
    );
}
```

## Where to Register

- Service providers (recommended)
- Package bootstrapping code
- Anywhere that runs early during application boot

Under the hood, settings are collected through the `ap.settings.registeredSettings` filter so multiple packages can contribute without conflicts.

See also: [Hooks and Events](Hooks-And-Events).
