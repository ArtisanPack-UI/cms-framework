---
title: Themes - Theme Base Class
---

# Theme Base Class

> **Added in 2.5.0** ([#198](https://github.com/ArtisanPack-UI/cms-framework/issues/198)).

Themes may ship an optional `themes/{slug}/Theme.php` that extends `ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme` to hook into the per-request lifecycle. Themes without a `Theme.php` stay fully backward compatible — the base class is opt-in.

## What it unlocks

- Enqueue front-end and editor CSS + JS from PHP.
- Register custom image sizes.
- Register custom REST endpoints or block filters.
- Override the resolved theme class through the manifest (`themeClass`), guarded by a `ReflectionClass` provenance check so an uploaded `theme.json` cannot instantiate an unrelated first-party or vendor `Theme` subclass.

## Example

```php
<?php

namespace App\Themes\DigitalShopfront;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme;

class DigitalShopfrontTheme extends Theme
{
    public function frontendStyles(): array
    {
        return [
            'digital-shopfront/main' => asset( 'themes/digital-shopfront/assets/main.css' ),
        ];
    }

    public function editorStyles(): array
    {
        return [
            'digital-shopfront/editor' => asset( 'themes/digital-shopfront/assets/editor.css' ),
        ];
    }

    public function frontendScripts(): array
    {
        return [
            'digital-shopfront/main' => asset( 'themes/digital-shopfront/assets/main.js' ),
        ];
    }
}
```

## Filter surface

Three filters let third parties add or mutate enqueue lists without subclassing:

- `ap.themes.frontendStyles`
- `ap.themes.editorStyles`
- `ap.themes.frontendScripts`

Each carries the array of handles the theme returned from its matching method, so subscribers can `array_merge` or unset entries.

## Static assets route

A new `/themes/{slug}/assets/{path}` route serves static assets from the theme's `assets/` directory with:

- Slug, extension, and traversal validation.
- An explicit MIME map (so CSS/JS are never served as `text/plain`).
- `X-Content-Type-Options: nosniff`.
- CSP sandbox + `Content-Disposition` for SVG to close the stored-XSS vector on uploaded themes.
