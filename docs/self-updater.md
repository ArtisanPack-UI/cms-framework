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

## Metadata request path

The feed check, single-release lookup, SHA-256 sidecar fetch, and custom JSON endpoint are issued through the internal `MetadataClient` — a raw `GuzzleHttp\Client` that bypasses Laravel's HTTP factory. This is deliberate: any userland `RequestSending`/`ResponseReceived` listener (Herd Pro's `HttpClientWatcher`, Telescope, Debugbar, custom monitoring) can block or corrupt the metadata request lifecycle, and a wedged Herd dump-server socket used to hang the updater until `max_execution_time` instead of the ~200ms round-trip.

Retry semantics for the custom JSON endpoint (5xx retry, config-driven attempt count) are preserved.

The download path still uses `Http::sink()` — it's shielded by a `withResponseMiddleware` that closes the sink body before Laravel dispatches `ResponseReceived`, so a listener calling `body()` gets an empty string instead of pulling the archive into memory.

## Composer discovery

`ApplicationUpdateManager::runComposerInstall()` resolves the composer command in this order:

1. **`COMPOSER_BINARY` environment variable.** Absolute path to composer. When set, the framework builds the command as `{PHP_BINARY} {binary} install --no-dev --no-interaction --optimize-autoloader`, so PHP-FPM's `PATH` never has to resolve composer's `#!/usr/bin/env php` shebang.

2. **`cms.updates.composer_install_command` config value**, when it differs from the shipped default. Full shell string — set this when you need extra flags, a prepended `PATH`, or a bespoke composer wrapper. Backwards-compatible escape hatch for hosts that already carry a custom command.

3. **Auto-discovery** across common install paths, first hit wins:
   - `/usr/local/bin/composer`
   - `/opt/homebrew/bin/composer`
   - `~/.composer/vendor/bin/composer`
   - `~/.config/composer/vendor/bin/composer`
   - `/usr/bin/composer`

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

Laravel Herd's PHP-FPM pool ships a minimal `PATH` (`/usr/bin:/bin:/usr/sbin:/sbin`) that doesn't include Homebrew or Herd's own composer. Auto-discovery handles `/opt/homebrew/bin/composer` cleanly; if your composer lives elsewhere, either point `COMPOSER_BINARY` at it or set a full command:

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

- **When rollback itself fails, the resulting exception preserves the original update-failure message.** You'll see both `Rollback failed: {rollback error}. Original update error: {original error}. Manual intervention required.` — no more losing the actual first-error text.

## Extraction safety

`ApplicationUpdateManager::extractUpdate()` streams each ZIP entry via `fopen()`/`fwrite()` (rather than `ZipArchive::extractTo()`) so a single large file can't OOM mid-extraction. Two guards keep that path safe:

- **Zip-slip protection.** Entries whose normalized path starts with `/` or contains `..` segments are rejected before the target directory is created. After path assembly, `realpath()` verifies the resolved parent still sits under the extraction root before opening the write stream. Rejected entries are logged and skipped — a crafted `release-root/../../../etc/cron.d/x` can no longer escape the install root.
- **fopen/fread failures surface as rollback triggers.** A failed `fopen('wb')` or `fread()` used to `continue`/`break` silently, leaving a partial install on disk that failed to boot on the next request with no obvious cause. Failures are now logged with the entry + errno and thrown via `UpdateException::extractionEntryFailed()`, so `performUpdate()`'s catch block rolls back to the pre-update snapshot.

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
| `cms.updates.exclude_from_update` | Paths preserved during extraction. |

Environment variables:

| Var | Purpose |
|-----|---------|
| `COMPOSER_BINARY` | Absolute path to composer, exposed to `cms.updates.composer_binary` via `env()`. |
| `CMS_PHP_BINARY` | Absolute path to a CLI PHP binary; overrides `PHP_BINARY` when the updater invokes composer. |
| `CMS_UPDATES_ALLOW_UNVERIFIED` | Boolean; opts into warn-and-continue when the source omits a SHA-256 checksum. |
