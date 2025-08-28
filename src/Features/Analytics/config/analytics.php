<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the ArtisanPack UI CMS Framework privacy-compliant
    | usage analytics system. This system is designed with privacy-first
    | principles and GDPR compliance in mind.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Analytics Tracking
    |--------------------------------------------------------------------------
    |
    | Control what data is tracked and how tracking behaves.
    |
    */

    // Enable or disable the entire analytics system
    'enabled' => env('ANALYTICS_ENABLED', true),

    // Automatically track page views for all web requests
    'auto_track_page_views' => env('ANALYTICS_AUTO_TRACK_PAGE_VIEWS', true),

    // Track user sessions
    'track_sessions' => env('ANALYTICS_TRACK_SESSIONS', true),

    // Track authenticated users (links analytics to user accounts)
    'track_authenticated_users' => env('ANALYTICS_TRACK_AUTHENTICATED_USERS', true),

    // Track anonymous users (uses hashed session identifiers)
    'track_anonymous_users' => env('ANALYTICS_TRACK_ANONYMOUS_USERS', true),

    /*
    |--------------------------------------------------------------------------
    | Privacy & GDPR Compliance
    |--------------------------------------------------------------------------
    |
    | Privacy-first configuration options for GDPR compliance.
    |
    */

    'privacy' => [
        // Hash IP addresses for privacy (recommended: true)
        'hash_ip_addresses' => env('ANALYTICS_HASH_IP_ADDRESSES', true),

        // Hash user agents for privacy
        'hash_user_agents' => env('ANALYTICS_HASH_USER_AGENTS', true),

        // Hash referrer URLs for privacy
        'hash_referrers' => env('ANALYTICS_HASH_REFERRERS', true),

        // Collect country-level geolocation (ISO country codes only)
        'collect_country_data' => env('ANALYTICS_COLLECT_COUNTRY_DATA', true),

        // Require user consent before tracking (GDPR compliance)
        'require_consent' => env('ANALYTICS_REQUIRE_CONSENT', false),

        // Default consent status for new users/sessions
        'default_consent' => env('ANALYTICS_DEFAULT_CONSENT', false),

        // Cookie name for storing consent status
        'consent_cookie_name' => env('ANALYTICS_CONSENT_COOKIE_NAME', 'analytics_consent'),

        // Consent cookie lifetime in days
        'consent_cookie_lifetime' => env('ANALYTICS_CONSENT_COOKIE_LIFETIME', 365),

        // Enable data export functionality for users
        'enable_data_export' => env('ANALYTICS_ENABLE_DATA_EXPORT', true),

        // Enable data deletion functionality for users
        'enable_data_deletion' => env('ANALYTICS_ENABLE_DATA_DELETION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot Detection
    |--------------------------------------------------------------------------
    |
    | Configuration for detecting and handling bot traffic.
    |
    */

    'bot_detection' => [
        // Enable bot detection
        'enabled' => env('ANALYTICS_BOT_DETECTION_ENABLED', true),

        // Track bot traffic (if false, bot requests are ignored entirely)
        'track_bots' => env('ANALYTICS_TRACK_BOTS', true),

        // User agent patterns that indicate bot traffic
        'bot_patterns' => [
            'bot', 'crawler', 'spider', 'scraper', 'indexer',
            'googlebot', 'bingbot', 'slurp', 'duckduckbot',
            'baiduspider', 'yandexbot', 'facebookexternalhit',
            'twitterbot', 'linkedinbot', 'whatsapp', 'telegram',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic data cleanup and retention policies.
    |
    */

    'retention' => [
        // Data retention period in days (0 = keep forever)
        'retention_days' => env('ANALYTICS_RETENTION_DAYS', 365),

        // Cleanup frequency: daily, weekly, monthly
        'cleanup_frequency' => env('ANALYTICS_CLEANUP_FREQUENCY', 'daily'),

        // Enable automatic cleanup
        'auto_cleanup' => env('ANALYTICS_AUTO_CLEANUP', true),

        // Batch size for cleanup operations
        'cleanup_batch_size' => env('ANALYTICS_CLEANUP_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tracking performance metrics.
    |
    */

    'performance' => [
        // Track server response times
        'track_response_times' => env('ANALYTICS_TRACK_RESPONSE_TIMES', true),

        // Track client-side page load times (requires JavaScript integration)
        'track_page_load_times' => env('ANALYTICS_TRACK_PAGE_LOAD_TIMES', false),

        // Maximum response time to track (ms) - ignore outliers
        'max_response_time_ms' => env('ANALYTICS_MAX_RESPONSE_TIME_MS', 30000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard & API
    |--------------------------------------------------------------------------
    |
    | Configuration for analytics dashboard and API access.
    |
    */

    'dashboard' => [
        // Enable analytics dashboard widgets
        'enabled' => env('ANALYTICS_DASHBOARD_ENABLED', true),

        // Default date range for dashboard (days)
        'default_date_range' => env('ANALYTICS_DEFAULT_DATE_RANGE', 30),

        // Cache dashboard data (in minutes, 0 = no cache)
        'cache_duration' => env('ANALYTICS_CACHE_DURATION', 60),
    ],

    'api' => [
        // Enable API endpoints for analytics data
        'enable_api_endpoints' => env('ANALYTICS_ENABLE_API_ENDPOINTS', false),

        // Require authentication for API access
        'require_authentication' => env('ANALYTICS_API_REQUIRE_AUTH', true),

        // Rate limiting for API endpoints (requests per minute)
        'rate_limit' => env('ANALYTICS_API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    |
    | Configure what requests should be excluded from tracking.
    |
    */

    'exclusions' => [
        // Exclude specific paths from tracking
        'excluded_paths' => [
            '/admin/analytics*',
            '/api/analytics*',
            '/_debugbar*',
            '/telescope*',
            '/horizon*',
        ],

        // Exclude specific IP addresses (supports CIDR notation)
        'excluded_ips' => [
            // '127.0.0.1',
            // '192.168.1.0/24',
        ],

        // Exclude requests with specific user agents
        'excluded_user_agents' => [
            // 'My Custom Bot',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for session analytics tracking.
    |
    */

    'sessions' => [
        // Session timeout in minutes (for calculating session end)
        'timeout_minutes' => env('ANALYTICS_SESSION_TIMEOUT', 30),

        // Consider single page views as bounce sessions
        'count_bounces' => env('ANALYTICS_COUNT_BOUNCES', true),

        // Minimum session duration to consider engaged (seconds)
        'engagement_threshold_seconds' => env('ANALYTICS_ENGAGEMENT_THRESHOLD', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Device & Browser Detection
    |--------------------------------------------------------------------------
    |
    | Configuration for device and browser detection.
    |
    */

    'device_detection' => [
        // Enable device type detection (mobile, tablet, desktop)
        'enabled' => env('ANALYTICS_DEVICE_DETECTION_ENABLED', true),

        // Enable browser family detection
        'detect_browser' => env('ANALYTICS_DETECT_BROWSER', true),

        // Enable operating system detection
        'detect_os' => env('ANALYTICS_DETECT_OS', true),

        // Device type detection patterns
        'mobile_patterns' => [
            'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone'
        ],

        'tablet_patterns' => [
            'iPad', 'Tablet', 'Kindle', 'Silk', 'PlayBook'
        ],
    ],
];