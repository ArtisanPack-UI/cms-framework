---
title: Comments
---

# Comments

*Added in 2.1.0.*

The Blog module ships a Comments submodule that adds threaded comments to posts. Comments can be left by authenticated users or guests, are moderated through a `pending` / `approved` / `spam` / `trash` workflow, and surface through a small REST surface that pairs with the `artisanpack/comment-*` blocks in `artisanpack-ui/visual-editor`.

## Data model

The `Comment` model lives at `ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `post_id` | FK → `posts.id` | The commented-on post |
| `parent_id` | FK → `post_comments.id`, nullable | Threaded reply parent |
| `user_id` | FK → `users.id`, nullable | Set when an authenticated user commented |
| `author_name` | string, nullable | Guest commenter name |
| `author_email` | string, nullable | Guest commenter email |
| `author_url` | string, nullable | Guest commenter URL |
| `content` | text | Comment body |
| `status` | string (enum) | `pending`, `approved`, `spam`, `trash` |
| `approved_at` | timestamp, nullable | Stamped when status moves to `approved` |
| `created_at` / `updated_at` / `deleted_at` | timestamps | `deleted_at` enables soft deletes |

The table is created by `2026_06_02_000001_create_post_comments_table.php` with indexes on `post_id`, `parent_id`, `status`, and `approved_at` for the most common read paths.

### Post relations

The `Post` model exposes:

- `comments()` — every comment (including pending / spam / trash)
- `approvedComments()` — the filtered set used by public reads
- `comments_count` accessor — count of approved comments for the post

## REST endpoints

All routes are mounted under `/api/v1/comments`.

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET    | `/comments` | public | List approved comments (paginated; filterable by `post_id`, `parent_id`) |
| GET    | `/comments/{comment}` | public | Show a single approved comment |
| POST   | `/comments` | public (throttled) | Create a comment — defaults to `pending` |
| PUT    | `/comments/{comment}` | auth | Update a comment |
| PATCH  | `/comments/{comment}` | auth | Partial update (e.g. moderation status) |
| DELETE | `/comments/{comment}` | auth | Soft delete |

### Public submission

`POST /comments` is reachable without an auth token so guest visitors can submit comments. The default `CommentPolicy::create()` returns `true` for guests via the `comments.create.public` hooks filter — filter it to `false` if you want to lock comments down to authenticated users.

Guest payloads must include `author_name`, `author_email`, and `content`. Authenticated payloads only need `content`; the controller resolves `user_id` / author fields from the session.

New comments are always created with `status = pending` so a moderator can approve them. Approval flips `status` to `approved` and stamps `approved_at`.

### Rate limiting

The public `POST /comments` route is throttled by the `throttle:comments` named limiter, registered in `BlogServiceProvider::registerCommentsRateLimiter()`:

- Guests: **10 requests/minute**, keyed by IP
- Authenticated users: **60 requests/minute**, keyed by user id

Both buckets are overridable via filters:

```php
addFilter( 'comments.rate-limit.guest', function ( int $perMinute ): int {
    return 5;
} );

addFilter( 'comments.rate-limit.authenticated', function ( int $perMinute ): int {
    return 120;
} );
```

## Policy + abilities

`CommentPolicy` covers the standard `viewAny` / `view` / `create` / `update` / `delete` abilities. Each method runs through a hooks filter (`comments.{ability}`) so applications can tighten or loosen the defaults without subclassing the policy.

The most common override is the public-create gate:

```php
// Disable public commenting — require authentication.
addFilter( 'comments.create.public', fn (): bool => false );
```

## Visual Editor integration

The `CommentResource` payload mirrors the shape `CommentResolver` in `artisanpack-ui/visual-editor` reads when stamping `_resolvedAuthor`, `_resolvedAvatar`, `_resolvedDate`, etc. on `artisanpack/comment-*` blocks. No extra wiring is required — once the visual-editor package is installed, comment blocks resolve against the same payload the REST endpoints return.

## Factory states

`CommentFactory` provides these states for testing:

- `pending()` / `approved()` / `spam()` / `trash()` — set the moderation status
- `guest()` — null `user_id`, populate guest author fields
- `forPost( $post )` — bind the comment to a specific post
- `replyTo( $comment )` — set `parent_id` to thread the reply
