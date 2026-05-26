---
title: Site Editor - Menus
---

# Menus

The Menus module introduces navigation menus and the locations API. Unlike templates / parts / patterns / global styles, menus are **DB-only** — themes contribute *location names* via `theme.json` `menus.locations`, but never menu *content*. Switching themes leaves prior menus untouched.

> **Added in 2.0.0** (H4).

## Storage

DB tables:

- `menus` — `id`, `theme`, `slug`, `name`, `description`, `auto_add_pages`, `author_id`, timestamps. Unique constraint on `(theme, slug)`.
- `menu_items` — `id`, `menu_id` (FK), `parent_id` (nullable, self-FK), `position`, `type` (`link` / `submenu` / `page-list`), `label`, `url`, `target`, `rel`, `classes`, `description`, `object_type`, `object_id`, `kind`, timestamps.
- `menu_location_assignments` — `id`, `theme`, `location`, `menu_id` (FK), timestamps. Unique constraint on `(theme, location)`.

A separate `menu_location_assignments` table (rather than a `location` column on `menus`) lets one menu satisfy multiple locations and keeps location keys theme-scoped — switching themes leaves a menu unassigned without orphaning the menu itself.

## Locations resolution

Locations resolve through `ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\Menus`:

```php
Menus::locations();                    // array<string, string>
Menus::assign( 'primary', $menuId );   // upsert assignment
Menus::unassign( 'primary' );          // delete assignment
Menus::assigned( 'primary' );          // ?Menu
```

Order:

1. App default — `config('cms.menus.locations')`, e.g. `['primary' => 'Primary', 'footer' => 'Footer']`.
2. Theme override — the active theme's `theme.json` `menus.locations` keys override the app defaults by key (theme wins); a warning is logged so app authors aren't confused.

Themes that need a location no app has registered can declare it in `theme.json` and the location appears with the theme-provided label.

## REST endpoints

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

## Response shapes

### Menu

Mirrors WP `/wp/v2/menus`:

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

### Menu item

Mirrors WP `/wp/v2/menu-items`. Note that `type` carries the WP-side vocabulary (`custom` / `post_type` / `taxonomy` derived from the model `kind`), while `link_type` carries the cms-side flag (`link` / `submenu` / `page-list`) for visual-editor's adapter:

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

## Resolver

`MenuResolver::all()` powers the `ap.visual-editor.navigation` filter. It returns `array<string, ResolvedMenu>` keyed by location key. Locations the active theme declares but no menu is assigned to still appear with `wp_id => null` and an empty `items` array, so editor surfaces can render empty slots.

The `items` field projects `MenuItem` rows into the upstream `core/navigation-link` / `core/navigation-submenu` / `core/page-list` shapes — children nest under parents by `(parent_id, position)`. Page-list items render as a dynamic placeholder (`'dynamic' => 'page-list'`) — the resolver does not enumerate pages here so cms-framework stays decoupled from content types. The navigation block performs the page enumeration at render time.

```php
class MenuResolver
{
    /** @return array<string, array{location: string, name: string, items: array, wp_id: int|null}> */
    public function all(): array;

    public function resolve( string $location ): ?array;
    public function revert( string $location ): bool; // unassigns, preserves Menu
}
```

## Item link types

The Menus module ships all three link types:

- **`link`** — direct URL link (`core/navigation-link`).
- **`submenu`** — container for nested items (`core/navigation-submenu`).
- **`page-list`** — dynamic page enumeration at render time (`core/page-list`).

The `(object_type, object_id)` pair is a soft polymorphic reference; items survive deletion of the linked resource and continue rendering with the stored `label` + `url`.

## Cascade behavior

Deleting a `Menu` cascades to its `MenuItem`s and `MenuLocationAssignment`s; deleting a `MenuItem` cascades to its children. Cascade is implemented at the Eloquent layer (in each model's `booted()` method) on top of the migration's `cascadeOnDelete()` foreign-key declarations, so behavior is consistent regardless of whether the database driver enforces FK constraints (notably SQLite needs `PRAGMA foreign_keys`).
