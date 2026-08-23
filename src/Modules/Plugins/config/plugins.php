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

    /*
    |--------------------------------------------------------------------------
    | Plugin Composer-package Dependencies
    |--------------------------------------------------------------------------
    | A plugin may declare Packagist dependencies in its manifest `composer`
    | block, e.g. `"composer": { "artisanpack-ui/convertkit": "^1.2" }`. At
    | activation the framework resolves those constraints against the host's
    | installed packages.
    |
    | autoInstallComposerDependencies:
    |   When false (the default), activation fails closed on an unmet requirement
    |   and the operator is expected to have installed the package themselves —
    |   the safe default, since auto-install fetches and boots arbitrary
    |   third-party Packagist code (its service provider via `package:discover`,
    |   and any eager `autoload.files`) in the host process. When true, an unmet
    |   requirement is installed on activation by shelling out to `composer
    |   require` in the host root, so the dependency survives a fresh deploy;
    |   enable it only where you trust every installed plugin's declared
    |   dependencies and the host may run Composer at runtime.
    |
    | Resolution always fails closed: if a required package is missing (and
    | auto-install is off or cannot complete — Packagist unreachable, a
    | `composer.lock` conflict, or a missing binary), the activation transaction
    | unwinds rather than booting a plugin with an absent vendor tree.
    |
    | Packages brought in by a plugin are left in place on deactivate/delete —
    | they are never auto-removed, since the host or another plugin may share
    | them and Composer offers no safe ref-counted uninstall here.
    */
    'autoInstallComposerDependencies' => env( 'PLUGINS_AUTO_INSTALL_COMPOSER_DEPENDENCIES', false ),

    // Override the composer binary/command used to install plugin dependencies.
    // Null falls back to the `COMPOSER_BINARY` env var, then a bare `composer`
    // on the PATH.
    'composerBinary' => env( 'PLUGINS_COMPOSER_BINARY' ),

    // Timeout, in seconds, for a plugin's `composer require` install.
    'composerTimeout' => 600,
];
