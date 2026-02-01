---
title: Settings — Getting Started
---

# Settings — Getting Started

This short guide walks you through registering your first setting and using it throughout your application.

## What is a Setting?

A setting is a named key that has:
- A default value
- A declared type (string, boolean, integer, etc.)
- A sanitization callback that cleans any value before it is saved

Settings are stored in the `settings` database table. If the table is not yet available (e.g., before migrations run), reads fall back to the registered default or an explicit fallback you pass when retrieving.

## Prerequisites

Run the package migrations so the `settings` table exists:

```bash
php artisan migrate
```

## Register a Setting

Register settings during application boot (e.g., in a service provider):

```php
use function apRegisterSetting;

public function boot(): void
{
    // A simple string setting that trims whitespace
    apRegisterSetting(
        key: 'site.title',
        defaultValue: 'My Site',
        callback: fn ($value) => trim((string) $value),
        type: 'string'
    );
}
```

## Use a Setting

Retrieve the current value anywhere in your code:

```php
use function apGetSetting;

$title = apGetSetting('site.title');
```

Update the value; it will be sanitized by your callback before saving:

```php
use function apUpdateSetting;

apUpdateSetting('site.title', '  A Better Title  ');
```

## Fallback Behavior

When calling `apGetSetting($key, $default)` the value is resolved as:
1) Stored database value, if present
2) Otherwise your explicit `$default` argument, if provided (takes precedence over registered default)
3) Otherwise the registered default from when you called `apRegisterSetting`

Proceed to [Registering Settings](Registering-Settings) for more options and examples.
