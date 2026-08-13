# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`UpdateCapability` — Gate abilities a host application can authorize the self-updater against** ([#266](https://github.com/ArtisanPack-UI/cms-framework/issues/266)) — `cms.updates.perform`, `cms.updates.rollback` and `cms.updates.view`, registered by `CoreServiceProvider` and **denying by default**. The framework still ships no HTTP or Livewire trigger for updates and every `update:*` command stays console-gated, but `ApplicationUpdateManager` is written for the HTTP case — `raiseExecutionLimits()` and `ignore_user_abort()` exist to support it, and `performUpdate()`'s docblock has always described the admin UI — while offering a host nothing to check. `performUpdate()` is by design a remote-code-execution channel: it overwrites PHP files and then runs `composer install`, which executes `post-install-cmd` scripts from the just-overwritten `composer.json`, so a host wiring that UI now has a name to authorize against, and the shipped default for that name is deny. Grant it by seeding an RBAC permission whose slug matches (`PermissionsTableSeeder` seeds all three, and the `admin` role receives every permission), or by defining the ability in the host's own `AppServiceProvider`. An ability the host has already defined is left alone. `config/updates.php` and `docs/self-updater.md` now both say plainly that the manager performs no authorization of its own and that an HTTP trigger must be gated, rate-limited and CSRF-protected. Deferred from the 2.7.1 deep review as SEC-10.
- **Themes have an update path** ([#278](https://github.com/ArtisanPack-UI/cms-framework/issues/278)) — a new `Modules\Themes\Managers\UpdateManager` gives themes parity with plugins: update check, in-place install over an existing theme, and rollback on failure. Themes were previously the one extension type that could only be updated by deleting the directory by hand and re-uploading — on a repo-less host, an SFTP session. The optional `update` key in `theme.json` is spelled identically to the plugin manifest key and resolves through the same `UpdateCheckerFactory` / `UpdateSourceInterface`, so GitHub Releases, GitLab and custom-JSON sources all come along and archives are streamed to disk and checksum-verified exactly as they are for plugins. `UpdateCheckerFactory` already handled `UpdateType::Theme` and already read a theme's version out of its manifest; nothing had ever called it with that type.
- **`GET /v1/themes/updates` and `POST /v1/themes/{slug}/update`** — mirroring `PluginsController::checkUpdates()` / `::update()`, behind the same `auth:sanctum` group, returning updates in the same payload shape so one admin component can render both extension types. Update failures surface as a `ValidationException` error bag rather than a bare JSON body, so host apps using Inertia get a working error bag ([#124](https://github.com/ArtisanPack-UI/cms-framework/issues/124)).
- **`ap.cmsFramework.theme.updating` / `ap.cmsFramework.theme.updated`** — theme update lifecycle hooks, joining the install and activation hooks from #123. `updating` receives `( $slug, $oldVersion, $newVersion )` and listeners may veto by throwing; `updated` receives `( $slug, $newVersion, $manifest )`.
- **`cms.themes.updateCacheTtl`, `cms.themes.backupPath`, `cms.themes.updateTokens`, `cms.themes.maxUpdateSize`** — update-check TTL, the directory an installed theme is archived to before it is replaced, per-slug access tokens for themes whose `update` source is a private repository, and a size ceiling for downloaded update archives. The last is deliberately separate from `maxUploadSize`: that one is an abuse control on the upload endpoint, and a theme shipping images and fonts clears its 10MB default easily — gating updates on it would leave such a theme permanently un-updatable, which is the exact situation this feature exists to fix.
- **`ThemeUpdateException`** — mirrors `PluginUpdateException`, with factories for a failed backup, a failed swap, a theme declaring no update source, and a verbatim failure reason so an integrity failure surfaces as what it is.
- **`ThemeManager::stageThemeFromZip()` / `::swapStagedTheme()`** — extract and fully validate an update archive in `themes/.updates/` before anything replaces the installed theme, then swap it into place with two `rename()` calls. This is what lets the *active* theme be updated without maintenance mode: the window during which its directory does not exist is a single syscall wide, and a bad archive is rejected before the swap is reached rather than after it has deleted a working theme. Rollback deletes the live directory outright before re-extracting the backup, so files a failed update added are removed rather than left orphaned alongside the restored ones ([#272](https://github.com/ArtisanPack-UI/cms-framework/issues/272)).
- **Plugins can update from GitHub Releases** ([#277](https://github.com/ArtisanPack-UI/cms-framework/issues/277)) — a new optional `update` key in `plugin.json` declares where self-updates come from, and `Modules\Plugins\Managers\UpdateManager` resolves it through the same `UpdateCheckerFactory` / `UpdateSourceInterface` the application updater uses. `{"update": {"github": "owner/repo"}}` is shorthand for the GitHub Releases source; `{"update": {"url": "https://..."}}` is handed to the source detector as-is, so GitLab repositories and custom JSON endpoints fall out of the same key. Both forms are https-only. Publishing a plugin update is now `git tag` plus a GitHub Release, with no separately-hosted JSON feed. `UpdateCheckerFactory` already accepted `UpdateType::Plugin` and already resolved a plugin's installed version out of the `plugins` table — nothing had ever called it with that type.
- **`cms.plugins.updateTokens`** — access tokens for plugins whose `update` source is a private repository, keyed by plugin slug. Deliberately per-slug rather than one global token: a plugin names its own update host in its own manifest, so a shared token would be handed to whatever host any installed plugin asks for. Public repositories need no entry.
- **`PluginUpdateException::updateFailed()`** — carries a reason string, so an integrity failure surfaces as what it is instead of being collapsed into `downloadFailed()`'s "Failed to download update for plugin".
- **A `cms` view namespace, and `cms::admin.layouts.app` inside it** ([#246](https://github.com/ArtisanPack-UI/cms-framework/issues/246)) — the layout Blade admin pages extend. `examples/hello-world-plugin/` has extended it since it was written and `grep -rn "loadViewsFrom.*'cms'"` returned nothing, so the reference plugin died on `No hint path defined for [cms]`. The layout is deliberately plain — the framework is front-end agnostic and ships no CSS build — and does three things: renders the menu from `apGetAdminMenu()`, yields a `content` section under a `title`, and exposes `styles` / `scripts` stacks. Hosts replace it with their own chrome via `php artisan vendor:publish --tag=cms-views`, which lands in `resources/views/vendor/cms/` — the path Laravel resolves ahead of the package's own, so a host swapping in real chrome requires no plugin to change the view it extends. Registered from `CMSFrameworkServiceProvider` rather than a module provider so the namespace exists for every consumer regardless of which modules are enabled.

### Changed

- **AI feature keys are read from the agent that owns them, not respelled per endpoint** ([#176](https://github.com/ArtisanPack-UI/cms-framework/issues/176)) — the five `cms.*` keys were hardcoded in ten places (`AiController` and `AiTools`, five methods each) and a further five times in `CMSFrameworkServiceProvider::aiFeatures()`, even though every agent already declares `public string $featureKey`. Renaming one meant editing six-plus files with nothing to catch a miss. `runAgent()` / `run()` now take the agent class plus its input and resolve the key themselves, and `aiFeatures()` is derived from a new `public const AI_AGENTS` rather than respelling the map — so a key is spelled in exactly two places, the agent that owns it and `AI_FEATURE_KEYS`, with a test asserting the two agree. Purely internal: the JSON envelopes, the `ap-cms-ai:{key}:{status}` event names, and the `aiFeatures()` return shape and key ordering are all unchanged.
- **Agent metadata is read off the declared class rather than a container-resolved instance** ([#176](https://github.com/ArtisanPack-UI/cms-framework/issues/176)) — `docs/AI-Features.md` invites hosts to bind a subclass over an agent, which makes construction a genuine throw site and a subclass's `$featureKey` a genuine source of divergence. Resolving metadata through the container would have meant two distinct faults: a failed binding escaping the very handler that builds the error envelope, so an HTTP caller loses its JSON body and a Livewire caller never receives a status event at all; and `aiFeatures()` keyed by the override while `'agent'` still named the original class and `AI_FEATURE_KEYS` still listed the original key, leaving three sources disagreeing. The new `Ai\Support\AgentMeta` reflects declared property defaults instead — it cannot throw, and it cannot disagree with the constant. Agent construction stays inside the wrappers' `try` block, and new tests cover all four exception categories on both trigger surfaces plus the construction-failure path.
- **`cms.themes.default` no longer names a specific consumer's theme** ([#125](https://github.com/ArtisanPack-UI/cms-framework/issues/125)) — **breaking for anyone relying on the implicit fallback.** The shipped config defaulted to `'digital-shopfront'`, and `ThemeManager::getActiveTheme()`, `ThemeManager::markActiveTheme()` and `ThemesServiceProvider::boot()` each repeated that literal as the third argument to their `config()` call, so removing or nulling the config key changed nothing — the slug was hard-coded in three more places than the one you could edit. The framework bundles no themes, so a consumer shipping a differently-named default got a silent lookup for a theme that does not exist: `getActiveTheme()` returning `null` for a reason that had nothing to do with their install. The config key is now `env( 'CMS_DEFAULT_THEME' )` and all three literals are gone. Unconfigured, "no theme is active yet" is an explicit state rather than a missed lookup — `getActiveTheme()` returns `null`, `registerThemeViewPath()` early-returns and leaves the host's view paths alone, `markActiveTheme()` flags every discovered theme inactive, and the site-editor resolvers take their existing theme-less paths. The registered `themes.activeTheme` setting default tracks the same config value, because `SettingsManager::getSetting()` falls through to it once the caller's own default is null — leaving a literal there would have reinstated the fallback the config removed. **To keep the old behavior, set `CMS_DEFAULT_THEME=digital-shopfront`** (or activate a theme explicitly, which consumers that call `activateTheme()` already do — for them nothing changes, since a stored `themes.activeTheme` has always won over the default). Same family of fix as #120.
- **Themes and Plugins action endpoints fail as a `ValidationException`, so Inertia's error bag works** ([#124](https://github.com/ArtisanPack-UI/cms-framework/issues/124)) — every failing action on `POST /v1/themes`, `POST /v1/themes/{slug}/activate`, `POST /api/v1/plugins/install`, and the plugin `activate` / `deactivate` / `update` / `destroy` endpoints now returns `422` carrying Laravel's `{"message": "...", "errors": {"field": ["..."]}}` shape, keyed by the field the failure belongs to — `theme_zip` / `plugin_zip` for the uploads, `slug` for the actions taken against an installed extension. The previous bodies were `message`-only, which Inertia lands as a generic exception rather than populating `usePage().props.errors` and `useForm().errors`, so an admin UI could not render field-level messages without forking the controllers. Pure-API consumers gain a parseable `errors` object and keep the `message` they already read. New `UploadThemeRequest` and `InstallPluginRequest` form requests front-load the upload rules — presence, ZIP MIME type, and the configured size ceiling — so those failures arrive in the same shape as the manager-level rejections they sit in front of. `IncompatiblePluginException` deliberately keeps its `409` and its structured `code` / `required_version` / `host_version` payload, which a flat error bag cannot express. The `422` covers request validation and the managers' own named rejections; an *unexpected* fault is reported and returns `500` on every Themes endpoint rather than being dressed up as a field error — `ThemesController::update()` was converting those into a `422` since 2.8.0 and now matches `upload()` and `activate()`. The Plugins endpoints raise the same generic `422` they always have on an unexpected fault, but no longer echo the raw exception message into it: the detail goes to `report()` instead of to the client, so a database, filesystem, or host-hook failure can no longer disclose internal paths through an endpoint reachable by any authenticated user.
- **`POST /api/v1/plugins/{slug}/update` distinguishes "already up to date" from "updated"** ([#124](https://github.com/ArtisanPack-UI/cms-framework/issues/124)) — `UpdateManager::updatePlugin()` returns `false` when no update is available, which the controller discarded, reporting `"Plugin updated successfully"` for a no-op. The response now carries an `updated` boolean alongside the matching message, the same shape `POST /v1/themes/{slug}/update` already returned.
- **`POST /v1/themes/{slug}/activate` answers an unknown slug with `422` instead of `404`** ([#124](https://github.com/ArtisanPack-UI/cms-framework/issues/124)) — **breaking for consumers branching on the status.** The slug is form input on this endpoint, not a resource path: an admin activating a theme from a list that has drifted out of sync with the themes directory should see a field error on the control they used, not a hard error page. `GET /v1/themes/{slug}` is unaffected and still answers `404`, because there the slug *is* the resource path.
- **The `update` manifest key is validated by one shared implementation** ([#278](https://github.com/ArtisanPack-UI/cms-framework/issues/278)) — the rules moved from `PluginManager` into `HasManifestParsing`, which both managers already use, so plugins and themes cannot drift on a key they deliberately spell identically. Each manager still raises its own exception type. No behavior change for plugins; the messages are byte-identical.
- **Source-backed plugin updates stream to disk and are checksum-verified** ([#277](https://github.com/ArtisanPack-UI/cms-framework/issues/277)) — a plugin using the new `update` key downloads through its update source, which streams the archive via `StreamsDownloadsToDisk` rather than buffering the whole ZIP in memory (the OOM shape fixed for the core updater in #214 / #216 / #219) and enforces https across the initial request and every redirect. The archive is then verified against the SHA-256 the release advertises — a `{asset}.zip.sha256` sidecar, or a `SHA-256:` line in the release body — honoring `cms.updates.verify_checksum` and `cms.updates.allow_unverified_updates` exactly as `ApplicationUpdateManager` does. With the shipped defaults a release publishing neither is refused; see #271 for the publishing-side workflow change that attaches the sidecar.
- **Plugin update checks normalize on `UpdateInfo` internally**, which is what makes `sha256` reachable at all. The payload at `GET /api/v1/plugins/updates` is unchanged: source-backed results are flattened onto the same `version` / `download_url` keys the endpoint has always returned, with `sha256`, `changelog`, `release_date`, `file_size` and `metadata` added alongside. Plugins declaring only the legacy `update_url` keep the existing custom-JSON behavior verbatim — raw feed payload as the response body, no checksum enforcement.
- **`UpdateChecker` no longer evicts plugin and theme cache entries using `app.version`** — the staleness heuristic added in 2.5.3 compares a cached `currentVersion` against the host application's version, which for a plugin is a different number by construction. Every plugin cache entry was therefore stale on its first read, so the cache was written and never served. The heuristic now applies only to `UpdateType::Application`.

### Deprecated

### Removed

### Security

- **The admin menu's URL allow-list is enforced where it can no longer be walked around** ([#246](https://github.com/ArtisanPack-UI/cms-framework/issues/246)) — three gaps in `sanitizeExternalUrl()`'s coverage, all latent while every consumer rendered the menu itself and all reachable the moment this release ships `cms::admin.partials.menu`, which puts `url` into an `<a href>` inside the package. Blade's escaping does not stop a `javascript:` scheme, so each one executed attacker JS in an authenticated admin session on click.
  - **The filter ran after sanitization.** `decorateItem()` checks a pre-set `url`, but decoration happens *before* `ap.cmsFramework.admin.menu`, so a row a subscriber injects had never been checked — and the documented injection shape, a bare array with a `url` and no `route`, also takes `decorateItem()`'s early return. `getAdminMenu()` now runs the post-filter tree through a recursive `sanitizeMenuUrls()` pass covering `items` and `subItems` as well as top-level rows. The pass is idempotent, so URLs already sanitized during decoration are unaffected, and `registerNavEntry()`'s ingress check is unchanged.
  - **The scheme regex could be stepped over.** `trim()` only touches the ends, so `java&#9;script:alert(1)` failed `^[a-zA-Z][a-zA-Z0-9+.\-]*:`, fell through the allow-list as a "relative reference", and survived `htmlspecialchars()` — which leaves tabs, newlines and C0 controls alone — to reach the browser, whose URL parser strips exactly those characters before resolving the scheme. Same for a leading `\x01`. The value is now normalized the way the URL parser normalizes it (tab/CR/LF removed anywhere, leading and trailing C0 and spaces stripped) *before* the scheme check, and the normalized form is what is returned, so the checked value and the rendered value cannot diverge.
  - **A non-string `url` skipped both layers.** The guard was `is_string()`, and Laravel's `e()` returns an `Htmlable` — `HtmlString` among them — unescaped, so `'url' => new HtmlString( 'javascript:alert(1)' )` missed the allow-list *and* the escaping. Values now go through `NavUrl::sanitizeValue()`, which resolves a `Stringable` before checking it; anything not stringable becomes `'#'`.

  Relatedly, `PluginServiceProvider::normalizeNavUrl()` now takes `mixed` rather than `string`. `registerNavEntry()` receives a plugin-supplied array and the file declares `strict_types=1`, so a `url` that was anything but a string — an `HtmlString`, an int, an accidental array — raised a TypeError at the parameter boundary inside the plugin's `boot()`: the same whole-application failure mode as the route-action bug above, from a plugin typo. Those values now take the documented `'#'` fallback. **Widening the parameter is a signature change on a `protected` method of an abstract base class**, so a plugin that overrides `normalizeNavUrl( string $url )` — no reason to, it is an internal sanitizer, but the method is part of the extension surface — must widen its own parameter to match.

  The rules themselves moved into a new `Modules\Admin\Support\NavUrl`, because they existed twice — once on the render path in `AdminMenuManager::sanitizeExternalUrl()`, once on the registry ingress path in `PluginServiceProvider::normalizeNavUrl()` — with the same regex and the same scheme list. That duplication is precisely how one copy came to be hardened while the other kept writing the un-normalized value into `PluginRegistry`, which is a public surface in its own right: `PluginRegistry::navEntries()` and `Plugin::getNavEntriesAttribute()` are read directly, not only through `getAdminMenu()`. Both now delegate, so the next change to the allow-list is one edit. Behavior for every URL form the allow-list already accepted — `http(s)`, `mailto:`, `tel:`, `/`-relative, `#`-fragment, and scheme-less relative paths — is unchanged, and pinned by a dataset.

- **Plugin update sources are re-checked for https at the point of use**, not only during install-time manifest validation. `UpdateManager::updatePlugin()` refreshes a plugin's `meta` straight from the manifest inside the downloaded ZIP and never re-runs `PluginManager::validateManifest()`, so an update can seat an `update` value that never passed validation. A plaintext source is not cosmetic there: `CustomJsonUpdateSource` would fetch the update metadata over http, and a network attacker rewriting that response chooses both the download URL *and* the `sha256` it is checked against — digest and archive come from the same document, so verification would confirm the attacker's own archive, which is then extracted into `plugins/` and executed as PHP. A non-https source is now ignored with a warning instead of being fetched.

### Fixed

- **A plugin admin page registered with a Blade `view` renders instead of throwing `Invalid route action`** ([#246](https://github.com/ArtisanPack-UI/cms-framework/issues/246)) — `PluginServiceProvider::registerAdminPage()` passed `$config['view']` straight through as the page's `action`, and `AdminPageManager::registerRoutes()` hands that to `Route::get( $slug, $action )`. Laravel accepts a closure, a controller class, a `Class@method` string or an array there — a Blade view name is none of them, so it was read as an invokable controller class and rejected. The flavor had never worked, and the blast radius was the whole application rather than the plugin's own page: `registerRoutes()` runs inside `AdminServiceProvider`'s `$this->app->booted()` callback, so the exception fired before routing and 500'd *every* URL, admin or not, for any host with such a plugin active — the bundled `hello-world` example among them. The view name is now wrapped in a closure that renders it, with the route's own parameters passed through as view data so a page registered at `reports/{id}` can read `$id`. Closure actions stay `route:cache`-safe — Laravel serializes them via `SerializableClosure`. `docs/admin/Menu-and-Pages.md` described `action` as accepting a "view response", which is the same misreading one layer down: the raw `apAddAdminPage()` helper does pass `action` to `Route::get()` unchanged, so a caller using it directly must wrap a view themselves, and the doc now says so and shows the wrap. **A `component` is still passed through verbatim and therefore still throws** — how a host mounts a federated page is a design question this fix deliberately does not answer, so `docs/plugin-authoring.md` and the example plugin's README now carry an explicit warning not to use that flavor yet, where they previously advertised it as working. Tracked as [#296](https://github.com/ArtisanPack-UI/cms-framework/issues/296), along with the neither-`view`-nor-`component` case, which throws the same way.

- **The bundled admin layout honors `showInMenu => false`** ([#246](https://github.com/ArtisanPack-UI/cms-framework/issues/246)) — `addSubPage()` has always stored the flag and `docs/admin/Menu-and-Pages.md` has always documented it as "routed but hidden from menu", but nothing in `getAdminMenu()` ever read it back; it was live only to the extent that each consumer's own renderer checked it. Shipping `cms::admin.partials.menu` in this release would have made the framework the thing that ignores it, putting a link to `posts/edit` in the sidebar. The renderer filters on it. `getAdminMenu()`'s payload is deliberately unchanged — hosts reading the flag themselves keep receiving those rows.

- **`cms-framework-config` is a real publish tag, so the documented install step publishes something** ([#290](https://github.com/ArtisanPack-UI/cms-framework/issues/290)) — `README.md` has always told consumers to run `php artisan vendor:publish --tag=cms-framework-config`, and that tag was registered nowhere in `src/`. `vendor:publish` exits `0` on a tag matching nothing, so following the README produced an empty `config/`, no framework config files, and no indication anything had gone wrong — the failure was indistinguishable from success. Each of the four config files is now tagged twice: under its existing module-only tag (`artisanpack-package-config`, `cms-themes-config`, `cms-plugins-config`, `cms-updates-config`), and under the umbrella `cms-framework-config` the README documents, so the one-liner publishes all four and a consumer who wants one module's config in isolation keeps that option. The umbrella tag is also the narrowest way to ask for *this package's* config specifically: `artisanpack-package-config` is an ecosystem-wide convention shared by every ArtisanPack UI package, so in a host with several of them installed it publishes far more than the CMS framework's file. No existing tag changed, so nothing that already worked breaks. `docs/Installation-Guide.md` and `docs/Configuration.md` were separately instructing `--tag="config"`, which was also never registered, and both named the main config file `config/cms-framework.php` when it publishes to `config/artisanpack/cms-framework.php`; both are corrected. `docs/themes.md` located the theme settings at `config/cms.php` under a `themes` key and showed the file wrapped in that key — the published path is `config/cms/themes.php` and it returns the settings array directly, since the module merges it under `cms.themes` — which is what made #125's new "edit the published config directly" guidance unreachable in two different ways at once. A new `ConfigPublishTagsTest` asserts every source file is reachable through both its tags and lands at the documented destination, and that every `--tag=` the README names is actually registered, so this particular drift fails at test time rather than silently at install time.

- **`comments.form.action` is aliased to `ap.cmsFramework.comments.form.action`, completing a wave 4b rename that never landed** ([#245](https://github.com/ArtisanPack-UI/cms-framework/issues/245)) — the 2.5.0 wave 4b table (#194) listed this filter as renamed, and the CHANGELOG entry below says so, but no entry was ever added to `HookAliases::wave4bLifecycle()`. The rename was therefore documentation-only: the new name resolved to nothing, and a host app that renamed its subscriber on the strength of the CHANGELOG would have silently stopped receiving the filter. Downstream apps had to stay on the un-prefixed name. The alias now exists and resolves in both directions, so a host may subscribe under either name. **This filter's only fire site is in `artisanpack-ui/visual-editor`** (the `post-comments-form` block), not in this package — it is namespaced here because comments are this package's domain and the filter's default value is this package's `POST /api/v1/comments` endpoint, the same emitter-is-not-owner split as `ap.rbac.roleRegistered`. visual-editor declares the identical alias on its own side, so the old name keeps resolving in installs that do not have the CMS Framework; because the alias is registered on both sides, a host may rename its subscriber to `ap.cmsFramework.comments.form.action` immediately, without waiting for the visual-editor release that switches the fire site over.

- **The release workflow publishes a release archive and a SHA-256 sidecar, so a GitHub-sourced host can self-update with the shipped defaults** ([#271](https://github.com/ArtisanPack-UI/cms-framework/issues/271)) — 2.7.1 taught `GitHubUpdateSource` to discover a checksum, but `release.yml` attached no assets for it to discover, so releases carried only GitHub's auto-generated zipball. The zipball has no asset name, `extractChecksumFromSidecar()` refuses to correlate a digest against an unnamed target, and the CHANGELOG-derived notes carry no `SHA-256:` marker — so `sha256` stayed null and, with `verify_checksum = true` and `allow_unverified_updates = false`, every GitHub-sourced update was refused. The `release` job now builds `cms-framework-{version}.zip` with `git archive` — whose bytes are stable for a given tree, unlike the zipball's — writes `cms-framework-{version}.zip.sha256` beside it, and attaches both. The sidecar name is the exact string the source correlates on, so a new `ReleaseWorkflowTest` asserts the two sides still agree rather than leaving the next rename to fail closed at tag time. The job also refuses to publish an archive missing `composer.lock`, which the updater installs from rather than re-resolving ([#255](https://github.com/ArtisanPack-UI/cms-framework/issues/255)).

- **A plugin update that fails while taking its backup no longer trips an undefined-variable error** — `updatePlugin()` referenced `$backupPath` from its `catch` blocks, but the variable is assigned by the first statement of the `try`. When `backupPlugin()` itself threw, the recovery path died on the undefined variable instead of reporting the backup failure. `restoreFromBackup()` now takes a nullable path and returns early when there is no backup to restore from, rather than deleting a working install it has nothing to replace.

## [2.7.2] - 2026-08-02

### Added

- **`ThemeFileBlockParser`** (`Modules\SiteEditor\Support`) — the seam that turns a block theme's raw `.html` markup into the block tree `ResolvedEntity::$blocks` carries. Prefers visual-editor's `BlockMarkupHydrator` (resolved out of the container by name, so visual-editor stays a non-dependency) and falls back to `BlockMarkupParser`'s WP `parse_blocks()` output when it is absent. A hydrator that throws is logged and degrades to the fallback rather than taking the template down.

### Changed

### Deprecated

### Removed

### Fixed

- **Theme-file templates and parts now resolve to a populated block tree** ([#274](https://github.com/ArtisanPack-UI/cms-framework/issues/274)) — `TemplateResolver` and `TemplatePartResolver` put a theme file's markup in `ResolvedEntity::$raw` and left `$blocks` empty, but every consumer in the stack reads `$blocks` and ignores `$raw`. On a fresh activation the `templates` table is empty, so every template and part in a block theme resolved to `[]` and the front end rendered no theme markup at all — visual-editor's `TemplatePartInliner` inlined empty `<header>` wrappers, and the public render pipeline produced nothing. Both resolvers now parse the file on resolve via the new `ThemeFileBlockParser`, making `$blocks` authoritative regardless of source. With visual-editor ≥ 1.5.5 installed the tree comes back in the same editor shape DB rows store, with block text recovered from the saved HTML ([visual-editor#688](https://github.com/ArtisanPack-UI/visual-editor/issues/688)); standalone, it comes back in the WP `parse_blocks()` shape. Theme files are parsed on every resolve — there is no parse cache, matching `PatternResolver`'s existing behavior. `GET /api/v1/templates/{slug}` for a theme-file slug consequently returns a populated `content.blocks` where it previously returned `[]`.

## [2.7.1] - 2026-08-01

### Security

- **Updated `guzzlehttp/guzzle` to `7.15.2`** (from `7.14.0`), clearing four advisories: host-only cookie scope not preserved, unbounded response cookies risking denial of service, and `Proxy-Authorization` headers being sent to origin servers. Guzzle sits directly on the update path — `MetadataClient` fetches release metadata and checksum sidecars through it, and `StreamsDownloadsToDisk` fetches the release archive itself — so the proxy-authorization advisory is the one that matters most for hosts behind a corporate proxy. `guzzlehttp/psr7` moved `2.12.4` → `2.13.0` as a required dependency of that upgrade. No other package changed, and `composer.json` is untouched, so the lock's `content-hash` is unaffected.

### Added

- **`cms.updates.allow_insecure_transport` config key** — release archives are now downloaded over `https` only. `download_url` arrives from the update source's own metadata (for the custom-JSON source, straight out of a remote document) and was previously passed to the downloader with no scheme validation at all. Defaults to `false`; set `CMS_UPDATES_ALLOW_INSECURE_TRANSPORT=true` for an air-gapped mirror that genuinely cannot serve https.
- **`update:perform --allow-downgrade`** — see the downgrade-protection entry under Fixed.
- **`update:rollback --allow-external`** — see the rollback-provenance entry under Fixed.
- **`CustomFieldManager::RESERVED_FIELD_KEYS`** and **`HasCustomFields::flushCustomFieldsRealColumnsCache()`** — see the custom-field entries under Fixed.

- **`php artisan update:status`** ([#256](https://github.com/ArtisanPack-UI/cms-framework/issues/256)) — reports the most recent `performUpdate()` run from a persisted step marker: the step it reached, the versions involved, the recorded error, and — for a run that died mid-flight — the outstanding steps with the command to run for each. Exits non-zero when the last run failed or was interrupted, so it composes with health checks. `--json` emits the raw record for an admin UI; `--clear` discards it after reporting. Host applications can read the same record programmatically via the new `ApplicationUpdateManager::updateState()` / `clearUpdateState()`.
- **`cms.updates.verify_composer_lock_sync` config key** ([#255](https://github.com/ArtisanPack-UI/cms-framework/issues/255)) — before invoking composer, the updater compares the on-disk `composer.lock`'s `content-hash` against a hash computed from `composer.json` using composer's own algorithm, and aborts with `UpdateException::composerFilesOutOfSync` naming the real cause when they diverge. Composer's own diagnosis of that state — "This usually happens when composer files are incorrectly merged or the composer.json file is manually edited" — sends the operator hunting for a merge conflict or a hand-edit that never happened. The check fails *open*: a missing `composer.json`, a missing or unparseable `composer.lock`, or a lock without a `content-hash` is left for composer to adjudicate, so a false alarm can never block an update that would otherwise install cleanly. Defaults to `true`; set `CMS_UPDATES_VERIFY_LOCK_SYNC=false` to skip it.
- **`cms.updates.state_path` and `cms.updates.lift_maintenance_on_interrupt` config keys** ([#256](https://github.com/ArtisanPack-UI/cms-framework/issues/256)) — where the step marker is written (relative paths resolve against `storage_path()`), and whether the new shutdown guard lifts maintenance mode when an update dies mid-flight. The latter defaults to `true`; set `CMS_UPDATES_LIFT_MAINTENANCE_ON_INTERRUPT=false` to fail closed and keep the site down until an operator has verified a possibly half-applied install.

### Changed

### Deprecated

### Removed

### Fixed

- **`HasCustomFields::applyCustomFieldValues()` could write Eloquent's own properties, giving an untrusted custom-field payload an arbitrary-table write primitive** — the guard added earlier in this release assigned via `$this->{$key} = $value` from inside the trait, which is compiled into the model class. In class scope that expression resolves Eloquent's *declared protected properties* directly and never reaches `__set()`. A payload of `{"table":"users","exists":true,"attributes":{"id":1,"password":"…"}}` therefore repointed the model at another table and issued an `UPDATE` against it on `save()`. The same assignment from the manager classes, where it lived before, landed harmlessly in the attribute bag — moving it into class scope is what created the hole. Three further bypasses of the same guard are closed with it:

  - **Case-variant keys.** `Schema::getColumnListing()` returns canonical-case names and the comparison was strict, but MySQL and SQLite resolve identifiers case-insensitively — so `custom_fields[AUTHOR_ID]` was "not a real column" to the guard and *was* the real column to the database. Column, cast, and attribute comparisons are now case-folded on both sides.
  - **JSON-path keys.** `metadata->x` matched none of the reserved-key branches, and `Model::setAttribute()` routes any key containing `->` to `fillJsonAttribute()` — writing the real column, including the very `metadata` store the guard exists to protect. On a non-JSON column, `title->x` silently replaced the column with a JSON string. Keys containing `->` are now rejected outright.
  - **Unregistered keys reaching `save()`.** A key naming neither a reserved attribute nor a registered field became an attribute with no column, so `save()` raised a `QueryException` inside the managers' transaction and rolled the whole write back — `custom_fields[x]=1` was a one-request DoS on the editor save path.

  `applyCustomFieldValues()` is now an **allowlist**: a key is applied only when it names a custom field registered for the model's content type, and values are assigned through `setAttribute()`, never a dynamic property write. Dropped keys are logged (`Log::warning`, deduplicated per instance per key) rather than vanishing without a trace. Behaviour for legitimate payloads is unchanged.

- **A custom field could be created with a key that already named a protected column, permanently converting that column into a payload-writable field** — `addColumnToTable()` silently returns when the column already exists, and validation constrained only the key's character class and its uniqueness *within* `custom_fields`. So a user with `customFields.manage` creating a field named `author_id` caused the existing column to be *adopted* rather than created, and from then on any content editor's `custom_fields[author_id]` wrote it through the legitimate DB-persisted-field exemption — a quiet escalation from "manage custom fields" to "reassign authorship or change the status of any post". `CustomFieldManager::createField()` now throws `InvalidArgumentException`, and `CustomFieldRequest` adds a matching field-level validation error, when the key already names a column on a target content type's table or appears in the new `CustomFieldManager::RESERVED_FIELD_KEYS` list.

- **An unknown attribute access ran one query per model instance** — the custom-field list was memoized on the model *instance*, so `foreach ( $posts as $post ) { $post->plugin_note; }` over 50 posts issued 50 queries, and `bootHasCustomFields()` flushes that memo on every `saved`/`deleted`, so a bulk import re-queried on every iteration. The memo now lives on the container-bound `CustomFieldManager` singleton, keyed by content type and invalidated by `createField()` / `updateField()` / `deleteField()` / `registerField()`.

- **A column-storage field's `default_value` was substituted whenever the value read null** — so a value an editor had deliberately cleared read back as the default, and a read-then-save round trip resurrected it. The default now applies only when the attribute is genuinely absent, matching what the metadata branch already did via `array_key_exists()`.

- **Dropped custom-field payload keys and inert field registrations produced no signal at all** — a plugin author saw only "my field doesn't save", and an operator had no way to notice someone probing `custom_fields[author_id]`. Dropped keys now emit a deduplicated `Log::warning`, and registering a field under a reserved key warns at registration time.

- **Several smaller updater defects** — `fclose()` failures went unchecked alongside the short-write fix; a ZIP entry whose `statIndex()` failed was skipped silently, leaving a stale file behind while the update reported success; entry *content* was fetched by name while metadata was read by index, so duplicate entry names paired the wrong content with the wrong permissions; archive-supplied permissions are now clamped with `& ~0022` so a `0777` `.php` file cannot land under the docroot writable by a neighbouring tenant; the state store's temp file used a predictable `getmypid()`-derived name written through `File::put()` (which follows symlinks) and is now randomized, created with `x`, and `chmod 0600`; backup directories are created `0700` rather than `0755`; `UpdateStateStore::merge()` no longer silently discards the whole record when a read fails; `state_path` now recognises Windows absolute paths; `UpdateChecker` caches a primitive array rather than a serialized `UpdateInfo`, removing an object-injection sink on a shared cache; `ApplicationUpdateManager` is bound as a singleton so the shutdown guard is registered once rather than leaking a closure per resolution; ZIP entry names are stripped of control characters before reaching log context; `UpdateStep::recoveryCommand()` now reflects the configured composer command instead of a bare `composer install` that cannot resolve on a Herd/FPM host; and operator guidance that hardcoded `storage/backups/application/` now names the configured `backup_path`.

- **`update:status` recovery advice was wrong for the earliest steps** — a death at steps 1-2 leaves the application tree untouched, but the command told the operator to restore the pre-update snapshot: at step 1 no snapshot exists, and at step 2 a death mid-backup can leave a *truncated* `backup-*.zip` that this advice invited them to extract over a healthy tree. Those steps now say to run `php artisan up` and retry, and warn about a possible partial archive.

- **The cached real-column listing was never invalidated, so schema changes went unnoticed for the life of the process** — nothing in `CustomFieldManager` flushed the cache it invalidates by mutating the table. After a field's column was removed, every payload key naming it stayed silently dropped, and a field re-registered under that key as `metadata` storage stayed dead; under Octane or a long-lived queue worker "the process" spans many requests. The listing now lives in the new table-keyed `CustomFieldColumnCache`, flushed by `addColumnToTable()` and `removeColumnFromTable()` and exposed as `HasCustomFields::flushCustomFieldsRealColumnsCache()` for hosts that alter these tables themselves. It previously lived in a `protected static` property on the trait, which PHP duplicates into every using class — so nothing outside the model could have flushed it.

- **Nothing prevented two updates running at once** — a double-clicked admin button, or the scheduled `auto_update` racing an operator's `update:perform`. Both would put the site down, both would extract over `base_path()`, and both would run `composer install` in the same directory; the interleaved writes produce a tree that no rollback repairs, because the second run's backup snapshots the first run's half-extracted state. Run A's step-10 `up` also undid run B's step-1 `down`, serving traffic mid-extraction. `performUpdate()` now takes an exclusive `flock` on a sentinel beside the state file (not a cache lock — step 8 runs `cache:clear`) and additionally refuses to start when the persisted record says `in_progress` and the PID it recorded is still alive. A stale marker from a `kill -9`'d run does not wedge the updater.

- **`update:status` reported a *failed* rollback as a successful one** — `UpdateRunStatus::Failed`'s label asserted `Failed (rolled back)` unconditionally, and the state file was never updated when the rollback itself threw. So the operator facing the single most dangerous state this updater can produce was told the tree had been restored. The same label lied when `backup_enabled = false` and when the failure landed before `createBackup()` ran, in which case no rollback was attempted at all. The label is now plain `Failed`, and a separate `rolled_back` field records `true` / `false` / `null` (not attempted), which `update:status` renders explicitly.

- **The shutdown guard overwrote a terminal `Failed` record with `Interrupted`, destroying the real error** — `performUpdate()`'s catch marks `Failed` with the actual message and then calls `handleUpdateFailure()`; if lifting maintenance mode threw in there, the active flag stayed set and the guard fired at shutdown even though the process never died and the error had been caught and handled. It re-stamped the record with a generic "the update process terminated before completing" and printed a resume checklist for a tree that had already been rolled back. The guard now leaves an already-terminal record alone — it still lifts maintenance mode, it just no longer rewrites history.

- **A failure at steps 8-10 rolled back a fully-applied update while leaving migrations applied** — a `cache:clear` hiccup at step 8 restored the pre-update snapshot, leaving old code against a new schema, and reported it as a clean rollback. Rollback is now gated on the failure having happened at or before `Migrations`; later failures log that the snapshot was deliberately *not* restored and point at `update:status`, which already prints the commands to finish forward.

- **The checksum was fetched for the latest release but applied to whatever version was pinned** — with `--target-version=1.2.3` while latest is `2.0.0`, the 1.2.3 archive was compared against 2.0.0's digest. That failed closed on its own, but combined with `allow_unverified_updates=true` the pinned path installed an arbitrary older archive with no integrity check at all. The digest is now resolved for the target version via the new `checksumForVersion()` on the GitLab and GitHub sources.

- **There was no downgrade protection anywhere** — `hasUpdate()` is only ever consulted against *latest*, never against the requested target, so `performUpdate('1.0.0')` on a 2.7.1 install was accepted and installed a known-vulnerable older release; migrations are not reversed, so the older code then ran against the newer schema. A target that is not newer than the installed version is now refused unless `--allow-downgrade` is passed.

- **The GitHub source never populated `sha256`, so every GitHub-sourced update failed** — with the shipped defaults (`verify_checksum = true`, `allow_unverified_updates = false`), `maybeVerifyChecksum()` threw `checksumRequired` on every update from GitHub, and the only way to make them work at all was `CMS_UPDATES_ALLOW_UNVERIFIED=true` — which the config itself warns disarms the control mitigating extraction-time vulnerabilities. A fail-closed default whose only workaround is permanently insecure is worse than an insecure default, because the operator makes the change deliberately and never revisits it. `GitHubUpdateSource` now discovers checksums the way `GitLabUpdateSource` does: a `*.sha256` release asset first, falling back to a `SHA-256:` marker in the release body.

- **`rollback()` restored an arbitrary ZIP with no exclusion filter and no provenance check** — unlike `extractUpdate()`, it never consulted `exclude_from_update`, so a backup archive carrying `.env` or `vendor/autoload.php` replaced the live copies, and `runComposerInstall()` then executed scripts from the restored `composer.json`. With no argument, `update:rollback` picks the newest `backup-*.zip` by mtime with no ownership check, so any other vulnerability yielding a file write under `storage/` let an attacker plant a backup and wait. The exclusion list now applies on restore, and a backup path outside the configured backup directory is refused without `--allow-external`.

- **The update-source SHA-256 was documented as though it were an authenticity control** — it is not. The digest comes from the same origin and trust domain as the archive (a sidecar on the same release, or the same JSON document that supplied `download_url`), so it protects against truncation, CDN corruption and partial downloads, and against nothing else — not a compromised update server, not a compromised release-editor account, not a plaintext MITM. The config comment now says so. There is still no signature verification in this module.

- **`php artisan update:status --json` exited 0 for failed and interrupted runs** — the `--json` branch returned success before the exit-code logic ran, so `php artisan update:status --json || alert` — the natural machine-consumption path, and the one the documentation advertises without qualification — never fired on a dead update, while the identical state without `--json` exited 1. The exit code is now resolved before the output branch and returned by both modes.

- **`composer test` fatalled instead of producing a report** — `phpunit.xml` set no memory limit, so the documented invocation died at PHP's 128M default inside `nikic/php-parser` while Scramble's generator walked the route table for the OpenAPI spec test. `phpunit.xml` now sets `memory_limit=512M`.

- **`./`-prefixed ZIP entries bypassed `exclude_from_update`, letting a release archive overwrite `.env`, `vendor/`, `bootstrap/cache/` and the SQLite database** — the exclusion list is matched against entry names as strings, and the zip-slip filter normalized `..` but never `./` or `//`. So an entry named literally `./.env` was a different string from `.env` and missed the list entirely, while `realpath( dirname( '/base/./.env' ) )` is just `/base`, so the containment check waved it through as well. Mixing one such entry in with normal `app/…` entries also made the first path segments differ, so `detectCommonRootPrefix()` returned null and the entry survived verbatim. The reachable targets were exactly the ones the operator believes are protected: `APP_KEY` and DB/mail credentials in `.env`, `vendor/` (which the operator believes is rebuilt from the lock), `bootstrap/cache/*.php` (compiled config executed on every request, bypassing the glob), and `database/database.sqlite`. Entry names are now canonicalized — split on `/`, empty and `.` segments dropped, any `..` segment rejected — before both the exclusion check and the containment check, and prefix detection uses the same canonical form so the two cannot disagree about what an entry is named.

- **`isPathExcluded()` matched on a bare string prefix, so files whose names merely *began* with an excluded name were never updated** — `str_starts_with( 'storage-helpers.php', 'storage' )` is true, so `storage-helpers.php`, `storage.php`, `vendors/`, `.envelope.json` and friends were skipped by both the backup and the extraction: neither snapshotted nor ever updated, silently stale forever. Matching is now anchored to a path-segment boundary (`$path === $exclude || str_starts_with( $path, $exclude . '/' )`).

- **`Artisan::call( 'down' )` / `call( 'up' )` exit codes were ignored, so the maintenance-mode flag could lie in both directions** — Laravel's `UpCommand::handle()` and `DownCommand::handle()` both wrap their bodies in a `try { … } catch { …; return 1; }`, so a genuine failure — permission denied unlinking `storage/framework/down`, an unwritable `storage/framework/` — arrives as a non-zero exit code and *never* as an exception. The `catch ( Throwable )` was effectively dead code and the exit code was never checked. A failing `up` at step 10 therefore "succeeded": the active flag was cleared, the shutdown guard disarmed, and the run marked `Completed` — leaving the site serving 503 to every visitor while `update:status` reported a clean finish, which is precisely the outcome the interruption machinery exists to prevent. Symmetrically, a failing `down` at step 1 let the update proceed to overwrite application files on a **live** site. Both calls now check the exit code and throw `maintenanceModeFailure`.

- **`fwrite()` short writes were unchecked, so a disk that filled mid-extraction silently truncated PHP files** — the return value was discarded and `fclose()`'s was too, so the write loop completed, the entry counted as extracted, and the update proceeded into `composer install` and migrations over truncated source. The asymmetry was the giveaway: `fread()` failure *was* checked and threw `extractionEntryFailed`, whose own docblock explains that throwing is what engages the rollback machinery — the write side never engaged it. The copy loop now lives in `streamEntryToDisk()`, which throws on a short write, on a `false` write, and on a failed `fclose()` (buffered data can fail to reach disk at close time, so a clean loop is not on its own proof the file landed intact).

- **Directory entries were created *before* the containment check ran** — `File::makeDirectory( …, recursive: true )` executed first and `isPathWithinExtractRoot()` was consulted afterwards, by which point the directories existed and were never removed. The string-level filter means `..` cannot reach this code, so the only escape is a pre-existing symlink inside `base_path()` pointing outside it — routine in Envoyer/Forge/Deployer layouts where `storage`, `public/uploads` or `bootstrap/cache` are symlinked to shared directories. Impact was directory creation only, since file *content* was correctly withheld by the check before `fopen()`. Both checks now run before their `makeDirectory()` call, validating the nearest existing ancestor. The unreachable `continue` in the directory branch is gone.

- **Extraction wrote through pre-existing symlinks** — the containment check validated the target's *parent directory*, never the target itself, so `fopen( …, 'wb' )` followed an existing symlink and truncated whatever it pointed at, and the `@chmod` that follows chmod'd the link target. The archive cannot *introduce* a symlink — every entry is written as a regular file and `symlink()` is never called — so this was strictly about links already on disk: shared config, a log file, a sibling release directory in a blue/green deploy. Extraction now skips any target that is a symlink or an existing non-regular file.

- **The updater never looked for composer where Laravel Herd puts it, so a Herd-only macOS host could not self-update at all** ([#254](https://github.com/ArtisanPack-UI/cms-framework/issues/254)) — `phpCandidatePaths()` has listed Herd's `php` **first** since [#225](https://github.com/ArtisanPack-UI/cms-framework/issues/225), annotated "so hosts that use Herd for both FPM and CLI stay on a single toolchain". Herd bundles composer in that same `bin/` directory, but `composerCandidatePaths()` never learned about it — the one place the Herd awareness didn't get carried over. On a clean Herd install with no Homebrew composer and no global composer (Herd ships one, so there's no reason to `brew install` another), all five candidate paths missed, discovery returned `null`, and the updater fell through to bare `composer install` — which PHP-FPM's stripped `PATH` (`/usr/bin:/bin:/usr/sbin:/sbin`) cannot resolve:

  ```
  Update failed: Rollback failed: Composer install failed. Output: sh: composer: command not found .
  Original update error: Composer install failed. Output: sh: composer: command not found .
  Manual intervention required. The pre-update snapshot was restored.
  ```

  Three changes:

  - **`~/Library/Application Support/Herd/bin/composer` now leads the composer candidate list**, mirroring the PHP list's ordering and rationale. Herd's composer is a `#!/usr/bin/env php` script rather than a standalone binary, which needed no other changes: `buildComposerCommand()` already invokes the discovered path as `{CLI PHP} {binary} install ...`.
  - **Both candidate lists now derive Herd's `bin/` directory from a single `herdBinPath()` helper**, so they cannot drift apart again the way they did here.
  - **A failed rollback `--version` probe against a path PHP also cannot `stat()` now throws the new `UpdateException::configuredComposerBinaryMissing`**, naming the offending path. Such a path can only have come from `COMPOSER_BINARY` / `cms.updates.composer_binary`, since discovery only ever returns a path it has already stat'd. This closes a second, more confusing failure: because `/opt/homebrew/bin/composer` is advertised in the `composerBinaryNotFound` message and is the canonical macOS location, the natural next move on a Herd-only machine was to set `COMPOSER_BINARY` to it — producing "Composer binary was located but could not be executed … Could not open input file" closing with a `CMS_PHP_BINARY` hint. It was never located, only configured; and `resolvePhpBinary()` had done its job correctly, so the trailing hint blamed the one component that was already right.

    The stat runs **after** the probe rather than gating it, which matters for [#233](https://github.com/ArtisanPack-UI/cms-framework/issues/233): a path PHP cannot `stat()` may still be perfectly reachable by the shelled-out child under PHP-FPM sandboxing, and pointing `COMPOSER_BINARY` at such a path is the documented workaround for that case. A pre-flight `is_file()` gate would have closed that escape hatch. Probes that succeed are still honoured regardless of what `is_file()` thinks.

  Hosts on an older framework version can work around this by setting the full path in `.env`, quoted because it contains spaces: `COMPOSER_BINARY="/Users/{you}/Library/Application Support/Herd/bin/composer"`.

- **The updater excluded `composer.lock` from extraction but ran `composer install`, breaking every release that changed a dependency constraint** ([#255](https://github.com/ArtisanPack-UI/cms-framework/issues/255)) — `composer.lock` sat in the `exclude_from_update` default annotated `// Rebuilt via composer install`. It isn't: `composer install` only ever *reads* a lock file and aborts when it disagrees with `composer.json`; only `composer update` / `composer require` write one. The identical comment on the neighbouring `vendor` entry *is* correct, which made the pair easy to read past. The consequence was that `extractUpdate()` skipped the release's lock — leaving the **old** one in place — while `composer.json` was not excluded and was overwritten with the **new** one, then handed that mismatched pair to composer:

  ```
  Update failed: Composer install failed. Output: Installing dependencies from lock file
    - Required package "artisanpack-ui/cms-framework" is in the lock file as "2.5.4" but that
      does not satisfy your constraint "^2.7.0". This usually happens when composer files are
      incorrectly merged or the composer.json file is manually edited.
  ```

  This was not an edge case: it failed **every release that changed any dependency constraint, on every host**, and was unaffected by the `COMPOSER_BINARY` / `CMS_PHP_BINARY` workarounds accumulated in [#225](https://github.com/ArtisanPack-UI/cms-framework/issues/225), [#232](https://github.com/ArtisanPack-UI/cms-framework/issues/232), [#233](https://github.com/ArtisanPack-UI/cms-framework/issues/233) and [#254](https://github.com/ArtisanPack-UI/cms-framework/issues/254) — those get composer *running*; this failure happens after composer is running correctly. Releases that changed no dependencies still installed, which is why it went unnoticed until the first constraint bump, at which point it broke every installation simultaneously. Three changes:

  - **`composer.lock` is no longer excluded from extraction**, so the release's lock lands beside its `composer.json` and hosts install the exact dependency set the release was built and tested against — rather than re-resolving the whole tree on production hardware at update time, which would leave every site on a slightly different, untested dependency set depending on when it updated. This makes a committed, in-sync `composer.lock` a requirement of the release archive; free for the `auto_archive` strategy, and now documented for `release_asset`. The pre-update snapshot picks the lock up for the same reason, so a rollback restores the old `composer.json` and old lock together.
  - **A lock-sync pre-flight check** aborts with an accurate message when the two files disagree, instead of letting composer's merge-conflict guess reach the operator. See the new `cms.updates.verify_composer_lock_sync` key above.
  - **The misleading `// Rebuilt via composer install` comment is gone**, replaced by a config block explaining why the lock must land. It is presumably what led to the exclusion in the first place.

  Hosts blocked on an older framework version can work around this by overriding `exclude_from_update` in `config/cms/updates.php` with `composer.lock` omitted; `mergeConfigFrom()` array-merges the package defaults underneath, so a partial override replaces only that key.

- **`performUpdate()` had no execution-time guard, so a PHP timeout killed it mid-flight and left the site stuck in maintenance mode** ([#256](https://github.com/ArtisanPack-UI/cms-framework/issues/256)) — `runComposerInstall()` gave the composer child a `cms.updates.composer_timeout` budget (default 600s), but the parent PHP request was still governed by `max_execution_time`, which defaults to **30 seconds** under PHP-FPM — the path the admin UI uses. Composer was given ten minutes and the request was killed after thirty seconds. Worse, an execution-time fatal is raised at shutdown rather than thrown, so `performUpdate()`'s `catch` block never ran: no rollback, and step 10's `disableMaintenanceMode()` never executed. The operator was left with a site returning 503 to every visitor, no error in the UI (the request died before rendering a response), and no automatic way back. Every other failure in this module to date failed *safely*; this one failed open. Three guards now cover it:

  - `performUpdate()` and `rollback()` call `set_time_limit( 0 )` and `ignore_user_abort( true )` up front, so neither PHP's execution ceiling nor the operator closing the browser tab can kill the request mid-update. Hosts that put `set_time_limit` in `disable_functions` get a warning log naming `php artisan update:perform` as the supported path instead.
  - `enableMaintenanceMode()` registers a shutdown guard that lifts maintenance mode if the process dies before step 10 — covering out-of-memory fatals and FPM's `request_terminate_timeout`, neither of which `set_time_limit()` can override. It logs a `critical` entry naming the step it died on, and if `artisan up` itself fails (likely when shutting down after an OOM fatal) it removes `storage/framework/down` directly.
  - Each of the ten steps is persisted to a state file as it is entered, so a killed update is *detectable*. Previously there was no way to distinguish "update in progress", "update died at step 6", and "the site was manually put into maintenance mode". A flat file rather than a cache entry on purpose: step 8 runs `cache:clear`, and the database cache driver is unavailable while step 7's migrations are mid-flight.

  Nothing here makes an HTTP request a *good* place to run a multi-minute job — `php artisan update:perform` from the CLI remains the supported path, and moving the HTTP endpoint to a queued job is tracked separately. These guards make the HTTP path fail safely instead of taking the site down and leaving it there.

### Security

- **A custom-field value whose key named a real column overwrote that column** ([#253](https://github.com/ArtisanPack-UI/cms-framework/issues/253)) — `HasCustomFields::findCustomFieldByKey()` has always refused to resolve a key that shadows a real DB column, so the metadata write was blocked; what it never blocked was the *fall-through*. `__set()` handed the value to `parent::__set()`, which wrote it straight into the column. `BlogManager::applyCustomFields()` and `PageManager::applyCustomFields()` ([#250](https://github.com/ArtisanPack-UI/cms-framework/issues/250)) assigned each payload key that way, and the custom-field half of a request payload gets no fillable filtering — so `custom_fields[author_id]` on a post update reassigned the author, whatever the attribute allowlist said. A registration wasn't even required: any key naming a column worked.

  New `HasCustomFields::applyCustomFieldValues()` is the supported way to apply an untrusted custom-field payload. It routes each value through the same magic setter as before, but silently drops keys that name a real column, cast, mutator, accessor, or relation first. Both managers now delegate to it, so `create()`/`update()` are covered on `Post` and `Page`, and any host content type using the trait gets the same guard for free — downstream apps no longer need to reimplement the check ahead of the manager call.

  DB-registered fields are exempt, because they legitimately own the column they name: `createField()` adds it, and a persisted row is always column-storage (`custom_fields` has no `storage` column, so `CustomField::storageMode()` resolves an existing row to `column`). The exemption keys off `exists`, which `CustomFieldManager::filterFieldsForContentType()` forces to `false` on every filter-registered field — so a plugin cannot buy itself a real-column write by declaring `storage => 'column'`, and only a row in the `custom_fields` table, which takes the custom-field admin capability to create, qualifies.

  The magic setter itself is deliberately unchanged. Guarding `__set()` would have meant that a plugin registering a field keyed to `title` could stop host code from writing `$post->title` at all — a required-column write turned into a save failure by anyone able to register a field. Trusted assignment and untrusted payload application are separate operations, and only the latter is guarded. Nothing legitimate is lost: a shadowing field can never round-trip, because the getter resolves a real column through Eloquent and never consults metadata.

## [2.7.0] - 2026-07-30

### Added

- **`BlogManager` write API** ([#250](https://github.com/ArtisanPack-UI/cms-framework/issues/250), Keystone [#183](https://gitlab.com/jacob-martella-web-design/jacob-martella-web-design/jmwd-keystone-cms/jmwd-keystone-cms/-/issues/183)) — new methods `autoDraft()`, `create()`, `update()`, `delete()`, `duplicate()`, `syncCategories()`, `syncTags()`, `uniqueSlug()` on `Modules\Blog\Managers\BlogManager`. All writes wrapped in a DB transaction. Unique-slug allocation skips soft-deleted rows so a restored draft can't collide. `create()`/`update()` route filter-registered custom-field values through the `HasCustomFields` magic setter before save so a single INSERT/UPDATE captures both hardcoded columns and metadata JSON. `update()` stamps `published_at = now()` exactly once on the first draft→published transition — subsequent republishes preserve the original stamp.
- **`PageManager` write API** ([#250](https://github.com/ArtisanPack-UI/cms-framework/issues/250)) — same seven methods on `Modules\Pages\Managers\PageManager` plus support for the hierarchical `parent_id`, `order`, and `template` attributes. `duplicate()` preserves `template` but resets status to Draft and clears `published_at`. Coexists with the existing hierarchy helpers (`getPageTree`, `movePage`, `reorderPages`) which stay unchanged.

### Changed

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
- **Correction (2.8.0):** the wave 4b list above names `comments.form.action` among the renamed comment surfaces, but no alias for it shipped in 2.5.0 — the new name resolved to nothing until [#245](https://github.com/ArtisanPack-UI/cms-framework/issues/245) added the missing entry. Do not rely on `ap.cmsFramework.comments.form.action` before 2.8.0. Every other name in this list landed as described.

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