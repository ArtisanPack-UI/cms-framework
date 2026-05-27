---
title: Blog
---

# Blog Module

The Blog module provides a full-featured blog with posts, categories, tags, and author relationships. Posts support drafts, scheduling, publishing, and the editor-friendly `block_content` column added in 2.0.0.

## Blog Guides

- [[blog/Query Runtime]] — The `QueryRuntime` service that resolves visual-editor `core/query` block loops *(2.0.0)*

## Overview

Posts are managed through the standard REST endpoints under `/api/v1/`:

| Method | Path | Purpose |
|---|---|---|
| GET    | `/posts` | List posts (paginated) |
| POST   | `/posts` | Create a post |
| GET    | `/posts/{id}` | Show one post |
| PUT    | `/posts/{id}` | Update a post |
| DELETE | `/posts/{id}` | Delete a post |
| POST   | `/posts/bulk` | Bulk actions (publish, unpublish, trash, restore, delete) |

Categories and tags are taxonomies registered automatically by the Blog module. See [[developer/taxonomies]] for working with taxonomies.

## Post model

The `Post` model lives at `ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post`. Key columns:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `title` | string | Post title |
| `slug` | string | Unique slug |
| `content` | longText | Legacy HTML body |
| `block_content` | JSON, nullable | Visual-editor block tree *(added 2.0.0)* |
| `status` | string (enum) | `draft`, `published`, `scheduled`, `trash` |
| `published_at` | timestamp | Publish date (null until published) |
| `author_id` | FK | The authoring user |
| `featured_image_id` | FK | Optional featured-image media item |
| `excerpt` | text, nullable | Short summary |

The `block_content` column is read by visual-editor when the editor is wired in. cms-framework loads the column as an array via the `HasBlockContent` trait (or its compatibility shim — see [[site-editor/Visual Editor Integration]]).

## Bulk actions

`POST /api/v1/posts/bulk` accepts a `{ ids: [...], action: "..." }` payload. Supported actions:

- `publish` — set status to `published` and stamp `published_at`
- `unpublish` — revert to `draft`
- `trash` — soft delete
- `restore` — restore from trash
- `delete` — hard delete

See [[api/Bulk Actions]] for the full payload shape and response format.

## Includable relationships

Use the `include` query parameter to eager-load relationships on the index and show endpoints:

```
GET /api/v1/posts?include=author,categories,tags,featured_image
```

See [[api/Includable Relationships]] for the full list of supported includes.
