# Self-Updater

The framework ships a self-updater that downloads a release archive, extracts it, runs `composer install`, executes migrations, and rolls back on failure. The bulk of it is transparent, but a few knobs matter on hosts where composer isn't on the PHP-FPM pool's `PATH`.

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

### Lock-sync pre-flight check

Before invoking composer, the framework compares the on-disk `composer.lock`'s `content-hash` against a hash computed from `composer.json` using composer's own algorithm. On a mismatch it aborts with `UpdateException::composerFilesOutOfSync` naming the real cause.

This exists because composer's own diagnosis of that state is actively misleading:

```
- Required package "artisanpack-ui/cms-framework" is in the lock file as "2.5.4" but that
  does not satisfy your constraint "^2.7.0". This usually happens when composer files are
  incorrectly merged or the composer.json file is manually edited.
```

Nothing was merged and nothing was hand-edited — but that sentence sends the operator hunting for both. The remaining ways to reach this state are a release that shipped no lock, or a host whose `exclude_from_update` override still lists `composer.lock`; the framework's message says so.

The check **fails open**: a missing `composer.json`, a missing or unparseable `composer.lock`, or a lock carrying no `content-hash` is left for composer to adjudicate. Only a positively-detected mismatch aborts, so a false alarm can't block an update that would otherwise have installed cleanly. Set `cms.updates.verify_composer_lock_sync` to `false` (env `CMS_UPDATES_VERIFY_LOCK_SYNC`) to skip it entirely — for instance if a future composer release changes the content-hash algorithm before the framework catches up.

## Long-running updates and interrupted processes

A full update — download, extract, `composer install` across a real dependency tree, migrate — routinely runs for several minutes. PHP's `max_execution_time` defaults to **30 seconds** under PHP-FPM, which is the path the admin UI uses. Three guards keep that survivable:

- **`performUpdate()` and `rollback()` call `set_time_limit( 0 )` and `ignore_user_abort( true )` up front.** The `Process::timeout()` in `runComposerInstall()` only bounds the composer *child*; without this the parent request was killed roughly twenty times sooner than composer's own budget allowed for. `ignore_user_abort()` covers the operator closing the browser tab. When a host has `set_time_limit` in `disable_functions`, the framework logs a warning naming `php artisan update:perform` as the supported path instead.

- **Maintenance mode is lifted by a shutdown handler when the process dies anyway.** An execution-time or out-of-memory fatal is raised at shutdown, not thrown, so `performUpdate()`'s `catch` never runs — no rollback, and step 10 (`disableMaintenanceMode()`) never executes. The site then serves 503 to every visitor with no error in the UI and no automatic way back. `enableMaintenanceMode()` now registers a shutdown guard that lifts maintenance mode in that case and logs a `critical` entry naming the step it died on. If `artisan up` itself fails (common when shutting down after an OOM fatal), the guard removes `storage/framework/down` directly.

  Leaving the site up on a possibly half-applied install is a real trade-off. Set `cms.updates.lift_maintenance_on_interrupt` to `false` to fail closed and keep the site down until an operator has verified it by hand.

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

> **Note:** the supported path for a slow host remains `php artisan update:perform` from the CLI, where `max_execution_time` is `0` and there is no gateway timeout to hit. The guards above make the HTTP path safe to *fail*; they don't make it a good place to run a multi-minute job.

### Two consequences worth knowing about

**Keep `performUpdate()` behind admin authorization.** Lifting `max_execution_time` and setting `ignore_user_abort( true )` means an HTTP-triggered update now occupies a PHP-FPM worker for as long as the update takes, and keeps occupying it even if the caller disconnects. The individual phases stay bounded (`download_timeout`, `composer_timeout`), so the total is bounded in practice — but an update endpoint reachable without authorization would be a much cheaper way to exhaust the worker pool than it was at 30 seconds. This has always been an operator responsibility; the guards raise the cost of getting it wrong.

**Nothing serializes concurrent updates.** Two overlapping `performUpdate()` calls will fight over the same application tree and the same state marker; the second `begin()` overwrites the first. That was true before this change too, but the longer window makes an overlap easier to hit. If your admin UI can dispatch an update more than once, gate it — disable the button while `updateState()` reports `in_progress`, or take a lock around the call.

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
| `cms.updates.state_path` | Where the step marker is written. Relative paths resolve against `storage_path()`. Default `framework/cms-update-state.json`. |
| `cms.updates.lift_maintenance_on_interrupt` | Whether the shutdown guard lifts maintenance mode when an update dies mid-flight. Default `true`. Env: `CMS_UPDATES_LIFT_MAINTENANCE_ON_INTERRUPT`. |
| `cms.updates.allow_insecure_transport` | Permit downloading a release archive over plaintext http, including via redirect. Default `false`. Env: `CMS_UPDATES_ALLOW_INSECURE_TRANSPORT`. |
| `cms.updates.backup_path` | Where snapshots are written. Relative paths resolve against `storage_path()`. Also bounds which archives `update:rollback` will restore without `--allow-external`. |

Environment variables:

| Var | Purpose |
|-----|---------|
| `COMPOSER_BINARY` | Absolute path to composer, exposed to `cms.updates.composer_binary` via `env()`. |
| `CMS_PHP_BINARY` | Absolute path to a CLI PHP binary; overrides `PHP_BINARY` when the updater invokes composer. |
| `CMS_UPDATES_ALLOW_UNVERIFIED` | Boolean; opts into warn-and-continue when the source omits a SHA-256 checksum. |
| `CMS_UPDATES_LIFT_MAINTENANCE_ON_INTERRUPT` | Boolean; set `false` to leave the site in maintenance mode when an update dies mid-flight. |
| `CMS_UPDATES_VERIFY_LOCK_SYNC` | Boolean; set `false` to skip the `composer.json`/`composer.lock` sync pre-flight check. |
| `CMS_UPDATES_ALLOW_INSECURE_TRANSPORT` | Boolean; set `true` to allow plaintext-http downloads on a trusted air-gapped mirror. |

## Command reference

| Command | Purpose |
|---------|---------|
| `php artisan update:check` | Report whether an update is available. |
| `php artisan update:perform` | Run the ten-step update. `--target-version=x.y.z` pins a release; `--allow-downgrade` permits a target that is not newer than the installed version. |
| `php artisan update:rollback` | Restore a snapshot. Takes an optional path; defaults to the newest archive in `backup_path`. `--allow-external` permits a path outside that directory; `--force` skips the confirmation prompt. |
| `php artisan update:status` | Report the most recent run. Exits non-zero when it failed or was interrupted, in **both** output modes. `--json` emits the raw record; `--clear` discards it after reporting. |
