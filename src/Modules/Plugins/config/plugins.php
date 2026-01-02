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
];
