---
title: Site Editor - Global Styles
---

# Global Styles

The Global Styles entity represents a singleton-per-theme tree of `settings` and `styles` deltas layered over the active theme's `theme.json` defaults. It powers a Gutenberg-style global-styles experience: color palettes, font sizes, spacing scales, per-element styling, and named variations.

> **Added in 2.0.0** (H3).

## Storage

Global styles are a singleton-per-theme. Theme defaults live in `themes/{active}/theme.json` (`settings` and `styles` top-level keys); user customization lives in a single `global_styles` row keyed by `theme`. Switching themes leaves prior rows untouched (data preservation); switching back resumes the prior customization.

Two JSON columns (`settings` + `styles`) mirror the `theme.json` top-level split. The DB row stores deltas only — the resolver merges with theme-file defaults at read time, so theme-default changes flow through without a full re-save.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint, auto-increment | Surfaced as `id` on REST responses. |
| `theme` | string, **unique** | The active theme slug at save time. Unique index enforces singleton-per-theme. |
| `title` | string, nullable | WP-style display title. |
| `settings` | JSON, nullable | User overrides for the `theme.json` `settings` tree. |
| `styles` | JSON, nullable | User overrides for the `theme.json` `styles` tree. |
| `variation` | string, nullable | Slug of the active variation declared in `themes/{active}/styles/{slug}.json`. |
| `author_id` | FK, nullable | Last editor. |

## Resolver merge order

`GlobalStylesResolver` deep-merges three sources in priority order (lowest → highest):

1. `themes/{active}/theme.json` `settings` + `styles` — theme defaults.
2. The active variation file at `themes/{active}/styles/{variation}.json`, when the DB row pins one.
3. The DB row's `settings` + `styles` columns — user customization.

Numeric arrays (palette lists, font-size lists) replace wholesale rather than appending — matches WP semantics where a downstream layer should redeclare the full list. Associative objects deep-merge.

The DB row is created lazily on the first `update()` call. Reads with no DB row return file-only resolution (theme + variation if pinned in a future write).

## Variations

Variations are theme-only for 2.0.0. Each variation lives at `themes/{active}/styles/{slug}.json` with the same shape as `theme.json` (typically `slug`, `title`, `description`, `settings`, `styles`). Runtime app-level variation registration is planned for a future release.

## CSS emission

`GlobalStylesEmitter` translates the resolved styles tree into CSS:

- `settings.color.palette` → `--wp--preset--color--{slug}` custom properties.
- `settings.typography.fontSizes` → `--wp--preset--font-size--{slug}`.
- `settings.typography.fontFamilies` → `--wp--preset--font-family--{slug}`.
- `settings.spacing.spacingSizes` → `--wp--preset--spacing--{slug}`.
- `settings.color.gradients` → `--wp--preset--gradient--{slug}`.
- `settings.custom` → `--wp--custom--{nested}--{kebab-key}` (recursive flattening).
- `styles.color` / `styles.typography` → applied to `:root`.
- `styles.elements.{link,heading,button}` → element-scoped rules (`a`, `h1, h2, …`, `.wp-element-button, .wp-block-button__link`).

Output is cached on a content-hash key derived from the resolved styles tree. Invalidation is event-driven, not time-driven — the cache busts when:

- The `GlobalStyles` model is saved or deleted (model observer in `SiteEditorServiceProvider`).
- The theme switches (the next resolve produces a new content hash; the prior entry ages out passively under the cache TTL).

## Front-end Blade directive

```blade
<head>
    @cmsGlobalStyles
</head>
```

Themes opt in by including `@cmsGlobalStyles` in their root layout. The directive expands to a `<style id="cms-global-styles">` block carrying the emitter's output. The same CSS is exposed via `GET /api/v1/global-styles/css` for the editor canvas and any front-end that prefers fetching to using the directive.

## REST endpoints

Singleton-per-theme — no `{slug}` segment.

| Method | Path | Purpose |
|---|---|---|
| GET    | `/global-styles` | Resolved styles for the active theme. |
| PUT    | `/global-styles` | Create or update the user-customization row. |
| DELETE | `/global-styles` | Revert to file-only authority. |
| GET    | `/global-styles/variations` | List variations from `themes/{active}/styles/*.json`. |
| GET    | `/global-styles/css` | Emit the resolved CSS as `text/css`. |

Response shape (`GET /global-styles`):

```json
{
    "id": 12,
    "theme": "digital-shopfront",
    "title": null,
    "settings": { "color": { "palette": [...] } },
    "styles": { "color": { "background": "#ffffff" } },
    "variation": "dark",
    "has_user_customization": true,
    "content_hash": "8b9a…",
    "modified": "2026-05-02T15:43:11+00:00"
}
```

## Resolver

```php
class GlobalStylesResolver
{
    public function resolve(): ?ResolvedGlobalStyles;            // singleton; null only when no active theme
    public function variations(): array;                         // theme-declared variations
    public function update(array $payload): ?GlobalStyles;       // lazy-create DB row
    public function revert(): bool;                              // delete DB row
}
```

`ResolvedGlobalStyles` carries `theme`, `settings`, `styles`, `variation`, `hasUserCustomization`, `model` (when DB-backed), plus a `contentHash()` accessor that the emitter uses for cache keying and a `toFilterEntry()` accessor that produces the `ap.visual-editor.global-styles` shape.
