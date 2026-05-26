---
title: Site Editor - Templates and Template Parts
---

# Templates and Template Parts

The Templates and Template Parts entities form the structural backbone of a site-editor experience: full-page templates (`single`, `page`, `archive`, etc.) and reusable parts (`header`, `footer`, `sidebar`, etc.) that templates compose.

> **Added in 2.0.0** (H1).

## Templates

### Storage

- Theme files: `themes/{active}/templates/{slug}.html`
- DB table: `templates` with columns `id`, `theme`, `slug`, `title`, `description`, `status`, `is_custom`, `block_content` (JSON), `author_id`, timestamps. Unique constraint on `(theme, slug)`.

### REST endpoints

All endpoints live under `/api/v1/` and require authentication (`auth:sanctum`).

| Method | Path | Purpose |
|---|---|---|
| GET | `/templates` | List resolved templates (file + DB merged; DB wins per slug) |
| GET | `/templates/{slug}` | Show single resolved template |
| POST | `/templates` | Create DB-stored template (custom or override) |
| PUT | `/templates/{slug}` | Upsert DB-stored template |
| DELETE | `/templates/{slug}` | Revert (deletes DB row; theme file stays authoritative) |

### Response shape

Mirrors WordPress's `/wp/v2/templates`:

```json
{
    "id": "{theme}//{slug}",
    "slug": "page",
    "theme": "digital-shopfront",
    "type": "wp_template",
    "source": "theme",
    "origin": null,
    "content": {
        "raw": "<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->",
        "blocks": [],
        "block_version": 1
    },
    "title": { "raw": "Page", "rendered": "Page" },
    "description": "",
    "status": "publish",
    "wp_id": 0,
    "has_theme_file": true,
    "is_custom": false,
    "author": 0,
    "modified": null
}
```

- `id` is always the `theme//slug` form, matching WP exactly.
- `wp_id` carries the DB row's integer ID separately (0 when only a theme file backs the slug).
- `content.raw` carries the file contents for theme-file-sourced entities and is the empty string `''` for DB-stored entities.
- `content.blocks` carries the parsed block array for DB-stored entities and is empty `[]` for theme-file-sourced entities.

cms-framework's `HasBlockContent` trait stores only the parsed block array — never a raw HTML mirror — so consumers requiring HTML render through the matching renderer package, and consumers needing the parsed tree read from `content.blocks`.

### Conflict and validation behavior

- `POST` returns **409 Conflict** when a `(theme, slug)` row already exists, with `errors.slug` set.
- `PUT` accepts a body without a slug (route slug is canonical) and returns **422** when a body slug is present but does not match the URL slug.

## Template parts

### Storage

- Theme files: `themes/{active}/parts/{slug}.html`
- DB table: `template_parts` — same shape as `templates` plus a required `area` column.

### Areas

Template parts are constrained to a closed list of area values: `header`, `footer`, `sidebar`, `uncategorized`, and `navigation-overlay`. These match WordPress's defaults (the legacy `general` area was renamed to `uncategorized` in 2.0.0 to align with WP core).

The closed list is enforced at the application layer:

- Form Request rejects payloads with any other value (HTTP 422).
- Theme-file parts whose slug starts with a known area prefix (`header-large`, `footer-mini`) are auto-categorized into that area; everything else falls back to `uncategorized`.

### REST endpoints

Same shape as templates, but under `/api/v1/template-parts`. The response carries an additional `area` field and `type` is `wp_template_part`.

## Resolver contract

Templates and template parts implement `ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\EntityResolver`:

```php
interface EntityResolver
{
    public function resolve(string $slug): ?ResolvedEntity;
    public function all(): array;
    public function revert(string $slug): bool;
}
```

`ResolvedEntity` is a value object carrying the merged source-of-truth: slug, theme, source (`'db'` or `'theme'`), content (block string), title, description, status, `hasThemeFile`, `isCustom`, `area` (parts only), and the backing model (when source is `'db'`).
