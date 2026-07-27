<?php

declare( strict_types = 1 );

return [
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
    | 3. Auto-discovery across common install paths (`/usr/local/bin/composer`,
    |    `/opt/homebrew/bin/composer`, `~/.composer/vendor/bin/composer`,
    |    `~/.config/composer/vendor/bin/composer`, `/usr/bin/composer`).
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
        'composer.lock', // Rebuilt via composer install
    ],

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
    | Whether to verify downloaded ZIPs using SHA-256 checksum.
    |
    */
    'verify_checksum' => true,

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
