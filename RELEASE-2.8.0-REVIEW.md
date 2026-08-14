# cms-framework v2.8.0 — Pre-Release Review & Fix Plan

**Branch reviewed:** `release/2.8` at commit `75b6d62` (diff base: `main`, merge-base `565c8b5`).
**Scope:** 84 files, +11,349 / −471. Self-updater for the application, themes, and plugins; queued update job; GitHub Releases update source; SHA-256 verification; admin menu / AI wiring.
**Baseline health:** `vendor/bin/pest` → **1,854 passed, 7 skipped**. `composer lint` → **clean** (php-cs-fixer 0/533, phpcs green).

This document is an ordered work plan. Each item has an exact `file:line`, the evidence, and the concrete fix. Apply top-down: **Blockers → High → Medium → Low → Cleanup**. After each fix, run the named test (or add the noted missing one) before moving on. Every claim here was verified against the current `release/2.8` checkout.

> **⚠️ Delete this file before opening the release PR.** `RELEASE-2.8.0-REVIEW.md` is a temporary work plan, not shipped documentation — it is `export-ignore`d from the Composer archive but should not land in the release PR. Once every item below is applied (or consciously deferred), `git rm RELEASE-2.8.0-REVIEW.md` and remove its `/RELEASE-2.8.0-REVIEW.md export-ignore` line from `.gitattributes` as the final cleanup step.

---

## How to read the priorities

| Tier | Meaning | Ship without fixing? |
|------|---------|----------------------|
| 🔴 Blocker | Security or data-loss defect that ships live in every install | No |
| 🟠 High | Headline-feature correctness bug or defense gap | Strongly discouraged |
| 🟡 Medium | Real bug with a narrower trigger, or a hardening gap | Fix if time allows |
| 🟢 Low | Cosmetic / consistency / minor correctness | Batch into a follow-up |
| 🧹 Cleanup | Dead code, duplication, docs drift (user explicitly asked about bloat) | Follow-up, but see note |

> **Note on cleanup:** the biggest bloat item — duplication between the theme and plugin `UpdateManager`s — is **required for this release, not optional**, and has been promoted to High priority as **H3**. It is *cheapest to fix now*, before v2.8.0 freezes two brand-new public APIs that have already begun to drift, and it resolves several other findings in one place. **The fixing agent must complete H3.**

---

# 🔴 Blockers

## B1 — Plugin, theme, and AI mutation endpoints enforce authentication but **no authorization**; the `manage-plugins` / `manage-themes` permissions are seeded but never checked

**This is the single most important finding. It ships live in every install** — the routes are auto-registered by the package's own service providers, not opt-in.

**Evidence (verified):**
- `src/Modules/Plugins/routes/api.php:8-9` — the whole group is `->middleware( 'auth' )`, no `can:` / gate.
- `src/Modules/Themes/routes/api.php:34` — `Route::middleware( ['auth:sanctum'] )`, no gate.
- `src/Http/routes/ai.php:22` — `Route::middleware( 'auth:sanctum' )`, no gate.
- `src/Modules/Plugins/Http/Requests/InstallPluginRequest.php:41` — `public function authorize(): bool { return true; }`
- `src/Modules/Themes/Http/Requests/UploadThemeRequest.php:41` — same `return true;`
- `database/seeders/PermissionsTableSeeder.php:84,89` — `manage-plugins` and `manage-themes` are seeded, but `grep -rn "manage-plugins\|manage-themes" src/` returns **zero** enforcement sites.
- Routes are auto-loaded: `PluginsServiceProvider.php:53`, `ThemesServiceProvider.php:105`, `CMSFrameworkServiceProvider.php:326` all call `loadRoutesFrom` / `->group()`.
- The chain reaches code execution: `PluginManager::activate()` (`src/Modules/Plugins/Managers/PluginManager.php:226`) runs `app()->register( $plugin->service_provider )` where `service_provider` comes straight from the attacker-supplied `plugin.json`; an activated theme's `Theme.php` and Blade templates execute on every request.

**Attack scenario:** any account that can obtain a session or Sanctum token — a subscriber, a self-registered user, a low-privilege API client — POSTs a crafted ZIP to `/api/v1/plugins/install`, then `/api/v1/plugins/{slug}/activate`, and gains arbitrary PHP execution as the web user. The theme path (`POST /v1/themes` → `POST /v1/themes/{slug}/activate`) is identical.

**Why it's a blocker and not "the host's job":** the framework *defined granular permissions for exactly this* (`manage-plugins` = "Install, activate, and configure plugins") and then never checked them, and the FormRequests actively `return true` instead of deferring to a gate. Contrast the app updater in this same release, which ships deny-by-default `UpdateCapability` gates (`CoreServiceProvider.php:117-131`) precisely because an under-authorized trigger was considered fatal. The extension modules ship the HTTP triggers the app updater deliberately withholds, without the authorization the app updater deliberately enforces.

> **Caveat to weigh:** if the intended deployment model is "every authenticated user is a trusted admin," the practical severity drops. But that is not what the seeded permissions imply, and the default must be closed, not open. Decide the model explicitly before shipping.

**Fix:**
1. Implement real authorization in both FormRequests:
   ```php
   // InstallPluginRequest / UploadThemeRequest
   public function authorize(): bool
   {
       return $this->user()?->can( 'manage-plugins' ) ?? false;   // 'manage-themes' for the theme request
   }
   ```
2. Gate the non-upload mutating routes (activate / deactivate / update / destroy) that don't run through those FormRequests. Add `can:` middleware, mirroring the deny-by-default posture:
   ```php
   // Plugins routes/api.php — mutating routes
   Route::post( 'install',  ... )->middleware( 'can:manage-plugins' );
   Route::post( '{slug}/activate',   ... )->middleware( 'can:manage-plugins' );
   Route::post( '{slug}/deactivate', ... )->middleware( 'can:manage-plugins' );
   Route::post( '{slug}/update',     ... )->middleware( 'can:manage-plugins' );
   Route::delete( '{slug}',          ... )->middleware( 'can:manage-plugins' );
   // Themes routes/api.php — upload / activate / update → 'can:manage-themes'
   ```
3. Add a `cms.ai.use`-style ability for the AI endpoints (see M8), or at minimum gate them the same way.
4. Register the abilities deny-by-default in the module service providers (as `CoreServiceProvider` does for updates), so a host that never seeds the permission is closed rather than open.

**Tests to add:** a feature test per module asserting a **non-privileged authenticated** user receives `403` from install/activate/update/destroy. Today `PluginApiTest.php:319` only asserts unauthenticated `401`s, and the "admin" in setup is a bare factory user with no distinct capability — so the gap is invisible to the suite.

---

## B2 — Plugin ZIP extraction has none of the zip-slip / single-top-level-dir guards the theme extractor has

**Evidence (verified):**
- `src/Modules/Plugins/Managers/PluginManager.php:680-701` (`extractZip`) derives the slug from `explode( '/', $firstEntry )[0]` and calls a bare `$zip->extractTo( $extractPath )` with **no per-entry validation**. The same bare pattern is in `src/Modules/Plugins/Managers/UpdateManager.php:202-205` (update) and `:501-504` (backup restore).
- Compare `src/Modules/Themes/Managers/ThemeManager.php:822-861` (`extractZipTo`), which the author deliberately hardened: rejects absolute paths and `..` segments per entry, **requires every entry's top segment to equal the derived slug** (with a comment explaining a second top-level folder "would escape rollback"), and realpath-anchors each destination under the themes root.

**Attack scenario:** PHP's `extractTo()` does strip `..`, so escaping *outside* the plugins root is mitigated by libzip — this is why it's a blocker-adjacent High rather than a clean traversal. But nothing stops a plugin ZIP carrying a *sibling* top-level directory (`payments/PaymentsServiceProvider.php`) from overwriting a **different, already-trusted plugin's** files inside the plugins root, since the "already installed" check keys only on the first entry's slug. Next boot executes attacker code under the trusted plugin's identity, surviving deletion of the uploaded plugin.

**Fix:** give `PluginManager::extractZip()` and the two `UpdateManager` raw `extractTo` sites the same guard set as `ThemeManager::extractZipTo()`. Factor the guard into a shared helper (see §C1) so the two modules cannot drift. Also run the derived slug through `validateSlug()` before use.

**Test to add:** replace the placeholder `PluginSecurityTest.php:301` (`expect( true )->toBeTrue()`) with a real crafted-ZIP assertion — an entry under a sibling top-level dir must be rejected.

---

# 🟠 High

## H1 — `handleUpdateFailure()` lifts maintenance mode **before** rollback (and even when no rollback is possible), ignoring the `lift_maintenance_on_interrupt` policy

**Evidence (verified):** `src/Modules/Core/Updates/Managers/ApplicationUpdateManager.php:3026-3034` calls `disableMaintenanceMode()` as its unconditional first act, then attempts rollback at `:3055+`. The step-aware policy the release advertises is applied only to the *interruption* path, never to this caught-exception path.

**Failure scenario:** with `backup_enabled = false` (a supported config), an extraction exception mid-tree → catch → maintenance lifted → no rollback possible → the site serves public traffic from a tree mixing old and new code. This is the exact "new controllers alongside old policies, silently disabling authorization" outcome `config/updates.php:228-237` promises the step-aware default prevents. Ironically, the *same* half-applied tree produced by a process death keeps the site down (correct), while produced by a thrown exception it goes live (wrong). With backups on, the site goes live and *then* runs `composer install` over live traffic during rollback.

**Fix:** in `handleUpdateFailure()`, for the steps where rollback still applies (≤ Migrations), roll back **first** and lift maintenance only after the tree is restored; route the lift through the existing `liftMaintenanceModeAfterFailure( ..., $this->currentStep )` so the `step_aware` / `false` policy governs the caught path too. The finish-forward branch (steps 8-10, already at `:3043`) stays as-is.

**Test to add:** assert the lift/rollback ordering and that a caught failure honours `lift_maintenance_on_interrupt`. `UpdateInterruptionGuardTest` covers the interrupt path but not this ordering.

## H2 — `--allow-downgrade` / pinned downgrade is unreachable in its primary use case (rolling off a bad *latest* release)

**Evidence (verified):** `ApplicationUpdateManager.php:207-210` throws `UpdateException::noUpdateAvailable()` when `! $updateInfo->hasUpdate()` — and `hasUpdate()` is `version_compare( latest, current, '>' )`. So on an install already at latest `2.8.0`, `performUpdate( '2.7.1', allowDowngrade: true )` throws "No update available" before the downgrade comparison at `:220` is ever reached. `PerformUpdateCommand.php:53-57` is worse: it prints "✓ You are already running the latest version" and exits **success**, silently ignoring `--target-version` + `--allow-downgrade`.

**Contradicts docs:** `docs/self-updater.md:113` and `:333` present pinned downgrade as working.

**Fix:** skip the `hasUpdate()` gate when `$version` is explicitly pinned — the `version_compare( $targetVersion, current, '>' )` check at `:220` already handles both directions and the `$allowDowngrade` guard already protects it. In `PerformUpdateCommand`, only early-return on "already latest" when no `--target-version` was passed.

**Test to add:** there is currently **no** test where a downgrade succeeds. Add one (pinned target below current, `allowDowngrade: true`).

## H3 — Collapse the duplicated update pipeline shared by the theme and plugin `UpdateManager`s into one home (**required for this release**)

**This fix is mandatory, not optional cleanup.** Both `UpdateManager`s were added *in this same release* as near-verbatim copies, and the copies have **already started to diverge** — which means the same bug now has to be fixed in two places, and a security hardening applied to one silently misses the other. That drift is exactly the release risk this refactor removes, and it is far cheaper to do now than after v2.8.0 freezes both public APIs.

**Evidence (verified):** byte-identical or noun-only-different methods across `src/Modules/Themes/Managers/UpdateManager.php` and `src/Modules/Plugins/Managers/UpdateManager.php` (theme / plugin line refs):

| Method | Themes | Plugins |
|--------|--------|---------|
| `rejectInsecureSource()` | 443-455 | 262-274 |
| `resolveUpdateToken()` | 536-545 | 371-380 |
| `serializeUpdateInfo()` | 561-573 | 392-404 |
| `nonEmptyString()` | 777-786 | 413-422 |
| `isUpdateAvailable()` | 798-801 | 620-623 |
| `verifyArchiveChecksum()` | 727-752 | 564-589 |
| `checkViaUpdateSource()` | 467-486 | 283-301 |
| `makeUpdateChecker()` | 498-520 | 335-357 |
| backup file-walk | 587-612 | 452-476 |
| restore-from-backup | 627-668 | 484-505 |

**The drift already present** (proof the copies are diverging, and why keeping them separate is a live hazard): the theme `verifyArchiveChecksum()` uses `hash_equals( strtolower(...) )` while the app updater uses case-sensitive `!==` (L1); the theme backup walk passes `File::allFiles( $path, true )` to include dotfiles while the plugin walk omits it and loses them (L5); the theme extractor has full zip-slip guards the plugin extractor lacks entirely (B2). These are the *same method* implemented three-plus times with different behaviour.

**Fix:** extract an abstract `ExtensionUpdateManager` base class (or a `ManagesExtensionUpdates` trait alongside `HasManifestParsing` in `src/Modules/Core/Managers/Concerns/`), parameterized by `UpdateType`, config prefix (`cms.themes` / `cms.plugins`), and log-noun. Move every method in the table above into it. **Keep the theme implementations wherever the two diverge — they are strictly better hardened** (backup `open()`/`close()` checks, the dotfile-inclusive walk, the zip-slip guards). Each concrete manager should retain only its storage-specific glue (how it reads the installed version and manifest).

**Why it's required and not deferrable:** this single refactor also closes **B2** (shared zip-slip guard), **M7** (shared uncompressed-size guard), **L1** (single checksum helper), and **L5** (dotfile walk) — fixing them once in the base rather than twice-with-drift in the copies. Do H3 *before* patching B2/M7/L1/L5 individually so those fixes land in the shared home. Also fold the twin `ZipUploadRequest` extraction (**C2**) and the shared checksum helper into the same effort.

**Verification:** the existing `tests/Unit/Themes/ThemeUpdateManagerTest.php` (909 lines) and `tests/Unit/Plugins/UpdateManagerTest.php` (371 lines) must both stay green after the extraction — they are the behavioural contract that proves the refactor preserved each manager's semantics. Net removal: **~250-350 lines** of `src`, plus the two extraction-guard and size-guard bodies that no longer need duplicating.

---

# 🟡 Medium

## M1 — `runningUpdatePid()` misreads `EPERM` as "process dead", defeating the "reconciliation only touches its own run" guarantee for cross-user updates

**Evidence (verified):** `ApplicationUpdateManager.php:861` — `return posix_kill( $pid, 0 ) ? $pid : null;`. `posix_kill( $pid, 0 )` returns `false` with `EPERM` for a **live** process owned by another user. The test suite already knows this: `tests/Unit/Updates/PerformUpdateJobTest.php:615` avoids PID 1 because it "would fail with EPERM" — but production code has no EPERM handling. This weakens the same PID guard in `acquireUpdateLock():794` and `guardNoPendingUpdate():2837`.

**Failure scenario:** a root cron `auto_update` run and a `www-data` queue worker: the worker's `handleFailedUpdateJob()` ownership check gets `null` for root's live PID, marks the healthy run `Interrupted`, and (if the recorded step is outside 5-7) runs `artisan up` on a site root is mid-extraction on.

**Fix:**
```php
if ( ! posix_kill( $pid, 0 ) ) {
    return posix_get_last_error() === PCNTL_EPERM_OR_1 ? $pid : null; // EPERM (1) proves it exists
}
return $pid;
```
Use `posix_get_last_error()` and treat `EPERM` (errno 1) as "alive".

## M2 — `rollback()` ignores `ZipArchive::extractTo()`'s return value; a partial restore is recorded as `rolled_back: true`

**Evidence (verified):** `ApplicationUpdateManager.php:624-625` — `$zip->extractTo( base_path(), $restore ); $zip->close();`. `extractTo()` returns `false` on failure (disk full, permission error mid-extract) and nothing checks it. Contrast the *forward* extraction, which 2.7.1 hardened with exhaustive short-write/close checks. `update:status` then reports "The pre-update snapshot was restored successfully" — the exact false reassurance the `rolled_back` field exists to prevent.

**Fix:**
```php
if ( true !== $zip->extractTo( base_path(), $restore ) ) {
    $zip->close();
    throw UpdateException::rollbackFailed( 'backup extraction failed partway' );
}
```
**Test to add:** a failing `extractTo` during rollback.

## M3 — The extraction-additions ledger is in-memory only, so the **manual** `update:rollback` path leaves the orphaned files #272 set out to remove

**Evidence (verified):** `ApplicationUpdateManager.php:105` (`$extractionAdditions` plain property), only consumed by `removeExtractionAdditions()` at `:3065` inside `handleUpdateFailure()`. `UpdateStateStore` never persists it, and `rollback()` (`:575-639`) never consults it. So an update *killed* during extraction (the `Interrupted` case) loses the ledger with the process; `update:status` then sends the operator to restore the snapshot manually (`UpdateStatusCommand.php:356`), which restores files but does **not** remove extraction-added files — recreating the hybrid tree (orphaned code + migrations) the #272 fix targets.

**Fix:** persist the additions list into the state file as entries are ledgered (the store's writes are already atomic/best-effort), and have `rollback()` / `RollbackUpdateCommand` consume and re-validate it. The containment re-checks already in `removeExtractionAdditions()` make stale entries safe to process.

## M4 — `CheckForUpdateScheduled` caches a **serialized `UpdateInfo` object** into a key nothing reads — the object-injection pattern 2.7.1 removed elsewhere

**Evidence (verified):** `src/Modules/Core/Updates/Console/CheckForUpdateScheduled.php:52` — `Cache::put( 'cms.update_available', $updateInfo, now()->addDays( 1 ) )`. `grep -rn "cms.update_available" src/` shows **zero readers** (only the put at `:52` and forget at `:86`). This contradicts `UpdateChecker.php:62-69`, which dehydrates to scalars precisely because "a serialized object here is both an RCE-on-next-update primitive and a PHP object-injection sink." Today it is a write-only daily liability; any future code that reads the key inherits the sink.

**Fix:** delete both `Cache::put`/`Cache::forget` lines, or store the dehydrated scalar array (`UpdateChecker::dehydrateUpdateInfo()` shape). Also delete the unreachable `else { Log::error('Auto-update failed') }` branch at `:77-80` (`performUpdate()` returns `true` or throws — it never returns `false`).

## M5 — Plugin update never invalidates its 12h check-cache and never re-compares versions → phantom updates and redundant full re-installs

**Evidence (verified):** `src/Modules/Plugins/Managers/UpdateManager.php:87-101` memoizes the *decision* (the `isUpdateAvailable( $plugin->version, ... )` comparison at `:296` runs **inside** the cached closure) under `plugin.update.{slug}` for `cms.plugins.updateCacheTtl` (12h). `grep` shows no `Cache::forget` anywhere in the file. `updatePlugin()` (`:171-173`) then relies solely on the cached truthiness — no fresh `version_compare` against the now-updated `$plugin->version`.

**Failure scenario:** update `foo` 1.0.0 → 2.0.0. The cache still says "2.0.0 available" for 12h, so `GET /api/v1/plugins/updates` keeps advertising it and a second `POST /{slug}/update` re-runs the full backup → download → delete → extract → reactivate cycle for the already-installed version, firing `plugin.updating`/`plugin.updated` needlessly.

**Fix:** `Cache::forget( "plugin.update.{$slug}" )` on the success path of `updatePlugin()`, and guard the top of `updatePlugin()` with `if ( ! $this->isUpdateAvailable( $plugin->version, $updateInfo['version'] ) ) { return false; }`.

## M6 — A transient GitHub failure (rate-limit / 5xx) is cached as "no update" for 12 hours

**Evidence (verified):** `src/Modules/Plugins/Managers/UpdateManager.php:89-101` — the `Cache::remember` closure catches every `Exception`, logs, and returns `null`, which is then cached for the full TTL. `GitHubUpdateSource::fetchReleases()` throws on any non-2xx including 403 rate-limiting (unauthenticated limit is 60 req/hr/IP, trivially hit when many plugins share a host).

**Fix:** distinguish "checked, no update" (cache `null`) from "check failed" (don't cache, or use a short negative-cache TTL). Catch inside `checkViaUpdateSource` and only cache a genuine success.

## M7 — No uncompressed-size / entry-count guard on theme or plugin extraction (zip-bomb / disk exhaustion)

**Evidence (verified):** `src/Modules/Themes/Managers/ThemeManager.php:721` bounds only the *compressed* `filesize( $zipPath )`. The `validateZip` loop (`:730`) and `extractZipTo` loop (`:823`) iterate entries for path-traversal but never accumulate `statIndex()[ 'size' ]` or cap `numFiles` before `extractTo()`. Same gap in the plugin extractor.

**Failure scenario:** an authorized uploader (or compromised update source) ships a 40 MB archive that expands to tens of GB, filling the disk during extraction after the compressed-size check has already passed.

**Fix:** in the extraction guard loop, accumulate `$zip->statIndex( $i )[ 'size' ]` and throw once it exceeds a configurable uncompressed ceiling (add e.g. `cms.themes.maxUncompressedSize` / `cms.plugins.maxUncompressedSize`); optionally cap entry count. Apply to both modules via the shared helper (§C1).

## M8 — Legacy plugin `update_url` path enforces no HTTPS and no checksum before extract-and-execute

**Evidence (verified):** `src/Modules/Plugins/Managers/UpdateManager.php:528` — when `makeUpdateChecker()` returns `null` (a legacy `update_url` feed), `downloadUpdateArchive()` calls `downloadUpdate()` (`:598-610`), a bare `Http::timeout( 60 )->get( $updateUrl )` with **no** scheme check and **no** `verifyArchiveChecksum()`. The archive is then `extractTo()`'d and the provider re-registered. The source-backed path, by contrast, routes through `StreamsDownloadsToDisk::assertSecureDownloadUrl()` and `verifyArchiveChecksum()`.

**Fix:** enforce `https` on `update_url` before fetching (reuse `rejectInsecureSource()`, already in this class) and route the download through `StreamsDownloadsToDisk` so redirects are protocol-pinned. If legacy feeds genuinely can't advertise a digest, gate them behind the same `allow_unverified_updates` opt-out rather than silently skipping verification.

## M9 — Admin AI endpoints have authentication but zero authorization

**Evidence (verified):** `src/Http/routes/ai.php:22` gates the five agent endpoints behind only `auth:sanctum`; `AiController::runAgent()` and `AiTools::run()` check feature toggles but never a gate. Any authenticated user (including the baseline `user` role) can invoke the agents and consume paid provider credits.

**Fix:** fold into B1 — add a `cms.ai.use` deny-by-default ability and check it in `runAgent()`/`run()` (or gate the route group).

---

# 🟢 Low

## L1 — Checksum comparison behaviour has drifted across three copies

**Evidence (verified):** `ApplicationUpdateManager::verifyChecksum()` (`:1163-1170`) uses `!==` (case-sensitive, non-constant-time). `Themes/UpdateManager::verifyArchiveChecksum()` (`:736-741`) and `Plugins/UpdateManager` (`:573-578`) use `hash_equals( strtolower( $expected ), $actual )`. An uppercase digest from a source passes in the new managers and **fails** in the app updater. Additionally, `Sources/CustomJsonUpdateSource.php:113` reads `sha256` with no `strtolower`/hex validation, so an uppercase custom-feed digest fails every update as `checksumMismatch` (a false positive that reads like tampering), and a non-string `sha256` throws an uncaught `TypeError` that `PerformUpdateCommand`'s `catch ( Exception )` (`:103`) misses.

**Fix:** extract one `verifySha256( string $path, ?string $expected, string $version, string $label )` helper (in `Core/Updates/Support`) used by all three, normalizing with `strtolower( trim( ... ) )` and validating `^[a-f0-9]{64}$`. Widen `PerformUpdateCommand`'s catches to `Throwable`.

## L2 — `ThemesController::update()` returns 404 for an unknown slug while `activate()` returns 422 for the same exception

**Evidence (verified):** `src/Modules/Themes/Http/Controllers/ThemesController.php:121-124` catches `ThemeNotFoundException` and returns **404 JSON**; `:255-256` (`activate`) throws a **422** `ValidationException` keyed by `slug`, and its own docblock (`:234`) argues 422 is correct "rather than a 404" because the slug is form input. `PluginsController` treats unknown slugs as 422 everywhere. Pick one convention (422 keyed by `slug` matches the release's stated direction and the plugin module).

**Fix:** make `ThemesController::update()` throw `ValidationException::withMessages( [ 'slug' => [ ... ] ] )` on `ThemeNotFoundException`, matching `activate()`.

## L3 — Admin menu renderer coerces `url` against the `Htmlable` escaping bypass but leaves `label` / `title` open to the identical vector

**Evidence (verified):** `resources/views/admin/partials/menu.blade.php:24,26` render `{{ $node['title'] ... }}` and `{{ $node['label'] ... }}`; Blade's `e()` returns `Htmlable` verbatim. `AdminMenuManager::sanitizeMenuUrls()` (`:257-259`) coerces only `url` via `NavUrl::sanitizeValue`. `NavUrl::sanitizeValue()`'s own docblock names this exact bypass as the reason `url` is coerced — but the text fields aren't. (Threat model is limited: a `ap.cmsFramework.admin.menu` filter subscriber already runs PHP in-process, so this is inconsistency-by-the-release's-own-standard rather than a new privilege boundary.)

**Fix:** in `sanitizeMenuUrls()`, cast `label`/`title`/`menuTitle` to plain `string` when non-string (a bare `(string)` cast is enough — `{{ }}` then escapes normally). Add the mirror test alongside `AdminMenuManagerFilterTest.php:147`.

## L4 — Admin layout double-escapes the idiomatic inline `@section('title', …)`

**Evidence (verified):** `resources/views/admin/layouts/app.blade.php:28` — `{{ trim( $__env->yieldContent( 'title', __( 'Admin' ) ) ) }}`. Laravel's inline `@section('title', $value)` already runs `$value` through `e()` on store, so `{{ }}` escapes a second time: `Posts & Pages` → tab shows `Posts &amp; Pages`. The block form (`@section…@endsection`) is stored raw.

**Fix:** the safe options are (a) document "block form only" and use `@yield('title', __('Admin'))` (inline stays escaped once by Blade; the docblock at `:15` already forbids raw block-form titles), or (b) keep `{{ }}` and document that titles must use the block form. Option (a) matches upstream Laravel convention.

## L5 — Plugin backup drops dotfiles, making plugin rollback lossy

**Evidence (verified):** `src/Modules/Plugins/Managers/UpdateManager.php:467` uses `File::allFiles( $pluginPath )` (excludes dotfiles); the theme equivalent `src/Modules/Themes/Managers/UpdateManager.php:602` correctly passes `File::allFiles( $themePath, true )`. A plugin shipping a dotfile (e.g. `.env.example`, `.htaccess`) loses it on rollback.

**Fix:** pass `true` in the plugin `backupPlugin()` walk to match the theme manager (resolved for free by §C1).

## L6 — `verifyChecksum` / `removeExtractionAdditions` / lock fallback minor robustness

- `ApplicationUpdateManager.php:2985-2997` — `removeExtractionAdditions()` counts `File::delete()` failures as removals (`File::delete()` returns `false`, rarely throws, so the `catch` is nearly dead). Change to `if ( ! File::delete( $p ) ) { Log::warning(...); continue; } $removed++;`.
- `ApplicationUpdateManager.php:776-786` — `acquireUpdateLock()`'s no-lock fallback (`false === $handle`) proceeds with *no* concurrency protection; it should still throw `updateAlreadyRunning( $pid )` when `runningUpdatePid()` returns a live PID.
- `ApplicationUpdateManager.php:3043-3052` vs `UpdateStatusCommand.php:315` — a deliberate finish-forward (`markRollback( null )`) renders as the alarming "No rollback was attempted… tree may still carry a partial update." Record a distinct `finish_forward => true` marker and branch on it.

## L7 — `NavUrl` admits protocol-relative `//host` (and `/\host`) despite the "same-origin relative" claim

**Evidence (verified):** `src/Modules/Admin/Support/NavUrl.php:41,76` — `str_starts_with( $trimmed, '/' )` admits `//evil.example`, which navigates off-origin. Not an escalation (the scheme list already allows `https://` external links), so this is a doc fix ("root-relative and protocol-relative") *or* block `//` / `/\` prefixes if same-origin was the intent.

## L8 — Re-seeding grants the `admin` role **every** permission, silently re-granting revoked update perms

**Evidence (verified):** `database/seeders/PermissionsTableSeeder.php:151-153` — `$admin->permissions()->syncWithoutDetaching( Permission::pluck( 'id' )->all() )` sweeps in third-party/consumer permissions and re-grants `cms.updates.perform` (RCE-grade) even where an operator revoked it. Scope the admin grant to the framework's own slug list.

## L9 — Duplicate-alias double-fire (hooks package, surfaces via `comments.form.action`)

**Evidence (reported by agent, root cause in the hooks package):** `src/Support/HookAliases.php:112-115` claims `deprecateHook()` is idempotent, but the hooks package's `HookDeprecations::alias()` appends to `reverseIndex` with no dedup. When cms-framework **and** visual-editor both declare the identical `comments.form.action → ap.cmsFramework.comments.form.action` alias (visual-editor `src/Support/HookAliases.php:69`), a subscriber that registered on the *old* name **before** the aliases were declared fires twice per dispatch. `HookAliasesTest` passes because it never covers the pre-alias-subscriber + double-declaration ordering.

**Fix:** upstream in `artisanpack-ui/hooks`, make `alias()` a no-op for exact re-registration (`array_unique` in `aliasesFor()`, or an early return when `(old,new)` already mapped). Until that ships, soften the `HookAliases.php:112-115` docblock claim or drop the duplicate declaration from one package. **This is cross-package — track it as a hooks issue, not a v2.8.0 blocker.**

---

# 🧹 Cleanup — dead code, bloat, docs drift

> The user explicitly asked to catch dead code and agent-added bloat. **Good news: there is no *unused-and-broken* code in `src/`** — every new method, exception factory, enum case, constant, and config key that ships is reachable, except the specific items below. The real cost is **duplication** and a handful of orphaned exception factories / config keys.

## C1 — Extract the duplicated update pipeline → **promoted to High priority; see H3**

The `UpdateManager` de-duplication is required for this release and has moved to **H3** in the High tier. It resolves B2, M7, L1, and L5 in one place and must be done *before* those individual patches so they land in the shared base. This entry remains only as a cross-reference.

## C2 — Extract a shared `ZipUploadRequest` base (~90 removable lines)

`UploadThemeRequest` and `InstallPluginRequest` differ only in field name, config key, and four message nouns. Abstract a base with `field(): string` / `sizeConfigKey(): string`; each concrete request shrinks to ~15 lines. Do this together with the B1 `authorize()` fix so both requests gain real authorization from one place.

## C3 — Remove orphaned exception factories

Verified never thrown in `src/` (`grep` confirmed only definitions + docblock references):
- `UpdateException::composerBinaryNotFound()` (`src/Modules/Core/Updates/Exceptions/UpdateException.php:183-228`, 46 lines) — **also** referenced by stale docs (`docs/self-updater.md:190`, `config/updates.php:123`) that name it as the error operators will see, when `verifyComposerBinaryAvailable()` actually throws `configuredComposerBinaryMissing` / `composerVerificationFailed`. Either wire it into `runComposerInstall()` when discovery exhausts, or delete it and fix both doc references.
- `UpdateException::permissionDenied()` (`:488-491`) — delete.
- `UpdateException::incompatiblePhpVersion()` / `incompatibleFrameworkVersion()` (`:508-521`) — delete, **or** (better) enforce them: `UpdateInfo::minPhpVersion` / `minFrameworkVersion` are parsed, cached, and dehydrated but **never checked**, so a release declaring `minPhpVersion: 8.4` installs on 8.2 silently. Add the guard in `performUpdate()` before `runUpdateSteps()`, or drop the fields.

## C4 — Remove dead config keys (verified 0 readers in `src/`)

- `cms.updates.check_frequency` (`config/updates.php:397`) — `update:check-scheduled` is *registered* as a command (`CoreServiceProvider.php:84`) but **never scheduled**, so `daily/twice_daily/weekly/disabled` are inert. Either wire a `Schedule` registration that honours the key, or delete the key and document that hosts schedule it themselves.
- `cms.updates.current_version_config_key` (`config/updates.php:64`) — never read; `UpdateInfo::resolveCurrentVersion()` hardcodes `config('app.version')`. A host overriding this key is silently ignored. Delete, or honour it.

## C5 — Minor over-engineering / duplication

- `Themes/UpdateManager::forgetUpdateCache()` (`:347`) is `public` but only called internally (`:404`) — make `protected`.
- `Themes/UpdateManager::wrapUpdateFailure()` (`:373-383`) — the `instanceof UpdateException` branch (`:374-376`) and the `instanceof Exception` branch (`:378-380`) return identical results, and `UpdateException extends … extends Exception`, so the first is subsumed. Delete the redundant branch.
- `AiController::features()` (`:63-74`) and `AiTools::enabledFeatures()` (`:187-197`) are byte-identical loops — extract a static helper next to `AgentMeta`.
- `downloadUpdateArchive()` rebuilds the update checker that `checkViaUpdateSource()` built moments earlier (both managers) — cache the checker per slug in the shared base (§C1).

## C6 — Docs drift

- `digital-shopfront` stragglers after the hard-coded-default removal: `ApplicationUpdateManager.php:888` (`slug: 'digital-shopfront-cms'` — the updater's cache-key slug, product-specific inside a now-generic framework; make configurable — note it invalidates existing `cms.application.digital-shopfront-cms.update_check` cache entries), `UpdateCheckerFactory.php:45` docblock, and doc examples in `docs/themes.md:149,166`, `docs/site-editor/Templates.md:38`, `Menus.md:74`, `Global-Styles.md:125`, `docs/themes/Theme-Base-Class.md:32-46`. Prefer a generic `my-theme` in examples so readers don't infer a bundled theme exists.
- `docs/self-updater.md:190` + `config/updates.php:123` — the `composerBinaryNotFound` reference (see C3).
- Comment volume: ~49% of added `src` lines are comments. Most carry real rationale, but the RCE/authorization essay is repeated in full across `UpdateCapability`, `performUpdate()`, `dispatchUpdate()`, `registerUpdateCapabilities()`, the `config/updates.php` banner, and `docs/self-updater.md`. Pick two canonical homes and cross-reference. Optional; ~80-120 comment lines.

---

# What was verified solid (no action needed)

So the reviewer knows these were checked, not skipped:

- **Application updater extraction** — manual per-entry extraction with `canonicalizeArchivePath`, realpath containment before *and* after mkdir, pre-existing-symlink refusal, permission clamp `& 0777 & ~0022`, streamed writes with short-write/close detection. Zip-slip hardened and thoroughly tested. **This headline feature is genuinely well built.**
- **Download transport** — https-only with redirect-protocol pinning (`StreamsDownloadsToDisk`); sidecar discovery validates 64-hex and correlates to the named asset; fails closed via `checksumRequired`; pinned-version digest resolves its *own* checksum.
- **Command construction** — composer + PHP binaries `escapeshellarg`'d; stale-lock recovery validates package names against a composer-name regex *and* escapes them; version strings never reach a shell; `update:status` queue-identifier sanitizer prevents state-file → shell injection.
- **Update-check cache rehydration** — `UpdateChecker` dehydrates to scalars and rehydrates with per-field type checks, closing object-injection on a shared cache. (This is exactly why M4's write-only serialized cache is a regression to flag.)
- **App-updater Gate abilities** — deny-by-default, guest-denying untyped `$user`, `Gate::has()` respects host definitions, seeder slugs match constants, no framework HTTP route touches the updater. (This is the model B1 should copy.)
- **State store** — atomic temp-file + `rename`, `x`-mode + random suffix, 0600/0700, `JSON_INVALID_UTF8_SUBSTITUTE`.
- **Queue guards** — driver refusal (`sync`/`null`), `retry_after` guard, unique-lock manual acquisition, stale-`queued` freshness window, dispatch-failure reconciliation. Verified against Laravel's actual dispatch/unique-lock behaviour; well tested in `PerformUpdateJobTest`.
- **Interruption machinery** — terminal-status guard, step re-record from memory, `forceLiftMaintenanceMode` fallback, exit-code checks on `up`/`down`. Well covered.
- **Theme install extraction** (`extractZipTo`) — the reference-quality guard set the plugin extractor should adopt (B2).
- **Null-safe `cms.themes.default`** change — correct across `ThemeManager`, the registered-setting default, and `markActiveTheme`; well tested (`ThemeDefaultThemeTest`).
- **AgentMeta / AI-absent no-op** — `registerAiSurfaces()` gates on `interface_exists`, reflection path unreachable without the AI package, refactor behaviour-preserving, all four exception→envelope mappings tested.
- **Config publish tags** — all four configs registered under module + `cms-framework-config` umbrella, no collisions, `cms-views` override path correct, `ConfigPublishTagsTest` greps docs and asserts registration.
- **Release workflow** (`release.yml`) — tag-injection sink closed via `env:`, `git archive` byte-stable, `composer.lock`-presence gate, `.sha256` sidecar named to the `{asset}.sha256` contract, Packagist update. Sound.

---

# Suggested sequencing

1. **B1** (authorization) + **C2** (shared FormRequest) — do together; this is the ship-blocker.
2. **H3** (shared update pipeline) — **required.** Do this *before* B2/M7/L1/L5 so those fixes land once in the shared base instead of twice-with-drift in the copies. Both `UpdateManager` test files must stay green.
3. **B2** (plugin zip-slip) — implement the guard *in the H3 base* (the theme guard becomes the shared one).
4. **H1, H2** — app-updater correctness; add the two missing tests.
5. **M1-M8** — remaining correctness/hardening; **M7 and L1/L5 land in the H3 base**, not the copies. Each has a named test to add.
6. **L / C3-C6** — batch cleanup.

Run `vendor/bin/pest` and `composer lint` after each tier. Bump `composer.json` `version` to `2.8.0` and move the CHANGELOG `[Unreleased]` block to `## [2.8.0] - <date>`. **Finally, delete this file** (`git rm RELEASE-2.8.0-REVIEW.md` + drop its `.gitattributes` line) before opening the release PR — see the note at the top.

---

*Generated from a six-track parallel review (application updater, themes, plugins, admin/AI/core, dedicated security sweep, dead-code sweep). Every finding above was re-verified against the `release/2.8` checkout at `75b6d62` before inclusion.*
