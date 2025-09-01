<?php

/**
 * CMS Framework Configuration
 *
 * This file contains the default configuration settings for the CMS Framework.
 * These settings can be overridden by the application or through the settings API.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

return [
    'site' => [
        'name' => 'ArtisanPack UI CMS Framework',
        'tagline' => 'A flexible framework to build a CMS for your website.',
        'url' => env('APP_URL', 'http://localhost'),
        'timezone' => 'UTC',
        'locale' => 'en',
    ],
    'paths' => [
        'plugins' => base_path('plugins'),  // Path name changed from 'cms-plugins'
        'themes' => base_path('themes'),   // Path name changed from 'cms-themes'
    ],
    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),     // Default to 'public' disk
        'directory' => env('MEDIA_DIRECTORY', 'media'), // Default storage directory within the disk
    ],
    'content_types' => [
        // Built-in Post Type
        'post' => [
            'label' => 'Post',
            'label_plural' => 'Posts',
            'slug' => 'posts',
            'public' => true,
            'hierarchical' => false,
            'supports' => ['title', 'content', 'author', 'featured_image', 'status', 'categories', 'tags'],
            'fields' => [], // Core fields, no special meta fields here
        ],
        // Built-in Page Type
        'page' => [
            'label' => 'Page',
            'label_plural' => 'Pages',
            'slug' => 'pages',
            'public' => true,
            'hierarchical' => true,
            'supports' => ['title', 'content', 'author', 'status', 'parent', 'order'],
            'fields' => [],
        ],
    ],
    'taxonomies' => [
        // Built-in Category Taxonomy
        'category' => [
            'label' => 'Category',
            'label_plural' => 'Categories',
            'hierarchical' => true,
            'content_types' => ['post'], // Applies to 'post' content type by default
        ],
        // Built-in Tag Taxonomy
        'tag' => [
            'label' => 'Tag',
            'label_plural' => 'Tags',
            'hierarchical' => false,
            'content_types' => ['post'], // Applies to 'post' content type by default
        ],
    ],
    'theme' => [
        'active' => env('CMS_ACTIVE_THEME', 'default-artisanpack-theme'), // Default theme name.
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for different API endpoint types to prevent
    | abuse and DoS attacks. Limits are defined as requests per minute.
    |
    */
    'rate_limiting' => [
        'enabled' => env('CMS_RATE_LIMITING_ENABLED', true),

        // General API endpoints (CRUD operations)
        'general' => [
            'requests_per_minute' => env('CMS_RATE_LIMIT_GENERAL', 60),
            'key_generator' => 'user_ip', // 'user_ip', 'user_id', or 'ip_only'
        ],

        // Authentication endpoints (login, registration, password reset)
        'auth' => [
            'requests_per_minute' => env('CMS_RATE_LIMIT_AUTH', 5),
            'key_generator' => 'ip_only', // More restrictive for auth endpoints
        ],

        // Administrative operations (plugin management, settings)
        'admin' => [
            'requests_per_minute' => env('CMS_RATE_LIMIT_ADMIN', 30),
            'key_generator' => 'user_id', // Admin operations should be user-specific
        ],

        // Resource-intensive operations (file uploads, plugin installations)
        'upload' => [
            'requests_per_minute' => env('CMS_RATE_LIMIT_UPLOAD', 10),
            'key_generator' => 'user_id',
        ],

        // Admin user bypass configuration
        'bypass' => [
            'enabled' => env('CMS_RATE_LIMIT_BYPASS_ADMIN', true),
            'admin_capabilities' => ['manage_options', 'administrator'], // Capabilities that bypass rate limiting
        ],

        // Rate limit headers configuration
        'headers' => [
            'enabled' => env('CMS_RATE_LIMIT_HEADERS', true),
            'remaining_header' => 'X-RateLimit-Remaining',
            'limit_header' => 'X-RateLimit-Limit',
            'retry_after_header' => 'Retry-After',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure search functionality including indexing, analytics, ranking,
    | and caching settings for the advanced search capabilities.
    |
    */
    'search' => [
        // Enable/disable search functionality
        'enabled' => env('CMS_SEARCH_ENABLED', true),

        // Enable/disable search analytics
        'analytics_enabled' => env('CMS_SEARCH_ANALYTICS_ENABLED', true),

        // Search indexing configuration
        'indexing' => [
            'batch_size' => env('CMS_SEARCH_INDEX_BATCH_SIZE', 100),
            'auto_index' => env('CMS_SEARCH_AUTO_INDEX', true), // Auto-index on model changes
            'indexable_models' => [
                \ArtisanPackUI\CMSFramework\Models\Content::class,
                \ArtisanPackUI\CMSFramework\Models\Term::class,
            ],
        ],

        // Search result limits and pagination
        'results' => [
            'max_results' => env('CMS_SEARCH_MAX_RESULTS', 1000),
            'default_per_page' => env('CMS_SEARCH_DEFAULT_PER_PAGE', 20),
            'max_per_page' => env('CMS_SEARCH_MAX_PER_PAGE', 100),
        ],

        // Search caching configuration
        'cache' => [
            'enabled' => env('CMS_SEARCH_CACHE_ENABLED', true),
            'ttl' => env('CMS_SEARCH_CACHE_TTL', 3600), // 1 hour in seconds
            'tags' => ['cms', 'search'],
        ],

        // Content type weights for search ranking
        'type_weights' => [
            'page' => env('CMS_SEARCH_WEIGHT_PAGE', 1.2),
            'post' => env('CMS_SEARCH_WEIGHT_POST', 1.0),
            'video' => env('CMS_SEARCH_WEIGHT_VIDEO', 0.9),
            'media' => env('CMS_SEARCH_WEIGHT_MEDIA', 0.7),
            'taxonomy_term' => env('CMS_SEARCH_WEIGHT_TERM', 0.8),
        ],

        // Search ranking algorithm weights (must sum to 1.0)
        'ranking_weights' => [
            'text_relevance' => 0.4,    // MySQL MATCH() AGAINST() score
            'type_weight' => 0.2,       // Content type importance
            'freshness' => 0.15,        // Recency boost
            'author_authority' => 0.1,  // Author reputation
            'manual_boost' => 0.1,      // Manual relevance_boost field
            'engagement' => 0.05,       // Views, comments, etc. (future)
        ],

        // Freshness scoring configuration
        'freshness' => [
            'decay_days' => env('CMS_SEARCH_FRESHNESS_DECAY', 365), // Days for 50% decay
            'enabled' => env('CMS_SEARCH_FRESHNESS_ENABLED', true),
        ],

        // Search suggestions configuration
        'suggestions' => [
            'enabled' => env('CMS_SEARCH_SUGGESTIONS_ENABLED', true),
            'min_query_length' => env('CMS_SEARCH_SUGGESTIONS_MIN_LENGTH', 2),
            'max_suggestions' => env('CMS_SEARCH_SUGGESTIONS_MAX', 10),
            'cache_ttl' => env('CMS_SEARCH_SUGGESTIONS_CACHE_TTL', 3600),
        ],

        // Faceted search configuration
        'facets' => [
            'enabled' => env('CMS_SEARCH_FACETS_ENABLED', true),
            'max_facet_items' => env('CMS_SEARCH_MAX_FACET_ITEMS', 20),
            'cache_ttl' => env('CMS_SEARCH_FACETS_CACHE_TTL', 1800), // 30 minutes
        ],

        // Search analytics configuration
        'analytics' => [
            'retention_days' => env('CMS_SEARCH_ANALYTICS_RETENTION', 365),
            'track_failed_searches' => env('CMS_SEARCH_TRACK_FAILED', true),
            'track_zero_results' => env('CMS_SEARCH_TRACK_ZERO_RESULTS', true),
            'privacy' => [
                'hash_ip_addresses' => env('CMS_SEARCH_HASH_IPS', true),
                'store_user_agents' => env('CMS_SEARCH_STORE_USER_AGENTS', true),
                'anonymize_after_days' => env('CMS_SEARCH_ANONYMIZE_AFTER', 30),
            ],
        ],

        // Search rate limiting (inherits from general rate limiting but can be overridden)
        'rate_limiting' => [
            'enabled' => env('CMS_SEARCH_RATE_LIMITING_ENABLED', true),
            'requests_per_minute' => env('CMS_SEARCH_RATE_LIMIT', 30),
            'burst_limit' => env('CMS_SEARCH_BURST_LIMIT', 10), // Allow bursts
        ],

        // Full-text search engine configuration
        'engine' => [
            'driver' => env('CMS_SEARCH_ENGINE', 'mysql'), // 'mysql', 'postgresql', 'elasticsearch' (future)
            'mysql' => [
                'ft_min_word_len' => env('CMS_SEARCH_MYSQL_MIN_WORD_LEN', 4),
                'ft_boolean_syntax' => env('CMS_SEARCH_MYSQL_BOOLEAN', true),
                'ft_query_expansion' => env('CMS_SEARCH_MYSQL_EXPANSION', false),
            ],
        ],

        // Search API configuration
        'api' => [
            'prefix' => 'search', // API prefix: /api/cms/search
            'middleware' => ['api', 'cms.rate_limit.general'],
            'admin_middleware' => ['api', 'cms.rate_limit.admin'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure APM functionality including external provider integration,
    | custom metrics collection, error tracking, and performance monitoring.
    |
    */
    'apm' => [
        'enabled' => env('CMS_APM_ENABLED', true),
        'default_provider' => env('CMS_APM_PROVIDER', 'internal'),

        // APM Providers Configuration
        'providers' => [
            'newrelic' => [
                'enabled' => env('NEWRELIC_ENABLED', false),
                'app_name' => env('NEWRELIC_APP_NAME', config('app.name')),
                'license_key' => env('NEWRELIC_LICENSE_KEY'),
            ],
            'datadog' => [
                'enabled' => env('DATADOG_ENABLED', false),
                'api_key' => env('DATADOG_API_KEY'),
                'app_key' => env('DATADOG_APP_KEY'),
                'service' => env('DATADOG_SERVICE', config('app.name')),
                'env' => env('DATADOG_ENV', env('APP_ENV')),
            ],
            'sentry' => [
                'enabled' => env('SENTRY_ENABLED', false),
                'dsn' => env('SENTRY_LARAVEL_DSN'),
                'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
            ],
            'internal' => [
                'enabled' => env('CMS_INTERNAL_APM_ENABLED', true),
                'retention_days' => env('CMS_APM_RETENTION_DAYS', 90),
                'sample_rate' => env('CMS_APM_SAMPLE_RATE', 1.0),
            ],
        ],

        // Metrics Collection Configuration
        'metrics' => [
            'enabled' => env('CMS_METRICS_ENABLED', true),
            'sample_rate' => env('CMS_METRICS_SAMPLE_RATE', 1.0),
            'batch_size' => env('CMS_METRICS_BATCH_SIZE', 100),
            'flush_interval' => env('CMS_METRICS_FLUSH_INTERVAL', 60), // seconds
        ],

        // Alerting Configuration
        'alerts' => [
            'enabled' => env('CMS_ALERTS_ENABLED', true),
            'response_time_threshold' => env('CMS_ALERT_RESPONSE_TIME', 2000), // ms
            'error_rate_threshold' => env('CMS_ALERT_ERROR_RATE', 5.0), // percentage
            'memory_usage_threshold' => env('CMS_ALERT_MEMORY_USAGE', 80), // percentage
            'notification_channels' => ['email', 'slack'],
        ],

        // Error Tracking Configuration
        'error_tracking' => [
            'enabled' => env('CMS_ERROR_TRACKING_ENABLED', true),
            'capture_unhandled' => env('CMS_CAPTURE_UNHANDLED_ERRORS', true),
            'capture_handled' => env('CMS_CAPTURE_HANDLED_ERRORS', false),
            'ignore_exceptions' => [
                \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
            ],
            'context_lines' => env('CMS_ERROR_CONTEXT_LINES', 5),
        ],

        // User Experience Monitoring Configuration
        'user_experience' => [
            'enabled' => env('CMS_UX_MONITORING_ENABLED', true),
            'track_page_loads' => env('CMS_TRACK_PAGE_LOADS', true),
            'track_api_calls' => env('CMS_TRACK_API_CALLS', true),
            'track_user_interactions' => env('CMS_TRACK_USER_INTERACTIONS', false),
            'sample_rate' => env('CMS_UX_SAMPLE_RATE', 0.1),
        ],

        // Performance Monitoring Configuration
        'performance' => [
            'slow_query_threshold' => env('CMS_SLOW_QUERY_THRESHOLD', 1000), // ms
            'memory_limit_warning' => env('CMS_MEMORY_WARNING_THRESHOLD', 128), // MB
            'track_queue_jobs' => env('CMS_TRACK_QUEUE_JOBS', true),
            'track_console_commands' => env('CMS_TRACK_CONSOLE_COMMANDS', false),
        ],

        // Monitoring Skip Patterns
        'skip_patterns' => [
            'health*',
            'status*',
            'ping*',
            '_debugbar*',
            'telescope*',
        ],

        // Dashboard Configuration
        'dashboard' => [
            'enabled' => env('CMS_APM_DASHBOARD_ENABLED', true),
            'refresh_interval' => env('CMS_APM_DASHBOARD_REFRESH', 30), // seconds
            'chart_data_points' => env('CMS_APM_CHART_DATA_POINTS', 50),
        ],
    ],
];
