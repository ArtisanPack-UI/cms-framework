---
title: Site Editor
---

# Site Editor Module

The Site Editor module hosts the backends behind cms-framework's WordPress-style site-editor surface: templates, template parts, patterns, global styles, and menus. H1 ships templates and template parts; H2 adds patterns; H3 adds global styles + CSS emission; H4 adds menus and the locations API.

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

## Global styles

### Storage

Global styles are a singleton-per-theme. Theme defaults live in `themes/{active}/theme.json` (`settings` and `styles` top-level keys); user customization lives in a single `global_styles` row keyed by `theme`. Switching themes leaves prior rows untouched (data preservation per plan 14 §5.5); switching back resumes the prior customization.

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

### Resolver merge order

`GlobalStylesResolver` deep-merges three sources in priority order (lowest → highest):

1. `themes/{active}/theme.json` `settings` + `styles` — theme defaults.
2. The active variation file at `themes/{active}/styles/{variation}.json`, when the DB row pins one.
3. The DB row's `settings` + `styles` columns — user customization.

Numeric arrays (palette lists, font-size lists) replace wholesale rather than appending — matches WP semantics where a downstream layer should redeclare the full list. Associative objects deep-merge.

The DB row is created lazily on the first `update()` call. Reads with no DB row return file-only resolution (theme + variation if pinned in a future write).

### Variations

Variations are theme-only for V1. Each variation lives at `themes/{active}/styles/{slug}.json` with the same shape as `theme.json` (typically `slug`, `title`, `description`, `settings`, `styles`). Runtime app-level variation registration is V1.1.

### CSS emission

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

### Front-end Blade directive

```blade
<head>
    @cmsGlobalStyles
</head>
```

Themes opt in by including `@cmsGlobalStyles` in their root layout. The directive expands to a `<style id="cms-global-styles">` block carrying the emitter's output. The same CSS is exposed via `GET /api/v1/global-styles/css` for the editor canvas (visual-editor H6) and any front-end that prefers fetching to using the directive.

### REST endpoints

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

### Resolver

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

## Menus

H4 introduces navigation menus and the locations API. Unlike templates / parts / patterns / global styles, menus are **DB-only** — themes contribute *location names* via `theme.json` `menus.locations`, but never menu *content*. Switching themes leaves prior menus untouched (data preservation per plan 14 §5.5).

### Storage

- DB tables:
  - `menus` — `id`, `theme`, `slug`, `name`, `description`, `auto_add_pages`, `author_id`, timestamps. Unique constraint on `(theme, slug)`.
  - `menu_items` — `id`, `menu_id` (FK), `parent_id` (nullable, self-FK), `position`, `type` (`link` / `submenu` / `page-list`), `label`, `url`, `target`, `rel`, `classes`, `description`, `object_type`, `object_id`, `kind`, timestamps.
  - `menu_location_assignments` — `id`, `theme`, `location`, `menu_id` (FK), timestamps. Unique constraint on `(theme, location)`.

A separate `menu_location_assignments` table (rather than a `location` column on `menus`) lets one menu satisfy multiple locations and keeps location keys theme-scoped — switching themes leaves a menu unassigned without orphaning the menu itself.

### Locations resolution

Locations resolve through `ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\Menus`:

```php
Menus::locations();                    // array<string, string>
Menus::assign( 'primary', $menuId );   // upsert assignment
Menus::unassign( 'primary' );          // delete assignment
Menus::assigned( 'primary' );          // ?Menu
```

Order:

1. App default — `config('cms.menus.locations')`, e.g. `['primary' => 'Primary', 'footer' => 'Footer']`.
2. Theme override — the active theme's `theme.json` `menus.locations` keys override the app defaults by key (theme wins per plan 14 §5.4); a warning is logged so app authors aren't confused.

Themes that need a location no app has registered can declare it in `theme.json` and the location appears with the theme-provided label.

### REST endpoints

All endpoints live under `/api/v1/` and require authentication.

| Method | Path | Purpose |
|---|---|---|
| GET | `/menus` | List menus for the active theme |
| POST | `/menus` | Create a menu |
| GET | `/menus/{id_or_slug}` | Show one menu |
| PUT | `/menus/{id_or_slug}` | Update menu metadata (slug renames not supported) |
| DELETE | `/menus/{id_or_slug}` | Delete the menu (cascades to items and assignments) |
| GET | `/menu-items?menus={id}` | List items for a menu, ordered by `(parent_id, position)` |
| POST | `/menu-items` | Create item under a menu (`menus` field required) |
| GET | `/menu-items/{id}` | Show one item |
| PUT | `/menu-items/{id}` | Update item (the `menus` field is prohibited — items can't be reparented across menus) |
| DELETE | `/menu-items/{id}` | Delete item (cascades to children) |
| GET | `/menu-locations` | List declared locations + their assignments (`menu` is `0` when unassigned) |
| PUT | `/menu-locations/{location}` | Assign a menu to a location (body: `{ "menu": <id> }`) |
| DELETE | `/menu-locations/{location}` | Unassign |

Menu response shape mirrors WP `/wp/v2/menus`:

```json
{
    "id": 1,
    "name": "Main Navigation",
    "slug": "main",
    "description": "",
    "meta": [],
    "locations": [ "primary" ],
    "auto_add_pages": false,
    "theme": "digital-shopfront"
}
```

Menu-item response shape mirrors WP `/wp/v2/menu-items`. Note that `type` carries the WP-side vocabulary (`custom` / `post_type` / `taxonomy` derived from the model `kind`), while `link_type` carries the cms-side flag (`link` / `submenu` / `page-list`) for visual-editor's adapter:

```json
{
    "id": 4,
    "title":  { "raw": "Home", "rendered": "Home" },
    "url":    "/",
    "menus":  1,
    "parent": 0,
    "menu_order": 0,
    "target": "_self",
    "type":       "custom",
    "type_label": "Custom Link",
    "link_type":  "link",
    "object":     "",
    "object_id":  0,
    "classes":    [],
    "xfn":        [],
    "attr_title": "",
    "description": "",
    "meta":       []
}
```

### Resolver

`MenuResolver::all()` powers the `ap.visual-editor.navigation` filter. It returns `array<string, ResolvedMenu>` keyed by location key. Locations the active theme declares but no menu is assigned to still appear with `wp_id => null` and an empty `items` array, so editor surfaces can render empty slots.

The `items` field projects `MenuItem` rows into the upstream `core/navigation-link` / `core/navigation-submenu` / `core/page-list` shapes — children nest under parents by `(parent_id, position)`. Page-list items render as a dynamic placeholder (`'dynamic' => 'page-list'`) — the resolver does not enumerate pages here so cms-framework stays decoupled from Phase G content types. The navigation block performs the page enumeration at render time.

```php
class MenuResolver
{
    /** @return array<string, array{location: string, name: string, items: array, wp_id: int|null}> */
    public function all(): array;

    public function resolve( string $location ): ?array;
    public function revert( string $location ): bool; // unassigns, preserves Menu
}
```

### Item link types (kickoff decision)

H4 ships all three link types per plan 14 §8 H4 row:

- **`link`** — direct URL link (`core/navigation-link`).
- **`submenu`** — container for nested items (`core/navigation-submenu`).
- **`page-list`** — dynamic page enumeration at render time (`core/page-list`).

The `(object_type, object_id)` pair is a soft polymorphic reference; items survive deletion of the linked resource and continue rendering with the stored `label` + `url`. Pruning dead links is deferred to V1.1.

### Cascade behavior

Deleting a `Menu` cascades to its `MenuItem`s and `MenuLocationAssignment`s; deleting a `MenuItem` cascades to its children. Cascade is implemented at the Eloquent layer (in each model's `booted()` method) on top of the migration's `cascadeOnDelete()` foreign-key declarations, so behavior is consistent regardless of whether the database driver enforces FK constraints (notably SQLite needs `PRAGMA foreign_keys`).

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

`PatternResolver` (H2), `GlobalStylesResolver` (H3), and `MenuResolver` (H4) do not implement `EntityResolver` — patterns surface `cloneToUser()` instead of slug-merge `revert()`, global styles are a singleton-per-theme without a slug keyspace, and menus are keyed by location (not slug) and have no theme-file fallback.

## Permissions

The slugs `visual_editor.templates.edit`, `visual_editor.template-parts.edit`, `visual_editor.patterns.edit`, `visual_editor.global-styles.edit`, and `visual_editor.navigation.edit` are seeded by the parent `CMSFrameworkServiceProvider` via the G5 (#98) bridge. The SiteEditor module does not register additional permissions; routes use the `auth` middleware (V1 baseline of "any authenticated user", per plan 12 §2.6). V1.1 introduces fine-grained policies.

## Service registration

`SiteEditorServiceProvider` is registered from the parent `CMSFrameworkServiceProvider`. It:

- Binds `TemplateResolver`, `TemplatePartResolver`, `PatternResolver`, `GlobalStylesResolver`, `GlobalStylesEmitter`, and `MenuResolver` as singletons.
- Loads `routes/api.php` for the REST endpoints.
- Registers `ap.visual-editor.{templates,template-parts,patterns,global-styles,navigation}` filters under a `class_exists(VisualEditor::class)` guard so cms-framework boots cleanly without visual-editor installed. The `global-styles` filter is a singleton (`?ResolvedGlobalStyles` array shape); the others are keyed maps. cms-framework's resolved map merges *under* the existing map so app-level config / earlier filter contributors win on key collision.
- Registers the `@cmsGlobalStyles` Blade directive.
- Wires a `GlobalStyles` model observer that invalidates the emitter cache on save and delete.

Migrations live at the package level (`database/migrations/`) and are loaded by the parent `CMSFrameworkServiceProvider`.

## Coordination with visual-editor

- `#407` (H5 — site-editor resource filters): visual-editor reads the `/templates`, `/template-parts`, `/blocks`, `/block-patterns/patterns`, `/global-styles`, `/menus`, `/menu-items`, and `/menu-locations` endpoints to populate `addEntities()` at editor bootstrap. Each entity surfaces through its corresponding `ap.visual-editor.*` filter that cms-framework registers behind a `class_exists` guard.
- `#399` (G3 — editor entity adapter): visual-editor's WP-shape resource adapter maps the REST responses back into the editor's `useEntityRecord` / `useEntityRecords` selectors.
