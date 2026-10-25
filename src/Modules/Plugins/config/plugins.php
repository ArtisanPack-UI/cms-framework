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
    | Host Application Version
    |--------------------------------------------------------------------------
    | The host application's own semver version (e.g. Keystone CMS 1.2.0), which
    | a plugin's `min_app_version` manifest key is compared against. The
    | framework cannot detect it, so the host supplies it, either through the
    | CMS_APP_VERSION environment variable or from a service provider's
    | register() method, once every config file has loaded:
    |
    |     config( [ 'cms.plugins.appVersion' => config( 'keystone.version' ) ] );
    |
    | Do not read another file's key with config() here: config files load in
    | name order, so `keystone.version` does not exist yet when this file
    | loads and the value would be null. `app.version` happens to work only
    | because `app` sorts before `cms`.
    |
    | When null, `min_app_version` checks are skipped: a warning is logged and
    | `ap.cmsFramework.plugin.versionCheckSkipped` fires. A plugin's
    | `min_framework_version` is separate and always compared against the
    | cms-framework package version.
    */
    'appVersion' => env( 'CMS_APP_VERSION' ),

    /*
    |--------------------------------------------------------------------------
    | Plugin Asset Route
    |--------------------------------------------------------------------------
    | When true, the framework registers the public
    | `/plugins/{slug}/assets/{path}` route ( `plugins.assets` ), which serves
    | the build directory of a plugin whose `federated_module.entry` is a
    | relative path. Set it to false when the host serves plugin assets
    | itself. A relative entry still resolves to that URL, so the host's own
    | route must answer it.
    |
    | A host with a GET catch-all route ( e.g. CMS pages at `/{path}` ) must
    | exclude the `plugins/` prefix from it, or the catch-all answers first.
    */
    'assetRoute' => env( 'CMS_PLUGINS_ASSET_ROUTE', true ),

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
    |
    | Update checks run on demand. To keep the cached answers warm, schedule
    | the `cms:plugins:check-updates` command in the host application, e.g. in
    | `routes/console.php`:
    |
    |     Schedule::command( 'cms:plugins:check-updates' )->daily();
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
    |
    | A string entry is a token: GitHub and GitLab send it as their own auth
    | header, and a custom JSON feed receives it as the `?token=` query
    | parameter. To keep a custom feed's credential out of URLs and access
    | logs, give an array with a `headers` key instead:
    |
    |     'updateTokens' => [
    |         'my-licensed-plugin' => [
    |             'headers' => [
    |                 'Authorization' => 'Bearer ' . env( 'MY_LICENSE_KEY' ),
    |             ],
    |         ],
    |     ],
    |
    | Those headers go to the feed, and to the feed's `download_url` only
    | while it stays on the feed's own scheme, host and port.
    */
    'updateTokens' => [],

    /*
    |--------------------------------------------------------------------------
    | Auto-clear Framework Caches on Lifecycle Events
    |--------------------------------------------------------------------------
    | If true, activate/deactivate/delete and plugin updates will run
    | route:clear, config:clear and view:clear so newly (un)registered plugin
    | routes/views take effect immediately. This is convenient in development but is a blunt hammer in
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
    | block, e.g. `"composer": { "artisanpack-ui/convertkit": "^1.2" }`.
    |
    | autoInstallComposerDependencies:
    |   When true (the default since 2.16.0), installing a plugin installs its
    |   declared packages too. Installing from a ZIP or from disk, and updating
    |   (active or not), resolve the `composer` block and shell out to
    |   `composer require` in the host root for any unmet requirement, so the
    |   dependency is added to the host's `composer.json` / `composer.lock` and
    |   survives a fresh deploy. If the packages can't be installed, the install
    |   fails (a ZIP install's extracted files are removed) and an update rolls
    |   back. Activation resolves again as a backstop, putting back a package
    |   lost since install.
    |
    |   Installing a plugin is already agreeing to run its code: it is arbitrary
    |   PHP the host will boot. The packages the plugin declares are part of
    |   that code, so installing them is part of installing the plugin.
    |
    |   When false, install and update never touch the `composer` block, and
    |   activation fails closed on an unmet requirement, expecting the operator
    |   to have installed the package themselves. This is the behavior before
    |   2.16.0. Use it on hosts that can't run Composer at runtime: read-only
    |   filesystems, immutable container images.
    |
    | Resolution always fails closed: a plugin never activates with a missing
    | or out-of-range package, and a plugin may not name a package the host
    | itself requires in its root `composer.json`.
    |
    | Packages brought in by a plugin are left in place on deactivate/delete —
    | they are never auto-removed, since the host or another plugin may share
    | them and Composer offers no safe ref-counted uninstall here.
    */
    'autoInstallComposerDependencies' => env( 'PLUGINS_AUTO_INSTALL_COMPOSER_DEPENDENCIES', true ),

    // Override the composer binary/command used to install plugin dependencies.
    // Null falls back to the `COMPOSER_BINARY` env var, then a bare `composer`
    // on the PATH.
    'composerBinary' => env( 'PLUGINS_COMPOSER_BINARY' ),

    // Timeout, in seconds, for a plugin's `composer require` install.
    'composerTimeout' => 600,
];
