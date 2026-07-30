# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Deprecated

### Removed

### Fixed

### Security

## [2.6.0] - 2026-07-30

### Added

- **`SupportsFeature` enum + `HasSupports` trait** ([#247](https://github.com/ArtisanPack-UI/cms-framework/issues/247), Keystone [#183](https://gitlab.com/jacob-martella-web-design/jacob-martella-web-design/jmwd-keystone-cms/jmwd-keystone-cms/-/issues/183)) — a canonical vocabulary for post-type `supports` flags (`title`, `editor`, `excerpt`, `featured_image`, `categories`, `tags`, `custom_fields`, `seo`, `author`, `page_attributes`, `revisions`, `templates`) modeled on WordPress's `post_type_supports()`. The new `HasSupports` trait exposes `supports(): array` and `supportsFeature(SupportsFeature|string): bool` on `Post`, `Page`, and `ContentType`, with per-model `defaultSupports()` overrides for the built-in types and DB-backed resolution on `ContentType` via `explicitSupports()`. Host apps consuming the framework should read the effective supports off the model instance instead of maintaining their own default arrays.

### Changed

- **Renamed the `content` supports flag to `editor`** ([#247](https://github.com/ArtisanPack-UI/cms-framework/issues/247), Keystone [#183](https://gitlab.com/jacob-martella-web-design/jacob-martella-web-design/jmwd-keystone-cms/jmwd-keystone-cms/-/issues/183)) — the underlying record-table column is still `content`; only the flag identifier changed to match the wider vocabulary in `SupportsFeature`. The `BlogServiceProvider` and `PagesServiceProvider` default registrations were also expanded to include the newly-canonical flags (`categories`, `tags`, `seo`, `revisions` for posts; `templates`, `revisions` for pages). A companion migration rewrites `content_types.supports` JSON rows so any admin-created content type that stored `'content'` is transparently upgraded to `'editor'` on the next `migrate` run — reversible via `migrate:rollback`.

- **`ContentType::supportsFeature()` accepts the enum case as well as the string value.** The previous string-only signature is preserved for callers passing `'editor'` / `'seo'` / etc., but callers can now pass `SupportsFeature::Editor` directly. `title` is treated as always-on regardless of the stored array, matching the "required — always on" note in the editor redesign spec.

## [2.5.4] - 2026-07-27

### Added

- **`cms.updates.composer_binary` config key** ([#232](https://github.com/ArtisanPack-UI/cms-framework/issues/232)) — priority-1 override for the self-updater's composer binary path, populated by default from `env('COMPOSER_BINARY')`. Setting `COMPOSER_BINARY=/opt/homebrew/bin/composer` in a Laravel `.env` file now works from HTTP-request context, matching the advice the `composerBinaryNotFound` error message gives.
- **`CMS_PHP_BINARY` env var + CLI PHP interpreter resolution for composer** (follow-up to [#225](https://github.com/ArtisanPack-UI/cms-framework/issues/225)) — a new `resolvePhpBinary()` walks CLI-shaped candidates (Herd, Homebrew, system) and rejects anything whose basename smells like `-fpm`/`-cgi`. `CMS_PHP_BINARY` wins when set; CLI SAPI short-circuits to `PHP_BINARY`. The resolved CLI PHP is used to invoke composer during both install and the rollback pre-check.

### Fixed

- **Updater metadata GETs blocked by userland HTTP listeners** ([#231](https://github.com/ArtisanPack-UI/cms-framework/issues/231)) — the feed check, single-release lookup, SHA-256 sidecar fetch, and custom JSON endpoint all went through Laravel's `Http` facade, which dispatches `RequestSending`/`ResponseReceived` around every request. Herd Pro's `HttpClientWatcher`, Telescope, Debugbar, and custom monitoring listeners could block or corrupt the request lifecycle — when Herd's dump-server socket was wedged, the check died after `max_execution_time` instead of the ~200ms round-trip. A new `MetadataClient` static helper uses a raw `GuzzleHttp\Client` and bypasses Laravel's HTTP factory event dispatch entirely; retry semantics (5xx retry, config-driven attempt count) are preserved for the custom JSON endpoint. The download path still uses `Http::sink()` (already shielded by #224).
- **`COMPOSER_BINARY` in `.env` didn't take effect under PHP-FPM** ([#232](https://github.com/ArtisanPack-UI/cms-framework/issues/232)) — `ApplicationUpdateManager::envComposerBinary()` read via `getenv('COMPOSER_BINARY')`, but Laravel 11+'s default dotenv adapter populates `env()`/`$_ENV` without calling `putenv()`, so `getenv()` returned `false` from HTTP-request context and the priority-1 override was silently skipped. `envComposerBinary()` now reads `cms.updates.composer_binary` (populated from `env('COMPOSER_BINARY')` in the shipped config) first, then falls back to `getenv()` for hosts that export `COMPOSER_BINARY` at the OS level. The `composerBinaryNotFound` error message now names both the `.env`/config path and the OS-level path so operators aren't sent down a broken workaround.
- **Composer discovery failures were undiagnosable** ([#233](https://github.com/ArtisanPack-UI/cms-framework/issues/233)) — `discoverComposerBinary()` now collects `is_file()`/`is_executable()` per candidate path and emits a structured `Log::warning` on failure so operators can distinguish a wrong-path failure from a PHP-FPM sandboxed-stat failure (macOS Herd Pro sandboxes `/opt/homebrew/*`; chrooted FPM pools and restrictive `open_basedir` behave the same way). The warning includes `php_sapi` and a hint pointing at the fix. `UpdateException::composerBinaryNotFound()` accepts either the legacy flat string list or the new per-candidate diagnostic shape and renders the stat outcome for each path in the exception message, so operators don't have to check the log. Legacy callers still work; a mixed-shape input degrades to path-only rendering instead of raising a `TypeError`.
- **PHP-FPM interpreter used to invoke composer** (follow-up to [#225](https://github.com/ArtisanPack-UI/cms-framework/issues/225)) — under PHP-FPM, `PHP_BINARY` points at the FPM daemon binary, which prints usage and exits 64 when handed the composer PHAR. That produced the misleading `"Composer install failed. Output: ."` symptom on Herd hosts — composer was discovered fine, but the interpreter used to invoke it was the wrong SAPI. Rollback then wrongly reported `"Composer binary could not be located"` because `verifyComposerBinaryAvailable()` reused the same broken interpreter. `buildComposerCommand()` and the rollback pre-check now use the resolved CLI PHP; a new `composerVerificationFailed` exception surfaces the resolved binary, PHP interpreter, exit code, and captured output when `--version` fails, so future misdiagnoses don't repeat.
- **Silent extraction failures left partial installs on disk** ([#236](https://github.com/ArtisanPack-UI/cms-framework/issues/236)) — inside `ApplicationUpdateManager::extractUpdate()`'s per-entry loop, a failed `fopen('wb')` or `fread()` on the target file previously did `continue`/`break` without logging or throwing. Extraction of that entry was silently abandoned, nothing propagated to `performUpdate()`'s catch block, and `handleUpdateFailure()`/rollback never triggered — leaving a partial install on disk that failed to boot on the next request with no obvious cause. Failures are now logged with the entry + errno and thrown via a new `UpdateException::extractionEntryFailed()` factory so `performUpdate()`'s catch block rolls back to the pre-update snapshot.

### Security

- **Fail closed when update source omits SHA-256 checksum** ([#235](https://github.com/ArtisanPack-UI/cms-framework/issues/235)) — `ApplicationUpdateManager::maybeVerifyChecksum()` previously logged a warning and returned silently when the update source did not advertise a SHA-256 hash, letting the updater download and execute arbitrary remote code without integrity verification. That fail-open behavior also weakened the reachability story for #234. `maybeVerifyChecksum()` now throws `UpdateException::checksumRequired()` when no checksum is advertised. An explicit opt-in — `cms.updates.allow_unverified_updates` (default `false`, env `CMS_UPDATES_ALLOW_UNVERIFIED`) — is available for hosts on trusted networks or air-gapped mirrors that intentionally accept the risk; those hosts still get the original warning log.
- **Zip-slip in `extractUpdate()`** ([#234](https://github.com/ArtisanPack-UI/cms-framework/issues/234)) — `ApplicationUpdateManager::extractUpdate()` built the write target by concatenating `base_path()` with an unvalidated entry name from the ZIP, then streamed via `fopen()`/`fwrite()`. Because this bypassed `ZipArchive::extractTo()`, PHP's own traversal mitigations did not apply, so a crafted archive entry like `release-root/../../../etc/cron.d/x` would write outside the install root once the common prefix was stripped. Entries whose normalized path starts with `/` or contains `..` segments are now rejected before the target directory is created, and `realpath()` verifies the resolved parent still sits under the extraction root before opening the write stream. Both failures are logged and the entry is skipped.

## [2.5.3] - 2026-07-23

### Fixed

- **Self-updater `ResponseReceived` listeners hit "Stream is detached"** ([#224](https://github.com/ArtisanPack-UI/cms-framework/issues/224)) — 2.5.2 sink'd the release archive straight to disk via `Http::sink()` and closed the underlying stream in a response middleware to keep listeners from copying the archive back into memory (#219). But closing the `LazyOpenStream` detaches it, so any `ResponseReceived` listener that called `$response->body()` — Herd Pro's `HttpClientWatcher`, Telescope, Debugbar, custom monitoring — threw `"Stream is detached"` from `GuzzleHttp\Psr7\LazyOpenStream::eof()`. The whole update pipeline succeeded, then died during trailing event fanout and got rolled back. The shared `StreamsDownloadsToDisk` middleware now swaps the closed body for a fresh in-memory empty stream via `Utils::streamFor('')` before returning, so `body()` returns `''` safely for any observer. The three source-test observer-safety assertions have been replaced with a real `ResponseReceived` listener that calls `->body()` — the exact path Herd/Telescope/Debugbar take — so future regressions get caught.
- **Composer discovery on hosts where PHP-FPM's `PATH` doesn't include composer, plus actionable rollback errors** ([#225](https://github.com/ArtisanPack-UI/cms-framework/issues/225)) — The self-updater's `runComposerInstall()` ran the bare command `composer install ...` via `/bin/sh -c`, which inherits PHP-FPM's `PATH`. On Laravel Herd and many Nginx/PHP-FPM production setups, that `PATH` doesn't include composer's directory, so the update failed with `"sh: composer: command not found"`. Rollback then re-ran the same failing command and reported `"Rollback failed: … Manual intervention required."` — masking the real cause (a resolvable "where does composer live?" problem) as an unfixable rollback failure. A new `resolveComposerCommand()` now walks (1) the `COMPOSER_BINARY` env var (absolute path), (2) `cms.updates.composer_install_command` when it differs from the shipped default (backwards-compatible operator override), (3) auto-discovery across `/usr/local/bin/composer`, `/opt/homebrew/bin/composer`, `~/.composer/vendor/bin/composer`, `~/.config/composer/vendor/bin/composer`, `/usr/bin/composer`, and (4) bare `composer install ...` (pre-2.5.3 behavior). When env or discovery wins, the command is built as `{PHP_BINARY} {binary} install ...` — both shell-escaped — so PHP-FPM's `PATH` never has to resolve composer's `#!/usr/bin/env php` shebang, and Herd's `~/Library/Application Support/Herd/bin/php-*` path with its embedded space stays intact. Before rollback invokes `composer install`, `verifyComposerBinaryAvailable()` now runs `{PHP_BINARY} {binary} --version` with a 10s timeout; when it fails we throw `UpdateException::composerBinaryNotFound($searchedPaths)` — an actionable message naming the paths inspected and the `COMPOSER_BINARY` override — instead of `"Manual intervention required."` When rollback itself fails, `UpdateException::rollbackAfterFailure()` preserves both the original update-failure message and the rollback message so operators see why the update failed alongside the rollback failure. New `docs/self-updater.md` documents the discovery precedence, env var, escape hatch, and rollback diagnostics; the config comment on `composer_install_command` now describes the full chain.
- **`UpdateInfo::hasUpdate()` stale after out-of-band version bumps** ([#226](https://github.com/ArtisanPack-UI/cms-framework/issues/226)) — `UpdateChecker::checkForUpdate()` cached the resolved `UpdateInfo` value object for `cms.updates.cache_ttl` seconds (default 12h). The cached object froze both `latestVersion` (from the feed) AND `currentVersion` (a snapshot of `config('app.version')` at cache-populate time). `hasUpdate()` then compared those two frozen strings, so if the host's installed version moved forward out-of-band (manual `composer install` on a release zip, unzip-over-site, deploy script) between cache-populate and cache-render, the Updates admin page kept saying "Update available to X" for a site already on X — for up to 12h. `UpdateInfo::hasUpdate()` and `UpdateInfo::toArray()` now route through `resolveCurrentVersion()`, which reads `config('app.version')` fresh at call time when the container is bootstrapped and the value is a non-empty string; falls back to the constructor snapshot for non-Laravel callers, tests, and hosts that never set `app.version`. The container check goes through `Illuminate\Container\Container::getInstance()->bound('config')` so we never touch an unbootstrapped container. The same helper is now used by `PerformUpdateCommand`, `CheckForUpdateCommand`, and `CheckForUpdateScheduled` so console output and log lines stay consistent with `hasUpdate()`. Belt-and-suspenders: `UpdateChecker::cacheIsStale()` reads `config('app.version')` without a default, treats null/empty as "no fresh version to compare against" (keeps the pre-2.5.3 cache-serving behavior on hosts that never set `app.version`), and only evicts the cache when the fresh value genuinely differs from the cached snapshot. New "Cached update info and out-of-band version bumps" section in `docs/self-updater.md` documents both mechanisms.

## [2.5.2] - 2026-07-22

### Fixed

- **Application updater still OOM'd post-download on 128M hosts** ([#219](https://github.com/ArtisanPack-UI/cms-framework/issues/219)) — 2.5.1's streaming-download fix was only half the fight. The release archive still OOM'd at `guzzlehttp/psr7/Utils.php:124` (`Utils::copyToString`) because a downstream `ResponseReceived` listener (Telescope-style) called `$response->body()` on the sink stream and pulled the whole ZIP back into a PHP string, and `ApplicationUpdateManager::extractUpdate()` then loaded each ZIP entry into memory via `getFromIndex()`. A new `StreamsDownloadsToDisk` trait shared by all three update sources wraps `Http::sink()` with a `withResponseMiddleware` that closes the sink body as soon as Guzzle hands the response back — before Laravel dispatches `ResponseReceived`, so any listener calling `body()` gets an empty string instead of copying the archive. `extractUpdate()` now streams each ZIP entry with `$zip->getStream()` + chunked `fread`/`fwrite` instead of `getFromIndex()`, so a single large file inside the release archive can't OOM mid-extraction either. Regression fences: one test per update source asserts the recorded PSR-7 response body is unreadable after `downloadUpdate()` returns, and `ApplicationUpdateManagerTest` runs `extractUpdate()` against a real ZIP under a scoped base path and confirms streamed extraction produces byte-for-byte copies.
- **`NotificationController::index` `TypeError` on every admin poll** ([#220](https://github.com/ArtisanPack-UI/cms-framework/issues/220)) — `NotificationController::index` passed the raw string from `?limit=…` straight to `NotificationManager::getUserNotifications( int $limit, … )`, tripping a `TypeError` under `strict_types` on every admin poll (roughly every 30s) and drowning `storage/logs/laravel.log` in dozens of exceptions per minute. Swapped `$request->input()` for `$request->integer()` so the value is coerced at the controller boundary, and dropped the now-unneeded `phpcs:ignore`. Existing validation (`sometimes|integer|min:1|max:100`) still rejects non-numeric input with a 422. A new `NotificationControllerTest` covers the string→int coercion path, the default limit, and the validation-rejection regression fence.

## [2.5.1] - 2026-07-22

### Fixed

- **`CustomJsonUpdateSource` OOM on large release tarballs** ([#216](https://github.com/ArtisanPack-UI/cms-framework/issues/216)) — `CustomJsonUpdateSource::downloadUpdate()` previously buffered the entire release archive into a string via `$response->body()` before writing it to disk with `File::put()`, which OOM'd inside Guzzle's `Utils::copyToString()` on any tarball larger than roughly half of PHP's `memory_limit`. The download now streams straight to disk via `Http::sink($tempPath)->get()`, mirroring the fix applied to `GitLabUpdateSource` and `GitHubUpdateSource` in 2.5.0 (#214). Both a transport-level throw and a non-2xx response now clean up the partial file via a shared `catch (Throwable)` block before rethrowing.

## [2.5.0] - 2026-07-21

### Added

- **Theme base class for per-request behavior** ([#198](https://github.com/ArtisanPack-UI/cms-framework/issues/198)) — Themes may now ship an optional `themes/{slug}/Theme.php` that extends `ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme` to hook into the per-request lifecycle: enqueue front-end/editor CSS + JS, register custom image sizes, and register custom REST endpoints or block filters. Themes without a `Theme.php` stay fully backward compatible. A new `/themes/{slug}/assets/{path}` route serves static assets from the theme's `assets/` directory with slug/extension/traversal validation, an explicit MIME map (so CSS/JS aren't served as `text/plain`), `nosniff`, and CSP sandbox + `Content-Disposition` for SVG to close the stored-XSS vector on uploaded themes. The manifest-driven `themeClass` override runs through a `ReflectionClass` provenance check so an uploaded `theme.json` cannot instantiate an unrelated first-party or vendor `Theme` subclass. Three new filters — `ap.themes.frontendStyles`, `ap.themes.editorStyles`, `ap.themes.frontendScripts` — let third parties add or mutate enqueue lists without subclassing.

- **Widened `GlobalStylesEmitter` coverage against WP `theme.json` v3** ([#200](https://github.com/ArtisanPack-UI/cms-framework/issues/200), [#201](https://github.com/ArtisanPack-UI/cms-framework/issues/201), [#202](https://github.com/ArtisanPack-UI/cms-framework/issues/202)) — Three closely-related emission gaps closed in `GlobalStylesEmitter`. The styles walker moves from a 5-key hardcoded map to a registry-based one covering `border` (radius/color/style/width, including per-corner and per-side shapes), `spacing.padding`/`margin` (scalar shorthand + per-side objects), extended typography (`fontWeight`/`fontStyle`/`letterSpacing`/`textTransform`/`textDecoration`), and `shadow` → `box-shadow`. The widened walker now feeds root, element, AND block rules, so per-element styles get the same coverage. A new `blockStyleBlocks()` emits `styles.blocks.{ns/name}` — `core/quote` → `.wp-block-quote` (namespace stripped to match Gutenberg), `artisanpack/card` → `.wp-block-artisanpack-card`. WP-canonical `var:preset|category|slug` shorthand is now translated into real `var(--wp--preset--category--slug)` refs before emission (idempotent — raw `var(...)` passes through unchanged). `SCHEMA_VERSION` bumped v2 → v3 so cached CSS from prior deploys busts automatically. Follow-up hardening in the same wave: `blockSelector()` rejects block names with more than one slash (`ns/foo/bar` used to explode into invalid `.wp-block-ns-foo/bar` and silently drop the rule), and `translatePresetValue()` anchors its regex with `(?![A-Za-z0-9_|-])` so `var:preset|color|primary|garbage` passes through unchanged instead of half-translating.

- **Populate blocks for theme patterns in `PatternResolver`** ([#204](https://github.com/ArtisanPack-UI/cms-framework/issues/204)) — `PatternResolver::buildThemePattern()` previously hardcoded `blocks: []`, which caused every theme-shipped pattern to render as an "Empty pattern" placeholder in the visual-editor pattern browser and open as an empty canvas until the client's `hydrateBlocks()` re-parsed `content.raw`. A new `BlockMarkupParser` support class ports a subset of WordPress's `parse_blocks()` and wires into the theme-file branch. The parser produces the WP `parse_blocks()` shape (`{blockName, attrs, innerBlocks, innerHTML, innerContent}`) and covers flat blocks, nested containers, void (self-closing) blocks, freeform HTML between blocks, and forgiving JSON-attr decoding. Client-side Gutenberg `parse(rawContent)` remains authoritative for editor correctness — this parser is a best-effort optimization for lightweight surfaces like the pattern thumbnail summary. Recursion is capped at depth 64 so adversarially-nested `block_content` can't blow the PHP call stack; PCRE errors and JSON-decode failures are logged via `Log::warning`; a leading UTF-8 BOM is stripped so BOM-prefixed theme files don't emit a phantom freeform sibling. The parser is deliberately generic — sibling `TemplateResolver` and `TemplatePartResolver` theme-file branches ship the same gap and can drop this in as-is.

- **Per-block-element overrides under `styles.blocks.{name}.elements.*`** ([#208](https://github.com/ArtisanPack-UI/cms-framework/issues/208)) — Extends `GlobalStylesEmitter` to recurse into each block's `elements` map after emitting the base block rule, composing the block selector with each supported element selector so per-block-element overrides render identically to Gutenberg's own emission. Comma-joined element selectors (`h1, h2, …`) are distributed across the block selector — `.wp-block-quote h1, .wp-block-quote h2, …` — never naively concatenated as `.wp-block-quote h1, h2, …`, which would leak the child selector out of block scope. `SCHEMA_VERSION` bumped v3 → v4 so the cache invalidates on deploy.

- **Post / Page lifecycle, dashboard-widgets, plugin.hookRegistered, and search-query hooks (Wave 5)** ([#196](https://github.com/ArtisanPack-UI/cms-framework/issues/196)) — Thirteen new fire sites bring the CMS Framework's hook surface up to parity with the rest of the ecosystem. Each `Post` and `Page` model now emits `ap.cmsFramework.{post,page}.saving`, `.saved`, `.published` (fires only on a transition into `ContentStatus::Published` — first-save-as-published, draft→published, and scheduled→published all count; subsequent saves of an already-published record do not), `.trashed` (soft delete only — force-delete is skipped), and `.restored` via the new `FiresLifecycleHooks` concern under `src/Modules/ContentTypes/Models/Concerns/`. `AdminWidgetManager::getAvailableWidgetsForUser()` now runs its output through `ap.cmsFramework.admin.dashboardWidgets`, passing the resolved user (or `null`) so subscribers can make per-user injections without re-resolving auth. `PluginManager::activate()` fires `ap.cmsFramework.plugin.hookRegistered` immediately after the plugin's service provider registers, carrying `(string $pluginSlug, array $hooks)` — the hooks array is the optional `hooks` field from the plugin's `plugin.json` (empty array when absent) so observers still get a per-plugin signal. `HasContentFilters::applySearchFilter()` (used by both `BlogManager::getArchiveQuery()` and `PageManager::getPageQuery()`) runs the assembled search query through `ap.cmsFramework.search.query` with `(Builder $q, string $term, array $context)` where `$context` carries the calling manager class, the queried model class, and the full filter array — enough for a subscriber to swap in a full-text index or route to an external search service.

- **Editor-only theme stylesheet** ([#199](https://github.com/ArtisanPack-UI/cms-framework/issues/199)) — Themes may now ship an optional `themes/{slug}/editor.css` alongside the existing `themes/{slug}/style.css`. The `GET /api/v1/global-styles/css` endpoint concatenates emitter output + `style.css` + `editor.css` (in that order), giving the site-editor canvas the full canvas stylesheet in a single fetch. The `@cmsGlobalStyles` Blade directive is unchanged — it renders only the emitter output — so `editor.css` never leaks to the public front-end. Analog to WordPress's `add_editor_style()`; lets themes use bare element selectors for canvas-only overrides without theming inspector-panel mini-previews.
- **`ThemeStylesheetReader` support class** ([#199](https://github.com/ArtisanPack-UI/cms-framework/issues/199)) — Public, container-bound reader (`app( ThemeStylesheetReader::class )`) that safely resolves `themes/{slug}/{filename}` for the active theme. Slug validation delegates to `ThemeManager::validateSlug()`, path resolution to `ThemeManager::getThemesPath()`, and traversal containment to the new `PathContainmentGuard`. Memoizes `getActiveTheme()` per instance so multiple reads inside a request pay the schema-validation cost once. `frontendStylesheet()` / `editorStylesheet()` are convenience wrappers around a generic `read( string $filename )`; `readWrapped( $filename )` returns the contents behind a `/* === filename === */` banner for concatenation, and `lastModified()` exposes the freshest theme-stylesheet mtime for cache-key composition. Not `final` — downstream packages (`packages/visual-editor`) can extend or fake it.
- **`PathContainmentGuard::within( $base, $candidate )`** ([#199](https://github.com/ArtisanPack-UI/cms-framework/issues/199)) — Shared realpath + `str_starts_with` containment helper for the security-sensitive path-traversal guard that was previously inlined in `ThemeStylesheetReader`, `ThemeAssetsController`, and the sibling visual-editor controller. Returns the canonicalized absolute path when the candidate lives inside `$base`, null otherwise.
- **HTTP caching on `/api/v1/global-styles/css`** ([#199](https://github.com/ArtisanPack-UI/cms-framework/issues/199)) — Response now carries an `ETag` derived from the concatenated body and honors `If-None-Match` with a 304, plus `Cache-Control: private, must-revalidate` so intermediaries revalidate cheaply instead of serving stale bytes when a theme edits `style.css` / `editor.css`.
- **`ThemeManager::getThemesPath()` and `validateSlug()` public** ([#199](https://github.com/ArtisanPack-UI/cms-framework/issues/199)) — Both accessors are now public so downstream helpers (like the new stylesheet reader and any package building on it) can anchor to the same themes root and slug rule instead of duplicating the resolution logic. `getThemesPath()` also gains absolute-path handling: an absolute value in `cms.themes.directory` (e.g. `/opt/themes`) is now honored verbatim instead of being prepended with `base_path()`, and a `null`/empty configured value falls back to `themes` so the containment guard never collapses to the app root.

### Changed

- **Normalized hook namespaces to `ap.cmsFramework.*`** ([#193](https://github.com/ArtisanPack-UI/cms-framework/issues/193), [#194](https://github.com/ArtisanPack-UI/cms-framework/issues/194), [#195](https://github.com/ArtisanPack-UI/cms-framework/issues/195)) — Roughly 120 hook names emitted by the CMS Framework have been renamed onto a consistent namespace. Wave 4a covers infrastructure surfaces (`ap.admin.menu` → `ap.cmsFramework.admin.menu`, the three `ap.*.enqueuedAssets` families, the `ap.admin.contentEdit.*` extension surfaces, `ap.dynamic_content.register-types` → `ap.cmsFramework.dynamicContent.registerTypes`, plus `ap.roleRegistered` / `ap.permissionRegistered` moving to `ap.rbac.*`). Wave 4b covers lifecycle events (`plugin.installing`/`installed`/`activating`/`activated`/`deactivating`/`deactivated`/`deleting`/`deleted`/`updating`/`updated` → `ap.cmsFramework.plugin.<action>`; `theme.installing`/`installed`/`activating`/`activated` → `ap.cmsFramework.theme.<action>`) and the comment surfaces (`comment.editLink`, `comments.store.defaultStatus`, `comments.rate-limit.{guest,authenticated}`, `comments.form.action` all move under `ap.cmsFramework.comment{s}.*`). Wave 4c namespaces every policy-level ability filter (`{resource}.{action}` such as `posts.view`, `pages.publish`, `comments.moderate`, `role.forceDelete`) under `ap.cmsFramework.abilities.{resource}.{action}`.
- Requires `artisanpack-ui/hooks: ^1.3` for the new `deprecateHook()` alias primitive that backs this change.

### Deprecated

- Every pre-2.5.0 hook name renamed above is registered as an alias via the new `ArtisanPackUI\CMSFramework\Support\HookAliases::register()` primitive (invoked in `CMSFrameworkServiceProvider::boot()`). Existing subscribers on the old names keep firing, and callbacks registered on old vs. new names dispatch together — no host-app changes are required to upgrade, but the old names will emit a one-per-request deprecation log entry so downstreams can migrate at their own pace.

### Removed

### Fixed

### Security

## [2.4.0] - 2026-07-16

### Added

- **Dynamic content system** ([#178](https://github.com/ArtisanPack-UI/cms-framework/issues/178)) — Site-wide dynamic content module at `src/Modules/DynamicContent/`. Site owners define a piece of content once and reference it anywhere via `{{source.field|mod:arg}}` merge tokens; edits propagate everywhere the token is used. Ships four migrations (`dynamic_content_types`, `_fields`, `_records`, `_record_values`); an in-memory registry that merges admin-created (DB) and code-registered types with slug-conflict warnings; 11 built-in field types (text, rich_text, url, email, phone, image, date, datetime, number, address, select) with HTML-safe rendering (text-shaped types escape at render time, rich text stays raw by design); 7 built-in modifiers (default, upper, lower, truncate, date, time, nl2br — `nl2br` escapes input before inserting `<br>` and returns `HtmlString`); a `DynamicContentResolver` with a 65KB `/resolve` cap plus throttle; the `apRenderContent()` helper and `@dynamicContent` Blade directive; a `DynamicContentCast` Eloquent cast (documented as a public content pipeline — do NOT store secrets in dynamic content records); filter-driven authz (`manage_dynamic_content` capability with per-type policy scoping via `viewAnyForType` / `createForType`); per-request memoization on the accessor to eliminate collection-token N+1s; a resolver signature cache invalidated on record save/delete; REST endpoints under `/api/v1/dynamic-content/*` with types bound by slug; a Livewire admin surface (type list, field builder, singleton editor, collection editor); and wiring into `HasRenderedBlockContent::renderContent()` so Blog Post, Pages, and any content type using the trait auto-resolve tokens.

- **Plugin system v2.4 — schema, filter, base provider, compat, lifecycle** ([#179](https://github.com/ArtisanPack-UI/cms-framework/issues/179), [#180](https://github.com/ArtisanPack-UI/cms-framework/issues/180), [#181](https://github.com/ArtisanPack-UI/cms-framework/issues/181), [#182](https://github.com/ArtisanPack-UI/cms-framework/issues/182), [#183](https://github.com/ArtisanPack-UI/cms-framework/issues/183), [#190](https://github.com/ArtisanPack-UI/cms-framework/pull/190)) — Five interrelated enhancements for the Keystone CMS plugin UI initiative:
  - `plugin.json` schema extended with `min_host_version`, `federated_module`, `nav_entries`, `permissions` (plugin-slug-namespaced), `migrations_path` (traversal-guarded), and `rollback_migrations_on_delete`.
  - New `ap.admin.menu` filter with a React/Inertia-friendly row shape (`url` / `label` / `iconId` / `permission` / `external`); post-filter capability re-check gates plugin-injected entries.
  - New `PluginServiceProvider` base class with `registerAdminPage` / `registerNavEntry` / `registerFederatedModule` / `pluginPath` / `pluginConfig` helpers, backed by a container-bound `PluginRegistry` singleton (Octane-safe).
  - Plugin `min_host_version` enforced at activation via `IncompatiblePluginException` with `composer.json` + `InstalledVersions` resolution; controller surfaces a structured 409 response.
  - Complete plugin lifecycle: transactional activation with PSR-4 snapshot/restore rollback, opt-in migration rollback on delete, namespace-scoped permission seed/unseed, and optional framework cache clears (`cms.plugins.autoClearFrameworkCaches`, default `false`).

- **Complete plugin extensibility** ([#184](https://github.com/ArtisanPack-UI/cms-framework/issues/184), [#185](https://github.com/ArtisanPack-UI/cms-framework/issues/185), [#186](https://github.com/ArtisanPack-UI/cms-framework/issues/186), [#187](https://github.com/ArtisanPack-UI/cms-framework/issues/187), [#188](https://github.com/ArtisanPack-UI/cms-framework/issues/188), [#191](https://github.com/ArtisanPack-UI/cms-framework/pull/191)) — Ships the remaining plugin-system issues so third-party plugins can extend the framework end-to-end:
  - `ContentTypeManager` / `TaxonomyManager` singular getters (`getContentType`, `contentTypeExists`, `getTaxonomy`, `taxonomyExists`, `getTaxonomiesForContentType`) now hydrate filter-registered entries as unpersisted models (backwards compatible via Option B); added `getPersistedContentType` / `getPersistedTaxonomy` for privileged callers that must never trust plugin-supplied `table_name`.
  - New `CustomFieldTypeRegistry` + `FieldTypeDefinition` value object under `src/Modules/ContentTypes/`. All 16 built-in `FieldType` enum cases are pre-registered. New `apRegisterFieldType(string $slug, array|FieldTypeDefinition $definition)` helper matches the sibling `apRegister*` signature convention. `CustomField.type` cast changed from `FieldType::class` to `string`; new `fieldTypeEnum()`, `fieldTypeDefinition()`, `storageMode()` accessors. `CustomFieldRequest` now validates against `Rule::in(registry->slugs())`.
  - `CustomFieldManager::getFieldsForContentType()` merges DB + filter fields. Filter fields carry `storage = metadata`. The `HasCustomFields` trait was rewritten to route metadata-storage fields to the model's `metadata` JSON column. Schema mutations (`createField`, `updateField`, `deleteField`, `generateMigration`) are now restricted to DB-persisted content types so a plugin's filter-registered `table_name` can never steer `Schema::table` at a host table.
  - New `ContentEditExtensions` manager exposing `panels()`, `tabs()`, `beforeEditor()`, `afterEditor()`, `saveData()` over `ap.admin.contentEdit.{panels,tabs,beforeEditor,afterEditor,saveData}`. Respects `contentTypes` restriction and `order`.
  - Plugin authoring guide (`docs/plugin-authoring.md`) and `examples/hello-world-plugin/` skeleton with `plugin.json`, base `PluginServiceProvider` usage, migration, and Blade admin view. Federated React flavor stubbed with pointer to Keystone. `README` plugin-system section rewritten; "experimental" wording removed.

### Security

- **Plugin system hardening** ([#190](https://github.com/ArtisanPack-UI/cms-framework/pull/190), [#191](https://github.com/ArtisanPack-UI/cms-framework/pull/191)) — surfaced by local `/code-review` passes:
  - Reject manifest `migrations_path` traversal (schema + runtime `realpath` check).
  - Namespace-prefix permission slugs so seed/unseed cannot touch core or other plugin rows.
  - Re-apply `Gate::allows` after the `ap.admin.menu` filter runs.
  - Allow-list URL schemes in `registerNavEntry` and `resolveRouteUrl`.
  - Constrain `PluginServiceProvider` manifest walk to direct children of the plugins root.
  - `HasCustomFields` short-circuits via `getCasts()` + real `Schema` column listing so a plugin cannot shadow a host attribute (`password`, `is_admin`, `metadata`) by registering a filter-scoped field with a colliding key.
  - `__set` on filter-registered fields throws `RuntimeException` when the host model has no `metadata` column instead of silently dropping writes.
  - `update/deleteContentType` and `update/deleteTaxonomy` refuse filter-only slugs (previously silently `INSERT`ed phantom rows via Eloquent `update()` on `exists = false`).
  - `forceFill` payloads strip `id` / `created_at` / `updated_at` / `deleted_at` so plugins cannot seed persistence-critical keys.
  - `CustomFieldTypeRegistry` rejects malformed filter entries (non-scalar `slug` or `label`, empty `slug`) — one bad plugin no longer DoSes the registry.
  - `getCustomFieldsForType` is memoized per instance and auto-flushed on `saved` / `deleted`.

## [2.3.0] - 2026-07-09

### Added

- **AI content-authoring agents** ([#170](https://github.com/ArtisanPack-UI/cms-framework/issues/170), [#171](https://github.com/ArtisanPack-UI/cms-framework/issues/171), [#172](https://github.com/ArtisanPack-UI/cms-framework/issues/172), [#173](https://github.com/ArtisanPack-UI/cms-framework/issues/173), [#174](https://github.com/ArtisanPack-UI/cms-framework/issues/174), [#175](https://github.com/ArtisanPack-UI/cms-framework/pull/175)) — Five new agents built on the `artisanpack-ui/ai` v1.0 foundation:
  - `PostTitleSuggestionAgent` (`cms.post_title`) — generate 3–5 title variants from a draft body.
  - `ExcerptGenerationAgent` (`cms.excerpt`) — generate a natural excerpt (default ≤200 chars) from full post content.
  - `TagSuggestionAgent` (`cms.suggest_tags`) — pick tags from an existing taxonomy, optionally propose new ones.
  - `CategorySuggestionAgent` (`cms.suggest_category`) — pick one category (slash-delimited path) from a hierarchical tree.
  - `SlugSuggestionAgent` (`cms.suggest_slug`) — produce an SEO-friendly kebab-case slug from a title, delegating ASCII folding to `Str::slug()`.

  Every agent honors the `artisanpack.ai.features.<key>.enabled` toggle and is auto-discovered via `CMSFrameworkServiceProvider::aiFeatures()`.

- **AI trigger surfaces** — two consumer surfaces expose the same five agents:
  - Livewire component `ap-cms-ai-tools` (`ArtisanPackUI\CMSFramework\Livewire\Ai\AiTools`) — dispatch `ap-cms-ai:{action}` browser events, receive results on `ap-cms-ai:{featureKey}:{status}`.
  - REST controller mounted at `/api/v1/cms/ai/*` (`ArtisanPackUI\CMSFramework\Http\Controllers\Ai\AiController`) — framework-agnostic path for React, Vue, or any HTTP client. Endpoints are guarded by `auth:sanctum` so bearer-token SPAs can authenticate. Adding a new CMS AI feature never requires touching the `@artisanpack-ui/react` or `@artisanpack-ui/vue` packages.

- **`AI_FEATURE_KEYS` constant** on `CMSFrameworkServiceProvider` — canonical list of the five `cms.*` feature keys, consumed by both trigger surfaces.

### Changed

- **Minimum PHP raised to 8.3** ([#175](https://github.com/ArtisanPack-UI/cms-framework/pull/175)) — the `artisanpack-ui/ai` foundation this release depends on requires PHP 8.3+, and running a Composer resolution across the two constraints is not possible on PHP 8.2. Hosts still on PHP 8.2 should stay on the 2.2.x line until they can upgrade the runtime. No API changes; the codebase itself does not yet require 8.3-only syntax.
- **Dependencies** — `artisanpack-ui/ai ^1.0` added as `suggest` + `require-dev`. Without it the AI Livewire component and REST routes stay unregistered and the framework boots normally.

## [2.2.3] - 2026-06-14

### Changed

- **Widened `laravel/tinker` constraint to allow v3** ([#167](https://github.com/ArtisanPack-UI/cms-framework/issues/167), [#168](https://github.com/ArtisanPack-UI/cms-framework/pull/168)) — `composer.json` `require.laravel/tinker` is now `^2.10.1|^3.0`. `laravel/tinker` v2 caps at `illuminate/* ^12.0`, so Laravel 13 consumer apps require `laravel/tinker ^3.0`; the previous pin blocked the upgrade even though the framework constraint already permitted Laravel 13. No code changes — `tinker` is not referenced in `src/` or `tests/`. Existing Laravel 12 users on `laravel/tinker ^2.10.1` are unaffected.

## [2.2.2] - 2026-06-09

### Added

- **Laravel 13 support** ([#161](https://github.com/ArtisanPack-UI/cms-framework/issues/161)) — Widened the `illuminate/support` and `laravel/framework` constraints to additionally allow `^13.0`. Existing users on Laravel 12 are unaffected; Laravel 13 is only selectable on PHP 8.3+ because Laravel 13's own `php` constraint enforces it, so no PHP-floor bump is required for users staying on Laravel 12.

## [2.2.1] - 2026-06-07

### Fixed

- **`update:perform` command `--version` flag collision** ([#162](https://github.com/ArtisanPack-UI/cms-framework/issues/162), [#163](https://github.com/ArtisanPack-UI/cms-framework/pull/163)) — Symfony Console reserves `--version`/`-V` as a global flag on every `Application`, so declaring `--version` on `PerformUpdateCommand` threw "An option named version already exists" at command-registration time, breaking every `php artisan ...` invocation in host apps as soon as the command was discoverable. Renamed the custom option to `--target-version` (and updated the corresponding `$this->option()` lookup). Added a regression test that verifies the command exposes `--target-version` and does not redeclare `--version` on its own definition.

## [2.2.0] - 2026-06-04

### Added

- **`Post::previous_post` and `Post::next_post` accessors** ([#154](https://github.com/ArtisanPack-UI/cms-framework/issues/154), [#157](https://github.com/ArtisanPack-UI/cms-framework/pull/157)) — published-adjacent post lookups so the `artisanpack-ui/visual-editor` `PostResolver` can stamp navigation block payloads. Lookup is ordered by `published_at`, with `id` as a deterministic tie-breaker when timestamps collide.
- **`HasRenderedBlockContent` concern on `Post` and `Page`** ([#155](https://github.com/ArtisanPack-UI/cms-framework/issues/155), [#156](https://github.com/ArtisanPack-UI/cms-framework/pull/156)) — exposes a `rendered_content` accessor so `PostResolver` in `artisanpack-ui/visual-editor` can stamp the `_resolvedContent` HTML payload directly from the model.
- **"Using with the visual editor" README section** ([#150](https://github.com/ArtisanPack-UI/cms-framework/issues/150), [#159](https://github.com/ArtisanPack-UI/cms-framework/pull/159)) — documents the opt-in bridge to the `artisanpack-ui/visual-editor` package, including the `block_content` column, `HasBlockContent` polyfill, `_resolved*` attribute flow, and the new `previous_post` / `next_post` / `rendered_content` accessors.

### Security

- **Hardened CI and release GitHub Actions workflows** ([#140](https://github.com/ArtisanPack-UI/cms-framework/issues/140), [#158](https://github.com/ArtisanPack-UI/cms-framework/pull/158)) — added least-privilege `permissions:` blocks to `.github/workflows/ci.yml` and `.github/workflows/release.yml` so workflow tokens no longer inherit broad write scopes by default.

## [2.1.0] - 2026-06-02

### Added

- **Comments submodule** for the Blog module ([#151](https://github.com/ArtisanPack-UI/cms-framework/pull/152)):
  - `Comment` model with `post_id`, `parent_id` threading, optional `user_id`, guest author fields (`author_name`, `author_email`, `author_url`), `content`, `status` (`pending` / `approved` / `spam` / `trash`), and `approved_at` timestamp; soft-deletable
  - `post_comments` migration with indexes for post, parent, status, and approved-at lookups
  - `Post` model gains `comments()` (approved-only, newest-first) and `commentsIncludingUnapproved()` relations, plus `comments_count` and `comments_url` accessors for visual-editor integration
  - REST endpoints under `/api/v1/comments` — public `GET` (`index`, `show`) returns the approved set, public `POST` creates a `pending` comment for guests, and `PUT` / `PATCH` / `DELETE` are auth-gated
  - `CommentRequest` form request with separate `store` / `update` rule sets and guest-vs-authenticated branching
  - `CommentResource` API resource shaping the response payload — mirrors the shape `CommentResolver` reads in `artisanpack-ui/visual-editor` to stamp `_resolved*` attributes on `artisanpack/comment-*` blocks
  - `CommentPolicy` with hookable abilities and a `comments.create.public` filter that defaults to allow
  - `CommentFactory` with `pending` / `approved` / `spam` / `trash` / `guest` / `forPost` / `replyTo` states
- **Public `POST /api/v1/comments` rate limiting** — `BlogServiceProvider::registerCommentsRateLimiter()` registers a `throttle:comments` named limiter that defaults to 10/min for guests (keyed by IP) and 60/min for authenticated users (keyed by user id). Both buckets are overridable via the `comments.rate-limit.guest` and `comments.rate-limit.authenticated` hooks filters.

### Security

- Public, unauthenticated `POST /api/v1/comments` is rate-limited by default (see Added) to keep guest commenters from bulk-inserting against `post_comments`.

## [2.0.0] - 2026-05-26

### Added

- **SiteEditor module** — entirely new module providing the back-end for a WordPress-style site editor experience:
  - **H0** — extended `theme.json` schema covering settings, styles, templates, parts, patterns, and `menus.locations`
  - **H1** — Templates + template-parts: `Template` and `TemplatePart` models, `TemplateResolver` / `TemplatePartResolver` merging theme-shipped files with DB user customizations, REST endpoints returning WP `/wp/v2/templates` and `/wp/v2/template-parts` shape, with `ap.visual-editor.templates` and `ap.visual-editor.template-parts` filter registration behind `class_exists` guards
  - **H2** — Patterns: `BlockPattern` model + `block_patterns` table, `PatternResolver` merging theme `.php` pattern files with user-source DB rows, REST endpoints under `/api/v1/blocks` (synced) and `/api/v1/block-patterns/patterns` (unsynced) returning WP-shape payloads, and `ap.visual-editor.patterns` filter registration behind a `class_exists` guard
  - `PatternFileParser` for theme-shipped pattern files with WP-style header doc-comments (`Title:`, `Slug:`, `Categories:`, `Description:`, `Block Types:`)
  - **H3** — Global styles: `GlobalStyles` model + `global_styles` table (singleton-per-theme), `GlobalStylesResolver` deep-merging `theme.json` defaults with theme-shipped variation files (`themes/{active}/styles/{slug}.json`) and DB user customization, `GlobalStylesEmitter` translating the resolved tree into `--wp--preset--*` custom properties + per-element CSS rules with content-hash cache invalidation on model save/delete, REST endpoints under `/api/v1/global-styles` (GET / PUT / DELETE / variations / css) returning WP `/wp/v2/global-styles` shape, `@cmsGlobalStyles` Blade directive for front-end head injection, and `ap.visual-editor.global-styles` singleton filter registration behind a `class_exists` guard
  - **H4** — Menus: `Menu`, `MenuItem`, and `MenuLocationAssignment` models with REST endpoints for menus, menu items, and theme-declared menu locations
- **Visual editor integration** — opt-in bridge to the optional `artisanpack-ui/visual-editor` package:
  - `block_content` JSON column on `posts` and `pages` tables for visual editor block trees, alongside the existing `content` longText column
  - `Post` and `Page` models adopt `ArtisanPackUI\VisualEditor\Concerns\HasBlockContent` with a polyfill stub so visual-editor remains an optional integration rather than a hard composer dependency
  - Auto-registration of `HasBlockContent` content types into the `ap.visual-editor.resources` filter, plus explicit registration of `Post` and `Page`
  - Registration of `visual_editor.*` permissions when visual-editor is installed
- **QueryRuntime service** (G4c-1) for resolving `core/query` block loops at render time
- **Site settings** — `site.*` settings registered with a WP-shape `/api/v1/settings/site` endpoint and a by-key save path that applies sanitizers and types
- **Themes lifecycle**:
  - `installFromZip()` API and `POST /v1/themes` upload route for installing themes from a ZIP archive
  - Strict manifest schema enforcement for theme installs (`WpThemeJsonValidator` + `WpThemeJsonValidationResult`)
  - Theme lifecycle hooks fired on install and activate
- **Updates**:
  - `GitLabUpdateSource` now supports release-asset download URLs in addition to tarball URLs

### Changed

- **RBAC migration (Wave 4)** — the Users module's bundled `Role`, `Permission`, `HasRolesAndPermissions`, and RBAC migrations have been removed and replaced by composition over the shared `artisanpack-ui/rbac` package. The cms-framework classes remain at the same paths and subclass the rbac base, so existing imports keep working. See [UPGRADE-RBAC.md](UPGRADE-RBAC.md) for migration details.
- Relaxed `artisanpack-ui/security` constraint to allow `^2.0` in addition to `^1.0.3`
- `RolesTableSeeder` and `PermissionsTableSeeder` are now opt-in; the default `DatabaseSeeder` only runs `SettingsTableSeeder`
- `roles` and `permissions` schemas now expose a `description` column (via the rbac base schema)
- New `role:` route middleware alias pairs with rbac's `permission:` alias

### Fixed

- `HasFeaturedImage` content-type trait rewritten to use the `featureables` pivot table (replacing the legacy direct column reference)
- Site editor REST routes now use `auth:sanctum` so REST clients can pass Bearer tokens
- Template parts now accept `navigation-overlay` as a valid area; the legacy `general` area was renamed to `uncategorized` to match WP core (Keystone #55)
- `UpdateInfo->sha256` is now populated from GitLab releases
- `Users` API routes (`users`, `roles`, `permissions`, `users/bulk`) are now gated behind the `auth` middleware (bug fix #129)
- `PermissionController` now calls `authorizeResource()` so it consults `PermissionPolicy` like every other controller in the framework (bug fix #127)
- `PermissionPolicy` methods now take `Authenticatable $user` (Laravel's standard signature) instead of `string|int $id` (bug fix #128)
- Bulk settings updates wrapped in a DB transaction
- Schema-guarded `ap.visual-editor.*` filter callbacks against missing tables
- `Post` and `Page` `$fillable` no longer include `block_content` (block trees flow through the visual-editor pipeline, not mass assignment)
- `PatternFileParser` regexes are now anchored

### Removed

- Bundled `Role` model, `Permission` model, `HasRolesAndPermissions` trait implementation, and the three RBAC migrations (`create_roles_table`, `create_permissions_table`, `create_user_role_permission_pivots`) — superseded by `artisanpack-ui/rbac`. The class/trait names are preserved as subclasses for backward compatibility.

### Migration / Upgrade

See [UPGRADE-RBAC.md](UPGRADE-RBAC.md) for the full upgrade guide. For most apps, the upgrade is `composer require artisanpack-ui/rbac:^0.1 && php artisan migrate`.

## [1.1.0] - 2026-04-06

### Added

- Bulk action API endpoints for posts, pages, and users (publish, unpublish, trash, restore, delete)
- OpenAPI specification generation for all API endpoints via Scramble
- `include` query parameter for on-demand relationship loading across API endpoints
- TypeScript type definitions for all API responses and models
- GitHub Actions CI workflow with lint and test jobs
- GitHub Actions release workflow with changelog-based release notes and Packagist integration
- Claude Code and Claude Code Review GitHub Actions workflows
- Auto-assign milestone workflow for new issues
- GitHub issue and pull request templates

### Changed

- Standardized JSON error response format across all API endpoints
- Extracted shared manifest parsing and discovery logic into `HasManifestParsing` trait
- Extracted shared content query and filter logic into reusable traits
- Converted `UpdateInfo::hasUpdate` from stored state to a computed method
- Replaced magic strings with enums: `ContentStatus`, `SettingType`, `FieldType`, `ColumnType`, `UpdateType`
- Hoisted policy and permission resolution before loops in bulk actions
- Added php-cs-fixer and ran code style formatting across the package
- Updated PHP version requirement to 8.4 for CI
- Migrated repository from GitLab to GitHub

### Fixed

- Sanitized error responses and normalized `published_at` in bulk actions
- Corrected LIKE wildcard escaping order and added ESCAPE clause
- Hardened URL parsing and cache serialization in update sources
- Fixed incorrect `@since 2.0.0` tags to `@since 1.0.0` in update test files

## [1.0.1] - 2026-01-09

### Added

- Support for the core ArtisanPack UI package in the service provider

### Changed

- Updated config settings names across controllers, models, policies, and tests for consistency

## [1.0.0] - 2026-01-02

### Added

- Configuration publishing for module-specific configs
  - Plugins config: `php artisan vendor:publish --tag=cms-plugins-config`
  - Themes config: `php artisan vendor:publish --tag=cms-themes-config`
  - Updates config: `php artisan vendor:publish --tag=cms-updates-config`

### Changed

- Moved developer documentation to `docs/developer/` directory
  - `SKIPPED_TESTS.md` → `docs/developer/Skipped-Tests.md`
  - `COVERAGE.md` → `docs/developer/Test-Coverage.md`
- Updated documentation to reflect PHP 8.2 and Laravel 12 requirements

### Fixed

- Replaced deprecated `mime_content_type()` with `finfo_file()` in PluginManager
- Fixed code style inconsistency in PluginManager exception handling
- Documented all skipped tests with explanations

### Removed

- `V1_RELEASE_CHECKLIST.md` - internal development tracking file

## [1.0.0-beta1] - 2024-12-21

### Added

- Core Updates Module with automatic update checking and management
  - GitHub, GitLab, and Custom JSON update source support
  - Version-specific update downloads with prerelease filtering
  - Automatic backup creation before updates with rollback capability
  - ZIP extraction with nested directory handling
  - Path validation and security checks in backup operations
  - Comprehensive error logging during update operations
  - Artisan commands: `check-for-update`, `perform-update`, `rollback-update`
- Plugin System foundation (experimental)
  - Plugin model with activation/deactivation tracking
  - Plugin manager for lifecycle management
  - Plugin update manager integration
  - Plugin validation and installation exceptions
- Theme System foundation (experimental)
  - Theme manager with theme discovery
  - Theme activation mechanism
  - JSON manifest validation
- Comprehensive input sanitization throughout codebase
  - Applied `sanitizeText()` and `sanitizeInt()` to all user inputs
  - Protected database queries from SQL injection
  - Validated and sanitized all controller inputs
- Type declarations for improved IDE support
  - Added `Builder` type hints to all Eloquent scope methods
  - Added return type declarations across models
  - Improved parameter type hints in managers and services
- Database seeders for default data
  - RolesTableSeeder (Admin, Editor, User roles)
  - PermissionsTableSeeder (content, user, settings, system permissions)
  - SettingsTableSeeder (site configuration defaults)
- Exception hierarchy with base `CMSFrameworkException`
  - ValidationException for validation errors
  - NotFoundException for missing resources
  - UnauthorizedException for authorization failures
  - All module exceptions now extend CMSFrameworkException
- Comprehensive documentation
  - API documentation structure (`docs/api/README.md`)
  - Route registry (`docs/routes.md`)
  - Relationship documentation (`docs/relationships.md`)
  - Helper functions reference (`docs/helpers.md`)
  - Exception handling guide (`docs/exceptions.md`)
  - Skipped tests documentation (now at `docs/developer/Skipped-Tests.md`)
- Improved `.gitattributes` for cleaner package distribution

### Changed

- **License changed from GPL-3.0-or-later to MIT** for better framework compatibility
- Standardized all `@since` annotations to 1.0.0 (removed premature 2.0.0 references)
- Configuration system improvements
  - Fixed publish tag from `artisanpack-package-config` to `cms-framework-config`
  - Corrected config validation to use `artisanpack.cms-framework.user_model`
  - Updated error messages to reflect actual file paths
- Code style improvements (74% PHPCS error reduction)
  - Fixed spacing issues in `declare(strict_types = 1)` statements
  - Fixed reference operator spacing in closures
  - Improved array alignment and formatting
  - Fixed Yoda conditions for comparison safety

### Fixed

- Configuration validation mismatch between publish tag, file path, and config key
- Test configuration (fixed config key from `cms-framework` to `artisanpack.cms-framework`)
- Progress bar in update command (removed misleading fake progress)
- `glob()` error handling for backup operations
- Path traversal security issues in backup ZIP creation
- JSON parsing errors in UpdateCheckerFactory
- Doctrine/DBAL deprecation warnings in migrations
- 706 code style violations (reduced from 941 to 235 errors)
- Input sanitization security vulnerabilities across multiple modules
- Unskipped 2 notification tests (role-based notification functionality now fully tested)

### Security

- Added comprehensive input sanitization using ArtisanPackUI Security package
  - Sanitized all user inputs before database operations
  - Protected against XSS attacks with proper output escaping
  - Validated file paths to prevent directory traversal
- Enhanced authorization with proper policy enforcement
- Improved error handling to prevent information disclosure

### Breaking Changes

- Configuration file publish tag changed to `cms-framework-config`
- Configuration structure now uses `artisanpack.cms-framework` instead of `cms-framework`
- All `@since 2.0.0` annotations changed to `@since 1.0.0`

### Known Limitations

- Plugin system is experimental - full lifecycle hooks not yet implemented
- Theme system is experimental - asset compilation and child themes pending
- 4 plugin-related tests remain skipped (documented in `docs/developer/Skipped-Tests.md`)
- Test coverage report requires Xdebug/PCOV (recommended for CI/CD)
- 235 PHPCS code style warnings remain (mostly spacing and false positives)

## [0.2.4] - 2025-09-02

### Added

- Enhanced user migration with password reset tokens and sessions table support
- Password reset tokens table with email primary key, token storage, and timestamp tracking
- Sessions table with comprehensive session management including user ID foreign key, IP address tracking, user agent storage, and activity indexing
- Table existence checks to prevent conflicts during migration execution

## [0.2.3] - 2025-09-02

### Removed

- Complete removal of all media library references from CMS framework core
- Removed media-related API routes and controller imports from api.php
- Removed MediaLibraryServiceProvider registration from CMSFrameworkServiceProvider
- Removed media library integration documentation
- Removed media-related admin page references from development guide
- Cleaned up media library package discovery ignoring in test configuration

### Changed

- Updated comprehensive CMS development guide to remove media library integration examples
- Restructured package ecosystem documentation to reflect media library as separate package

## [0.2.2] - 2025-09-02

### Added

- Complete media library decoupling and cleanup functionality

## [0.2.1] - 2025-09-02

### Added

- Comprehensive media library integration documentation
- Integration guide for external `artisanpack-ui/media-library` package
- Migration instructions for transitioning from integrated media system

### Changed

- Decoupled media library functionality from CMS framework core
- Updated service provider to remove media manager bindings
- Refactored CMS configuration schema to support external media library integration

### Removed

- Built-in media management system (models, controllers, policies, tests)
- Internal media database migrations and factories
- MediaManager, MediaServiceProvider, and related media classes
- Media-related HTTP controllers, requests, and resources
- All media-related unit and feature tests
- Legacy media documentation

### Breaking Changes

- Media functionality now requires separate `artisanpack-ui/media-library` package installation### Added
- Comprehensive CMS development guide and API documentation
- Analytics system with page views, user sessions, and performance tracking
- Search functionality with full-text search and analytics
- Internationalization support with multi-language capabilities
- Health monitoring and system diagnostics
- Application Performance Monitoring (APM) with metrics collection
- Docker deployment setup with multi-service containers
- Performance testing suite with benchmarking tools
- Security testing suite including penetration testing
- Console commands for content, user, and system management
- Configuration validation and documentation generation
- Caching implementation with Redis support
- Structured logging and audit trail capabilities
- Rate limiting middleware for API protection
- Input sanitization utilities

### Changed

- Updated content management system with enhanced controllers
- Improved user management with additional authentication features
- Enhanced media management with better error handling
- Refined plugin and theme management systems
- Updated all policy classes with improved authorization logic
- Modernized database models with better relationships
- Enhanced API routes with comprehensive endpoints

### Fixed

- Critical security vulnerabilities with input validation
- Error handling and exception management
- Cache implementation and performance issues
- Authorization policy implementations
- Database query optimization
- API response formatting and error codes
- User authentication and session management

### Removed

- Temporary documentation files and test scripts
- Legacy configuration files
- Unused development artifacts

### Security

- Implemented comprehensive input sanitization
- Added CSRF protection across all forms
- Enhanced rate limiting for API endpoints
- Improved authorization checks in all policies
- Added security testing suite for vulnerability detection
- Implemented audit logging for security events
- MediaManagerInterface moved to external package namespace
- Media-related routes and API endpoints moved to external package

## [0.2.0] - 2025-09-01

## [0.1.0] - 2025-07-13

### Added

- Initial test release
- Core CMS framework functionality
- Content management system
- User management with authentication
- Plugin and theme support
- Admin interface and dashboard widgets
- Settings management
- Media management integration
- Two-factor authentication
- Progressive Web App (PWA) support
- Audit logging capabilities