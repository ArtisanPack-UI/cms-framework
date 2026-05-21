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

## REST API

### Saving registered settings (recommended)

To update registered settings over HTTP, use the by-key bulk endpoint. Every value is routed through `SettingsManager::updateSetting()`, so the registered sanitizer callback **and** `SettingType` are applied — exactly as if you had called `apUpdateSetting()` in PHP.

```http
PUT /api/v1/settings
Content-Type: application/json

{
  "settings": {
    "site.title": "  My Site  ",
    "site.logo_id": "42"
  }
}
```

The response echoes the freshly-stored, sanitized and cast values:

```json
{
  "settings": {
    "site.title": "My Site",
    "site.logo_id": 42
  }
}
```

Notes:

- Authorization goes through `SettingPolicy` (`settings.manage` by default, via the `settings.update` capability filter).
- Every key in the payload must be **registered** (`apRegisterSetting`/`SettingsManager::registerSetting`). Unregistered keys are rejected with a `422` validation error, since there would be no sanitizer or type to apply.
- This is the natural fit for an admin settings page that saves a group of keys at once.

### Generic CRUD caveat

The resourceful endpoints — `POST /api/v1/settings`, `PUT /api/v1/settings/{key}`, etc. — write **directly to the `Setting` model** and therefore **bypass the registered sanitizer callback and `SettingType`**. They are intended for ad-hoc, unregistered key/value storage.

> **Do not use the generic CRUD to save registered settings.** Use `PUT /api/v1/settings` (above) or `apUpdateSetting()` so sanitizers and types are always applied.

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
