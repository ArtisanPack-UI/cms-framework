---
title: Themes - Installing from a ZIP
---

# Installing Themes from a ZIP Archive

The Themes module supports uploading and installing themes from a ZIP archive at runtime. Use this for themes acquired from a marketplace, sent over by a designer, or restored from a backup — anything where you don't have direct filesystem access.

> **Added in 2.0.0.**

## Programmatic install

```php
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;

$themeManager = app(ThemeManager::class);

try {
    $manifest = $themeManager->installFromZip('/path/to/uploaded-theme.zip');
    // $manifest is the parsed theme.json of the freshly installed theme
} catch (\ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeInstallationException $e) {
    // ZIP corrupt, extraction failed, or slug already exists
} catch (\ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeValidationException $e) {
    // Extracted theme failed strict manifest validation
}
```

## REST endpoint

```
POST /v1/themes
Content-Type: multipart/form-data

theme_zip=@/path/to/theme.zip
```

The endpoint accepts a single multipart file field named `theme_zip`. On success it returns `201 Created` with the parsed manifest.

A rejected upload returns `422` carrying Laravel's standard validation shape, keyed by the field the failure belongs to:

```json
{
  "message": "Theme 'my-theme' is already installed.",
  "errors": {
    "theme_zip": ["Theme 'my-theme' is already installed."]
  }
}
```

Both the request-level rules (file present, ZIP MIME type, within `cms.themes.maxUploadSize`) and the manager-level rejections (`ThemeValidationException`, `ThemeInstallationException`) produce this shape, so an Inertia consumer can render either against the file field without a bespoke adapter. A failure that is *not* a rejected upload — an unexpected server fault — is reported and returns `500`.

## Pre-extraction validation

Before extracting, `ThemeManager::installFromZip()`:

1. Confirms the file exists, is readable, and ends in `.zip`.
2. Confirms the slug derived from the ZIP's root directory name does not collide with an already-installed theme.
3. Scans the archive for ZIP-slip attempts (entries whose normalized destination falls outside the themes base directory). Any such entry aborts extraction.

## Post-extraction strict validation

After extraction, `validateManifest()` runs an additional pass that is **stricter** than the standard discovery validator. This is the contract every uploadable theme must satisfy. It is **not** applied to themes already on disk so existing installations are not broken by tightened rules.

**Required fields:**

- `slug` — alphanumeric, hyphens, and underscores only (`/^[a-zA-Z0-9_-]+$/`).
- `name` — non-empty string.
- `version` — anchored semver `MAJOR.MINOR.PATCH` (`/^\d+\.\d+\.\d+$/`). Anchoring is deliberate; it prevents injection suffixes such as `1.0.0'; DROP TABLE`.

**Optional fields, validated when present:**

- `screenshot` — basename only, no path separators (`/` or `\`), with an allowlisted image extension (`png`, `jpg`, `jpeg`, `webp`).
- `requires` — anchored semver, same shape as `version`.
- `templates.layouts`, `templates.pages`, `templates.partials` — arrays of strings.
- `supports.*` — booleans (e.g. `supports.menus`, `supports.widgets`).

If strict validation fails, the freshly extracted theme directory is removed and a `ThemeValidationException` is thrown — you never end up with a half-installed theme on disk.

## WP theme.json schema validation

Themes that carry the WP-shape subset (`settings`, `styles`, `customTemplates`, `templateParts`, `patterns`) are additionally validated against the pinned WordPress `theme.json` schema. Schema failures throw `ThemeValidationException::invalidManifest()` with the offending key in the message.

The pinned schema version lives at `config('cms.themes.wpThemeJsonSchemaVersion')` (default `'3'`, matching WordPress 6.8). Bumping it requires also updating the bundled schema file at `src/Modules/Themes/Validation/schemas/wp-theme-json-v{N}.json`.

## Lifecycle hooks

`installFromZip()` fires two action hooks — see [[themes/Lifecycle Hooks]] for details:

| Hook | When |
|---|---|
| `theme.installing` | After successful extraction and validation, before the install is committed. Listeners may throw to abort; the extracted directory is rolled back. |
| `theme.installed` | After the install is committed. Listeners may run side-effects (run seeders, send notifications, etc.). |

## Limits and cleanup

- ZIP files larger than `php.ini`'s `upload_max_filesize` are rejected by Laravel's form-request validator before reaching the controller.
- The temporary extracted directory is removed automatically on validation failure.
- The original ZIP file is **not** deleted by `installFromZip()` — that's the caller's responsibility (a controller wrapping a multipart upload would typically use `$request->file('theme_zip')->store('temp')` and clean up after success).
