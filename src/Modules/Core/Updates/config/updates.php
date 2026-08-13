<?php

declare( strict_types = 1 );

return [
    /*
    |--------------------------------------------------------------------------
    | Authorization (read this before wiring an admin UI)
    |--------------------------------------------------------------------------
    |
    | `ApplicationUpdateManager` performs NO authorization of its own. It
    | cannot: the five `update:*` commands run from the console with no
    | authenticated user. Everything below configures *how* an update runs,
    | never *who* may run one.
    |
    | The framework ships no HTTP or Livewire trigger for updates. If you add
    | one, it is yours to gate. `performUpdate()` is by design a
    | remote-code-execution channel — it overwrites PHP files and then runs
    | `composer install`, which executes `post-install-cmd` scripts from the
    | just-overwritten `composer.json` — so an unauthenticated or
    | under-authorized endpoint is total compromise.
    |
    | Authorize against the abilities in `UpdateCapability`, which the
    | framework registers and denies by default:
    |
    |   Gate::authorize( UpdateCapability::PERFORM );   // cms.updates.perform
    |   Gate::authorize( UpdateCapability::ROLLBACK );  // cms.updates.rollback
    |   Gate::authorize( UpdateCapability::VIEW );      // cms.updates.view
    |
    | Grant them by seeding an RBAC permission whose slug matches, or by
    | defining the ability yourself in `AppServiceProvider::boot()`.
    |
    | Rate-limit and CSRF-protect the route as well. An update occupies a
    | PHP-FPM worker for the whole run and keeps occupying it after the caller
    | disconnects (`ignore_user_abort`), so a trigger reachable without those
    | guards is a cheap way to exhaust the worker pool. See
    | `docs/self-updater.md` for the full wiring.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Application Update Source
    |--------------------------------------------------------------------------
    |
    | URL to update source. Can be:
    | - GitHub repository URL (e.g., https://github.com/user/repo)
    | - GitLab repository URL (e.g., https://gitlab.com/user/repo)
    | - Custom JSON endpoint (e.g., https://example.com/updates.json)
    |
    | This URL will be overridden by the application's config.
    |
    */
    'update_source_url' => env( 'UPDATE_SOURCE_URL', null ),

    /*
    |--------------------------------------------------------------------------
    | Current Version Detection
    |--------------------------------------------------------------------------
    |
    | Where to find the current application version.
    |
    */
    'current_version_config_key' => 'app.version',

    /*
    |--------------------------------------------------------------------------
    | Update Process Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the update download and installation process.
    |
    */
    'download_timeout' => 300, // 5 minutes for large ZIPs

    /*
    |--------------------------------------------------------------------------
    | Composer Install Command
    |--------------------------------------------------------------------------
    |
    | The command the self-updater invokes to rebuild vendor dependencies
    | after extracting a release. The default assumes bare `composer` on the
    | PHP-FPM pool's PATH, which is fine on most production hosts.
    |
    | When it's *not* fine (Laravel Herd, PHP-FPM pools with a stripped-down
    | PATH, containers without composer in a well-known location), the
    | framework resolves the composer command in this order:
    |
    | 1. `COMPOSER_BINARY` environment variable — set this to an absolute path
    |    if you know where composer lives (e.g. `/opt/homebrew/bin/composer`)
    |    and want the framework to build the command as `{CLI PHP} {binary}
    |    install --no-dev --no-interaction --optimize-autoloader`. Set it in
    |    your Laravel `.env` (populated via `composer_binary` below) or as an
    |    OS-level env var visible to `getenv()` in the PHP-FPM pool.
    | 2. A non-default value for this config key — set this to a bespoke shell
    |    string if you need full control (custom flags, prepended PATH, etc.).
    | 3. Auto-discovery across common install paths
    |    (`~/Library/Application Support/Herd/bin/composer`,
    |    `/usr/local/bin/composer`, `/opt/homebrew/bin/composer`,
    |    `~/.composer/vendor/bin/composer`,
    |    `~/.config/composer/vendor/bin/composer`, `/usr/bin/composer`).
    |    Laravel Herd's bundled composer leads the list so a macOS Herd host
    |    stays on one toolchain for both FPM and CLI.
    | 4. This default command (bare `composer`).
    |
    | The PHP interpreter used to invoke composer is resolved from a CLI SAPI
    | binary — never from `PHP_BINARY` when the caller is PHP-FPM, because
    | `PHP_BINARY` under FPM points at the daemon and cannot execute PHARs.
    | Set `CMS_PHP_BINARY` if the framework picks the wrong CLI PHP on your
    | host.
    |
    */
    'composer_install_command' => 'composer install --no-dev --no-interaction --optimize-autoloader',

    /*
    |--------------------------------------------------------------------------
    | Composer Binary Path (priority-1 override)
    |--------------------------------------------------------------------------
    |
    | Absolute path to the composer binary the self-updater should invoke,
    | resolved via Laravel's `env()` so setting `COMPOSER_BINARY` in your
    | `.env` file works — which is the natural first move for a Laravel
    | operator and what the `composerBinaryNotFound` error message advertises.
    |
    | Laravel 11+'s default dotenv adapter populates `env()` and `$_ENV` but
    | does not `putenv()`, so `getenv('COMPOSER_BINARY')` returns `false` from
    | HTTP-request context under PHP-FPM even when `.env` has the value. This
    | config key bridges the gap: the framework reads this key first, then
    | falls back to `getenv()` for hosts that set the value at the OS level.
    |
    */
    'composer_binary' => env( 'COMPOSER_BINARY' ),

    'composer_timeout' => 600, // 10 minutes

    /*
    |--------------------------------------------------------------------------
    | Update State File
    |--------------------------------------------------------------------------
    |
    | Where the updater records which step of `performUpdate()` is in flight.
    | Relative paths resolve against `storage_path()`; an absolute path is used
    | verbatim.
    |
    | This is a plain file rather than a cache entry on purpose: step 8 of the
    | update runs `cache:clear`, and the database cache driver is unavailable
    | while step 7's migrations are mid-flight. `storage/` is in
    | `exclude_from_update` below, so the marker survives extraction.
    |
    | Read it with `php artisan update:status`, or programmatically via
    | `ApplicationUpdateManager::updateState()`.
    |
    */
    'state_path' => 'framework/cms-update-state.json',

    /*
    |--------------------------------------------------------------------------
    | Queued Updates
    |--------------------------------------------------------------------------
    |
    | `ApplicationUpdateManager::dispatchUpdate()` pushes the update onto a
    | queue instead of running it inline, which is the supported way to trigger
    | one from an HTTP request: an inline `performUpdate()` occupies a PHP-FPM
    | worker for several minutes, keeps occupying it after the caller
    | disconnects, and is subject to gateway timeouts and FPM's
    | `request_terminate_timeout` that no userland call can override.
    |
    | `connection` — the queue connection to dispatch to. Null uses
    | `queue.default`.
    |
    | `queue` — the queue name to push onto. Null uses the connection's default
    | queue. Give updates their own queue if your default queue is busy; an
    | update that sits behind a thousand emails is an update that has not
    | started.
    |
    | `timeout` — seconds the worker allows the job before killing it. Null
    | derives it from `download_timeout` + `composer_timeout` + a 900s buffer
    | for the steps that have no timeout of their own (backup, extraction,
    | migrations). This value travels with the job and **takes precedence over
    | the worker's `--timeout`** (`Worker::timeoutForJob()` falls back to the
    | worker flag only when the job carries no timeout of its own), so a worker
    | started with a short `--timeout` will not cut an update short.
    |
    | What does have to be set by hand is the connection's `retry_after` in
    | config/queue.php. That is the queue's "this reserved job must have died"
    | timer, and Laravel ships **90 seconds** for `database`, `redis` and
    | `beanstalkd` — far shorter than any real update. Left alone, the queue
    | hands the same update to a second worker 90 seconds in, while the first
    | is still running `composer install`. `dispatchUpdate()` refuses to
    | dispatch when `retry_after` is not greater than the timeout above, so
    | raise it past this value:
    |
    |     'retry_after' => 1900,   // config/queue.php, above the job timeout
    |
    |     php artisan queue:work --queue=updates --tries=1
    |
    | `allow_sync` — dispatching to the `sync` driver runs the job inline in the
    | dispatching process, which reintroduces exactly the blocking behavior
    | queueing exists to avoid while looking from the outside like the feature
    | works. `dispatchUpdate()` therefore refuses a `sync` connection unless
    | this is set to `true`, in which case it warns and proceeds. It does not
    | rescue the `null` driver or an unconfigured connection: neither ever runs
    | the job, so there is nothing to opt in to.
    |
    | Nothing here affects a direct `performUpdate()` call, which remains
    | supported and unchanged.
    |
    */
    'queue' => [
        'connection' => env( 'CMS_UPDATES_QUEUE_CONNECTION' ),
        'queue'      => env( 'CMS_UPDATES_QUEUE' ),
        'timeout'    => env( 'CMS_UPDATES_QUEUE_TIMEOUT' ),
        'allow_sync' => env( 'CMS_UPDATES_QUEUE_ALLOW_SYNC', false ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lift Maintenance Mode On Interrupted Updates
    |--------------------------------------------------------------------------
    |
    | An update that dies mid-flight (PHP fatal, out of memory, FPM's
    | `request_terminate_timeout`, the operator closing the tab) never reaches
    | step 10, so maintenance mode is never disabled and the site serves 503 to
    | every visitor until somebody notices and runs `php artisan up`.
    |
    | When enabled (the default), a shutdown handler lifts maintenance mode in
    | that case and logs a `critical` entry naming the step it died on. The
    | install may be half-applied, which is a real trade-off — but an
    | unattended outage the operator may not notice for hours is worse, and
    | `php artisan update:status` reports exactly what is outstanding.
    |
    | Set to `false` to fail closed and keep the site down until an operator
    | has verified the install by hand.
    |
    | Note this cannot help against `kill -9`, which runs no shutdown handlers.
    |
    */
    'lift_maintenance_on_interrupt' => env( 'CMS_UPDATES_LIFT_MAINTENANCE_ON_INTERRUPT', true ),

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic backups before updates.
    |
    */
    'backup_enabled'        => true,
    'backup_path'           => 'backups/application', // Relative to storage_path()
    'backup_retention_days' => env( 'BACKUP_RETENTION_DAYS', 30 ),

    /*
    |--------------------------------------------------------------------------
    | File Exclusions
    |--------------------------------------------------------------------------
    |
    | Files and directories to exclude from updates (preserved during extraction).
    | These are relative to base_path().
    |
    | `composer.lock` is deliberately NOT excluded. `composer install` only ever
    | *reads* a lock file — it never writes one — so excluding the lock while
    | letting `composer.json` be overwritten leaves the two out of sync and
    | aborts step 6 on every release that changes a dependency constraint. The
    | release's lock must land alongside its `composer.json` so the host
    | installs the exact dependency set the release was built and tested
    | against. See `verify_composer_lock_sync` below.
    |
    | (The `vendor` comment below is correct as written — that directory really
    | is rebuilt by `composer install`.)
    |
    */
    'exclude_from_update' => [
        '.env',
        '.env.example',
        'storage',
        'database/database.sqlite',
        'node_modules',
        'vendor', // Will be rebuilt via composer install
        '.git',
        '.gitignore',
        'bootstrap/cache/*.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Composer Lock Sync Pre-Flight Check
    |--------------------------------------------------------------------------
    |
    | Before invoking composer, verify that the on-disk `composer.lock` is in
    | sync with the on-disk `composer.json` by comparing the lock's
    | `content-hash` against a hash computed from `composer.json` using
    | composer's own algorithm.
    |
    | When they diverge, composer aborts with "This usually happens when
    | composer files are incorrectly merged or the composer.json file is
    | manually edited" — which sends the operator hunting for a merge conflict
    | or a hand-edit that never happened. The real cause is almost always a
    | release that shipped no `composer.lock`, or a host whose
    | `exclude_from_update` override still excludes it. This check names that
    | cause instead.
    |
    | The check fails *open*: a missing `composer.json`, a missing or
    | unparseable `composer.lock`, or a lock without a `content-hash` key is
    | left for composer itself to adjudicate. Only a positively-detected
    | mismatch aborts.
    |
    | Set to `false` to skip the check entirely — for instance if a future
    | composer release changes the content-hash algorithm and the framework has
    | not caught up.
    |
    */
    'verify_composer_lock_sync' => env( 'CMS_UPDATES_VERIFY_LOCK_SYNC', true ),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for update checks.
    |
    */
    'cache_enabled' => env( 'UPDATE_CACHE_ENABLED', true ),
    'cache_ttl'     => 43200, // 12 hours in seconds

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Settings
    |--------------------------------------------------------------------------
    |
    | Settings for HTTP requests to update sources.
    |
    */
    'http_timeout' => 15, // Seconds for JSON/API fetch
    'http_retries' => 3,

    /*
    |--------------------------------------------------------------------------
    | Update Check Schedule
    |--------------------------------------------------------------------------
    |
    | How often to check for updates (used by scheduled task).
    | Values: 'daily', 'twice_daily', 'weekly', 'disabled'
    |
    */
    'check_frequency' => env( 'UPDATE_CHECK_FREQUENCY', 'daily' ),

    /*
    |--------------------------------------------------------------------------
    | Automatic Updates
    |--------------------------------------------------------------------------
    |
    | Whether to automatically install updates when available.
    | CAUTION: Only enable if you trust the update source completely.
    |
    */
    'auto_update_enabled' => env( 'AUTO_UPDATE_ENABLED', false ),

    /*
    |--------------------------------------------------------------------------
    | Integrity Verification
    |--------------------------------------------------------------------------
    |
    | Whether to verify downloaded ZIPs using a SHA-256 checksum.
    |
    | Be clear about what this does and does not buy you. The digest comes
    | from the *same origin and trust domain* as the archive: for GitLab and
    | GitHub, a `*.sha256` sidecar on the same release or a `SHA-256:` line in
    | the release description; for custom JSON, the same document that
    | supplied `download_url`. It is therefore an **integrity** check — it
    | catches truncation, CDN corruption and partial downloads — and not an
    | **authenticity** check. It does not protect against a compromised update
    | server, a compromised release-editor account, or a plaintext-HTTP MITM.
    | There is no signature verification in this module.
    |
    */
    'verify_checksum' => true,

    /*
    |--------------------------------------------------------------------------
    | Allow Insecure Transport
    |--------------------------------------------------------------------------
    |
    | Release archives are downloaded over https only. TLS is one of the very
    | few things standing between a compromised update source and a
    | compromised host: this pipeline overwrites PHP files and then runs
    | `composer install`, which executes `post-install-cmd` scripts from the
    | just-overwritten `composer.json`.
    |
    | Set to `true` only for an air-gapped mirror on a trusted network that
    | genuinely cannot serve https. The updater logs a warning and proceeds.
    |
    */
    'allow_insecure_transport' => env( 'CMS_UPDATES_ALLOW_INSECURE_TRANSPORT', false ),

    /*
    |--------------------------------------------------------------------------
    | Allow Unverified Updates
    |--------------------------------------------------------------------------
    |
    | When `verify_checksum` is enabled and the update source does not
    | advertise a SHA-256 checksum, the updater fails closed by default and
    | aborts the update rather than installing arbitrary remote code without
    | an integrity check.
    |
    | Set to `true` only when consuming a trusted update source (e.g. an
    | air-gapped mirror or an internal feed) that intentionally does not
    | publish checksums. The updater will log a warning and proceed.
    |
    | CAUTION: This weakens the reachability story for extraction-time
    | vulnerabilities such as zip-slip. Do not enable in production unless
    | you fully control the source and transport.
    |
    */
    'allow_unverified_updates' => env( 'CMS_UPDATES_ALLOW_UNVERIFIED', false ),

    /*
    |--------------------------------------------------------------------------
    | GitLab Update Strategy
    |--------------------------------------------------------------------------
    |
    | Controls how `GitLabUpdateSource` resolves the download URL for a
    | release. Two strategies are supported:
    |
    | - 'auto_archive' (default): use the auto-generated source archive at
    |   `/api/v4/projects/{id}/repository/archive.zip?sha={tag}`. This works
    |   for any repository regardless of whether CI uploaded release assets.
    |
    | - 'release_asset': resolve the download URL from `release.assets.links[]`
    |   by matching against `gitlab_release_asset_pattern`. This lets CI-built
    |   ZIP archives (with curated contents and a sidecar checksum) be consumed
    |   by the updater. The matched asset must be a ZIP — the extractor in
    |   `ApplicationUpdateManager::extractUpdate()` uses `ZipArchive`, so other
    |   archive formats (e.g. tarballs) are not supported until that extractor
    |   is extended. If no link matches the pattern, the update fails loudly
    |   rather than silently falling back to the auto-archive.
    |
    */
    'gitlab_update_strategy' => env( 'GITLAB_UPDATE_STRATEGY', 'auto_archive' ),

    /*
    |--------------------------------------------------------------------------
    | GitLab Release Asset Pattern
    |--------------------------------------------------------------------------
    |
    | Glob pattern (compatible with `fnmatch`) used to select the release
    | asset link when `gitlab_update_strategy` is `'release_asset'`. Matched
    | case-insensitively against the asset link's `name` field, falling back
    | to the basename of its `url` when the name is missing.
    |
    | The default is `*.zip` because the update extractor uses `ZipArchive`.
    | Customizing this glob only changes which asset link is selected; the
    | matched asset must still be a ZIP. Supporting other archive formats
    | (tarballs, etc.) would require extending `ApplicationUpdateManager::extractUpdate()`.
    |
    */
    'gitlab_release_asset_pattern' => env( 'GITLAB_RELEASE_ASSET_PATTERN', '*.zip' ),
];
