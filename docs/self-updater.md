# Self-Updater

The framework ships a self-updater that downloads a release archive, extracts it, runs `composer install`, executes migrations, and rolls back on failure. The bulk of it is transparent, but a few knobs matter on hosts where composer isn't on the PHP-FPM pool's `PATH`.

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

4. **Bare `composer install ...`** — the pre-2.5.3 behavior, kept as a final fallback.

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

## Cached update info and out-of-band version bumps

`UpdateChecker::checkForUpdate()` caches the resolved `UpdateInfo` value object under `cms.{type}.{slug}.update_check` for `cms.updates.cache_ttl` seconds (default 43,200s / 12h). The cached object contains both the feed's `latestVersion` and a snapshot of `config('app.version')` taken when the cache was populated.

When the host's installed version changes *out-of-band* (a manual `composer install` on a release zip, an unzip-over-site, a deploy script) the framework keeps the cached feed data honest in two ways:

- **`UpdateChecker::checkForUpdate()` discards the cached `UpdateInfo` when the cached `currentVersion` snapshot no longer matches `config('app.version')`** and re-fetches from the source. No stale positive for up to 12h.
- **`UpdateInfo::hasUpdate()` reads `config('app.version')` at call time** rather than comparing against its own frozen `currentVersion`, so any cached instance still returns the correct answer even if the invalidation branch above hasn't fired yet. Falls back to the constructor value when the container has no bound `config`.

## Related config

| Key | Purpose |
|-----|---------|
| `cms.updates.composer_install_command` | Full command; overrides discovery when non-default. |
| `cms.updates.composer_timeout` | Seconds to wait for composer install (default 600). |
| `cms.updates.backup_enabled` | Whether to snapshot before updating. |
| `cms.updates.exclude_from_update` | Paths preserved during extraction. |
