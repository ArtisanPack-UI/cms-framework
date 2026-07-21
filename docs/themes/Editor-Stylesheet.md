---
title: Themes - Editor Stylesheet
---

# Editor-only Theme Stylesheet

> **Added in 2.5.0** ([#199](https://github.com/ArtisanPack-UI/cms-framework/issues/199)).

Themes may now ship an optional `themes/{slug}/editor.css` alongside the existing `themes/{slug}/style.css`. This is the analog to WordPress's `add_editor_style()`.

## Where it renders

- `GET /api/v1/global-styles/css` concatenates emitter output + `style.css` + `editor.css` (in that order), giving the site-editor canvas the full canvas stylesheet in a single fetch.
- The `@cmsGlobalStyles` Blade directive is unchanged — it renders only the emitter output — so `editor.css` never leaks to the public front-end.

Use `editor.css` for bare element selectors (`h1`, `blockquote`, `pre`) that should apply on the site-editor canvas without leaking into inspector-panel mini-previews.

## Reading theme stylesheets from PHP

The container-bound `ThemeStylesheetReader` (`app( ThemeStylesheetReader::class )`) resolves `themes/{slug}/{filename}` for the active theme:

```php
use ArtisanPackUI\CMSFramework\Modules\Themes\Support\ThemeStylesheetReader;

$reader = app( ThemeStylesheetReader::class );

$reader->frontendStylesheet();   // themes/{active}/style.css contents or null
$reader->editorStylesheet();     // themes/{active}/editor.css contents or null
$reader->read( 'other.css' );    // generic read for any filename under the theme
$reader->readWrapped( 'style.css' ); // wraps with /* === style.css === */ banner for concatenation
$reader->lastModified();         // freshest theme-stylesheet mtime for cache-key composition
```

Slug validation delegates to `ThemeManager::validateSlug()`, path resolution to `ThemeManager::getThemesPath()`, and traversal containment to the shared `PathContainmentGuard::within( $base, $candidate )` helper. `getActiveTheme()` is memoized per instance so multiple reads inside a request pay the schema-validation cost once. The reader is not `final` — downstream packages can extend or fake it.

## HTTP caching on `/api/v1/global-styles/css`

The endpoint carries an `ETag` derived from the concatenated body and honors `If-None-Match` with a 304, plus `Cache-Control: private, must-revalidate` so intermediaries revalidate cheaply instead of serving stale bytes when a theme edits `style.css` / `editor.css`.
