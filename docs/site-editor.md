---
title: Site Editor
---

# Site Editor Module

The Site Editor module hosts the backends behind cms-framework's WordPress-style site-editor surface: templates, template parts, patterns, global styles, and menus. H1 ships templates and template parts; subsequent phases (H2–H4) add patterns, global styles, and menus to the same module.

## Overview

Site-editor entities resolve through a hybrid file + database model: themes ship default content as files (e.g. `themes/{active}/templates/{slug}.html`), and the database stores user overrides. When both exist, the DB row wins. Reverting a DB override deletes the row and returns the entity to its theme-file source.

This mirrors WordPress's site-editor authority chain. Visual-editor (`#407` H5) consumes these endpoints to populate `addEntities()` configuration at editor bootstrap.

## Module placement

`src/Modules/SiteEditor/` is a sibling to `src/Modules/Themes/`. Conceptually it acts as a sub-module of the theme system — site-editor entities are theme-driven with DB overrides — but it earns its own module because:

- It owns its own models, migrations, and REST endpoints.
- Future phases (patterns, global styles, menus) extend the same module.
- The Themes module stays focused on theme discovery, activation, view-path registration, and `theme.json` validation.

## Templates

### Storage

- Theme files: `themes/{active}/templates/{slug}.html`
- DB table: `templates` with columns `id`, `theme`, `slug`, `title`, `description`, `status`, `is_custom`, `block_content` (JSON), `author_id`, timestamps. Unique constraint on `(theme, slug)`.

### REST endpoints

All endpoints live under `/api/v1/` and require authentication.

| Method | Path | Purpose |
|---|---|---|
| GET | `/templates` | List resolved templates (file + DB merged; DB wins per slug) |
| GET | `/templates/{slug}` | Show single resolved template |
| POST | `/templates` | Create DB-stored template (custom or override) |
| PUT | `/templates/{slug}` | Upsert DB-stored template |
| DELETE | `/templates/{slug}` | Revert (deletes DB row; theme file stays authoritative) |

Response shape mirrors WordPress's `/wp/v2/templates`:

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

`wp_id` is the DB-row integer ID (0 when only a theme file backs the slug). `id` uses the `theme//slug` form for theme-backed templates and the integer DB ID for purely custom ones.

## Template parts

### Storage

- Theme files: `themes/{active}/parts/{slug}.html`
- DB table: `template_parts` — same shape as `templates` plus a required `area` column.

### Areas

Template parts are constrained to four area values for V1: `header`, `footer`, `sidebar`, `general`. These match WordPress's default areas. Open-ended user-defined areas are deferred to V1.1 per plan 14 §8.

The closed list is enforced at the application layer:
- Form Request rejects payloads with any other value (HTTP 422).
- Theme-file parts whose slug starts with a known area prefix (`header-large`, `footer-mini`) are auto-categorized into that area; everything else falls back to `general`.

### REST endpoints

Same shape as templates, but under `/api/v1/template-parts`. The response carries an additional `area` field and `type` is `wp_template_part`.

## Resolver contract

Both entity types implement `ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\EntityResolver`:

```php
interface EntityResolver
{
    public function resolve(string $slug): ?ResolvedEntity;
    public function all(): array;
    public function revert(string $slug): bool;
}
```

`ResolvedEntity` is a value object carrying the merged source-of-truth: slug, theme, source (`'db'` or `'theme'`), content (block string), title, description, status, `hasThemeFile`, `isCustom`, `area` (parts only), and the backing model (when source is `'db'`).

H2–H4 will add `PatternResolver`, `GlobalStylesResolver`, and `MenuResolver` against the same interface.

## Permissions

The slugs `visual_editor.templates.edit` and `visual_editor.template-parts.edit` are seeded by the parent `CMSFrameworkServiceProvider` via the G5 (#98) bridge. The SiteEditor module does not register additional permissions; routes use the `auth` middleware (V1 baseline of "any authenticated user", per plan 12 §2.6). V1.1 introduces fine-grained policies.

## Service registration

`SiteEditorServiceProvider` is registered from the parent `CMSFrameworkServiceProvider`. It:

- Binds `TemplateResolver` and `TemplatePartResolver` as singletons.
- Loads `routes/api.php` for the REST endpoints.

Migrations live at the package level (`database/migrations/`) and are loaded by the parent `CMSFrameworkServiceProvider`.

## Coordination with visual-editor

- `#407` (H5 — site-editor resource filters): visual-editor reads the `/templates` and `/template-parts` endpoints to populate `addEntities()` at editor bootstrap.
- `#399` (G3 — editor entity adapter): visual-editor's WP-shape resource adapter maps the REST responses back into the editor's `useEntityRecord` / `useEntityRecords` selectors.
