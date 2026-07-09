---
title: AI Features
---

# AI Features

*Added in 2.3.0.*

The CMS Framework ships five AI-powered content-authoring agents built on top of the [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai) foundation. Each agent maps to a feature key, validates its own input and output, and is auto-discovered by the AI package's `FeatureRegistry` at boot — hosts get the surfaces for free once the AI package is installed.

## Feature keys

| Feature key            | Agent                                                              | Purpose                                                                 |
|------------------------|--------------------------------------------------------------------|-------------------------------------------------------------------------|
| `cms.post_title`       | `ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent`    | Generate 3–5 title variants from a draft body.                          |
| `cms.excerpt`          | `ArtisanPackUI\CMSFramework\Ai\Agents\ExcerptGenerationAgent`      | Generate a natural excerpt (default ≤200 chars) from full post content. |
| `cms.suggest_tags`     | `ArtisanPackUI\CMSFramework\Ai\Agents\TagSuggestionAgent`          | Pick tags from an existing taxonomy (optionally propose new ones).      |
| `cms.suggest_category` | `ArtisanPackUI\CMSFramework\Ai\Agents\CategorySuggestionAgent`     | Pick one category (slash-delimited path) from a hierarchical tree.      |
| `cms.suggest_slug`     | `ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent`         | Produce an SEO-friendly kebab-case slug from a title.                   |

Every agent honors the toggle in `artisanpack.ai.features.<key>.enabled`. When the toggle is off, the agent throws `FeatureDisabledException` before any model call.

The canonical list is exposed as `CMSFrameworkServiceProvider::AI_FEATURE_KEYS` — the Livewire component, the REST controller, and any host bundle wiring read from there so a future 6th feature only lands in one place.

---

## Installation

The AI foundation is a soft dependency, declared in `composer.json` under `suggest` and `require-dev`. To unlock the AI features in a host application:

```bash
composer require artisanpack-ui/ai:^1.0
```

Without it, `aiFeatures()` returns and the AI Livewire component + REST routes stay unregistered — the framework still boots normally.

Configure credentials on the AI package side. See the [AI package's Configuration guide](https://github.com/ArtisanPack-UI/ai/blob/main/docs/configuration.md) for details.

---

## Trigger surfaces

The same five agents are reachable through two independent transports, so the AI features work uniformly across Livewire, React, and Vue front-ends.

### Livewire component

`ArtisanPackUI\CMSFramework\Livewire\Ai\AiTools`, registered as `ap-cms-ai-tools`:

```blade
<livewire:ap-cms-ai-tools />
```

The component holds no state — it's a transport. Front-end code dispatches browser events; the component runs the agent and dispatches a status event back:

```javascript
Livewire.dispatch('ap-cms-ai:suggest-post-titles', {
    content: draft.body,
    tone: 'authoritative',
    count: 5,
});

Livewire.on('ap-cms-ai:cms.post_title:success', ({ output }) => {
    // output.titles → [{ title, rationale }, ...]
});
```

Status suffixes: `success`, `disabled`, `missing-credentials`, `invalid-input`, `error`.

### REST endpoints

Mounted at `/api/v1/cms/ai/*` and protected by `auth:sanctum` so React and Vue SPAs with bearer tokens can hit them directly:

| Method | Path                                | Body                                                                 |
|--------|-------------------------------------|----------------------------------------------------------------------|
| `GET`  | `/api/v1/cms/ai/features`           | *(none)* — returns `{ features: { [key]: boolean } }`                |
| `POST` | `/api/v1/cms/ai/post-title`         | `{ content, tone?, count? }`                                         |
| `POST` | `/api/v1/cms/ai/excerpt`            | `{ content, max_chars? }`                                            |
| `POST` | `/api/v1/cms/ai/suggest-tags`       | `{ content, available_tags, allow_new?, max_selected? }`             |
| `POST` | `/api/v1/cms/ai/suggest-category`   | `{ content, category_tree }`                                         |
| `POST` | `/api/v1/cms/ai/suggest-slug`       | `{ title, excerpt?, max_chars? }`                                    |

Success responses: `200 { feature, output }`. Error envelopes:

| Status | `error`                | Meaning                                            |
|--------|------------------------|----------------------------------------------------|
| `403`  | `feature_disabled`     | Feature toggle is off.                             |
| `422`  | `invalid_input`        | Input failed the agent's validation.               |
| `503`  | `missing_credentials`  | No credentials resolved for the feature/provider.  |
| `500`  | `internal_error`       | Unexpected exception — details in `Log::error`.    |

Because the transport is plain HTTP, `@artisanpack-ui/react` and `@artisanpack-ui/vue` do NOT need to know anything about CMS AI features — hosts add a new capability entirely on the backend.

---

## Agent contracts

Each agent validates input and shapes output; front-ends and controllers can trust the returned envelope.

### `cms.post_title` — `PostTitleSuggestionAgent`

**Input**

| Field     | Type          | Required | Notes                                       |
|-----------|---------------|----------|---------------------------------------------|
| `content` | `string`      | yes      | Draft body.                                 |
| `tone`    | `string\|null`| no       | Optional tone hint (e.g. `authoritative`).  |
| `count`   | `int\|null`   | no       | 3–5 (default 5).                            |

**Output**

```php
[
    'titles' => [
        [ 'title' => string, 'rationale' => string ],
        // ...
    ],
]
```

Titles longer than 80 characters are clamped; entries with an empty title or rationale are dropped.

### `cms.excerpt` — `ExcerptGenerationAgent`

**Input**

| Field       | Type         | Required | Notes                              |
|-------------|--------------|----------|------------------------------------|
| `content`   | `string`     | yes      | Full post body.                    |
| `max_chars` | `int\|null`  | no       | 80–400 (default 200).              |

**Output**

```php
[
    'excerpt'    => string,
    'char_count' => int, // recomputed from returned excerpt, not model-reported
]
```

### `cms.suggest_tags` — `TagSuggestionAgent`

**Input**

| Field            | Type         | Required | Notes                                                                    |
|------------------|--------------|----------|--------------------------------------------------------------------------|
| `content`        | `string`     | yes      | Content to tag.                                                          |
| `available_tags` | `string[]`   | yes      | Existing taxonomy tag names.                                             |
| `allow_new`      | `bool\|null` | no       | Default `false`. When `true`, the agent may return `suggested_new`.      |
| `max_selected`   | `int\|null`  | no       | 1–10 (default 5).                                                        |

**Output**

```php
[
    'selected' => [
        [ 'tag' => string, 'confidence' => float ], // 0.0-1.0
        // ...
    ],
    // Only present when allow_new is true:
    'suggested_new' => string[],
]
```

Tags returned by the model that are not in `available_tags` are silently dropped. Confidence values are clamped to `[0, 1]`. Whitespace on model-returned tags is trimmed before the allow-list lookup.

### `cms.suggest_category` — `CategorySuggestionAgent`

**Input**

| Field           | Type    | Required | Notes                                              |
|-----------------|---------|----------|----------------------------------------------------|
| `content`       | `string`| yes      | Content to categorize.                             |
| `category_tree` | `array` | yes      | Nested list of `{name, children?}` nodes.          |

**Output**

```php
[
    'selected'   => string, // slash-delimited path or '' if no fit
    'confidence' => float,  // 0.0-1.0, forced to 0 when selected is ''
    'rationale'  => string,
]
```

Nodes whose `name` contains a `/` are rejected during path collection — they would collide with real parent/child paths and make the model pick ambiguous.

### `cms.suggest_slug` — `SlugSuggestionAgent`

**Input**

| Field       | Type            | Required | Notes                                          |
|-------------|-----------------|----------|------------------------------------------------|
| `title`     | `string`        | yes      | Post title.                                    |
| `excerpt`   | `string\|null`  | no       | Optional excerpt for disambiguation.           |
| `max_chars` | `int\|null`     | no       | 20–100 (default 60).                           |

**Output**

```php
[
    'slug'       => string, // sanitized kebab-case, <= max_chars
    'alternates' => string[], // 0-2 backup slugs for uniqueness collisions
]
```

Slug sanitization delegates to `Illuminate\Support\Str::slug()` — non-ASCII characters are transliterated (`café` → `cafe`), and the length cap is applied on a hyphen boundary so words aren't truncated mid-token.

Uniqueness against your existing slugs is intentionally NOT part of the agent's contract — callers enforce uniqueness (Eloquent lookup, DB constraint, ...) using the returned slug.

---

## Extending

### Override an agent

Bind a subclass in the container to replace an agent globally:

```php
use ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent;
use App\Ai\Agents\OpusSlugSuggestionAgent;

$this->app->bind(SlugSuggestionAgent::class, OpusSlugSuggestionAgent::class);
```

`SlugSuggestionAgent::for(…)` resolves through the container, so the subclass takes over on every consumer without touching the trigger surfaces.

### Register additional CMS AI features

Add a `aiFeatures()` method to your own service provider — the AI foundation walks every loaded provider and merges the results:

```php
public function aiFeatures(): array
{
    return [
        'myapp.suggest_seo_meta' => [
            'agent'   => \App\Ai\Agents\SeoMetaAgent::class,
            'package' => 'myapp/seo',
        ],
    ];
}
```

Then hook it into `AiTools` / `AiController` (or your own controller) the same way the five built-ins do.

---

## Related

- [`artisanpack-ui/ai` documentation](https://github.com/ArtisanPack-UI/ai/tree/main/docs)
- [`artisanpack-ui/visual-editor` AI features](https://github.com/ArtisanPack-UI/visual-editor/blob/release/1.x/README.md) — cross-cutting `ai.alt_text`, `ai.content_rewrite`, and the visual-editor-owned `visual_editor.*` agents.
