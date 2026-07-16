<?php

declare( strict_types = 1 );

return [
    /*
    |--------------------------------------------------------------------------
    | Plugins Directory
    |--------------------------------------------------------------------------
    | Relative path from base_path() where plugins are stored.
    */
    'directory' => 'plugins',

    /*
    |--------------------------------------------------------------------------
    | Required Manifest Files
    |--------------------------------------------------------------------------
    | Files that must exist for a directory to be considered a valid plugin.
    */
    'requiredFiles' => [
        'plugin.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    | Configure caching for plugin discovery to improve performance.
    */
    'cacheEnabled' => env( 'PLUGINS_CACHE_ENABLED', true ),
    'cacheKey'     => 'cms.plugins.discovered',
    'cacheTtl'     => 3600, // 1 hour in seconds

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    | Security settings for plugin ZIP uploads.
    */
    'maxUploadSize'    => 10 * 1024 * 1024, // 10MB in bytes
    'allowedMimeTypes' => [
        'application/zip',
        'application/x-zip-compressed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Update Settings
    |--------------------------------------------------------------------------
    | Settings for plugin update checking and execution.
    */
    'updateCheckTimeout' => 10, // HTTP request timeout in seconds
    'updateCacheTtl'     => 43200, // 12 hours in seconds
    'backupPath'         => 'plugin-backups', // Relative to storage_path()

    /*
    |--------------------------------------------------------------------------
    | Auto-clear Framework Caches on Lifecycle Events
    |--------------------------------------------------------------------------
    | If true, activate/deactivate/delete will run route:clear, config:clear
    | and view:clear so newly (un)registered plugin routes/views take effect
    | immediately. This is convenient in development but is a blunt hammer in
    | production hosts that rely on `route:cache` / `config:cache` for
    | performance — every toggle deletes the compiled cache files, causing a
    | measurable latency regression until the next deploy rebuilds them.
    |
    | Default: false. Enable it explicitly in development, or when your
    | deployment pipeline handles rebuilds on demand.
    */
    'autoClearFrameworkCaches' => env( 'PLUGINS_AUTO_CLEAR_FRAMEWORK_CACHES', false ),
];
