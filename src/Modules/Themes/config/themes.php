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
];
