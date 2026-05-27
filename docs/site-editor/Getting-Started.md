---
title: Site Editor - Getting Started
---

# Getting Started with the Site Editor

The Site Editor module is enabled automatically by the parent `CMSFrameworkServiceProvider`. There is no opt-in flag — once the framework is installed and the migrations are run, the REST endpoints described in [[site-editor/API Reference]] are live and the resolvers are bound in the container.

## How the file + DB authority chain works

Most site-editor entities resolve from two sources:

1. **Theme file** — shipped inside the active theme directory (`themes/{slug}/...`). Theme files are read-only at runtime.
2. **Database** — a per-entity table that stores user customizations.

For templates and template parts, the two sources merge by slug — the DB row wins when both exist. Reverting a DB entry deletes the row and returns the entity to its theme-file source.

Patterns occupy *disjoint* keyspaces — theme patterns and user patterns are surfaced together but never collide (the storage slug carries a `user/` prefix on the user side).

Global styles deep-merge — `theme.json` defaults, then the active variation file, then the user's `global_styles` row.

Menus are DB-only — themes contribute *location names* via `theme.json` `menus.locations` but never menu content.

## Quick example — reading a resolved template

```php
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;

$resolver = app(TemplateResolver::class);

$resolved = $resolver->resolve('page');

if ($resolved !== null) {
    echo $resolved->source;   // 'db' or 'theme'
    echo $resolved->content;  // block content (raw HTML for theme-file, empty for DB)
    var_dump($resolved->isCustom);
}
```

## Quick example — calling the REST surface

```bash
# List all resolved templates for the active theme
curl -H "Authorization: Bearer {token}" \
    http://your-app.test/api/v1/templates

# Override a template by upserting a DB row
curl -X PUT \
    -H "Authorization: Bearer {token}" \
    -H "Content-Type: application/json" \
    -d '{ "title": "Page", "block_content": [{"name":"core/paragraph","attributes":{"content":"Hi"}}] }' \
    http://your-app.test/api/v1/templates/page

# Revert by deleting the DB row (theme file becomes authoritative again)
curl -X DELETE -H "Authorization: Bearer {token}" \
    http://your-app.test/api/v1/templates/page
```

## What's next

- [[site-editor/Templates]] — how the templates and template-parts modules resolve and serve content
- [[site-editor/Patterns]] — synced vs. unsynced patterns and how theme + user sources surface together
- [[site-editor/Global Styles]] — `theme.json`-driven settings/styles, variations, and CSS emission
- [[site-editor/Menus]] — building navigation menus that survive theme switches
- [[site-editor/Visual Editor Integration]] — wiring the editor in via `artisanpack-ui/visual-editor`
