---
title: Sanitization and Types
---

# Sanitization and Types

Each setting defines a sanitization callback and a type. The callback is invoked on update to clean incoming values; the type guides how values are stored and read back.

## Sanitization Callback

Your callback should:
- Accept raw input (string, number, array, etc.)
- Convert it to the correct, safe PHP value
- Return the cleaned value

Examples:

```php
// String (trim + length limit)
apRegisterSetting(
    'site.title',
    defaultValue: 'My Site',
    callback: function ($value) {
        $value = trim((string) $value);
        return mb_strimwidth($value, 0, 120);
    },
    type: 'string'
);

// Boolean
apRegisterSetting(
    'site.is_private',
    defaultValue: false,
    callback: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
    type: 'boolean'
);

// Integer (bounded)
apRegisterSetting(
    'site.items_per_page',
    defaultValue: 10,
    callback: fn ($value) => max(1, min(200, (int) $value)),
    type: 'integer'
);

// Float (precision)
apRegisterSetting(
    'tax.rate',
    defaultValue: 0.0825,
    callback: fn ($value) => round((float) $value, 4),
    type: 'float'
);

// JSON (structured config)
apRegisterSetting(
    'ui.colors',
    defaultValue: ['primary' => '#2f6fef'],
    callback: function ($value) {
        // Accept JSON string or array; always return array
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        return $value;
    },
    type: 'json'
);
```

## Type Behavior

The Setting model persists a `type` along with `value` and casts automatically when reading:
- string → stored as string; null becomes empty string on read
- boolean → stored as '1'/'0'; returns true/false
- integer → stored as string; returns int
- float → stored as string; returns float
- json → stored as JSON; returns associative array

Tip: Keep your sanitizer consistent with the declared `type`.

## Nulls and Empty Values

- If your sanitizer returns null for a non-string type, the model may read back null.
- For strings, null is normalized to an empty string when stored.

## Validation vs. Sanitization

- Use form request validation for user input errors (required, min/max, etc.).
- Use the sanitizer to coerce/clean values and enforce invariants before persistence.

See also: [Retrieving and Updating](Retrieving-And-Updating) and [Registering Settings](Registering-Settings).
