# v2.9.0 Pre-Release Review — Fix Plan

**Branch:** `release/2.9` (reviewed against `main`, merge-base `e399727`)
**Reviewed:** 2026-08-19 — 29 commits, 113 files, ~4,556 insertions
**Baseline at review time:** all 1,996 tests pass (7 pre-existing skips) · `phpcs` clean · `php-cs-fixer --dry-run` flags exactly 1 file

This document is a start-to-finish work plan for an agent applying the fixes. Work the phases **in order**. Line numbers were accurate at review time — always match on the quoted code, not the line number.

## How to work in this repo

- Repo root: `~/Code/ArtisanPack UI Packages/cms-framework`
- Format with `composer fix` (php-cs-fixer). **Never run plain `vendor/bin/pint`** — it strips the WordPress-style spacing. If the fixer skips files, delete `.php-cs-fixer.cache` first.
- Lint gate: `composer lint` (fixer dry-run + phpcs, including the re-enabled `ValidatedSanitizedInput` sniff).
- Tests: `./vendor/bin/pest` (use `--filter` while iterating, full run at the end).
- Style: spaces inside parens/brackets, Yoda conditions, aligned `=`/`=>`, `__()` on all user-facing strings, `@since 2.9.0` on new symbols.
- Every fix must come with a new or updated test (the specific tests are named per item).

---

## Phase 1 — Release blockers

### 1.1 CRITICAL — `UserController` has no authorization on any CRUD action

**Files:** `src/Modules/Users/Http/Controllers/UserController.php`, `src/Modules/Users/routes/api.php:15-20`, new `src/Modules/Users/Policies/UserPolicy.php`

`index`, `store`, `show`, `update`, `destroy` contain zero `authorize()`/Gate calls (verified: the only gate in the file is inside `bulk()`, ~line 224). Routes carry only `auth` middleware. There is no `UserPolicy` (only `RolePolicy` and `PermissionPolicy` exist).

**Failure scenario:** any authenticated user — a subscriber-level account — calls `PUT /api/v1/users/{adminId}` with `{"password": "..."}` and takes over the admin account. Also user enumeration via `index`, arbitrary user creation/deletion.

This is pre-existing (the branch only removed an unused import from the file), but it blocks release: 2.9 must not ship an unauthenticated-in-practice user CRUD next to a changelog touting authorization hardening.

**Fix:**
1. Create `src/Modules/Users/Policies/UserPolicy.php` mirroring `RolePolicy` exactly (structure, capabilities naming — `users.view` / `users.create` / `users.edit` / `users.delete`, matching what `bulk()` already checks: `users.delete` / `users.manage`).
2. Register it wherever `RolePolicy` is registered (check the Users module service provider's `Gate::policy(...)` calls).
3. Add `$this->authorize( ... )` to all five actions, mirroring `RoleController`.
4. Ensure the policy degrades on a host `User` model without `HasRolesAndPermissions`: guard with `method_exists( $user, 'hasCapability' )` (see item 1.5 — same pattern).

**Tests:** in the Users feature test file, add: non-privileged authed user gets `403` from each of the five endpoints; privileged user still succeeds; guest gets `401`.

**Changelog:** add a Security entry (same style as the 2.8 "mutation endpoints now enforce authorization" entry, including the "Breaking for API consumers" note).

---

### 1.2 HIGH — Notification rename migration can rename **Laravel's own** `notifications` table

**File:** `src/Modules/Notifications/database/migrations/2026_08_18_000000_rename_notification_tables_to_cms_prefix.php:29-33`

The only guard is `Schema::hasTable( $from ) && ! Schema::hasTable( $to )`. No column-shape check distinguishes the CMS table (`title`/`content`/`send_email`) from Laravel's database-channel table (`uuid` id, `notifiable_type`, `data`).

**Failure scenario:** the exact population this migration targets — #281 collision victims — may have worked around the 2.8 crash by manually recording the create migrations as run. On such an install: creates recorded, `cms_notifications` absent, Laravel's `notifications` present → the rename migration renames **Laravel's** table to `cms_notifications`. The CMS `Notification` model then errors on every query, and Laravel's database channel writes to a fresh empty `notifications` table — data appears lost.

**Fix:** shape-sniff the one ambiguous name (`notifications`; the other two names are CMS-specific):

```php
public function up(): void
{
	foreach ( $this->tables as $from => $to ) {
		if ( ! Schema::hasTable( $from ) || Schema::hasTable( $to ) ) {
			continue;
		}

		// `notifications` is also the name Laravel's database notification
		// channel uses. Only rename it when it carries the CMS shape.
		if ( 'notifications' === $from
			&& ( ! Schema::hasColumn( $from, 'send_email' ) || Schema::hasColumn( $from, 'notifiable_type' ) ) ) {
			continue;
		}

		Schema::rename( $from, $to );
	}
}
```

**Tests:** in `tests/Feature/Notifications/NotificationRenameMigrationTest.php` add:
1. **The danger case (this fix's regression test):** stage a Laravel-shaped `notifications` table (uuid id, `notifiable` morphs, `data` json) with **no** `cms_` tables, run `up()`, assert Laravel's table was NOT renamed. (The existing coexistence test creates Laravel's table only *after* the `cms_` tables exist, so it never exercises this path.)
2. **Explicit idempotency:** `up(); up();` after a real legacy rename.
3. **FK survival:** stage the real legacy schema with the pivot's `constrained( 'notifications' )` FK, run the rename, delete a notification, assert pivot rows cascade.

---

### 1.3 HIGH — Livewire `CollectionEditor`: attacker-writable `$typeId`, mount-only checks bypassable

**File:** `src/Modules/DynamicContent/Livewire/CollectionEditor.php`

`public int $typeId` (line ~24) has no `#[Locked]`; Livewire lets the client rewrite it between requests. `mount()` checks `isCollection()` + `Gate::authorize( 'update', $type )`, but downstream never re-checks:

- `render()` (~line 117) re-fetches by the rewritten `typeId` with no gate → lists any type's records, bypassing the per-type capability scoping that `DynamicContentRecordPolicy` documents (e.g. credential-type records).
- `save()` create path (~line 89) uses type-less `Gate::authorize( 'create', DynamicContentRecord::class )` while the policy provides `createForType` and the REST controller (`DynamicContentRecordController.php:36`) uses it.
- `save()` create path has no `isCollection()` re-check → crafted payload creates collection records on a **singleton** type.
- `save()` update path lacks the `$record->dynamic_content_type_id === $this->typeId` check that `edit()` (~49) and `delete()` (~109) both enforce.

**Fix:**
1. Add `use Livewire\Attributes\Locked;` and `#[Locked]` on `$typeId`.
2. In `save()` create branch: `Gate::authorize( 'createForType', [ DynamicContentRecord::class, $type ] );` and `abort_unless( $type->isCollection(), 404 );`.
3. In `save()` update branch: add the same type-match `abort_unless` used by `edit()`/`delete()`.
4. Update the `phpcs:ignore` rationale at ~line 90 to name the `createForType` gate.

---

### 1.4 HIGH — Livewire `FieldBuilder`: attacker-writable `$typeId`, no create gate in `save()`

**File:** `src/Modules/DynamicContent/Livewire/FieldBuilder.php`

`$typeId` is not `#[Locked]` (~line 23). `save()`'s create branch (`null === $this->typeId`, ~lines 133-135) calls `$manager->create()` with **no Gate check** — the only create authorization is in `mount()` (~line 63). The update branch does re-authorize (~line 138), making the omission clearly unintentional.

**Failure scenario:** a user who mounted in edit mode (needs only `update` on one type — hosts can scope abilities separately via the `applyFilters` hooks) sets `typeId` to `null` and calls `save()` → creates new content types without `create` authorization.

**Fix:**
1. `#[Locked]` on `$typeId`.
2. In the create branch: `Gate::authorize( 'create', DynamicContentType::class );`.
3. Correct the overstated `phpcs:ignore` rationale at ~line 139: the slug **uniqueness/immutability** rules from `DynamicContentTypeRequest` are *not* mirrored — say "shape-validated" (or fix item 2.6 and keep the claim).

**Tests for 1.3 + 1.4:** extend `tests/Feature/DynamicContent/DynamicContentLivewireValidationTest.php`. Note its `beforeEach` currently defines `Gate::define( 'manage_dynamic_content', fn () => true )` for everyone, so no authorization behavior is exercised at all. Add:
1. Collection editor refuses `save()` when `typeId` is rewritten to a singleton type (`->set( 'typeId', $singleton->id )->call( 'save' )` — with `#[Locked]` this should throw Livewire's locked-property exception).
2. Collection editor denies `save()`/`render()` to a user whose gate returns false (direct `call( 'save' )` without going through the UI).
3. Collection editor refuses `save()` when `editingRecordId` belongs to a different type.
4. Field builder denies create via `save()` after `typeId` is nulled post-mount (under an update-only user).

---

### 1.5 MEDIUM (blocker because it re-opens #280) — `NotificationPolicy` fatals on host user models without the traits

**File:** `src/Modules/Notifications/Policies/NotificationPolicy.php:60, 87`

`create()`/`delete()` call `$user->hasCapability( 'notifications.manage' )` unguarded. The policy is registered globally (`NotificationServiceProvider.php:63`), so a host app with a plain `User` model calling `$user->can( 'create', Notification::class )` — including via `@can` or `Gate::any` sweeps — throws `Call to undefined method`. This is the exact failure class the #280 changelog entry claims is fixed.

**Fix (both methods):**

```php
return method_exists( $user, 'hasCapability' ) && $user->hasCapability( 'notifications.manage' );
```

**Test:** in `NotificationManagerTest` (or a new policy test) use the existing `tests/Support/PlainUser.php`: `Gate::forUser( $plainUser )->allows( 'create', Notification::class )` returns `false` without throwing.

---

### 1.6 HIGH — Release metadata

1. **`composer.json:6`** — `"version": "2.8.0"` → `"2.9.0"`. Not cosmetic: `resolveHostFrameworkVersion()` prefers composer.json's version, so 2.9.0 code self-reporting 2.8.0 skews the new `requires.cms-framework` plugin checks.
2. **`CHANGELOG.md:8`** — `## [2.9.0] - Unreleased` → the actual release date.
3. **CHANGELOG omissions** — the branch ships these with no entry; add them:
   - **#298** (Added): `installFromDisk()`, `syncFromDisk()`, and the `cms:plugins:sync` artisan command (disk → database plugin promotion).
   - **#126** (Added): Blade theme-file fallback for templates/template parts — new `is_blade`/`editable` fields in `TemplateResource`/`ResolvedEntity::toArray()` (API response change), the 422 write-rejection on Blade-backed slugs (observable behavior change), and the `customTemplates` Blade warning.
   - `author_name` on `discoverPlugins()`/`getPlugin()` (#297) — documented in plugin-authoring.md and typed in plugins.d.ts, absent from the changelog.
   - **#315** install-time **and** update-time manifest-slug identity guards — a new hard failure mode for previously-installable ZIPs; only the update-path half is currently described under #283.
   - Optionally **#301** (the ValidatedSanitizedInput sniff re-enable + Livewire payload validation).
4. Whatever Phase 1/2 fixes land also need changelog entries.

---

## Phase 2 — High-value correctness fixes (strongly recommended before tag)

### 2.1 `cms:plugins:sync` crashes on a directory whose name isn't a valid slug

**File:** `src/Modules/Plugins/Managers/PluginManager.php:321` (catch inside `syncFromDisk()`)

`scanPluginsDirectory()` (~1305) derives slugs from `basename()` without `validateSlug()`. For `plugins/My Plugin/` whose manifest also says `"slug": "My Plugin"`, the equality guard passes, `installFromDisk()` → `getPlugin()` fails `validateSlug` → `PluginNotFoundException` — which the `catch ( PluginValidationException | PluginInstallationException )` does **not** catch. The headline #298 command dies with a stack trace instead of reporting one `failed` row and continuing. Same escape if the discovery cache goes stale mid-sync.

**Fix:**

```php
} catch ( PluginValidationException | PluginInstallationException | PluginNotFoundException $e ) {
```

(Optionally also `continue` early in the loop when `! $this->validateSlug( $slug )`.)

**Test:** `PluginDiskSyncTest`: a plugin directory named `bad slug` (manifest slug matching) → sync reports one `failed`, exits normally, other plugins still sync.

### 2.2 Reactivation failure after update is misreported as "download failed"

**File:** `src/Modules/Plugins/Managers/UpdateManager.php:331-337`

`updatePlugin()` step 7 calls `activate()`, which since 2.9 can throw `DependencyNotSatisfiedException` / `PluginConflictException` (new manifest adds `requires`/`conflicts`). Both land in the generic `catch ( Exception )` → rollback is correct but the surfaced error is `PluginUpdateException::downloadFailed( $slug )`.

**Fix:** add alongside the existing `IncompatiblePluginException` catch:

```php
} catch ( DependencyNotSatisfiedException | PluginConflictException $e ) {
	$this->restoreFromBackup( $slug, $backupPath );
	$this->revertPluginRow( $plugin, $oldVersion, $oldMeta, $oldServiceProvider, $wasActive );

	throw PluginUpdateException::updateFailed( $slug, $e->getMessage() );
}
```

**Test:** `PluginUpdateTest`: update whose new manifest adds an unsatisfiable `requires.plugins` entry → assert files/row/`is_active` rolled back AND the exception message mentions the dependency, not the download.

### 2.3 `PluginsController::update()` swallows `IncompatiblePluginException`

**File:** `src/Modules/Plugins/Http/Controllers/PluginsController.php:289-306`

`UpdateManager` deliberately rethrows `IncompatiblePluginException` (~303-311), but `update()` only catches `PluginUpdateException` then generic `Exception` → the incompatibility becomes `report()` + generic 422, instead of the structured 409 that `activate()` renders (~149-156).

**Fix:** add the same `catch ( IncompatiblePluginException $e )` 409 block that `activate()` has — and, with 2.2 in place, matching 409 blocks for `DependencyNotSatisfiedException` / `PluginConflictException` if you choose to rethrow them structurally instead.

**Test:** `POST /plugins/{slug}/update` where the new version's `min_host_version` is too high → 409 with the structured code (currently would assert the generic 422).

### 2.4 `Notification::getPivotAttribute` shadows the real loaded pivot → returns null

**File:** `src/Modules/Notifications/Models/Notification.php:74-81`

Eloquent accessors take precedence over loaded relations. For a model fetched via `$user->systemNotifications`, `$notification->pivot` invokes the accessor, which checks the (unloaded) `users` relation and returns **null**, even though the real pivot IS loaded. Empirically confirmed. This breaks the package's own documented pattern (`docs/notifications/Managing-Notifications.md:66`, `$notification->pivot->is_read` → "Attempt to read property on null").

**Fix:** first branch of the accessor:

```php
if ( $this->relationLoaded( 'pivot' ) ) {
	return $this->getRelation( 'pivot' );
}
```

**Test:** fetch via `$user->systemNotifications()->first()` and assert `$notification->pivot->is_read` is readable.

### 2.5 Raw route slug used in filesystem delete/update paths

**Files:** `src/Modules/Plugins/Managers/PluginManager.php:556` (`delete()`), `src/Modules/Plugins/Managers/UpdateManager.php:250` (`updatePlugin()`)

Both build `pluginsPath . '/' . $slug` from the **raw route parameter** while the DB lookup uses `sanitizeText( $slug )`. If the two differ but the sanitized form matches a row (`valid<b></b>-plugin` → `valid-plugin`), the lookup succeeds while the filesystem path is the unvalidated raw string. Route params can't contain `/` so traversal isn't currently reachable — but this is the pattern `getPlugin()` was hardened against.

**Fix:** after the lookup, use the trusted DB value everywhere: `$pluginPath = $this->getPluginsPath() . '/' . $plugin->slug;` in `delete()`, and in `updatePlugin()` use `$plugin->slug` for `$pluginPath` / `extractUpdateArchive( ... )` / `restoreFromBackup( ... )`.

### 2.6 `FieldBuilder` slug rules diverge from `DynamicContentTypeRequest`

**File:** `src/Modules/DynamicContent/Livewire/FieldBuilder.php:25, 112-121` vs `DynamicContentTypeRequest.php:29-35`

- Create: missing `Rule::unique( 'dynamic_content_types', 'slug' )` → duplicate slug surfaces as an unhandled `QueryException` (500) instead of a validation message (the table has a unique index).
- Update: missing the immutability rule; the UI accepts slug edits and `DynamicContentTypeManager::update()` silently drops them — REST returns the translated "slug is immutable" error, Livewire silently discards.

**Fix:** build the slug rules conditionally on `$this->typeId`, exactly as the Form Request does. While there: merge the two sequential `$this->validate()` calls (~111-112) into one so scalar and `fields.*` errors report together, and add the request's custom message `'fields.*.type.in' => __( 'The field type is not registered.' )`.

**Test:** duplicate slug via the builder → validation error, not a 500.

### 2.7 Style fix — the one fixer violation

**File:** `tests/Feature/Notifications/NotificationTableNamingTest.php:63-68`

Run `composer fix` (delete `.php-cs-fixer.cache` first if it skips). Fixes `->count())->toBe( 1)` → `->count() )->toBe( 1 )`.

---

## Phase 3 — Decisions needed (ask the maintainer, don't just apply)

### 3.1 Conflict semantics: "installed" vs "active"

**File:** `src/Modules/Plugins/Support/DependencyResolver.php:97 (forward), 117-118 (reverse)`

Conflicts currently block on *installed* (even inactive) plugins. Scenario: A declares `conflicts: {B: "*"}`; both installed, both inactive → activating either is blocked; the pair is unusable until one is **deleted**. WordPress/runtime semantics would be "cannot both be *active*". The docblock says "installed", so this may be intentional Composer-style semantics — but the mutual-deadlock consequence deserves an explicit decision before the API ships.

**If runtime semantics are chosen:** gate both loops on activity — forward: `if ( null !== $other && ! empty( $other['is_active'] ) && $this->satisfies( ... ) )`; reverse: add `&& ! empty( $otherEntry['is_active'] )` at ~118. Existing tests set the conflicting plugin active, so none would break. Either way, document the chosen semantics in `docs/plugin-authoring.md` and the changelog #45 entry.

### 3.2 Empty `capability` on admin pages yields an auth-only route

**Files:** `src/Modules/Plugins/Support/PluginServiceProvider.php:66-72`, `src/Modules/Admin/Managers/AdminPageManager.php:71`

`'capability' => ''` produces a route with `auth` only (`array_filter` drops only `null`; `registerRoutes()` skips `can:` for empty strings). Pre-existing and identical for the 2.8 `view` flavor — but the new federated tests use `'capability' => ''`, cementing it as API. Consider coercing empty capability to the `access_admin_dashboard` default (fine post-release; decide now so the tests don't enshrine it).

---

## Phase 4 — Hardening & polish (recommended; none block the tag)

| # | File | Issue | Fix |
|---|------|-------|-----|
| 4.1 | `PluginsController.php:392-403` | `POST /plugins/check-dependencies` accepts an unbounded slug list; each unknown slug costs a DB query + disk stat + manifest parse | `$slugs = array_slice( $slugs, 0, 100 );` after the filter |
| 4.2 | `PluginsController.php:329-335` | `dependencies()` 404s for a DB-registered plugin whose files are gone; `dependents()` deliberately doesn't | Fall back to the graph: 404 only when `! isset( $graph[ $slug ] ) && ! $plugin`, sourcing `requires`/`conflicts` from the graph entry |
| 4.3 | `PluginsController::checkDependencies()` | Uninstalled slug reports `satisfied: true` and is silently dropped from `order` | Add `'installed' => isset( $graph[ $slug ] )` to each per-slug result |
| 4.4 | `PluginManager.php:151` (`installFromZip()`) | ZIP with `plugin.json` nested (not at slug root) → `validateManifest( null )` TypeError + orphaned extracted dir | After parse: `if ( null === $manifest ) { File::deleteDirectory( $this->getPluginsPath() . '/' . $slug ); throw PluginValidationException::invalidManifest( ... ); }` |
| 4.5 | `PluginManager.php:598-605`, `PluginsServiceProvider.php:72-80` | Boot-time plugin load catches `Exception`, not `Throwable` — a missing provider class (`Error`) still takes down the whole site | `catch ( Throwable $e )` in both |
| 4.6 | `PluginManager.php:822-829` (`resolveAuthorName()`) | Docblocks (~57, 820, 827) say "display-safe" / "can echo directly" but the value is shape-safe, not escape-safe | Either `sanitizeText()` the return, or reword to "always a string (shape-normalized); escape on output" |
| 4.7 | `NotificationController.php:90-92` | `show()` returns 403 (exists, not yours) vs 404 (missing) — id-enumeration oracle | Return 404 in both cases; consider `$this->authorize( 'view', $notification )` so the registered policy isn't dead code |
| 4.8 | `TemplatesController.php:264-267` + `TemplatePartsController` equivalent | New user-facing `rejectIfBlade()` messages not wrapped in `__()` (same release wraps the 501 message and `MenuItemRequest` messages) | Wrap in `__()` |
| 4.9 | `ResolvesUserFactory.php:40` | `@since 2.1.0` on a method in a trait new in 2.9 | `@since 2.9.0` |
| 4.10 | `resources/types/plugins.d.ts` | Half-updated: no `requires.plugins`/`conflicts` manifest shape, no types for the three dependency endpoints | Add `PluginManifestRequires`, `PluginDependencyStatus` (`satisfied`/`missing`/`inactive`/`version_mismatch`/`conflicts`), and the three response interfaces |
| 4.11 | `PluginManager.php` `activeLoadOrder()` | "Defensive" append loop is unreachable (`activationOrder()` emits every graph-present slug; active ⊂ all) | Delete, or re-comment as an invariant note |
| 4.12 | `PluginManager.php:296` | Triple slug-match guard: `syncFromDisk()` pre-check duplicates `installFromDisk()`'s (~236); the pre-check exists only to guard the refresh branch | Move the guard into `refreshInstalledPlugin()`, drop the pre-check |
| 4.13 | `TemplateResolver`/`TemplatePartResolver`, `TemplatesController`/`TemplatePartsController` | `resolveThemeFile()`/`themeFileSlugs()`/`rejectIfBlade()` duplicated near-verbatim (~80 new lines on an existing duplication pattern) | Post-release: `ResolvesThemeFiles` trait parameterized on subdirectory + a shared `RejectsBladeWrites` concern |
| 4.14 | Rename migration | Stale FK constraint names survive the MySQL rename (`notification_user_notification_id_foreign`); a future `dropConstrainedForeignId()` would fail on upgraded installs only | Document in the migration docblock (or rename constraints) |
| 4.15 | `NotificationController.php:86-154` | API messages (`'Notification not found'`, `"Marked {$count} notifications as read"`, …) untranslated; counts should use `trans_choice()` | Wrap per the translatable-strings guideline (pre-existing; fine as follow-up issue) |
| 4.16 | `NotificationManager.php:414-417`, `NotificationController.php:170`, `PostFactory.php:154`, `PageFactory.php:184`, `QueryRuntime.php:473` | Pre-existing asymmetric-paren spacing the fixer misses (e.g. `->pluck( 'id')`, `]);`) | Manual sweep, low priority |

---

## Phase 5 — Tests to add (beyond those attached to fixes above)

Plugin dependency API:
1. `PluginApiTest.php:319-333` — add the three new endpoints (`GET {slug}/dependencies`, `GET {slug}/dependents`, `POST check-dependencies`) to the guest-401 endpoint list (currently missed).
2. A test locking the deliberate posture that a non-`manage-plugins` authed user gets 200 from the three read-only dependency endpoints (so a future middleware change can't silently flip the decision).
3. `GET /plugins/{slug}/dependencies` has **zero** coverage (its two siblings are tested) — add the 404 branch and the `requires`/`conflicts` echo, mirroring the `dependents` tests.
4. `check-dependencies` request-shape branches: bare JSON array body; non-array `plugins` value → empty result.
5. `dependents` for a DB-row-without-files plugin (the explicitly coded branch at `PluginsController.php:362`).
6. Resolver garbage inputs: unparseable constraint (`"^not^a^constraint"`) and `v`-prefixed installed version (the `Throwable` catch and `ltrim( 'vV' )` normalization are untested).
7. Boot cycle fallback: two *active* plugins requiring each other → `loadActivePlugins()` still registers both (the `activeLoadOrder` catch is untested).

SiteEditor / federated:
8. **Federated XSS regression** (escaping is correct today; nothing pins it): register a component name like `"><script>alert(1)</script>` and assert the response contains the escaped attribute, not raw markup. Same for a `<script>` title through the shell (the single-vs-double-escape subtlety in `app.blade.php:25-28`).
9. Blade-extension traversal: plant `secret.blade.php` outside `templates/` and assert `resolve( '../secret' )` is null (existing traversal tests only plant `.html`).
10. 501 over HTTP (currently only unit-tested by invoking the closure directly).
11. Federated authz denial: guest redirected / capability-less user 403 on a federated route (the feature test uses `Gate::before( fn () => true )`).

Factories:
12. `PostFactory::new()->byAuthor( $id )->create()` works when the user model is unresolvable — pinning the documented "caller must supply" contract. Note: the graceful-null degrade does **not** survive a bare `create()` (`posts.author_id` is NOT NULL/FK), which contradicts the trait docblock — either document that `create()` requires `byAuthor()` or throw a descriptive exception.

---

## Phase 6 — Final verification checklist

Run in order from the repo root; all must pass:

1. `rm -f .php-cs-fixer.cache && composer lint` — clean.
2. `./vendor/bin/pest` — full suite green (was 1,996 passed / 7 skipped at review time; count should only grow).
3. `composer validate` — only the standing "version field present" warning.
4. Confirm `composer.json` says `2.9.0` and `CHANGELOG.md` has a real date plus the Phase 1.6 additions and entries for every behavior-changing fix applied from this plan.
5. Grep the changed docs for every symbol/hook they name (`ap.cmsFramework.admin.federatedPageAction`, `assertManifestValid`, `checkDependencies`, `getDependents`, `canDeactivate`, `getActivationOrder`) — all verified existing at review time; re-verify if any were renamed while fixing.
6. Delete this file (`RELEASE-2.9-REVIEW.md`) — or move it out of the repo — before tagging.

---

## Reviewed and found clean (no action; recorded so effort isn't duplicated)

- **Plugin ZIP/traversal hardening:** zip-slip, sibling-top-segment, and uncompressed-size guards run before extraction on install, update, and backup restore; `migrations_path` traversal rejected at validation and re-anchored with `realpath` at use.
- **Manifest revalidation on update (#283) + slug identity (#315):** implemented, rollback-safe, tested.
- **Update rollback:** all four failure branches restore files + row including `is_active` (the `refresh()` fix is real and unit-tested).
- **Dependency resolver:** DFS topological sort with on-path cycle detection is correct and deterministic; self-dependency, missing/inactive deps, version-less plugins, and semver-on-garbage all handled; 18-case unit matrix.
- **Disk→DB promotion:** console-only (no HTTP route), re-runs full manifest validation + slug guard.
- **`federated.blade.php`:** all output escaped (`{{ }}` only); the filter accepts only real Closures — no route-action injection; federated pages get the same `['web','auth']` + `can:` middleware as view pages.
- **Blade-fallback resolvers:** slug-validated before any FS/DB access (`/^[a-z0-9]+(?:-[a-z0-9]+)*$/`); Blade files are never read or rendered by the resolvers (metadata-only entities) — no traversal/RCE path.
- **MenuItemRequest fail-closed (#291):** both rules identical `whereRaw( '1 = 0' )`, controllers still independently fail closed, feature-tested.
- **Notifications table rename consistency:** zero stale references to the old table names anywhere in `src/`, `database/`, `config/`, `docs/`.
- **`method_exists` degrade in `NotificationManager` (#280):** semantics correct on all four paths, tested with `PlainUser` (the gap is the policy — item 1.5).
- **phpcs suppressions (~70 added for #301):** every rationale verified true against the code except the two corrected in items 1.3/1.4; the sniff re-enable is real (probe-verified).
- **SQL/column injection sweep:** `QueryRuntime` orderBy allow-listed, raw fragments driver-constants only, LIKE patterns escaped + bound; AI agent outputs all validated/clamped before reuse.
- **No dead code / debug residue:** every new public symbol has call sites; no `dd`/`dump`/`ray`/TODOs introduced; all test support files used; `composer/semver` dependency genuinely used and sanely constrained; committed `composer.lock` matches repo convention.
- **`examples/hello-world-plugin`:** still valid against the new manifest validation and slug-match guard.
