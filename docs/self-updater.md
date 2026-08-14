# Self-Updater

The framework ships a self-updater that downloads a release archive, extracts it, runs `composer install`, executes migrations, and rolls back on failure. The bulk of it is transparent, but a few knobs matter on hosts where composer isn't on the PHP-FPM pool's `PATH`.

## Authorization (2.8.0)

**`ApplicationUpdateManager` performs no authorization of its own.** It cannot: the five `update:*` commands run from the console with no authenticated user. Nothing in the framework changes that — the manager stays a plain service, and every shipped trigger stays console-gated.

What the framework does ship, as of 2.8.0, is the ability names a host application authorizes against when it wires the admin UI this module was written for:

| Constant | Ability | Covers |
|----------|---------|--------|
| `UpdateCapability::PERFORM` | `cms.updates.perform` | `performUpdate()` — the ten-step update. |
| `UpdateCapability::ROLLBACK` | `cms.updates.rollback` | `rollback()` — restore a pre-update snapshot. |
| `UpdateCapability::VIEW` | `cms.updates.view` | `checkForUpdate()` / `updateState()` — read update availability and status. |

```php
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCapability;
use Illuminate\Support\Facades\Gate;

Route::post( '/admin/updates/perform', function () {
    Gate::authorize( UpdateCapability::PERFORM );

    app( ApplicationUpdateManager::class )->dispatchUpdate();

    return response()->noContent();
} )->middleware( ['web', 'auth', 'throttle:2,60'] );
```

Note the endpoint **dispatches** and returns rather than calling `performUpdate()` and blocking — see [Queued updates](#queued-updates-280). `dispatchUpdate()` performs no authorization of its own either: queueing a job that will overwrite PHP files and run `composer install` is the same remote-code-execution channel as running it inline.

### The default is deny

`CoreServiceProvider` registers each ability with a definition that returns `false`. That is deliberate. `performUpdate()` is by design a remote-code-execution channel — it overwrites PHP files and then runs `composer install`, which executes `post-install-cmd` scripts from the just-overwritten `composer.json` — so a permissive default would hand that to every authenticated user of every host that upgrades. Two paths grant it:

- **Seed an RBAC permission whose name or slug matches the ability.** `PermissionsTableSeeder` seeds all three and assigns them to the `admin` role (which receives every permission). rbac's `Gate::before` hook runs ahead of every Gate definition and short-circuits it, so a seeded permission decides on its own.

  **This beats your own definition too, not just the framework's.** `Gate::before` matches an ability against the `name` and `slug` columns of every row in `permissions`; a hit returns `hasPermissionTo()` and the `Gate::define()` below is never reached. So if you seed `cms.updates.perform` *and* define a stricter rule for it, the seeded permission wins and your rule is silently bypassed. Pick one mechanism per ability: grant through roles, or define the ability and don't seed the permission.
- **Define the ability in your own application.** Provider boot order puts `AppServiceProvider::boot()` after the framework's package providers, so your definition replaces the shipped one:

  ```php
  Gate::define( UpdateCapability::PERFORM, fn ( $user ) => $user->hasRole( 'owner' ) );
  ```

  If your host registers the ability from a provider that boots *before* the framework's, that definition is left alone too — the framework skips any ability `Gate::has()` already reports.

### Gate the route, not just the ability

Authorization is necessary and not sufficient. The route wants a rate limiter and CSRF protection as well, and the UI wants the button disabled while `updateState()` reports `queued` or `in_progress`. A route that calls `performUpdate()` inline rather than dispatching also occupies a PHP-FPM worker for the whole run and keeps occupying it after the caller disconnects (see [Long-running updates](#long-running-updates-and-interrupted-processes)), which makes it a cheap way to exhaust the worker pool if the guards above are missing.

## Integrity verification (fail-closed by default)

As of 2.5.4, `ApplicationUpdateManager::maybeVerifyChecksum()` **throws** `UpdateException::checksumRequired()` when the update source does not advertise a SHA-256 hash. This closes the "download and execute arbitrary remote code without integrity verification" gap that existed through 2.5.3.

Hosts on trusted networks or air-gapped mirrors that intentionally accept the risk can opt back into the pre-2.5.4 warn-and-continue behavior:

```php
// config/cms.php (or via env)
'updates' => [
    'allow_unverified_updates' => env('CMS_UPDATES_ALLOW_UNVERIFIED', false),
],
```

Those hosts still get the original warning log for every unverified update.

### What the checksum does and does not protect against

Be clear about the guarantee. The digest comes from the **same origin and trust
domain** as the archive — a `*.sha256` sidecar on the same release, a `SHA-256:`
line in the release description, or the same JSON document that supplied
`download_url`. It is therefore an **integrity** check: it catches truncation,
CDN corruption, and partial downloads. It is **not** an authenticity check. It
does not protect against a compromised update server, a compromised
release-editor account, or a plaintext-HTTP MITM. There is no signature
verification in this module.

As of 2.7.1, `GitHubUpdateSource` discovers checksums the same way
`GitLabUpdateSource` does — a `{asset}.sha256` release asset first, falling back
to a `SHA-256:` marker in the release body. Before that it never populated
`sha256` at all, so with shipped defaults every GitHub-sourced update threw
`checksumRequired` and the only workaround was enabling
`allow_unverified_updates` permanently.

### Transport (2.7.1)

Release archives are downloaded over `https` only, and **redirects are
constrained to the same scheme** — an `https` URL that 302s to `http` is
refused rather than silently downgraded. This matters because the update
pipeline is by design a remote-code-execution channel: it overwrites PHP files
and then runs `composer install`, which executes `post-install-cmd` scripts from
the just-overwritten `composer.json`.

Air-gapped mirrors on a trusted network that genuinely cannot serve https can
opt out with `cms.updates.allow_insecure_transport` (env:
`CMS_UPDATES_ALLOW_INSECURE_TRANSPORT`). The updater logs a warning and
proceeds.

### Target version and downgrades (2.7.1)

When `--target-version` pins a release other than the latest, the checksum is
resolved for **that** release rather than for the latest one. Previously the
pinned archive was compared against the latest release's digest, which failed
closed on its own but installed an unverified archive when combined with
`allow_unverified_updates`.

A target that is not newer than the installed version is refused, because
installing an older release re-introduces every vulnerability fixed since it
shipped and migrations are not reversed:

```bash
php artisan update:perform --target-version=2.6.0                    # refused
php artisan update:perform --target-version=2.6.0 --allow-downgrade   # explicit opt-in
```

## Concurrent updates (2.7.1)

`performUpdate()` takes an exclusive `flock` on a sentinel beside the state file
before doing anything. A second update — a double-clicked admin button, or the
scheduled `auto_update` racing an operator's `update:perform` — is refused with
`UpdateException::updateAlreadyRunning()` rather than extracting over the first
one. Interleaved runs produce a tree no rollback repairs, because the second
run's backup snapshots the first run's half-applied state.

The lock is deliberately **not** a cache lock: step 8 runs `cache:clear` and
would drop it mid-run. As a second line of defence, a persisted `in_progress`
record whose recorded PID is still alive also blocks a new run. A stale marker
from a `kill -9`'d run does not wedge the updater — liveness is checked, not
just presence.

## Metadata request path

The feed check, single-release lookup, SHA-256 sidecar fetch, and custom JSON endpoint are issued through the internal `MetadataClient` — a raw `GuzzleHttp\Client` that bypasses Laravel's HTTP factory. This is deliberate: any userland `RequestSending`/`ResponseReceived` listener (Herd Pro's `HttpClientWatcher`, Telescope, Debugbar, custom monitoring) can block or corrupt the metadata request lifecycle, and a wedged Herd dump-server socket used to hang the updater until `max_execution_time` instead of the ~200ms round-trip.

Retry semantics for the custom JSON endpoint (5xx retry, config-driven attempt count) are preserved.

The download path still uses `Http::sink()` — it's shielded by a `withResponseMiddleware` that closes the sink body before Laravel dispatches `ResponseReceived`, so a listener calling `body()` gets an empty string instead of pulling the archive into memory.

## Composer discovery

`ApplicationUpdateManager::runComposerInstall()` resolves the composer command in this order:

1. **`COMPOSER_BINARY` environment variable.** Absolute path to composer. When set, the framework builds the command as `{PHP_BINARY} {binary} install --no-dev --no-interaction --optimize-autoloader`, so PHP-FPM's `PATH` never has to resolve composer's `#!/usr/bin/env php` shebang.

2. **`cms.updates.composer_install_command` config value**, when it differs from the shipped default. Full shell string — set this when you need extra flags, a prepended `PATH`, or a bespoke composer wrapper. Backwards-compatible escape hatch for hosts that already carry a custom command.

3. **Auto-discovery** across common install paths, first hit wins:
   - `~/Library/Application Support/Herd/bin/composer`
   - `/usr/local/bin/composer`
   - `/opt/homebrew/bin/composer`
   - `~/.composer/vendor/bin/composer`
   - `~/.config/composer/vendor/bin/composer`
   - `/usr/bin/composer`

   Laravel Herd's bundled composer leads the list for the same reason the CLI PHP list leads with Herd's `php`: a host that uses Herd for both FPM and CLI stays on a single toolchain. Herd's composer is a `#!/usr/bin/env php` script rather than a standalone binary, which is fine — the command is built as `{CLI PHP} {binary} install ...` regardless. Both lists derive the Herd `bin/` directory from one `herdBinPath()` helper so they can't drift apart.

   When discovery fails, the framework logs a structured `Log::warning` with the `is_file()`/`is_executable()` result for each candidate path, the current `php_sapi`, and a pointer at the fix. `UpdateException::composerBinaryNotFound()` renders the same per-candidate stat outcome in the exception message, so operators can distinguish a wrong-path failure from a PHP-FPM sandboxed-stat failure (macOS Herd Pro sandboxes `/opt/homebrew/*`; chrooted FPM pools and restrictive `open_basedir` behave the same way) without digging through the log.

4. **Bare `composer install ...`** — the pre-2.5.3 behavior, kept as a final fallback.

## PHP interpreter used to invoke composer

Under PHP-FPM, `PHP_BINARY` points at the FPM daemon binary, which prints usage and exits 64 when handed the composer PHAR. Prior to 2.5.4 that produced the misleading `"Composer install failed. Output: ."` symptom on Herd hosts — composer was discovered fine, but the interpreter used to invoke it was the wrong SAPI.

`resolvePhpBinary()` now walks CLI-shaped candidates and rejects anything whose basename smells like `-fpm`/`-cgi`. Resolution order:

1. **`CMS_PHP_BINARY` environment variable** (absolute path). Set this when you need to pin a specific PHP CLI binary — a Herd version, a Homebrew-managed 8.4, a custom build.
2. **`PHP_BINARY` when the current SAPI is CLI.** Short-circuit for artisan/console callers.
3. **Auto-discovery** across common CLI PHP locations (Herd, Homebrew, system).

The resolved CLI PHP is used to build both the install command and the rollback `--version` pre-check. When the pre-check fails, `UpdateException::composerVerificationFailed()` surfaces the resolved binary, PHP interpreter, exit code, and captured output — so future misdiagnoses don't repeat.

### When you need the escape hatch

Laravel Herd's PHP-FPM pool ships a minimal `PATH` (`/usr/bin:/bin:/usr/sbin:/sbin`) that doesn't include Homebrew or Herd's own composer. Auto-discovery covers both `~/Library/Application Support/Herd/bin/composer` and `/opt/homebrew/bin/composer`, so a stock Herd or Homebrew install needs no configuration; if your composer lives elsewhere, either point `COMPOSER_BINARY` at it or set a full command:

```php
// AppServiceProvider::boot()
config()->set(
    'cms.updates.composer_install_command',
    'PATH=/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin '
        . '/opt/homebrew/bin/composer install --no-dev --no-interaction --optimize-autoloader',
);
```

## Rollback error surfacing

When an update fails, the framework rolls back to the pre-update backup. Two behaviors matter for diagnostics:

- **Before invoking rollback's `composer install`, the framework runs `{binary} --version` with a 10-second timeout.** If that fails, you get `UpdateException::composerBinaryNotFound` naming the paths inspected and the `COMPOSER_BINARY` override — not the vague "Manual intervention required".

- **When that probe fails, the diagnosis depends on whether PHP can see the binary on disk.** If `is_file()` also reports false, you get `UpdateException::configuredComposerBinaryMissing` naming the configured path — such a path can only have come from `COMPOSER_BINARY` / `cms.updates.composer_binary`, since auto-discovery only ever returns a path it has already stat'd. Otherwise you get `composerVerificationFailed` as before, with its exit code, captured output, and `CMS_PHP_BINARY` hint. Previously every failure took the second form, blaming the PHP interpreter on hosts where the interpreter had resolved correctly and the composer path was the sole fault.

  The stat runs **after** the probe, never as a pre-flight gate — deliberately. A path PHP cannot `stat()` may still be perfectly reachable by the shelled-out child under PHP-FPM sandboxing, and pointing `COMPOSER_BINARY` at such a path is the documented workaround for exactly that case (see the discovery section above). Gating the probe on `is_file()` would close that escape hatch. Once the probe has failed too, the path is overwhelmingly just wrong.

- **When rollback itself fails, the resulting exception preserves the original update-failure message.** You'll see both `Rollback failed: {rollback error}. Original update error: {original error}. Manual intervention required.` — no more losing the actual first-error text.

## Extraction safety

`ApplicationUpdateManager::extractUpdate()` streams each ZIP entry via `fopen()`/`fwrite()` (rather than `ZipArchive::extractTo()`) so a single large file can't OOM mid-extraction. Two guards keep that path safe:

- **Zip-slip protection.** Entries whose normalized path starts with `/` or contains `..` segments are rejected before the target directory is created. After path assembly, `realpath()` verifies the resolved parent still sits under the extraction root before opening the write stream. Rejected entries are logged and skipped — a crafted `release-root/../../../etc/cron.d/x` can no longer escape the install root.
- **Path canonicalization (2.7.1).** Entry names are canonicalized — split on `/`, empty and `.` segments dropped, any `..` segment rejected — *before* both the exclusion check and the containment check. Without this, an entry named `./.env` was simply a different string from `.env` and slipped past `exclude_from_update` entirely, while `realpath( dirname( '/base/./.env' ) )` resolves to `/base` so the containment check passed too. The reachable targets were the ones the exclusion list is believed to protect: `.env`, `vendor/`, `bootstrap/cache/*.php`, and the SQLite database.
- **Exclusion matches on path-segment boundaries (2.7.1).** `isPathExcluded()` used a bare string prefix, so `storage-helpers.php` matched `storage` and was skipped by both the backup and the extraction — neither snapshotted nor ever updated.
- **fopen/fread failures surface as rollback triggers.** A failed `fopen('wb')` or `fread()` used to `continue`/`break` silently, leaving a partial install on disk that failed to boot on the next request with no obvious cause. Failures are now logged with the entry + errno and thrown via `UpdateException::extractionEntryFailed()`, so `performUpdate()`'s catch block rolls back to the pre-update snapshot.
- **Short writes and close failures too (2.7.1).** `fwrite()`'s return value and `fclose()`'s are both checked. A disk filling mid-extraction previously produced a short write with no exception: the entry counted as extracted and the update proceeded into `composer install` and migrations over truncated PHP files. The partial file is removed before the exception propagates.
- **Symlinks and permissions (2.7.1).** A target that is an existing symlink (or any non-regular file) is skipped rather than followed and truncated — relevant on Envoyer/Forge/Deployer layouts where `storage` or `bootstrap/cache` are symlinked to shared directories. Directory entries are validated *before* `mkdir -p` runs rather than after. Archive-supplied permissions are clamped with `& ~0022`, so a `0777` `.php` file cannot land under the docroot writable by a neighbouring tenant.

## Rollback safety (2.7.1)

- **`exclude_from_update` applies on restore.** Rollback previously restored every entry in the backup ZIP with no filter, so an archive carrying `.env` or `vendor/autoload.php` replaced the live copies — and the `composer install` that follows executes scripts from the restored `composer.json`.
- **Backups outside the configured directory are refused.** `update:rollback` with no argument picks the newest `backup-*.zip` by mtime. Any other vulnerability yielding a file write under `storage/` would otherwise let an attacker plant a backup and wait for a restore. Pass `--allow-external` to restore from a path outside `cms.updates.backup_path`.
- **Rollback is skipped once the update is fully applied.** A failure at steps 8-10 (`cache:clear`, cleanup, `up`) happens *after* the code and schema are updated, so restoring the snapshot would discard a working install and leave old code against a new schema. Those failures log and point at `update:status`, which prints the commands to finish forward.
- **A failed rollback is reported as such.** The `Failed` status label used to read `Failed (rolled back)` unconditionally, so an operator facing the most dangerous state this updater produces was told the tree had been restored. A separate `rolled_back` field now records `true` / `false` / `null` (not attempted), and `update:status` renders each distinctly.

## Dependency installation: `composer.lock` ships with the release

**Releases must ship a committed `composer.lock` that matches their `composer.json`.** The updater extracts both and runs `composer install` against the pair, so the host installs the exact dependency set the release was built and tested against rather than re-resolving the tree on production hardware at update time.

`composer.lock` is therefore **not** in the `exclude_from_update` default. It used to be, annotated `// Rebuilt via composer install` — but `composer install` only ever *reads* a lock file. It never writes one; only `composer update` / `composer require` do. Excluding the lock while letting `composer.json` be overwritten left the two out of sync and aborted step 6 on **every release that changed any dependency constraint, on every host**. Releases that changed no dependencies still worked, which is why it went unnoticed until the first constraint bump.

For the `auto_archive` GitLab strategy this is free — the archive is the repository tree, and an application should commit its lock. For `release_asset`, whoever builds the ZIP must include the lock.

The pre-update snapshot includes `composer.lock` for the same reason, so a rollback restores the old `composer.json` and old lock together.

### Lock-sync diagnostic

Before invoking composer, the framework compares the on-disk `composer.lock`'s `content-hash` against a hash computed from `composer.json` using composer's own algorithm, to detect whether the two have diverged. This is a **diagnostic, not a gate**: it does not abort the update.

`composer install` installs from the lock even when the `content-hash` is stale — it only emits a warning — and hard-fails solely when the lock cannot satisfy `composer.json` (a required package missing from the lock, or a constraint it violates). An earlier version (2.7.1, #255) aborted step 6 and triggered a full rollback on *any* divergence, which turned a release that shipped an old-but-satisfying lock into a failed update composer would have installed cleanly (#264). It no longer pre-empts composer.

What it keeps is the *diagnosis*. When composer itself fails over a genuinely unsatisfiable lock, its own message is actively misleading:

```
- Required package "artisanpack-ui/cms-framework" is in the lock file as "2.5.4" but that
  does not satisfy your constraint "^2.7.0". This usually happens when composer files are
  incorrectly merged or the composer.json file is manually edited.
```

Nothing was merged and nothing was hand-edited — but that sentence sends the operator hunting for both. The remaining ways to reach this state are a release that shipped no lock, or a host whose `exclude_from_update` override still lists `composer.lock`. When a divergence was detected, `UpdateException::composerInstallFailed` wraps composer's own output with that accurate explanation — but only after composer, not the framework, has decided the update cannot proceed.

The check **fails open**: a missing `composer.json`, a missing or unparseable `composer.lock`, or a lock carrying no `content-hash` records no divergence and is left for composer to adjudicate. Set `cms.updates.verify_composer_lock_sync` to `false` (env `CMS_UPDATES_VERIFY_LOCK_SYNC`) to skip it entirely — for instance if a future composer release changes the content-hash algorithm before the framework catches up.

### Stale-lock recovery (2.8.0)

The lock-sync fix above has a reachability gap of its own (#273): it ships **inside** an update, and the broken lock it fixes is exactly what aborts that update. An install still on an affected line (≤ 2.7.0) therefore has no updater-reachable path to the fix — the population that needs it is precisely the population that cannot receive it. Recovery otherwise needs a shell on the server, which for a click-to-update product is the wrong failure mode.

So when `composer install` aborts *because* the extracted `composer.json` requires a dependency set the still-in-place previous release's `composer.lock` cannot satisfy, the updater does not immediately roll back. It parses the packages composer named as unsatisfiable and runs a **targeted** `composer update <those packages> --with-all-dependencies`, re-resolving the flagged packages and their dependency closure (everything outside it stays pinned at the lock) so the resulting lock satisfies `composer.json` and the update proceeds. `--with-all-dependencies` is what lets a flagged package's new version pull the transitive bumps it needs; without it composer would leave those pinned and fail to resolve the very case recovery exists to unstick.

Every guard fails *toward* the original safe rollback. The recovery runs only after composer itself has failed on an unsatisfiable lock — never pre-emptively — and only when composer named at least one package and `composer_install_command` is not an operator override that cannot be rewritten into an `update`. If the recovery `composer update` also fails, the update rolls back exactly as before.

Recovery keys off composer's own "Required package" diagnosis, **not** the content-hash check above, so it is independent of `verify_composer_lock_sync`: disabling that hash check (only enriches the failure message) does not disable recovery. That is deliberate — the documented reason to disable the hash check is a future composer that changes the hash algorithm, which is exactly when a stale lock is most likely and recovery most wanted. `cms.updates.recover_stale_lock` is the only switch for recovery.

This does mean the recovered packages land on a freshly resolved version rather than the one the release's lock pinned — the documented trade-off from unexcluding the lock, but scoped here to only the packages composer flagged. A host that treats the tested dependency set as inviolable, and would rather fail loudly than re-resolve any package on production at update time, sets `cms.updates.recover_stale_lock` to `false` (env `CMS_UPDATES_RECOVER_STALE_LOCK`) to keep the pre-#273 behavior.

**Recovering an install by hand.** Recovery is automatic on 2.8.0+, but an install *arriving* at 2.8.0 from an affected line first has to run the update that carries it — and that first update is the one the stale lock can block, before 2.8.0's recovery code is in place to catch it. If the in-app updater aborts on a message like the one above, run this once on the host and then retry the update:

```
composer update artisanpack-ui/cms-framework --with-all-dependencies
```

On many hosts this resolves cleanly on its own, because a constraint like `^2.5.3` already admits 2.7.1+ — the lock was merely stale, not incompatible. `--with-all-dependencies` matches what the automatic recovery runs and lets any transitive bump the new version needs come along; drop it if you want to hold every other package at its locked version. After the install is on 2.8.0+, `recover_stale_lock` handles subsequent updates without the manual step.

## Long-running updates and interrupted processes

A full update — download, extract, `composer install` across a real dependency tree, migrate — routinely runs for several minutes. PHP's `max_execution_time` defaults to **30 seconds** under PHP-FPM, which is the path the admin UI uses. Three guards keep that survivable:

- **`performUpdate()` and `rollback()` call `set_time_limit( 0 )` and `ignore_user_abort( true )` up front.** The `Process::timeout()` in `runComposerInstall()` only bounds the composer *child*; without this the parent request was killed roughly twenty times sooner than composer's own budget allowed for. `ignore_user_abort()` covers the operator closing the browser tab. When a host has `set_time_limit` in `disable_functions`, the framework logs a warning naming `php artisan update:perform` as the supported path instead.

- **Maintenance mode is lifted by a shutdown handler when the process dies anyway.** An execution-time or out-of-memory fatal is raised at shutdown, not thrown, so `performUpdate()`'s `catch` never runs — no rollback, and step 10 (`disableMaintenanceMode()`) never executes. The site then serves 503 to every visitor with no error in the UI and no automatic way back. `enableMaintenanceMode()` now registers a shutdown guard that lifts maintenance mode in that case and logs a `critical` entry naming the step it died on. If `artisan up` itself fails (common when shutting down after an OOM fatal), the guard removes `storage/framework/down` directly.

  Leaving the site up on a possibly half-applied install is a real trade-off, so `cms.updates.lift_maintenance_on_interrupt` is **step-aware** by default (`'step_aware'`). The guard lifts maintenance mode for deaths in steps 1-4 (nothing has touched the tree) and steps 8-10 (the code and schema are fully applied), but keeps the site **down** for deaths in steps 5-7 — extract, composer install, migrations — where a half-extracted tree or half-run migration set would otherwise go back on the public internet and could silently disable authorization. Set the config to `true` to always lift regardless of step (the pre-2.8 behaviour), or `false` to fail closed and keep the site down after any interruption until an operator has verified it by hand.

  This cannot help against `kill -9`, which runs no shutdown handlers. The persisted state marker below covers that case.

- **Each step is persisted to a state file, so a killed update is diagnosable.** Before this there was no way to distinguish "update in progress", "update died at step 6", and "the site was manually put into maintenance mode".

### `php artisan update:status`

Reports the most recent run — the step it reached, the versions involved, the recorded error, and, for an interrupted run, the outstanding steps with the command for each:

```console
$ php artisan update:status
✗ Interrupted (process died mid-update)

  From version    0.2.4
  To version      0.3.0
  Last step       7/10 — Run database migrations
  ...

The update process died before finishing. The install may be half-applied.

These steps had not completed. Run them in order to finish the update:

  7. Run database migrations
     php artisan migrate --force
  8. Clear application caches
     php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
  10. Disable maintenance mode
     php artisan up
```

It exits non-zero when the last run failed or was interrupted, so it composes with health checks — in **both** output modes, so `php artisan update:status --json || alert` is a valid monitoring idiom. (Before 2.7.1 the `--json` branch returned success unconditionally and never alerted on a dead update.) `--json` emits the raw record for an admin UI; `--clear` discards it after reporting. Host applications can read the same record programmatically via `ApplicationUpdateManager::updateState()`.

A run interrupted during download or extraction is not resumable — the application tree may be partially overwritten — so the command points at the pre-update snapshot in `storage/backups/application/` instead of a resume checklist.

The marker is a flat JSON file rather than a cache entry on purpose: step 8 runs `cache:clear`, and the database cache driver is unavailable while step 7's migrations are mid-flight. `storage/` is in `exclude_from_update`, so it survives extraction of the new release.

> **Note:** the guards above make the HTTP path safe to *fail*; they don't make it a good place to run a multi-minute job. Run updates from the CLI with `php artisan update:perform`, where `max_execution_time` is `0` and there is no gateway timeout to hit — or, when the trigger has to be an HTTP request, dispatch to a queue worker with [`dispatchUpdate()`](#queued-updates-280).

### Keep `performUpdate()` behind admin authorization

`Gate::authorize( UpdateCapability::PERFORM )`, per [Authorization](#authorization-280). Lifting `max_execution_time` and setting `ignore_user_abort( true )` means an inline HTTP-triggered update occupies a PHP-FPM worker for as long as the update takes, and keeps occupying it even if the caller disconnects. The individual phases stay bounded (`download_timeout`, `composer_timeout`), so the total is bounded in practice — but an update endpoint reachable without authorization would be a much cheaper way to exhaust the worker pool than it was at 30 seconds. This has always been an operator responsibility; the guards raise the cost of getting it wrong.

## Queued updates (2.8.0)

The guards above make an HTTP-triggered update *survivable*. They do not make it *appropriate*. An update run inline from a POST handler still:

- occupies a PHP-FPM worker for the full run, and keeps occupying it after the caller disconnects;
- is subject to gateway timeouts — nginx's `proxy_read_timeout`, load balancers, Cloudflare's 100s — that no userland call can override;
- is subject to FPM's `request_terminate_timeout`, which `set_time_limit()` cannot override;
- gives the operator no feedback beyond polling the state file.

`ApplicationUpdateManager::dispatchUpdate()` is the supported answer. It pushes a `PerformUpdateJob` onto a queue and returns immediately; the endpoint polls `updateState()` for progress:

```php
$manager = app( ApplicationUpdateManager::class );

Gate::authorize( UpdateCapability::PERFORM );

$manager->dispatchUpdate();              // latest
$manager->dispatchUpdate( '2.9.0' );     // pinned
$manager->dispatchUpdate( '2.6.0', true ); // pinned, downgrade opted in

$manager->updateState();                 // poll this — status goes queued → in_progress → completed/failed
```

`php artisan update:perform --queue` does the same thing from the console, after the usual confirmation prompt.

**`performUpdate()` is unchanged and still supported as a direct call.** Integrators calling it inline are unaffected by this job existing; the job simply calls it on a worker instead of in a request.

### The `sync` driver is refused, not tolerated

Dispatching to the `sync` driver executes the job inline in the dispatching process — the exact multi-minute blocking request queueing exists to avoid, reintroduced silently while the feature looks from the outside like it works. `dispatchUpdate()` therefore inspects the resolved connection's driver first and **throws** `UpdateException::updateQueueUnusable()` rather than degrading:

| Driver | Behavior |
|--------|----------|
| Any real driver (`database`, `redis`, `sqs`, …) | Dispatched. |
| `sync` | Refused, unless `cms.updates.queue.allow_sync` is `true` — then it warns and proceeds. |
| `null` | Refused. It discards every job it is given, so the update would never run. `allow_sync` does not rescue it. |
| Unconfigured connection | Refused, naming `cms.updates.queue.connection`. |

What the guard **cannot** detect is a perfectly well-configured connection with no worker consuming it — that is not knowable from config. That case shows up as a record stuck at `queued`, which `update:status` names explicitly and answers with the `queue:work` invocation to run.

### Raise `retry_after` — this is the one you have to set by hand

The job's own `$timeout` defaults to `download_timeout` + `composer_timeout` + a 900s buffer for the steps that have no timeout of their own (backup, extraction, migrations) — 1,800s with the shipped values. Override it with `cms.updates.queue.timeout`.

That timeout travels with the job and **takes precedence over the worker's `--timeout`**: `Worker::timeoutForJob()` is `$job->timeout() ?? $options->timeout`, so the worker flag is only a fallback for jobs that carry no timeout. A worker started with a short `--timeout` will not cut an update short.

**`retry_after` is the setting that will bite you.** It is the queue's "this reserved job must have died, hand it to someone else" timer, and Laravel ships **90 seconds** for the `database`, `redis` and `beanstalkd` connections — far shorter than any real update. Left alone, the queue redelivers the update 90 seconds in while the first worker is still running `composer install`.

`$tries = 1` does not save you from that; it is what makes it land badly. The duplicate exceeds max attempts, so the worker fails it *without* ever calling `handle()` — it goes straight to `failed()` carrying a `MaxAttemptsExceededException` against a run that is perfectly healthy.

So `dispatchUpdate()` refuses to dispatch when `retry_after` is not greater than the job timeout, with `UpdateException::updateQueueRetryTooShort()` naming both numbers:

```php
// config/queue.php
'database' => [
    'driver'      => 'database',
    'retry_after' => 1900,   // above cms.updates.queue.timeout
],
```

```bash
php artisan queue:work --queue=updates --tries=1
```

Only connections that actually carry the setting are checked; SQS expresses the same idea as a queue-side visibility timeout that is not readable from the app.

### Concurrency, and what happens when a queued update dies

- **A second dispatch is refused loudly.** `dispatchUpdate()` takes the job's unique lock itself rather than leaving it to `PerformUpdateJob::dispatch()`, which takes the same lock and then *silently returns* when it cannot get it — the caller would be told the update was queued and nothing would ever run it. A double-clicked admin button gets `UpdateException::updateAlreadyQueued()` instead.
- **A `queued` record stops blocking once it is older than the job timeout**, so a host that dispatched before starting a worker is not wedged by its own first attempt.
- **`$tries` is 1 and not configurable.** A retry would restart the update at step 1 over a tree the previous attempt had already partly overwritten — extracting a second release over a half-extracted first one, which is the interleaving the `flock` sentinel exists to prevent, reintroduced by the queue rather than by a concurrent caller.
- **The `flock` sentinel is still the real guarantee.** The job's `ShouldBeUnique` lock lives in the cache, and step 8 of the update runs `cache:clear` — so it is dropped near the end of every successful run. The [concurrency guard](#concurrent-updates-271) inside `performUpdate()` is unaffected by any cache flush.
- **A killed job does not leave the site down.** `PerformUpdateJob::failed()` reconciles the persisted record: a run killed by the worker timeout is stamped `interrupted` and maintenance mode is lifted according to `cms.updates.lift_maintenance_on_interrupt` — including the step-aware default, which keeps the site down when the job died in a half-applied step (5-7) — and a job that failed before the update started is stamped `failed` rather than left claiming `queued` forever. A run that already recorded its own outcome is left alone, so the real error is never replaced by the worker's generic one.
- **Reconciliation only ever touches its own run.** A failing job can be handed a record it does not own — losing the `flock` race throws `updateAlreadyRunning` before `performUpdate()`'s bookkeeping starts, and a `retry_after` redelivery arrives having done no work at all. Both would otherwise mark a healthy in-flight run `interrupted` and run `artisan up` on a site that is mid-extraction. `failed()` checks the recorded PID for liveness first and leaves other processes' runs alone.
- **`update:status --clear` really resets.** The record lives in `storage/` and the dispatch lock lives in the cache, so they can desynchronise. Clearing the record releases the lock too — otherwise clearing a stuck `queued` record left dispatch refusing with "an update is already queued" while `update:status` reported that none had ever been recorded, recoverable only by `cache:clear` or by waiting out the TTL.

### Wiring the admin UI

Dispatch, then poll `updateState()`. The `status` field moves through:

| Status | Meaning |
|--------|---------|
| `queued` | On the queue, no worker has claimed it. Nothing on the installation has changed. |
| `in_progress` | Running. `step` / `step_number` / `step_label` name the step in flight. |
| `completed` | Finished. |
| `failed` | The updater caught the error; `rolled_back` says what became of the snapshot. |
| `interrupted` | The process died before the catch block could run. |

A run dispatched through the queue also carries `queued_at`, `queue_connection` and `queue_name`, and keeps them for the whole run — "which worker is meant to be running this" stays the operative question until it finishes. Disable the update button while `status` is `queued` or `in_progress`.

## Cached update info and out-of-band version bumps

`UpdateChecker::checkForUpdate()` caches the resolved `UpdateInfo` value object under `cms.{type}.{slug}.update_check` for `cms.updates.cache_ttl` seconds (default 43,200s / 12h). The cached object contains both the feed's `latestVersion` and a snapshot of `config('app.version')` taken when the cache was populated.

When the host's installed version changes *out-of-band* (a manual `composer install` on a release zip, an unzip-over-site, a deploy script) the framework keeps the cached feed data honest in two ways:

- **`UpdateChecker::checkForUpdate()` discards the cached `UpdateInfo` when the cached `currentVersion` snapshot no longer matches `config('app.version')`** and re-fetches from the source. No stale positive for up to 12h.
- **`UpdateInfo::hasUpdate()` reads `config('app.version')` at call time** rather than comparing against its own frozen `currentVersion`, so any cached instance still returns the correct answer even if the invalidation branch above hasn't fired yet. Falls back to the constructor value when the container has no bound `config`.

## Related config

| Key | Purpose |
|-----|---------|
| `cms.updates.composer_binary` | Absolute path to composer; priority-1 override, populated from `env('COMPOSER_BINARY')`. |
| `cms.updates.composer_install_command` | Full command; overrides discovery when non-default. |
| `cms.updates.composer_timeout` | Seconds to wait for composer install (default 600). |
| `cms.updates.allow_unverified_updates` | Opt-in to warn-and-continue when the source omits a SHA-256 checksum. Default `false`. Env: `CMS_UPDATES_ALLOW_UNVERIFIED`. |
| `cms.updates.backup_enabled` | Whether to snapshot before updating. |
| `cms.updates.exclude_from_update` | Paths preserved during extraction. Must **not** list `composer.lock` — see above. |
| `cms.updates.verify_composer_lock_sync` | Whether to check `composer.json`/`composer.lock` agreement before invoking composer. Default `true`. Env: `CMS_UPDATES_VERIFY_LOCK_SYNC`. |
| `cms.updates.recover_stale_lock` | Whether a `composer install` that aborts on a stale previous-release lock triggers a targeted `composer update` of the flagged packages instead of rolling back. Default `true`. Env: `CMS_UPDATES_RECOVER_STALE_LOCK`. |
| `cms.updates.state_path` | Where the step marker is written. Relative paths resolve against `storage_path()`. Default `framework/cms-update-state.json`. |
| `cms.updates.lift_maintenance_on_interrupt` | Whether the shutdown guard lifts maintenance mode when an update dies mid-flight. `'step_aware'` (default) lifts for steps 1-4 and 8-10 but stays down for the half-applied steps 5-7; `true` always lifts; `false` never lifts. Env: `CMS_UPDATES_LIFT_MAINTENANCE_ON_INTERRUPT`. |
| `cms.updates.allow_insecure_transport` | Permit downloading a release archive over plaintext http, including via redirect. Default `false`. Env: `CMS_UPDATES_ALLOW_INSECURE_TRANSPORT`. |
| `cms.updates.backup_path` | Where snapshots are written. Relative paths resolve against `storage_path()`. Also bounds which archives `update:rollback` will restore without `--allow-external`. |
| `cms.updates.queue.connection` | Connection a queued update is dispatched to. Null uses `queue.default`. Env: `CMS_UPDATES_QUEUE_CONNECTION`. |
| `cms.updates.queue.queue` | Queue name to push onto. Null uses the connection's default queue. Env: `CMS_UPDATES_QUEUE`. |
| `cms.updates.queue.timeout` | Seconds the worker allows the job. Null derives it from `download_timeout` + `composer_timeout` + 900s. Env: `CMS_UPDATES_QUEUE_TIMEOUT`. |
| `cms.updates.queue.allow_sync` | Opt in to dispatching onto the `sync` driver, which runs the update inline and blocks the caller. Default `false`. Env: `CMS_UPDATES_QUEUE_ALLOW_SYNC`. |

Environment variables:

| Var | Purpose |
|-----|---------|
| `COMPOSER_BINARY` | Absolute path to composer, exposed to `cms.updates.composer_binary` via `env()`. |
| `CMS_PHP_BINARY` | Absolute path to a CLI PHP binary; overrides `PHP_BINARY` when the updater invokes composer. |
| `CMS_UPDATES_ALLOW_UNVERIFIED` | Boolean; opts into warn-and-continue when the source omits a SHA-256 checksum. |
| `CMS_UPDATES_LIFT_MAINTENANCE_ON_INTERRUPT` | `'step_aware'` (default), `true`, or `false`. Step-aware keeps the site down only for interruptions in the half-applied steps 5-7; `false` leaves it down after any interruption; `true` always lifts. |
| `CMS_UPDATES_VERIFY_LOCK_SYNC` | Boolean; set `false` to skip the `composer.json`/`composer.lock` sync pre-flight check. |
| `CMS_UPDATES_RECOVER_STALE_LOCK` | Boolean; set `false` to roll back rather than run a targeted `composer update` when a stale previous-release lock aborts the install. |
| `CMS_UPDATES_ALLOW_INSECURE_TRANSPORT` | Boolean; set `true` to allow plaintext-http downloads on a trusted air-gapped mirror. |
| `CMS_UPDATES_QUEUE_CONNECTION` | Queue connection a queued update is dispatched to. |
| `CMS_UPDATES_QUEUE` | Queue name a queued update is pushed onto. |
| `CMS_UPDATES_QUEUE_TIMEOUT` | Seconds the worker allows a queued update before killing it. |
| `CMS_UPDATES_QUEUE_ALLOW_SYNC` | Boolean; set `true` to permit dispatching onto the blocking `sync` driver. |

## Command reference

| Command | Purpose |
|---------|---------|
| `php artisan update:check` | Report whether an update is available. |
| `php artisan update:perform` | Run the ten-step update. `--target-version=x.y.z` pins a release; `--allow-downgrade` permits a target that is not newer than the installed version; `--queue` dispatches it to a worker instead of running it here. |
| `php artisan update:rollback` | Restore a snapshot. Takes an optional path; defaults to the newest archive in `backup_path`. `--allow-external` permits a path outside that directory; `--force` skips the confirmation prompt. |
| `php artisan update:status` | Report the most recent run, including a queued run that no worker has claimed. Exits non-zero when it failed or was interrupted, in **both** output modes. `--json` emits the raw record; `--clear` discards it after reporting. |
