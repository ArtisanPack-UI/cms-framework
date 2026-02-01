---
title: Settings Hooks and Events
---

# Settings Hooks and Events

The Settings module exposes a single filter used to register and discover settings definitions from anywhere in your application or packages.

## Filters

### ap.settings.registeredSettings

- Type: filter
- Purpose: Provide and aggregate settings definitions
- Signature: `applyFilters('ap.settings.registeredSettings', array $settings): array`

Each entry in the array should be keyed by the setting key and contain:

```php
[
    'default'  => mixed,     // Default value for reads when not stored
    'type'     => string,    // 'string' | 'boolean' | 'integer' | 'float' | 'json'
    'callback' => callable,  // Sanitizer used on update
]
```

### Example: Register via Filter

While the preferred way is using `apRegisterSetting()` or `SettingsManager::registerSetting()`, you can also contribute directly via the filter if needed:

```php
addFilter('ap.settings.registeredSettings', function (array $settings) {
    $settings['site.tagline'] = [
        'default'  => 'Just another site',
        'type'     => 'string',
        'callback' => fn ($value) => trim((string) $value),
    ];

    return $settings;
});
```

## Related Docs

- [Registering Settings](Registering-Settings)
- [Retrieving and Updating](Retrieving-And-Updating)
- Global hooks overview: ../Hooks-and-Events.md
