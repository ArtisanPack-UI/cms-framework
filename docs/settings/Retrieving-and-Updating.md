---
title: Retrieving and Updating
---

# Retrieving and Updating Settings

This guide shows how to read and write setting values using either the global helpers or the SettingsManager service.

## Helpers

The helpers are the simplest way to work with settings anywhere in your codebase.

```php
use function apGetSetting;    // Retrieve
use function apUpdateSetting; // Update (sanitizes before persisting)

// Retrieve with optional explicit fallback default
$siteTitle = apGetSetting('site.title', 'My Site');

// Update — the value is sanitized by the callback you registered
apUpdateSetting('site.title', '  New Title  ');
```

## Manager Service

```php
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;

public function show(SettingsManager $settings)
{
    $itemsPerPage = $settings->getSetting('site.items_per_page', 10);

    // ...
}

public function update(SettingsManager $settings)
{
    $settings->updateSetting('site.items_per_page', request('per_page'));
}
```

## Default Resolution Order

When you request a setting value using `getSetting($key, $default)` or `apGetSetting($key, $default)`, the value is resolved in this order:

1. Stored database value (if the `settings` table exists and a record is present)
2. The explicit `$default` argument you provided to `getSetting`/`apGetSetting` (if not null)
3. The registered default from `apRegisterSetting`/`SettingsManager::registerSetting`

If none are available, `null` (or an empty string for string type) may be returned depending on the model cast behavior.

## Sanitization on Update

All updates go through the registered sanitization callback for the setting key. The callback should:
- Accept any raw input
- Return a cleaned value of the expected type

Example boolean sanitizer:

```php
apRegisterSetting(
    'site.is_private',
    defaultValue: false,
    callback: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
    type: 'boolean'
);

// Later...
apUpdateSetting('site.is_private', '1'); // stores true
```

## Type Casting on Read/Write

The underlying Setting model casts values based on a stored `type`:
- On write, the model determines a type from the PHP value (boolean, integer, float, json, string) and stores both the `type` and a normalized representation of `value`.
- On read, it converts the stored value back to the proper PHP type using `type`.

Tip: If you always return the correct type from your sanitizer, your stored type will remain consistent.
