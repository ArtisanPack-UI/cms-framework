---
title: Site Editor - Patterns
---

# Block Patterns

The patterns module surfaces both theme-shipped patterns (PHP files) and user-authored patterns (DB rows) through a single read API. Unlike templates, patterns do **not** merge by slug — theme patterns and user patterns occupy disjoint keyspaces.

> **Added in 2.0.0** (H2).

## Storage

Patterns occupy two disjoint sources rather than a file/DB merge:

- **Theme patterns** — PHP files at `themes/{active}/patterns/{slug}.php` with a leading WP-style header doc-comment (`Title:`, `Slug:`, `Categories:`, `Description:`, `Block Types:`). Read-only at runtime; the body after the doc-comment becomes the pattern content.
- **User patterns** — DB rows in `block_patterns` with columns `id`, `slug`, `theme` (nullable), `title`, `description`, `source`, `synced` (bool), `categories` (JSON), `block_types` (JSON), `block_content` (JSON via `HasBlockContent`), `author_id`, timestamps.

User-pattern slugs carry a `user/` prefix at storage time — this guarantees a theme pattern named `hero` and a user pattern named `hero` do not collide in the merged inserter map. The REST surface presents the unprefixed user-facing slug; the storage form is exposed as `name` on the unsynced response (mirroring WP's namespaced pattern names).

## Pattern file format

Theme patterns are parsed by `PatternFileParser` from PHP files of the form:

```php
<?php
/**
 * Title: Call to Action
 * Slug: cta
 * Categories: featured, cta
 * Description: A simple call-to-action block.
 * Block Types: core/group
 */
?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
    <!-- wp:heading -->
    <h2>Get started today</h2>
    <!-- /wp:heading -->
</div>
<!-- /wp:group -->
```

The parser regexes are anchored — the doc-comment must lead the file. Everything after the closing `?>` (or after the closing `*/` when there is no PHP tag) becomes the pattern content. Recognized header keys: `Title:`, `Slug:`, `Categories:` (comma-separated), `Description:`, `Block Types:` (comma-separated).

## Sync semantics

`synced` distinguishes the two pattern shapes Gutenberg recognizes:

- `synced = true` — surfaced under `/blocks` (WP `wp_block` shape). Editing the pattern updates every occurrence in the editor.
- `synced = false` — surfaced under `/block-patterns/patterns` (WP `wp_block_pattern` shape). Each insertion is a snapshot; later edits to the pattern do not propagate.

Theme patterns are always `synced = false`. Cross-state conversion happens through dedicated POST flows on each endpoint, not via PUT (a PUT to one endpoint never flips a pattern's `synced` bit on the other endpoint).

## REST endpoints

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

## Resolver

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
