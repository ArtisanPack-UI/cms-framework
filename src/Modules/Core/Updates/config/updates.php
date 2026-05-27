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
    'download_timeout'         => 300, // 5 minutes for large ZIPs
    'composer_install_command' => 'composer install --no-dev --no-interaction --optimize-autoloader',
    'composer_timeout'         => 600, // 10 minutes

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
