---
title: Settings
---

# Settings Module

The Settings module provides a lightweight, application‑wide key–value store with:
- Declarative registration of settings (with defaults, types, and sanitization callbacks)
- Simple helpers to retrieve and update values
- Database persistence in a dedicated `settings` table
- A filter hook so packages and modules can register settings from anywhere

## Settings Guides

- [Getting Started](Settings-Getting-Started) — Quick intro and first setting
- [Registering Settings](Settings-Registering-Settings) — Define defaults, types, and sanitizers
- [Retrieving and Updating](Settings-Retrieving-And-Updating) — Read and write values at runtime
- [Sanitization and Types](Settings-Sanitization-And-Types) — Ensure clean, typed input
- [Hooks and Events](Settings-Hooks-And-Events) — `ap.settings.registeredSettings` filter
- [Database and Migrations](Settings-Database-And-Migrations) — Storage schema and considerations

## Overview

Settings are discovered via a filter and stored in the database. You register settings during boot, then read and write them anywhere in your app.

### Quick Example

```php
use function apRegisterSetting;
use function apGetSetting;
use function apUpdateSetting;

// Register during boot (e.g., a service provider)
apRegisterSetting(
    key: 'site.title',
    defaultValue: 'My Site',
    callback: fn ($value) => trim((string) $value),
    type: 'string'
);

// Retrieve (uses stored value, registered default, or explicit fallback)
$title = apGetSetting('site.title', 'Fallback Title');

// Update (value will be sanitized using the registered callback)
apUpdateSetting('site.title', '  New Title  ');
```

See the guides above for more details and patterns.
