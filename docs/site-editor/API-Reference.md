---
title: Site Editor - API Reference
---

# Site Editor API Reference

All site-editor REST endpoints live under `/api/v1/`, require authentication via Laravel Sanctum (`auth:sanctum` middleware) and, since 2.15.0, the Site Editor abilities described under [Authorization](#authorization).

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

## Pattern fields

Since 2.15.0 every pattern response (`/block-patterns/patterns`, theme and user, and `/blocks`) carries the WordPress pattern-header fields:

| Field | Type | Meaning |
|---|---|---|
| `keywords` | `string[]` | Inserter search terms (`Keywords:` header). |
| `inserter` | `bool` | `false` hides the pattern from the inserter (`Inserter:` header). Defaults to `true`. |
| `template_types` | `string[]` | Template types the pattern suits (`Template Types:` header). |
| `post_types` | `string[] \| null` | Post types the pattern is offered for (`Post Types:` header); `null` means every post type. |
| `viewport_width` | `int \| null` | Preview layout width (`Viewport Width:` header); `null` means the client default. |

`POST` / `PUT` on `/block-patterns/patterns` and `/blocks` accept the same fields for user patterns. `post_types` entries must be lowercase post-type keys (`[a-z0-9_-]`, up to 20 characters), `viewport_width` must be 320–2560 and `inserter` must be a boolean when sent (omit it to keep the current value); anything else is a `422`. See [[site-editor/Patterns]].

## Authentication

All site-editor endpoints use the `auth:sanctum` middleware. Pass a Sanctum bearer token in the `Authorization: Bearer {token}` header. Sanctum SPA cookies also work — both are accepted on every endpoint.

## Authorization

> **Added in 2.15.0.** Before 2.15.0 any authenticated user could read and write every Site Editor endpoint.

Authenticated users also need a Site Editor ability, checked by the `AuthorizeSiteEditorAccess` middleware (and again by each Form Request). The abilities follow WordPress's REST permissions:

| Group | Endpoints | Default ability | WordPress analogue |
|---|---|---|---|
| `read` | Every endpoint, `GET` included | `manage-content` (a user with the `structure` ability also passes) | `edit_posts` |
| `structure` | `POST` / `PUT` / `DELETE` on templates, template parts, global styles, menus, menu items and menu locations | `manage-themes` | `edit_theme_options` |
| `content` | `POST` / `PUT` / `DELETE` on block patterns and synced blocks | `manage-content` | `edit_posts` |

A user without the ability gets `403`. `PermissionsTableSeeder` grants `admin` both abilities and `editor` `manage-content`, so editors can read the Site Editor and manage patterns but can't change site structure. Grant the abilities to any custom role that uses the Site Editor.

Each default is filterable, resolved per request through `SiteEditorAbilities::for()`:

```php
addFilter( 'ap.cmsFramework.abilities.siteEditor.read', fn (): string => 'view-site-editor' );
addFilter( 'ap.cmsFramework.abilities.siteEditor.structure', fn (): string => 'edit-site' );
addFilter( 'ap.cmsFramework.abilities.siteEditor.content', fn (): string => 'manage-patterns' );
```

visual-editor's own Site Editor routes (`/visual-editor/api/...`) don't use these abilities; they go through its `EnsureSiteEditorAccess` gate.

## Error responses

Site-editor endpoints follow the framework's standard JSON error envelope. See [[api/Error Responses]] for the response shape.

Notable status codes:

- **403 Forbidden** — The user lacks the Site Editor ability for the request (since 2.15.0; see [Authorization](#authorization)), or the request PUTs/DELETEs a theme-sourced pattern.
- **409 Conflict** — POST to `/templates` or `/template-parts` when a `(theme, slug)` row already exists.
- **422 Unprocessable Entity** — Form validation failures (invalid area, slug mismatch on PUT, invalid menu item type, etc.).
