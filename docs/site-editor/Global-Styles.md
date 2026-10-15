---
title: Site Editor - Global Styles
---

# Global Styles

The Global Styles entity represents a singleton-per-theme tree of `settings` and `styles` deltas layered over the active theme's `theme.json` defaults. It powers a Gutenberg-style global-styles experience: color palettes, font sizes, spacing scales, per-element styling, and named variations.

> **Added in 2.0.0** (H3).

## Storage

Global styles are a singleton-per-theme. Theme defaults live in `themes/{active}/theme.json` (`settings` and `styles` top-level keys); user customization lives in a single `global_styles` row keyed by `theme`. Switching themes leaves prior rows untouched (data preservation); switching back resumes the prior customization.

Two JSON columns (`settings` + `styles`) mirror the `theme.json` top-level split. The DB row stores deltas only — the resolver merges with theme-file defaults at read time, so theme-default changes flow through without a full re-save.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint, auto-increment | Surfaced as `id` on REST responses. |
| `theme` | string, **unique** | The active theme slug at save time. Unique index enforces singleton-per-theme. |
| `title` | string, nullable | WP-style display title. |
| `settings` | JSON, nullable | User overrides for the `theme.json` `settings` tree. |
| `styles` | JSON, nullable | User overrides for the `theme.json` `styles` tree. |
| `variation` | string, nullable | Slug of the active variation declared in `themes/{active}/styles/{slug}.json`. |
| `author_id` | FK, nullable | Last editor. |

## Resolver merge order

`GlobalStylesResolver` deep-merges three sources in priority order (lowest → highest):

1. `themes/{active}/theme.json` `settings` + `styles` — theme defaults.
2. The active variation file at `themes/{active}/styles/{variation}.json`, when the DB row pins one.
3. The DB row's `settings` + `styles` columns — user customization.

Numeric arrays (palette lists, font-size lists) replace wholesale rather than appending — matches WP semantics where a downstream layer should redeclare the full list. Associative objects deep-merge.

The DB row is created lazily on the first `update()` call. Reads with no DB row return file-only resolution (theme + variation if pinned in a future write).

## Variations

Variations are theme-only for 2.0.0. Each variation lives at `themes/{active}/styles/{slug}.json` with the same shape as `theme.json` (typically `slug`, `title`, `description`, `settings`, `styles`). Runtime app-level variation registration is planned for a future release.

### Block style variation partials

> **Changed in 2.15.0** ([#389](https://github.com/ArtisanPack-UI/cms-framework/issues/389)).

theme.json v3 also puts **block style variations** in `styles/`: partials that declare `blockTypes` and style only those blocks (`.is-style-{slug}`). Any `blockTypes` value counts, as in WordPress's `WP_Theme_JSON_Resolver::style_variation_has_scope()`. These are not theme style variations, so `variations()` and `GET /global-styles/variations` skip them and they are never applied to the whole theme. `null` doesn't count as declaring it.

If the stored `variation` slug doesn't load — it names a block style variation partial (picked before 2.15.0) or a file that's gone — the resolver applies no variation and reports `variation: null`. The DB row keeps the stored slug.

cms-framework doesn't emit block style variation CSS; visual-editor appends it after the global-styles output. Hosts without visual-editor can add their own with the [`ap.cmsFramework.globalStyles.css`](Hooks-and-Events.md#framework-filters) filter.

## CSS emission

`GlobalStylesEmitter` translates the resolved styles tree into CSS.

### Preset custom properties

- `settings.color.palette` → `--wp--preset--color--{slug}` custom properties.
- `settings.typography.fontSizes` → `--wp--preset--font-size--{slug}`.
- `settings.typography.fontFamilies` → `--wp--preset--font-family--{slug}`.
- `settings.spacing.spacingSizes` → `--wp--preset--spacing--{slug}`.
- `settings.color.gradients` → `--wp--preset--gradient--{slug}`.
- `settings.shadow.presets` → `--wp--preset--shadow--{slug}` *(2.15.0)*.
- `settings.custom` → `--wp--custom--{nested}--{kebab-key}` (recursive flattening).

Preset slugs (and the segments of `var:preset|…` references) are normalized to `[a-z0-9-]` — camelCase is kebab-cased and any other character becomes `-` — so a slug can't break out of the class or custom-property name it's written into *(2.15.0)*.

### Font faces

> **Added in 2.15.0** ([#382](https://github.com/ArtisanPack-UI/cms-framework/issues/382)).

Every `settings.typography.fontFamilies[].fontFace` entry becomes an `@font-face` rule, emitted before `:root`. Sources:

- `file:./assets/…` resolves through the `themes.assets` route, which serves the theme's `assets/` directory. The URL keeps only its path, so an app in a subdirectory keeps its base path and the cached CSS doesn't pin a host.
- Absolute `http(s)://` URLs and root-relative `/…` paths pass through.
- Other `file:` paths, `..` segments, paths containing `%` (so encoded segments such as `%2e%2e` can't climb out; name font files without percent-encoding — a literal space is fine), protocol-relative `//…` URLs and anything with quotes, whitespace, parentheses or `<>{};` are skipped. A face with no usable source is skipped.

`format()` comes from the file extension (`woff2`, `woff`, `ttf` → `truetype`, `otf` → `opentype`, `eot`). Missing `fontStyle`, `fontWeight` and `fontDisplay` default to `WP_Font_Face`'s `normal`, `400` and `fallback`. The other descriptors (`fontStretch`, `unicodeRange`, `fontFeatureSettings`, `fontVariationSettings`, `ascentOverride`, `descentOverride`, `lineGapOverride`, `sizeAdjust`) pass through when set.

### Preset classes

Each palette, font-size, font-family and gradient preset also gets the WordPress utility classes that point at its custom property: `.has-{slug}-color`, `.has-{slug}-background-color`, `.has-{slug}-border-color`, `.has-{slug}-font-size`, `.has-{slug}-font-family` *(2.15.0)* and `.has-{slug}-gradient-background`, all `!important`.

A palette slug named `text`, `link` or `border` produces a `.has-{slug}-color` class that is also a block-support marker (`has-text-color`, `has-link-color`, `has-border-color`), which blocks carry for any custom color. Since 2.14.1 each of those slugs gets an exact `.has-{slug}-color-preset` rule, which visual-editor 1.14.1's Blade renderer targets by adding that class to blocks that pick the slug as their text color. The `.has-{slug}-color` rule is kept as a zero-specificity (`:where()`) fallback for markup the renderer doesn't build, such as the editor canvas: it skips elements with an inline `color:`, requires `has-text-color` for `link` / `border`, and is emitted after every other color rule with `text` last. In that markup a `link` / `border` text pick looks the same as a `text` pick plus a link or border color, so it resolves to `text`. Before 2.14.1 the slug's color was forced onto every block with a custom text, link or border color.

### Styles walker

The `styles` walker is registry-based against WP `theme.json` v3. The same registry feeds root, element, and block rules — so per-element and per-block styles get the same coverage the root does. Covered keys:

- `color` — `background`, `text`, `gradient`.
- `typography` — `fontSize`, `fontFamily`, `lineHeight`, `fontWeight`, `fontStyle`, `letterSpacing`, `textTransform`, `textDecoration`.
- `spacing.padding` / `spacing.margin` — scalar shorthand and per-side objects (`{top,right,bottom,left}`).
- `border` — `radius`, `color`, `style`, `width`, including per-corner (`topLeft`, `topRight`, `bottomLeft`, `bottomRight`) and per-side (`top`, `right`, `bottom`, `left`) shapes.
- `shadow` → `box-shadow`.
- `outline` — `color`, `offset`, `style`, `width` → `outline-*` *(2.15.0)*. WordPress themes put focus indicators here, e.g. `elements.button[":focus"].outline`.

> **Extended in 2.5.0** ([#200](https://github.com/ArtisanPack-UI/cms-framework/issues/200), [#201](https://github.com/ArtisanPack-UI/cms-framework/issues/201), [#202](https://github.com/ArtisanPack-UI/cms-framework/issues/202)).

### Elements

`styles.elements.{name}` → element-scoped rules, using WordPress's selectors:

| Element | Selector |
|---|---|
| `link` | `a:where(:not(.wp-element-button))` *(since 2.15.0; `a` before)* |
| `heading` | `h1, h2, h3, h4, h5, h6` |
| `h1`–`h6` *(2.15.0)* | `h1` … `h6` |
| `button` | `.wp-element-button, .wp-block-button__link` |
| `caption` *(2.15.0)* | `.wp-element-caption` |
| `cite` *(2.15.0)* | `cite` |

`heading` is emitted before `h1`–`h6`, so a per-level rule overrides the shared heading rule. The `link` selector skips `.wp-element-button` links, so link styles never restyle a button rendered as a link.

#### States

> **Added in 2.15.0** ([#382](https://github.com/ArtisanPack-UI/cms-framework/issues/382)).

`link` and `button` accept WordPress's pseudo-state sub-nodes, emitted in this order: `:link`, `:any-link`, `:visited`, `:hover`, `:focus`, `:focus-visible`, `:active`. The state is appended to every part of the selector (`.wp-element-button:hover, .wp-block-button__link:hover`). States under any other element are ignored, as in WordPress (`WP_Theme_JSON::VALID_ELEMENT_PSEUDO_SELECTORS`).

```json
"elements": {
    "button": {
        "color": { "background": "var:preset|color|primary" },
        ":hover": { "color": { "background": "var:preset|color|secondary" } },
        ":focus": { "outline": { "color": "var:preset|color|primary", "offset": "2px", "style": "solid", "width": "2px" } }
    }
}
```

### Blocks

- `styles.blocks.{ns/name}` → block-scoped rules. `core/*` blocks strip the namespace to match Gutenberg's own emission (`core/quote` → `.wp-block-quote`); other namespaces are kebab-joined (`artisanpack/card` → `.wp-block-artisanpack-card`). Block names with more than one slash (`ns/foo/bar`) are rejected so an invalid identifier can't drop the rule silently. Since 2.15.0 both parts must also use WordPress's block-name charset (`[a-z0-9-]`); other keys are skipped ([#385](https://github.com/ArtisanPack-UI/cms-framework/issues/385)).

> **Added in 2.5.0** ([#201](https://github.com/ArtisanPack-UI/cms-framework/issues/201)).

### Per-block-element overrides

- `styles.blocks.{ns/name}.elements.{element}` → block-scoped element rules, for every element in the table above, including link and button states. The block selector composes with each element selector; comma-joined element selectors (`h1, h2, …`) are **distributed** across the block selector — `.wp-block-quote h1, .wp-block-quote h2, …` — never naively concatenated as `.wp-block-quote h1, h2, …`, which would leak the child selector out of block scope.

> **Added in 2.5.0** ([#208](https://github.com/ArtisanPack-UI/cms-framework/issues/208)).

### Specificity

> **Changed in 2.15.0** ([#390](https://github.com/ArtisanPack-UI/cms-framework/issues/390)).

Element, block and block-element rules (and their states) are wrapped in `:root :where(…)`, as WordPress 6.6+ does, so they all share a flat (0,1,0) specificity and source order decides between them: a block's element rule overrides the root element's states, and block style variation rules appended after this output override the block's own. `.has-*` preset classes (`!important`) and inline styles still win.

```css
:root :where(a:where(:not(.wp-element-button))) { color: var(--wp--preset--color--primary); }
:root :where(.wp-block-quote a:where(:not(.wp-element-button))) { color: #0000ff; }
```

**Upgrading from 2.14 or earlier:**

- Root element rules (`a`, `h1`…) used to be (0,0,1). They now beat bare element selectors (`h1 { … }`, `a { … }`) in a theme's `style.css` / `editor.css`, even though those files load after the global styles.
- Block-element rules (`.wp-block-quote a`) used to be (0,1,1). A later (0,1,0) rule can now override them.

Move element colors and spacing into `theme.json`, or target classes in `style.css`.

### Value validation

> **Added in 2.15.0** ([#385](https://github.com/ArtisanPack-UI/cms-framework/issues/385), [#387](https://github.com/ArtisanPack-UI/cms-framework/issues/387)).

Global styles come from theme files and from users saving through the API, so every value is checked before it's written into the stylesheet. That covers preset values, `settings.custom` values, `styles.*` declarations (checked after `var:preset|…` translation) and `@font-face` descriptors. A value is dropped, and its siblings kept, when it contains:

- `;`, `{`, `}`, `<`, `>` or a backslash;
- a comment marker (`/*` or `*/`), a line break or another control character;
- a string left open, or quotes that interleave;
- a parenthesis or bracket outside a string that is unbalanced or crossed (`rgb(0 0 0`, `x [`, `([)]`). An open `(` or `[` would swallow every rule after it.

An unquoted `url(…)` must close and contain no quote or `(`. Parentheses, brackets and quotes inside a string are literal, so values like `"a(b"`, `[full-start] minmax(0, 1fr) [full-end]` and `url("a b.png")` are kept.

### Preset-var shorthand translation

WP-canonical `var:preset|category|slug` shorthand is translated into real `var(--wp--preset--category--slug)` refs before emission. Idempotent — raw `var(...)` passes through unchanged. The trailing anchor `(?![A-Za-z0-9_|-])` prevents half-translating values like `var:preset|color|primary|garbage`.

> **Added in 2.5.0** ([#202](https://github.com/ArtisanPack-UI/cms-framework/issues/202)).

### Caching

Output is cached on a content-hash key derived from the resolved styles tree, keyed with a `SCHEMA_VERSION` constant that bumps whenever the emitter's output shape changes (v10 as of 2.15.0). The cached CSS is passed through the `ap.cmsFramework.globalStyles.css` filter on every call, after the cache (see [Hooks and Events](Hooks-and-Events.md#framework-filters)). Invalidation is event-driven:

- The `GlobalStyles` model is saved or deleted (model observer in `SiteEditorServiceProvider`).
- The theme switches (the next resolve produces a new content hash; the prior entry ages out passively under the cache TTL).
- Emitter shape changes on framework upgrade (schema-version bump).

## Front-end Blade directive

```blade
<head>
    @cmsGlobalStyles
</head>
```

Themes opt in by including `@cmsGlobalStyles` in their root layout. The directive expands to a `<style id="cms-global-styles">` block carrying the emitter's output. The same CSS is exposed via `GET /api/v1/global-styles/css` for the editor canvas and any front-end that prefers fetching to using the directive.

## REST endpoints

Singleton-per-theme — no `{slug}` segment. Since 2.15.0 every endpoint needs the Site Editor `read` ability, and `PUT` / `DELETE` also need the `structure` ability (`manage-themes` by default); see [[site-editor/API Reference#authorization]].

| Method | Path | Purpose |
|---|---|---|
| GET    | `/global-styles` | Resolved styles for the active theme. |
| PUT    | `/global-styles` | Create or update the user-customization row. |
| DELETE | `/global-styles` | Revert to file-only authority. |
| GET    | `/global-styles/variations` | List variations from `themes/{active}/styles/*.json`. |
| GET    | `/global-styles/css` | Emit the resolved CSS as `text/css`. Concatenates emitter output + `themes/{active}/style.css` + `themes/{active}/editor.css` (in that order). Carries an `ETag` derived from the body, honors `If-None-Match` with a 304, and sends `Cache-Control: private, must-revalidate`. See [[themes/Editor Stylesheet]]. |

Response shape (`GET /global-styles`):

```json
{
    "id": 12,
    "theme": "digital-shopfront",
    "title": null,
    "settings": { "color": { "palette": [...] } },
    "styles": { "color": { "background": "#ffffff" } },
    "variation": "dark",
    "has_user_customization": true,
    "content_hash": "8b9a…",
    "modified": "2026-05-02T15:43:11+00:00"
}
```

## Resolver

```php
class GlobalStylesResolver
{
    public function resolve(): ?ResolvedGlobalStyles;            // singleton; null only when no active theme
    public function variations(): array;                         // theme-declared variations
    public function update(array $payload): ?GlobalStyles;       // lazy-create DB row
    public function revert(): bool;                              // delete DB row
}
```

`ResolvedGlobalStyles` carries `theme`, `settings`, `styles`, `variation`, `hasUserCustomization`, `model` (when DB-backed), plus a `contentHash()` accessor that the emitter uses for cache keying and a `toFilterEntry()` accessor that produces the `ap.visual-editor.global-styles` shape.
