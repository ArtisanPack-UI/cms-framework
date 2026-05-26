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
    | The slug of the default theme to use if no theme is activated.
    |
    */
    'default' => 'digital-shopfront',

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
];
