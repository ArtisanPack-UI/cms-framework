---
title: Site Editor - API Reference
---

# Site Editor API Reference

All site-editor REST endpoints live under `/api/v1/` and require authentication via Laravel Sanctum (`auth:sanctum` middleware).

> **Added in 2.0.0.**

## Templates

| Method | Path | Purpose |
|---|---|---|
| GET    | `/api/v1/templates` | List resolved templates (file + DB merged; DB wins per slug) |
| GET    | `/api/v1/templates/{slug}` | Show single resolved template |
| POST   | `/api/v1/templates` | Create DB-stored template (custom or override) |
| PUT    | `/api/v1/templates/{slug}` | Upsert DB-stored template |
| DELETE | `/api/v1/templates/{slug}` | Revert (deletes DB row; theme file becomes authoritative) |

Response shape mirrors WP `/wp/v2/templates` — see [[site-editor/Templates]] for the full shape.

## Template parts

| Method | Path | Purpose |
|---|---|---|
| GET    | `/api/v1/template-parts` | List resolved template parts |
| GET    | `/api/v1/template-parts/{slug}` | Show single resolved template part |
| POST   | `/api/v1/template-parts` | Create DB-stored template part |
| PUT    | `/api/v1/template-parts/{slug}` | Upsert DB-stored template part |
| DELETE | `/api/v1/template-parts/{slug}` | Revert |

Response shape adds an `area` field and uses `type: "wp_template_part"`. Areas are restricted to `header`, `footer`, `sidebar`, `uncategorized`, and `navigation-overlay`.

## Patterns (synced — WP `wp_block`)

| Method | Path | Purpose |
|---|---|---|
| GET    | `/api/v1/blocks` | List user-source synced patterns |
| GET    | `/api/v1/blocks/{slug}` | Show single synced pattern |
| POST   | `/api/v1/blocks` | Create a synced user pattern |
| PUT    | `/api/v1/blocks/{slug}` | Upsert a synced user pattern |
| DELETE | `/api/v1/blocks/{slug}` | Delete a synced user pattern |

## Patterns (unsynced — WP `wp_block_pattern`)

| Method | Path | Purpose |
|---|---|---|
| GET    | `/api/v1/block-patterns/patterns` | List theme + user-source unsynced patterns merged |
| GET    | `/api/v1/block-patterns/patterns/{slug}` | Show single unsynced pattern (theme or user) |
| POST   | `/api/v1/block-patterns/patterns` | Create an unsynced user pattern |
| PUT    | `/api/v1/block-patterns/patterns/{slug}` | Upsert. **403** when targeting a theme slug. |
| DELETE | `/api/v1/block-patterns/patterns/{slug}` | Delete. **403** when targeting a theme slug. |

## Global styles

Singleton-per-theme — no `{slug}` segment.

| Method | Path | Purpose |
|---|---|---|
| GET    | `/api/v1/global-styles` | Resolved styles for the active theme |
| PUT    | `/api/v1/global-styles` | Create or update the user-customization row |
| DELETE | `/api/v1/global-styles` | Revert to file-only authority |
| GET    | `/api/v1/global-styles/variations` | List variations from `themes/{active}/styles/*.json` |
| GET    | `/api/v1/global-styles/css` | Emit the resolved CSS as `text/css` |

## Menus

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/menus` | List menus for the active theme |
| POST | `/api/v1/menus` | Create a menu |
| GET | `/api/v1/menus/{id_or_slug}` | Show one menu |
| PUT | `/api/v1/menus/{id_or_slug}` | Update menu metadata (slug renames not supported) |
| DELETE | `/api/v1/menus/{id_or_slug}` | Delete the menu (cascades to items and assignments) |

## Menu items

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/menu-items?menus={id}` | List items for a menu, ordered by `(parent_id, position)` |
| POST | `/api/v1/menu-items` | Create item under a menu (`menus` field required) |
| GET | `/api/v1/menu-items/{id}` | Show one item |
| PUT | `/api/v1/menu-items/{id}` | Update item (the `menus` field is prohibited) |
| DELETE | `/api/v1/menu-items/{id}` | Delete item (cascades to children) |

## Menu locations

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/menu-locations` | List declared locations + their assignments (`menu` is `0` when unassigned) |
| PUT | `/api/v1/menu-locations/{location}` | Assign a menu to a location (body: `{ "menu": <id> }`) |
| DELETE | `/api/v1/menu-locations/{location}` | Unassign |

## Authentication

All site-editor endpoints use the `auth:sanctum` middleware. Pass a Sanctum bearer token in the `Authorization: Bearer {token}` header. Sanctum SPA cookies also work — both are accepted on every endpoint.

## Error responses

Site-editor endpoints follow the framework's standard JSON error envelope. See [[api/Error Responses]] for the response shape.

Notable status codes:

- **403 Forbidden** — Attempting to PUT/DELETE a theme-sourced pattern.
- **409 Conflict** — POST to `/templates` or `/template-parts` when a `(theme, slug)` row already exists.
- **422 Unprocessable Entity** — Form validation failures (invalid area, slug mismatch on PUT, invalid menu item type, etc.).
