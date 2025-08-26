<?php

/**
 * CMS Framework Cache Configuration
 *
 * This file contains configuration options for the CMS framework's caching strategy.
 * It defines cache settings for different components like users, roles, plugins, themes,
 * content, and database queries to improve performance at scale.
 *
 * @since 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Driver
    |--------------------------------------------------------------------------
    |
    | The cache driver to use for CMS framework caching. This can be different
    | from your application's default cache driver if needed. Supported drivers:
    | "file", "database", "redis", "memcached", "dynamodb", "octane", "array"
    |
    */
    'driver' => env('CMS_CACHE_DRIVER', env('CACHE_DRIVER', 'file')),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | A prefix for all CMS cache keys to avoid conflicts with other cache entries.
    | This is especially important in shared cache environments.
    |
    */
    'prefix' => env('CMS_CACHE_PREFIX', 'cms_framework'),

    /*
    |--------------------------------------------------------------------------
    | Default TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | The default cache expiration time in seconds. Can be overridden per cache type.
    | Set to 0 for no expiration (cache forever until manually cleared).
    |
    */
    'default_ttl' => env('CMS_CACHE_DEFAULT_TTL', 3600), // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Cache Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch to enable/disable all CMS framework caching.
    | Useful for development or troubleshooting.
    |
    */
    'enabled' => env('CMS_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | User & Permission Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for user permissions, roles, and capabilities.
    | These are frequently accessed and benefit greatly from caching.
    |
    */
    'users' => [
        'enabled' => env('CMS_CACHE_USERS_ENABLED', true),
        'ttl' => env('CMS_CACHE_USERS_TTL', 1800), // 30 minutes
        'tags' => ['users', 'permissions'],
        'keys' => [
            'user_permissions' => 'user_permissions_{user_id}',
            'user_settings' => 'user_settings_{user_id}',
            'user_capabilities' => 'user_capabilities_{user_id}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role & Capability Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for roles and their associated capabilities.
    | Role capabilities are relatively static and benefit from longer TTL.
    |
    */
    'roles' => [
        'enabled' => env('CMS_CACHE_ROLES_ENABLED', true),
        'ttl' => env('CMS_CACHE_ROLES_TTL', 7200), // 2 hours
        'tags' => ['roles', 'permissions'],
        'keys' => [
            'role_capabilities' => 'role_capabilities_{role_id}',
            'all_roles' => 'all_roles',
            'role_users' => 'role_users_{role_id}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Discovery Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for plugin discovery and metadata.
    | Plugin scanning is expensive, so longer TTL is beneficial.
    |
    */
    'plugins' => [
        'enabled' => env('CMS_CACHE_PLUGINS_ENABLED', true),
        'ttl' => env('CMS_CACHE_PLUGINS_TTL', 14400), // 4 hours
        'tags' => ['plugins', 'discovery'],
        'keys' => [
            'all_installed' => 'plugins_all_installed',
            'active_plugins' => 'plugins_active',
            'plugin_instance' => 'plugin_instance_{slug}',
            'plugin_metadata' => 'plugin_metadata_{slug}',
            'plugin_discovery' => 'plugin_discovery_{directory}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme Discovery Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for theme discovery and metadata.
    | Theme scanning is also expensive and themes change infrequently.
    |
    */
    'themes' => [
        'enabled' => env('CMS_CACHE_THEMES_ENABLED', true),
        'ttl' => env('CMS_CACHE_THEMES_TTL', 14400), // 4 hours
        'tags' => ['themes', 'discovery'],
        'keys' => [
            'all_themes' => 'themes_all',
            'active_theme' => 'theme_active',
            'theme_metadata' => 'theme_metadata_{slug}',
            'theme_discovery' => 'theme_discovery_{directory}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for content items, queries, and search results.
    | Published content benefits from caching but needs careful invalidation.
    |
    */
    'content' => [
        'enabled' => env('CMS_CACHE_CONTENT_ENABLED', true),
        'ttl' => env('CMS_CACHE_CONTENT_TTL', 1800), // 30 minutes
        'tags' => ['content', 'posts'],
        'keys' => [
            'published_content' => 'content_published',
            'content_by_type' => 'content_type_{type}',
            'content_item' => 'content_item_{id}',
            'content_meta' => 'content_meta_{id}',
            'content_search' => 'content_search_{query_hash}',
            'content_hierarchy' => 'content_hierarchy_{parent_id}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Query Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for expensive database queries.
    | Shorter TTL to ensure data freshness while improving performance.
    |
    */
    'queries' => [
        'enabled' => env('CMS_CACHE_QUERIES_ENABLED', true),
        'ttl' => env('CMS_CACHE_QUERIES_TTL', 900), // 15 minutes
        'tags' => ['queries', 'database'],
        'keys' => [
            'query_result' => 'query_{query_hash}',
            'model_count' => 'model_count_{model}_{conditions_hash}',
            'model_list' => 'model_list_{model}_{conditions_hash}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for application settings.
    | Settings are frequently accessed and change infrequently.
    |
    */
    'settings' => [
        'enabled' => env('CMS_CACHE_SETTINGS_ENABLED', true),
        'ttl' => env('CMS_CACHE_SETTINGS_TTL', 3600), // 1 hour
        'tags' => ['settings', 'configuration'],
        'keys' => [
            'all_settings' => 'settings_all',
            'setting_value' => 'setting_{key}',
            'settings_by_type' => 'settings_type_{type}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warming Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cache warming operations that pre-populate
    | critical cache entries during deployment or maintenance.
    |
    */
    'warming' => [
        'enabled' => env('CMS_CACHE_WARMING_ENABLED', true),
        'chunk_size' => env('CMS_CACHE_WARMING_CHUNK_SIZE', 100),
        'delay_between_chunks' => env('CMS_CACHE_WARMING_DELAY', 100), // milliseconds
        'items' => [
            'all_roles',
            'all_settings',
            'all_installed_plugins',
            'active_plugins',
            'published_content',
            'all_themes',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Invalidation Rules
    |--------------------------------------------------------------------------
    |
    | Define which cache entries should be invalidated when certain models
    | are created, updated, or deleted. This ensures cache consistency.
    |
    */
    'invalidation' => [
        'User' => [
            'created' => ['users'],
            'updated' => ['users', 'permissions'],
            'deleted' => ['users', 'permissions'],
        ],
        'Role' => [
            'created' => ['roles', 'permissions'],
            'updated' => ['roles', 'permissions'],
            'deleted' => ['roles', 'permissions'],
        ],
        'Content' => [
            'created' => ['content'],
            'updated' => ['content'],
            'deleted' => ['content'],
        ],
        'Plugin' => [
            'created' => ['plugins', 'discovery'],
            'updated' => ['plugins', 'discovery'],
            'deleted' => ['plugins', 'discovery'],
        ],
        'Setting' => [
            'created' => ['settings', 'configuration'],
            'updated' => ['settings', 'configuration'],
            'deleted' => ['settings', 'configuration'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Monitoring & Debugging
    |--------------------------------------------------------------------------
    |
    | Configuration for cache performance monitoring and debugging.
    | Useful for development and performance optimization.
    |
    */
    'monitoring' => [
        'enabled' => env('CMS_CACHE_MONITORING_ENABLED', false),
        'log_hits' => env('CMS_CACHE_LOG_HITS', false),
        'log_misses' => env('CMS_CACHE_LOG_MISSES', false),
        'log_invalidations' => env('CMS_CACHE_LOG_INVALIDATIONS', true),
        'performance_threshold' => env('CMS_CACHE_PERFORMANCE_THRESHOLD', 100), // milliseconds
    ],
];
