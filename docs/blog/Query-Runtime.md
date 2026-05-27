---
title: Blog - Query Runtime
---

# Query Runtime

`QueryRuntime` resolves the `core/query` block attribute payload to a paginated Eloquent result. It is the runtime that backs visual-editor's `core/query` loop block: given a normalized attribute set, it picks the right model, applies filters / taxonomies / ordering, and returns a `LengthAwarePaginator`.

> **Added in 2.0.0** (G4c-1).

## When to use it

You typically don't call `QueryRuntime` directly — visual-editor's renderer packages (`@artisanpack-ui/visual-editor-react`, `@artisanpack-ui/visual-editor-vue`, and the Blade renderer) call it through a thin REST endpoint shipped by visual-editor.

You'd reach for it directly when:

- Hosting the editor canvas in-process (e.g. server-side preview rendering) and you want to skip the REST round-trip.
- Writing custom block types whose data model matches `core/query` semantics.
- Writing tests against the resolver directly.

## Basic usage

```php
use ArtisanPackUI\CMSFramework\Modules\Blog\Services\QueryRuntime;

$runtime = app(QueryRuntime::class);

$result = $runtime->resolve([
    'postType' => 'post',
    'perPage'  => 6,
    'orderBy'  => 'date',
    'order'    => 'desc',
    'taxQuery' => [
        'category' => [12],
    ],
]);

// $result is a LengthAwarePaginator of Post models
foreach ($result as $post) {
    echo $post->title;
}
```

## Routing by `postType`

`postType` selects the underlying model and manager:

| `postType` value | Backend |
|---|---|
| `post` | `BlogManager::getArchiveQuery()` |
| `page` | `PageManager::getPageQuery()` |
| Any registered custom type slug | `ContentTypeManager` lookup → the type's `model_class` |

For custom types, the slug must be a known content type registered via the Content Types module. Unknown slugs throw `InvalidArgumentException`.

## Supported attributes

`QueryRuntime` recognizes the standard `core/query` V1 attribute keys:

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `postType` | string | `post` | Routes to the model/manager |
| `perPage` | int | `10` | Hard-capped — see "Safety limits" |
| `page` | int | `1` | 1-indexed pagination |
| `orderBy` | string | `date` | `date`, `title`, `menu_order`, `random` |
| `order` | string | `desc` | `asc` or `desc` |
| `parents` | array of int | `[]` | Pages only — limit to children of these parents |
| `taxQuery` | array | `[]` | Map of taxonomy slug → term IDs |
| `sticky` | string | — | `exclude` to skip sticky posts; `only` to filter to them |
| `search` | string | — | Free-text title/content search |

Unknown attributes are ignored — visual-editor passes through some block-side metadata (`namespace`, `query.author`, etc.) that the runtime safely discards.

## Taxonomy filtering

`taxQuery` is a `taxonomy => [term_ids]` map. Multiple taxonomies AND together; multiple terms within a taxonomy OR together:

```php
$runtime->resolve([
    'postType' => 'post',
    'taxQuery' => [
        'category' => [5, 12],      // category 5 OR 12
        'tag'      => [42],         // AND tag 42
    ],
]);
```

## Ordering

| `orderBy` | Resolves to |
|---|---|
| `date` | `published_at DESC` (or whatever the `order` attribute selects) |
| `title` | `title` |
| `menu_order` | `menu_order` (pages only — falls back to `id` for posts) |
| `random` | `RANDOM()` — note this disables pagination caching |

## Safety limits

`QueryRuntime` enforces a hard cap on `perPage` to prevent a malicious or accidental `?perPage=999999` from dragging the renderer down. The cap is `100` by default. Requests exceeding the cap are clamped silently — no error is raised so editor previews degrade gracefully.

## Return type

`resolve()` returns `Illuminate\Contracts\Pagination\LengthAwarePaginator`. The pagination metadata (`currentPage()`, `lastPage()`, `total()`) is honored by visual-editor's pagination block.

## Decoupling from visual-editor

`QueryRuntime` lives in the Blog module rather than the SiteEditor module because it depends on `BlogManager`, `PageManager`, and `ContentTypeManager`. It does **not** depend on `artisanpack-ui/visual-editor` — the runtime is callable from any context that builds a `core/query` attribute payload (server-side rendering, custom REST endpoints, console commands).
