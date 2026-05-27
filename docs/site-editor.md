---
title: Site Editor
---

# Site Editor Module

The Site Editor module hosts the back-ends behind cms-framework's WordPress-style site-editor surface: templates, template parts, patterns, global styles, and menus. The module is consumed by the optional `artisanpack-ui/visual-editor` package via the `ap.visual-editor.*` filter family, but it also stands on its own as a REST surface that mirrors WordPress's `/wp/v2/*` shapes.

> **Added in 2.0.0.**

## Site Editor Guides

- [[site-editor/Getting Started]] — Module overview and how the file + DB authority chain works
- [[site-editor/Templates]] — Templates and template parts (H1)
- [[site-editor/Patterns]] — Synced and unsynced block patterns (H2)
- [[site-editor/Global Styles]] — `theme.json`-driven styles, variations, and CSS emission (H3)
- [[site-editor/Menus]] — Menus, menu items, and theme-declared locations (H4)
- [[site-editor/Visual Editor Integration]] — Optional `artisanpack-ui/visual-editor` bridge, `HasBlockContent` adoption, and the `block_content` column
- [[site-editor/API Reference]] — Full REST endpoint listing
- [[site-editor/Hooks and Events]] — `ap.visual-editor.*` filters, resolvers, and the `@cmsGlobalStyles` Blade directive

## Overview

Site-editor entities resolve through a hybrid file + database model: themes ship default content as files (e.g. `themes/{active}/templates/{slug}.html`), and the database stores user overrides. When both exist, the DB row wins. Reverting a DB override deletes the row and returns the entity to its theme-file source.

This mirrors WordPress's site-editor authority chain. Visual-editor consumes these endpoints to populate `addEntities()` configuration at editor bootstrap.

## Module placement

`src/Modules/SiteEditor/` is a sibling to `src/Modules/Themes/`. Conceptually it acts as a sub-module of the theme system — site-editor entities are theme-driven with DB overrides — but it earns its own module because:

- It owns its own models, migrations, and REST endpoints.
- The Themes module stays focused on theme discovery, activation, view-path registration, and `theme.json` validation.

## Authority chain (at a glance)

| Entity | Theme file source | DB source | Conflict winner |
|---|---|---|---|
| Templates | `themes/{active}/templates/{slug}.html` | `templates` table | DB row |
| Template parts | `themes/{active}/parts/{slug}.html` | `template_parts` table | DB row |
| Patterns | `themes/{active}/patterns/{slug}.php` | `block_patterns` table (`user/` prefix) | Disjoint keyspaces |
| Global styles | `themes/{active}/theme.json` + `styles/*.json` variations | `global_styles` table (singleton per theme) | Deep merge (DB wins on overlap) |
| Menus | — | `menus` + `menu_items` + `menu_location_assignments` | DB-only (themes contribute *location names* only) |

## Service registration

`SiteEditorServiceProvider` is registered from the parent `CMSFrameworkServiceProvider`. It:

- Binds `TemplateResolver`, `TemplatePartResolver`, `PatternResolver`, `GlobalStylesResolver`, `GlobalStylesEmitter`, and `MenuResolver` as singletons.
- Loads `routes/api.php` for the REST endpoints.
- Registers `ap.visual-editor.{templates,template-parts,patterns,global-styles,navigation}` filters under a `class_exists(VisualEditor::class)` guard so cms-framework boots cleanly without visual-editor installed.
- Registers the `@cmsGlobalStyles` Blade directive.
- Wires a `GlobalStyles` model observer that invalidates the emitter cache on save and delete.

Migrations live at the package level (`database/migrations/`) and are loaded by the parent `CMSFrameworkServiceProvider`.

## Permissions

The slugs `visual_editor.templates.edit`, `visual_editor.template-parts.edit`, `visual_editor.patterns.edit`, `visual_editor.global-styles.edit`, and `visual_editor.navigation.edit` are seeded by the parent `CMSFrameworkServiceProvider` when the `artisanpack-ui/visual-editor` package is installed. The SiteEditor module itself does not register additional permissions; routes use `auth:sanctum` so any authenticated user with a valid Sanctum token can read and write.

See the guides above for detailed module documentation.
