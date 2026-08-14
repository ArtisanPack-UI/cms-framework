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

    // Ceiling on the *uncompressed* size of an extracted plugin archive
    // (install, update, and restore). The compressed-size check says nothing
    // about what the archive expands to; this caps a zip-bomb before it fills
    // the disk mid-extraction.
    'maxUncompressedSize' => 100 * 1024 * 1024, // 100MB in bytes

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
    | Plugin Update Source Tokens
    |--------------------------------------------------------------------------
    | Access tokens for plugins whose `update` manifest key points at a private
    | repository, keyed by plugin slug:
    |
    |     'updateTokens' => [
    |         'my-private-plugin' => env( 'MY_PRIVATE_PLUGIN_UPDATE_TOKEN' ),
    |     ],
    |
    | Deliberately per-slug rather than one global token. A plugin names its own
    | update host in its own manifest, so a shared token would be handed to
    | whatever host any installed plugin asks for. Public repositories — the
    | normal case — need no entry here.
    */
    'updateTokens' => [],

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
