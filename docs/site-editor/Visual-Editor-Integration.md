---
title: Site Editor - Visual Editor Integration
---

# Visual Editor Integration

cms-framework's site-editor surface is consumed by the optional `artisanpack-ui/visual-editor` package, but is **not** dependent on it. visual-editor remains an opt-in integration rather than a hard composer requirement — apps that don't install it still get the REST endpoints and the resolvers, just without the editor UI on top.

> **Added in 2.0.0.**

## The compatibility shim

cms-framework's `Post` and `Page` models (and `BlockPattern`, `Template`, `TemplatePart`) `use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;`. When `artisanpack-ui/visual-editor` is installed, that trait is autoloaded from the visual-editor package and provides the full editor surface (search extractor, query scope, render pipeline, etc.).

When visual-editor is **not** installed, cms-framework's `src/Compatibility/visual-editor.php` stub registers a minimal version of the trait. The stub:

- Casts the `block_content` column to `array` so model round-trips work.
- Exposes `getBlockContentColumn()` for the default column name (`block_content`).

The stub is loaded via Composer's `autoload.files`. It checks `trait_exists(__NAMESPACE__.'\\HasBlockContent', true)` before declaring — when visual-editor is installed, the real trait is autoloaded via PSR-4 and the stub is skipped.

This means:

- Models that adopt `HasBlockContent` load cleanly with or without visual-editor.
- Apps that need the editor surface install `artisanpack-ui/visual-editor` and get the full trait.
- Apps that only need the REST API and resolvers don't need visual-editor at all.

## The `block_content` column

cms-framework's `2026_04_28_000001_add_block_content_to_posts_table.php` and `_to_pages_table.php` migrations add a JSON `block_content` column to `posts` and `pages`, alongside the existing `content` longText column. The two columns are independent:

- `content` carries the legacy HTML body (rendered output, sometimes hand-edited).
- `block_content` carries the parsed block tree (array of block descriptors) for visual-editor-authored content.

`block_content` is **not** in the `$fillable` array on `Post` or `Page` — block trees flow through the visual-editor pipeline, not mass assignment. Direct calls to `$post->block_content = [...]; $post->save();` still work.

## Filter registration

When visual-editor is installed, `SiteEditorServiceProvider` registers cms-framework's resolvers against the `ap.visual-editor.*` filter family:

| Filter | What it provides |
|---|---|
| `ap.visual-editor.templates` | Map of resolved templates from `TemplateResolver` |
| `ap.visual-editor.template-parts` | Map of resolved template parts from `TemplatePartResolver` |
| `ap.visual-editor.patterns` | Map of resolved patterns (theme + user) from `PatternResolver` |
| `ap.visual-editor.global-styles` | Singleton resolved global styles from `GlobalStylesResolver` |
| `ap.visual-editor.navigation` | Map of resolved menus by location from `MenuResolver` |
| `ap.visual-editor.resources` | Auto-registers content types that adopt `HasBlockContent` |

All registrations are gated behind `class_exists(VisualEditor::class)` so cms-framework boots cleanly when visual-editor isn't installed.

## Permissions

When visual-editor is installed, cms-framework seeds the following permission slugs via the parent `CMSFrameworkServiceProvider`:

- `visual_editor.templates.edit`
- `visual_editor.template-parts.edit`
- `visual_editor.patterns.edit`
- `visual_editor.global-styles.edit`
- `visual_editor.navigation.edit`

Routes themselves use `auth:sanctum` for the 2.0.0 baseline; future releases will introduce policy-based gating on these slugs.

## Content type auto-registration

Any Eloquent model that adopts `HasBlockContent` is automatically registered into the `ap.visual-editor.resources` filter via cms-framework's bridge code. `Post` and `Page` are registered explicitly; custom content types that adopt the trait are picked up at boot.

## Schema guards

All `ap.visual-editor.*` filter callbacks are schema-guarded — they skip resolution and return the existing filter value early when the underlying tables are missing. This lets the package boot cleanly in environments where the migrations haven't run yet (CI bootstraps, fresh installs).
