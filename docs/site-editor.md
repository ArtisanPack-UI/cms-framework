---
title: Site Editor
---

# Site Editor Module

The Site Editor module hosts the backends behind cms-framework's WordPress-style site-editor surface: templates, template parts, patterns, global styles, and menus. H1 ships templates and template parts; H2 adds patterns; subsequent phases (H3–H4) add global styles and menus to the same module.

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

`id` is always the `theme//slug` form, matching WP exactly. `wp_id` carries the DB row's integer ID separately (0 when only a theme file backs the slug).

`content.raw` carries the file contents for theme-file-sourced entities and is the empty string `''` for DB-stored entities. `content.blocks` carries the parsed block array for DB-stored entities and is empty `[]` for theme-file-sourced entities. cms-framework's `HasBlockContent` trait stores only the parsed block array — never a raw HTML mirror — so consumers requiring HTML render through the matching renderer package, and consumers needing the parsed tree read from `content.blocks`. This mirrors the visual-editor adapter convention in `Adapters\CmsFramework\WpEntityResource`.

### Conflict and validation behavior

- `POST` returns **409 Conflict** when a `(theme, slug)` row already exists, with `errors.slug` set.
- `PUT` accepts a body without a slug (route slug is canonical) and returns **422** when a body slug is present but does not match the URL slug.

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

## Patterns

### Storage

Patterns occupy two disjoint sources rather than a file/DB merge:

- **Theme patterns** — PHP files at `themes/{active}/patterns/{slug}.php` with a leading WP-style header doc-comment (`Title:`, `Slug:`, `Categories:`, `Description:`, `Block Types:`). Read-only at runtime; the body after the doc-comment becomes the pattern content.
- **User patterns** — DB rows in `block_patterns` with columns `id`, `slug`, `theme` (nullable), `title`, `description`, `source`, `synced` (bool), `categories` (JSON), `block_types` (JSON), `block_content` (JSON via `HasBlockContent`), `author_id`, timestamps.

User-pattern slugs carry a `user/` prefix at storage time per plan 14 §5.6 — this guarantees a theme pattern named `hero` and a user pattern named `hero` do not collide in the merged inserter map. The REST surface presents the unprefixed user-facing slug; the storage form is exposed as `name` on the unsynced response (mirroring WP's namespaced pattern names).

### Sync semantics

`synced` distinguishes the two pattern shapes Gutenberg recognizes:

- `synced = true` — surfaced under `/blocks` (WP `wp_block` shape). Editing the pattern updates every occurrence in the editor.
- `synced = false` — surfaced under `/block-patterns/patterns` (WP `wp_block_pattern` shape). Each insertion is a snapshot; later edits to the pattern do not propagate.

Theme patterns are always `synced = false`. Cross-state conversion happens through dedicated POST flows on each endpoint, not via PUT (a PUT to one endpoint never flips a pattern's `synced` bit on the other endpoint).

### REST endpoints

| Method | Path | Purpose |
|---|---|---|
| GET    | `/blocks` | List user-source synced patterns (`wp_block` shape). |
| GET    | `/blocks/{slug}` | Show single synced pattern. |
| POST   | `/blocks` | Create a synced user pattern. |
| PUT    | `/blocks/{slug}` | Upsert a synced user pattern. |
| DELETE | `/blocks/{slug}` | Delete a synced user pattern. |
| GET    | `/block-patterns/patterns` | List theme + user-source unsynced patterns merged. |
| GET    | `/block-patterns/patterns/{slug}` | Show single unsynced pattern (theme or user). |
| POST   | `/block-patterns/patterns` | Create an unsynced user pattern. |
| PUT    | `/block-patterns/patterns/{slug}` | Upsert an unsynced user pattern. **403** when targeting a theme slug. |
| DELETE | `/block-patterns/patterns/{slug}` | Delete a user pattern. **403** when targeting a theme slug. |

A theme pattern can be cloned to an editable user pattern via `PatternResolver::cloneToUser($slug)` — used by the admin "edit" affordance on theme patterns. The clone copies title, description, categories, and block types from the theme file into a new user-source DB row.

### Resolver

`PatternResolver` returns `ResolvedPattern` value objects. Unlike `EntityResolver` (templates / parts), it surfaces a `cloneToUser()` affordance and `toFilterMap()` for filter consumers, but does not implement `revert()` — patterns have no override-then-revert workflow.

```php
class PatternResolver
{
    public function resolve(string $slug): ?ResolvedPattern;
    public function all(): array;                         // map<storage-slug, ResolvedPattern>
    public function toFilterMap(): array;                 // map<storage-slug, array<string, mixed>>
    public function cloneToUser(string $themeSlug): BlockPattern;
}
```

The merged map keys use the storage form: theme patterns under their natural slug (`hero`), user patterns under `user/{slug}`. visual-editor's `ap.visual-editor.patterns` consumer expects this shape.

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

`PatternResolver` (H2) does not implement `EntityResolver` — patterns have a different storage model and surface a `cloneToUser()` affordance instead of a slug-merge `revert()`. H3 (`GlobalStylesResolver`) and H4 (`MenuResolver`) will follow the same case-by-case shape decision.

## Permissions

The slugs `visual_editor.templates.edit`, `visual_editor.template-parts.edit`, and `visual_editor.patterns.edit` are seeded by the parent `CMSFrameworkServiceProvider` via the G5 (#98) bridge. The SiteEditor module does not register additional permissions; routes use the `auth` middleware (V1 baseline of "any authenticated user", per plan 12 §2.6). V1.1 introduces fine-grained policies.

## Service registration

`SiteEditorServiceProvider` is registered from the parent `CMSFrameworkServiceProvider`. It:

- Binds `TemplateResolver`, `TemplatePartResolver`, and `PatternResolver` as singletons.
- Loads `routes/api.php` for the REST endpoints.
- Registers `ap.visual-editor.patterns` filter under a `class_exists(VisualEditor::class)` guard so cms-framework boots cleanly without visual-editor installed.

Migrations live at the package level (`database/migrations/`) and are loaded by the parent `CMSFrameworkServiceProvider`.

## Coordination with visual-editor

- `#407` (H5 — site-editor resource filters): visual-editor reads the `/templates`, `/template-parts`, `/blocks`, and `/block-patterns/patterns` endpoints to populate `addEntities()` at editor bootstrap. Patterns specifically surface through the `ap.visual-editor.patterns` filter that cms-framework registers behind a `class_exists` guard.
- `#399` (G3 — editor entity adapter): visual-editor's WP-shape resource adapter maps the REST responses back into the editor's `useEntityRecord` / `useEntityRecords` selectors.
