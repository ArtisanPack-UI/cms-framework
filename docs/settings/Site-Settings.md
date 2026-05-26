---
title: Settings - Site Settings
---

# Site Settings

The Settings module ships with a built-in `site.*` namespace covering the five WordPress site-meta fields: title, tagline, URL, logo, and icon. These are surfaced both as ordinary settings (via `apGetSetting()` / `apUpdateSetting()`) and as a dedicated WP-shape REST envelope at `/api/v1/settings/site`.

> **Added in 2.0.0.**

## Registered keys

The `SettingsServiceProvider` registers the following five settings at boot:

| Setting key | Default | Type | Sanitizer |
|---|---|---|---|
| `site.title` | `config('app.name')` | string | `sanitizeText` |
| `site.tagline` | `''` | string | `sanitizeText` |
| `site.url` | `config('app.url')` | string | `sanitizeUrl` |
| `site.logo_id` | `null` | integer | `sanitizeInt` |
| `site.icon_id` | `null` | integer | `sanitizeInt` |

The two `*_id` fields are foreign keys to the Media Library (when installed). They are nullable — the editor surfaces "no logo/icon set" gracefully.

## Reading and writing programmatically

```php
use function apGetSetting;
use function apUpdateSetting;

// Read
$title   = apGetSetting('site.title');
$tagline = apGetSetting('site.tagline');
$logoId  = apGetSetting('site.logo_id');

// Write
apUpdateSetting('site.title', 'My New Site');
apUpdateSetting('site.logo_id', 42);
```

Sanitization is applied automatically — `site.title` is trimmed and HTML-stripped, `site.url` is validated as a URL, the `*_id` fields are cast to int (or null).

## WP-shape REST envelope

`GET /api/v1/settings/site` returns the five site fields in the WordPress `/wp/v2/settings` envelope shape:

```json
{
    "title":       "Digital Shopfront",
    "description": "An independent maker's catalog.",
    "url":         "https://example.com",
    "site_logo":   42,
    "site_icon":   17,
    "language":    "en"
}
```

`PUT /api/v1/settings/site` accepts the same envelope and applies sanitizers + types via the underlying `SettingsManager`. The body is partial — omit a field to leave it unchanged.

```json
{
    "title":     "Digital Shopfront 2.0",
    "site_logo": 87
}
```

The controller validates each field against its registered sanitizer and type. A `422` is returned when validation fails; the response body identifies the offending field.

## Envelope ↔ setting key mapping

| Envelope key | Setting key |
|---|---|
| `title` | `site.title` |
| `description` | `site.tagline` |
| `url` | `site.url` |
| `site_logo` | `site.logo_id` |
| `site_icon` | `site.icon_id` |

`language` is read-only and derived from `config('app.locale')`.

## Authorization

The site-settings endpoints share the Settings module's policy gate. The current 2.0.0 baseline is `auth:sanctum` — any authenticated user with a Sanctum token can read and write. Future releases will add fine-grained policy hooks for the site-meta fields specifically.

## Why a dedicated envelope?

visual-editor's `core/site-title`, `core/site-tagline`, `core/site-logo`, and `core/site-icon` blocks expect the WordPress `/wp/v2/settings` shape. Wrapping the five settings in a single envelope lets the editor read everything it needs in one request and lets it PUT a partial update without orchestrating five separate `/settings/{key}` calls.

The underlying values are still ordinary settings — anything outside the editor surface keeps using `apGetSetting('site.title')` etc.
