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