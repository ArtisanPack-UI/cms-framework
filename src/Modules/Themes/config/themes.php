<?php

/**
 * Themes Configuration
 *
 * Configuration options for the CMS theme system.
 *
 * @since      1.0.0
 */

declare( strict_types = 1 );

return [
    /*
    |--------------------------------------------------------------------------
    | Themes Directory
    |--------------------------------------------------------------------------
    |
    | The directory where themes are stored, relative to base_path().
    |
    */
    'directory' => 'themes',

    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | The slug of the default theme to fall back to when no theme has been
    | activated. Deliberately null unless the host application names one: the
    | framework ships no themes of its own, so any hard-coded slug here would
    | send every consumer that ships a differently-named default looking for a
    | theme that does not exist. Left null, "no theme is active yet" stays an
    | explicit state — `ThemeManager::getActiveTheme()` returns null and
    | callers already handle that — rather than a lookup that silently misses.
    |
    */
    'default' => env( 'CMS_DEFAULT_THEME' ),

    /*
    |--------------------------------------------------------------------------
    | Required Files
    |--------------------------------------------------------------------------
    |
    | Files that must exist in every theme directory.
    |
    */
    'requiredFiles' => [
        'theme.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Whether to cache theme discovery results.
    |
    */
    'cacheEnabled' => env( 'THEMES_CACHE_ENABLED', true ),
    'cacheKey'     => 'cms.themes.discovered',
    'cacheTtl'     => 3600, // 1 hour

    /*
    |--------------------------------------------------------------------------
    | WordPress theme.json Schema Version
    |--------------------------------------------------------------------------
    |
    | The WordPress theme.json schema version against which the WP-shape
    | subset of theme.json (settings, styles, customTemplates, templateParts,
    | patterns) is validated. Bumping this requires also updating the bundled
    | schema file at src/Modules/Themes/Validation/schemas/wp-theme-json-v{N}.json.
    |
    | Pinned to match the @wordpress/* package versions in
    | artisanpack-ui/visual-editor.
    |
    */
    'wpThemeJsonSchemaVersion' => '3',

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Security settings for theme ZIP uploads via the POST /v1/themes endpoint.
    |
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
    |
    | Settings for checking and installing in-place theme updates, declared by
    | the optional `update` key in a theme's theme.json.
    |
    | `backupPath` is where the installed theme is archived before it is
    | replaced; a failed update is restored from there. Relative to
    | storage_path().
    |
    | Metadata and download HTTP timeouts are governed by the shared
    | `cms.updates.http_timeout` / `cms.updates.download_timeout` keys, since
    | theme updates run entirely through the Core update sources.
    |
    | `maxUpdateSize` is deliberately separate from `maxUploadSize` above.
    | That one is an abuse control on the upload endpoint, where the archive
    | comes from whoever is logged in. An update archive comes from the source
    | the theme itself names, and a theme shipping images and fonts clears
    | 10MB easily — gating updates on the upload ceiling would leave such a
    | theme permanently un-updatable.
    |
    */
    'updateCacheTtl' => 43200, // 12 hours in seconds
    'backupPath'     => 'theme-backups', // Relative to storage_path()
    'maxUpdateSize'  => 50 * 1024 * 1024, // 50MB in bytes

    // Ceiling on the *uncompressed* size of an extracted theme archive. The
    // compressed-size checks above say nothing about what the archive expands
    // to; this caps a zip-bomb before it fills the disk mid-extraction.
    'maxUncompressedSize' => 100 * 1024 * 1024, // 100MB in bytes

    /*
    |--------------------------------------------------------------------------
    | Theme Update Source Tokens
    |--------------------------------------------------------------------------
    |
    | Access tokens for themes whose `update` manifest key points at a private
    | repository, keyed by theme slug:
    |
    |     'updateTokens' => [
    |         'my-private-theme' => env( 'MY_PRIVATE_THEME_UPDATE_TOKEN' ),
    |     ],
    |
    | Deliberately per-slug rather than one global token. A theme names its own
    | update host in its own manifest, so a shared token would be handed to
    | whatever host any installed theme asks for. Public repositories — the
    | normal case — need no entry here.
    |
    */
    'updateTokens' => [],

    /*
    |--------------------------------------------------------------------------
    | Theme Asset Cache
    |--------------------------------------------------------------------------
    |
    | Cache-Control max-age (in seconds) applied to responses served by the
    | ThemeAssetsController. Themes ship static bytes, so a warm CDN edge
    | can safely hold them for the full TTL — bumped up in production if
    | your bundler emits fingerprinted filenames.
    |
    */
    'assetsMaxAge' => 3600,
];
