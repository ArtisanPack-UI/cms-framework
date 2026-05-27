# cms-framework — Roadmap to v2.0.0

_Generated 2026-05-24. Current released version: **v1.1.0** (2026-04-06)._
_28 open issues on GitHub at time of writing — 10 of which are already resolved by merged PRs and just need to be closed._

## Version decision: this should be v2.0.0, not v1.2.0

The unreleased work since v1.1.0 ships breaking changes. Under SemVer, that requires a MAJOR bump.

- **RBAC migration (PR #134, Wave 4) is breaking.** `PermissionPolicy` method signatures changed from `string|int $id` to `Authenticatable $user`. Users-module API routes (`users`, `roles`, `permissions`, `users/bulk`) now require `auth` middleware — unauthenticated callers get 401. `PermissionController` now requires capabilities (`permissions.viewAny` / `view` / `create` / `update` / `delete`); previously any authenticated request was allowed. Bundled `RolesTableSeeder` + `PermissionsTableSeeder` no longer auto-run.
- **New required composer dependency:** `artisanpack-ui/rbac:^0.1`. SemVer treats a new required dep as breaking.
- **The author already framed this as a major.** `UPGRADE-RBAC.md` was written with a `1.x → 2.x` migration table.
- **The scope warrants it.** 50 commits added an entire Site Editor surface (Templates, Template Parts, Patterns, Global Styles, Menus, theme.json schema, QueryRuntime), the visual-editor bridge with `HasBlockContent`, the `block_content` column on `posts`/`pages`, and the `/api/v1/settings/site` endpoint. Calling this a minor bump understates the surface change for consumers.

A v1.2.0 would only be defensible if the RBAC migration were reverted (or polyfilled to preserve the old `PermissionPolicy` signature, auto-seed default roles, and ship rbac as a soft-suggest). That's a real option, but the cost (extra adapter code, two-step migration plan, deferring rbac alignment) outweighs the benefit (avoiding a major bump for one cycle).

---

## What landed since v1.1.0 (the v2.0 surface)

### Site Editor — entirely new module (`src/Modules/SiteEditor/`)

WordPress-parity site-editor backend behind a hybrid file + DB resolution model (theme files ship defaults; DB stores user overrides; DB wins per slug; delete = revert).

- **H0** — extended `theme.json` with WP schema (settings, styles, templates, parts, patterns) + `menus.locations` (#100)
- **H1** — Templates + template-parts module; REST under `/api/v1/templates` and `/api/v1/template-parts` (#108)
- **H2** — Patterns module: `BlockPattern` model, `PatternResolver`, `PatternFileParser`, REST under `/api/v1/blocks` (synced) and `/api/v1/block-patterns/patterns` (unsynced); WP-shape payloads (#110)
- **H3** — Global styles module: `GlobalStyles` model (singleton-per-theme), `GlobalStylesResolver` (deep-merges `theme.json` + variation files + DB), `GlobalStylesEmitter` (CSS custom properties + per-element rules with content-hash cache), REST under `/api/v1/global-styles`, `@cmsGlobalStyles` Blade directive (#112)
- **H4** — Menus module: `Menu` + `MenuItem` models, locations, REST under `/api/v1/menus` (#114)
- **G4c-1** — `QueryRuntime` service for `core/query` loop resolution (#97)

### Visual Editor bridge (all guarded behind `class_exists` so visual-editor stays optional)

- `block_content` JSON column on `posts` and `pages` (#94)
- `Post` + `Page` adopt `HasBlockContent` trait with polyfill stub (#94)
- Auto-register `HasBlockContent` content types into `ap.visual-editor.resources` filter (#95)
- Register `Post` + `Page` into `ap.visual-editor.resources` filter (#99)
- Register `visual_editor.*` permissions when visual-editor is installed (#98)
- `ap.visual-editor.{templates,template-parts,patterns,global-styles}` filter wiring with schema-guard for missing tables (#116)

### Settings

- Register `site.*` settings + WP-shape `/api/v1/settings/site` endpoint (GET/PUT) (#96)
- Bulk settings update wrapped in DB transaction (#137 fix)
- By-key settings save path that applies sanitizers and types (#137 fix)

### RBAC migration (Wave 4 — the breaking change)

- Users-module RBAC migrated to `artisanpack-ui/rbac` (PR #134, closes #70–73, #127–132)
- Three RBAC migrations removed; rbac package owns the schema
- `Role` and `Permission` now subclass rbac base models (existing imports keep working)
- `HasRolesAndPermissions` trait now composes rbac's `HasRoles` + `HasPermissions` (existing `use` statements keep working)
- New `role:` route middleware (companion to rbac's `permission:`)
- See `UPGRADE-RBAC.md` for the full migration guide

### Compatibility

- Relax `artisanpack-ui/security` constraint to allow `^2.0`

---

## Issue cleanup — close before shipping (10 issues)

PR #134 merged the work, but the squash commit didn't carry the `Closes` keyword, so these never auto-closed. Verify the code is on `main` and close.

| Issue | Title | Status |
|---|---|---|
| #70 | Replace built-in RBAC models with rbac base models | ✅ done in PR #134 |
| #71 | Remove RBAC migrations in favor of rbac package | ✅ done in PR #134 |
| #72 | Replace `HasRolesAndPermissions` with rbac trait | ✅ done in PR #134 |
| #73 | Add upgrade guide for RBAC migration | ✅ done in PR #134 (`UPGRADE-RBAC.md`) |
| #69 | Add upgrade guide for RBAC migration | ✅ duplicate of #73 — close |
| #127 | `PermissionController` zero authorization | ✅ done in PR #134 |
| #128 | `PermissionPolicy` signature `string\|int $id` | ✅ done in PR #134 |
| #129 | `apiResource` routes not behind `auth` | ✅ done in PR #134 |
| #130 | `description` column missing on `roles`/`permissions` | ✅ done in PR #134 |
| #131 | Ship `role:` + `permission:` route middleware | ✅ done in PR #134 |
| #132 | Make `Roles`/`Permissions` seeders opt-in | ✅ done in PR #134 |

---

## v2.0.0 — issues to ship (target: 6–8 issues)

The bar for v2.0.0: ship the Site Editor + Visual Editor bridge cleanly, fix the security/correctness issues hanging over the modules that *did* change, and don't drag in new feature scope.

### Must-ship (blockers)

- [ ] **[#133](https://github.com/ArtisanPack-UI/cms-framework/issues/133)** — Site editor REST routes use `'auth'` instead of `'auth:sanctum'`; Sanctum tokens rejected (Med/Bug, Module/Site Editor). _Same surface as the new SiteEditor module; ship the fix with the module._
- [ ] **[#141](https://github.com/ArtisanPack-UI/cms-framework/issues/141)** — `HasFeaturedImage` trait references columns that don't exist on the media table (Bug, just filed). _Trait is currently dead code on real schemas. Option A (rewrite as `morphToMany` through the existing `featureables` pivot) is the right fix; the pivot table already ships. ~2 hours._
- [ ] **[#119](https://github.com/ArtisanPack-UI/cms-framework/issues/119)** — `GitLabUpdateSource` never populates `UpdateInfo->sha256`; checksum verification silently skipped (High/Bug, Type/Security, Module/Core Updater). _Security correctness issue in shipping code._
- [ ] **rbac package must be tagged and published to Packagist before v2.0.0 release.** PR #134 ships with a path repository for local dev; the composer constraint needs to swap to a Packagist or VCS URL. This is the single hardest external blocker.

### Should-ship (high-priority, scope-paired with what's already changing)

- [ ] **[#118](https://github.com/ArtisanPack-UI/cms-framework/issues/118)** — `GitLabUpdateSource` downloads source archive instead of release-asset tarball (High/Enhancement, Module/Core Updater). _Pair with #119; same surface._
- [ ] **[#122](https://github.com/ArtisanPack-UI/cms-framework/issues/122)** — Themes module: enforce a manifest schema in `validateManifest()` (High/Enhancement, Module/Themes). _Site Editor is theme-driven; manifest validation hardens the whole stack._
- [ ] **[#123](https://github.com/ArtisanPack-UI/cms-framework/issues/123)** — Themes module: fire lifecycle hooks (`doAction`) from install/activate/deactivate/delete (High/Enhancement, Module/Themes). _Pair with #122; theme lifecycle work cluster._
- [ ] **[#121](https://github.com/ArtisanPack-UI/cms-framework/issues/121)** — Themes module: add `installFromZip()` + upload route mirroring Plugins pattern (High/Enhancement, Module/Themes). _Pair with #122/#123; rounds out theme lifecycle._

### Nice-to-have (only if cheap)

- [ ] **[#124](https://github.com/ArtisanPack-UI/cms-framework/issues/124)** — Themes + Plugins: throw `ValidationException` instead of bare JSON 400 (Med/Refactor)
- [ ] **[#140](https://github.com/ArtisanPack-UI/cms-framework/issues/140)** — Harden CI/release workflows: least-privilege permissions + fail release on Packagist HTTP errors (Med/Security)

---

## Defer to v2.1+ (or later)

These don't block v2.0 and don't pair with what already changed.

- **#126** — Blade-fallback support for theme templates and template parts (Low/Feature, Module/Themes). _Currently milestoned v1.x; re-milestone to v2.1._
- **#42** — Implement Plugin Update Tests with Mock ZIP Infrastructure (Enhancement). _Plugin module didn't change; can ship anytime._
- **#43** — Add Theme Asset Compilation Support (Med/Feature). _Larger scope; v2.1._
- **#44** — Add Child Theme Support (Med/Feature). _Larger scope; v2.1._
- **#45** — Add Plugin Dependency Management (Low/Feature). _v2.x._
- **#125** — Themes: replace hard-coded 'digital-shopfront' default with configurable null-safe fallback (Low/Refactor)
- **#120** — `ApplicationUpdateManager` hard-codes slug "digital-shopfront-cms" (Low/Refactor). _Pair with #125 — both are the same hard-coded-tenant smell._
- **#138** — Ship a default catalog of universal CMS settings (Feature, Module/Settings). _Currently milestoned "Future Release"; keep there._

---

## Pre-release tasks

In rough order — most of this is mechanical.

1. [ ] **Close the 10 already-done issues** listed in the cleanup section above.
2. [ ] **Tag and publish `artisanpack-ui/rbac` 0.1.0** to Packagist (or pin to VCS). Update `cms-framework/composer.json` to swap the path repository for the real constraint.
3. [ ] **Land the must-ship list** (#133, #141, #119) and the should-ship cluster (#118, #121, #122, #123) above.
4. [ ] **Finalize `CHANGELOG.md`.** The `[Unreleased]` section currently only documents H2 + H3 + block_content. Add H0, H1, H4, QueryRuntime, RBAC migration (with breaking changes section), settings endpoint, visual-editor bridge work, security constraint relaxation.
5. [ ] **Add a `### Breaking Changes` section to the v2.0.0 changelog entry**, summarizing what's in `UPGRADE-RBAC.md` plus the new required composer dependency. Link the upgrade guide.
6. [ ] **Bump `version` in `composer.json`** from `1.1.0` to `2.0.0`.
7. [ ] **Create milestone `v2.0` on GitHub**, re-milestone the must-ship + should-ship issues into it, and re-milestone deferred work from `v1.x` to `v2.1`.
8. [ ] **Verify the test suite passes** at the release commit. PR #134 reported 853 passing / 2236 assertions; spot-check that the Site Editor module tests run green against the rbac dependency from Packagist (not the path repo).
9. [ ] **Run `vendor/bin/pint`** and `vendor/bin/phpcs` clean.
10. [ ] **Tag `v2.0.0`** and let the release workflow publish to Packagist.

---

## Risks and watch-items

- **rbac package isn't on Packagist yet.** The single biggest external blocker. v2.0.0 cannot ship until rbac is tagged + published. If rbac slips, either delay v2.0 or revert PR #134 and ship the Site Editor work as v1.2.
- **Visual-editor bridge is guarded with `class_exists`, but real integration testing requires the visual-editor package installed.** Smoke-test the H0–H4 filter wiring in this dev app (`packages/visual-editor/` is symlinked) before tagging.
- **CodeRabbit cloud review on PR #134 was deferred** ("327-file diff exceeds CR's 300-file ceiling — directory-by-directory pass needed"). Worth a targeted review of `src/Modules/Users/`, the migration deletions, and the new `CheckRole` middleware before v2.0 tag.
- **The CHANGELOG is significantly behind reality.** Closing this gap is the highest-leverage prerelease task — consumers read the changelog to decide whether to upgrade.
