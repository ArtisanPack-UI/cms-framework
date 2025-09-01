<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | CMS Framework Error Handling Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all the configuration options for the CMS Framework's
    | comprehensive error handling and logging system. These settings control
    | how errors are tracked, logged, reported, and handled throughout the
    | application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Error Tracking
    |--------------------------------------------------------------------------
    |
    | Configuration for error tracking and monitoring services.
    |
    */
    'tracking' => [
        // Enable or disable error tracking
        'enabled' => env('CMS_ERROR_TRACKING_ENABLED', true),

        // Error tracking service provider (sentry, bugsnag, rollbar, custom, null)
        'provider' => env('CMS_ERROR_TRACKING_PROVIDER', 'sentry'),

        // Provider-specific configuration
        'providers' => [
            'sentry' => [
                'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
                'environment' => env('APP_ENV', 'production'),
                'release' => env('APP_VERSION', '1.0.0'),
                'sample_rate' => (float) env('CMS_SENTRY_SAMPLE_RATE', 1.0),
                'traces_sample_rate' => (float) env('CMS_SENTRY_TRACES_SAMPLE_RATE', 0.2),
            ],

            'bugsnag' => [
                'api_key' => env('BUGSNAG_API_KEY'),
                'notify_release_stages' => ['production', 'staging'],
            ],

            'rollbar' => [
                'access_token' => env('ROLLBAR_TOKEN'),
                'environment' => env('APP_ENV', 'production'),
                'level' => env('ROLLBAR_LEVEL', 'error'),
            ],
        ],

        // Context data to include with errors
        'context' => [
            'include_user_data' => true,
            'include_request_data' => true,
            'include_session_data' => false, // Be careful with sensitive data
            'include_environment_data' => true,
            'include_breadcrumbs' => true,
            'max_breadcrumbs' => 50,
        ],

        // Error filtering
        'filters' => [
            // Exclude certain exception types from tracking
            'excluded_exceptions' => [
                // Add exception class names to exclude
                'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
                'Illuminate\Auth\AuthenticationException',
            ],

            // Only track errors above this severity level
            'minimum_severity' => 'warning', // debug, info, warning, error, critical

            // Rate limiting for error tracking
            'rate_limiting' => [
                'enabled' => true,
                'max_errors_per_minute' => 60,
                'max_errors_per_hour' => 1000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Structured Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for structured logging throughout the application.
    |
    */
    'logging' => [
        // Default log channel for CMS errors
        'default_channel' => env('CMS_LOG_CHANNEL', 'cms'),

        // Log channels configuration
        'channels' => [
            'cms' => [
                'driver' => 'daily',
                'path' => storage_path('logs/cms/cms.log'),
                'level' => env('CMS_LOG_LEVEL', 'debug'),
                'days' => (int) env('CMS_LOG_DAYS', 14),
                'permission' => 0664,
            ],

            'errors' => [
                'driver' => 'daily',
                'path' => storage_path('logs/cms/errors.log'),
                'level' => 'error',
                'days' => (int) env('CMS_ERROR_LOG_DAYS', 30),
                'permission' => 0664,
            ],

            'audit' => [
                'driver' => 'daily',
                'path' => storage_path('logs/cms/audit.log'),
                'level' => 'info',
                'days' => (int) env('CMS_AUDIT_LOG_DAYS', 90),
                'permission' => 0664,
            ],

            'security' => [
                'driver' => 'daily',
                'path' => storage_path('logs/cms/security.log'),
                'level' => 'warning',
                'days' => (int) env('CMS_SECURITY_LOG_DAYS', 180),
                'permission' => 0664,
            ],
        ],

        // Log formatters
        'formatters' => [
            'default' => 'json', // json, line, custom
            'include_context' => true,
            'include_extra' => true,
            'max_context_depth' => 3,
        ],

        // Structured data fields to include
        'structured_fields' => [
            'timestamp' => true,
            'level' => true,
            'message' => true,
            'context' => true,
            'user_id' => true,
            'session_id' => true,
            'request_id' => true,
            'ip_address' => true,
            'user_agent' => true,
            'url' => true,
            'method' => true,
            'memory_usage' => false,
            'execution_time' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for audit logging of sensitive operations.
    |
    */
    'audit' => [
        // Enable audit logging
        'enabled' => env('CMS_AUDIT_ENABLED', true),

        // Events to audit
        'events' => [
            'user_login' => true,
            'user_logout' => true,
            'user_created' => true,
            'user_updated' => true,
            'user_deleted' => true,
            'permission_granted' => true,
            'permission_revoked' => true,
            'content_created' => true,
            'content_updated' => true,
            'content_deleted' => true,
            'content_published' => true,
            'media_uploaded' => true,
            'media_deleted' => true,
            'plugin_activated' => true,
            'plugin_deactivated' => true,
            'settings_changed' => true,
            'error_occurred' => true,
        ],

        // Audit log retention
        'retention' => [
            'days' => (int) env('CMS_AUDIT_RETENTION_DAYS', 365),
            'auto_cleanup' => env('CMS_AUDIT_AUTO_CLEANUP', true),
        ],

        // Additional context for audit logs
        'context' => [
            'include_user_details' => true,
            'include_ip_address' => true,
            'include_user_agent' => true,
            'include_request_data' => false,
            'include_changes' => true, // Before/after values for updates
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Response Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for how errors are presented to users.
    |
    */
    'responses' => [
        // Default error response format
        'format' => env('CMS_ERROR_RESPONSE_FORMAT', 'json'), // json, html, auto

        // Include error details in responses (be careful in production)
        'include_details' => env('CMS_ERROR_INCLUDE_DETAILS', false),

        // Include stack traces in error responses (never in production)
        'include_trace' => env('CMS_ERROR_INCLUDE_TRACE', false),

        // Custom error response templates
        'templates' => [
            '400' => 'cms::errors.400',
            '401' => 'cms::errors.401',
            '403' => 'cms::errors.403',
            '404' => 'cms::errors.404',
            '422' => 'cms::errors.422',
            '500' => 'cms::errors.500',
            '503' => 'cms::errors.503',
        ],

        // API error response structure
        'api' => [
            'include_error_code' => true,
            'include_error_type' => true,
            'include_validation_errors' => true,
            'include_suggestion' => true,
            'include_documentation_link' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Recovery
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic error recovery mechanisms.
    |
    */
    'recovery' => [
        // Enable automatic error recovery
        'enabled' => env('CMS_ERROR_RECOVERY_ENABLED', true),

        // Recovery strategies
        'strategies' => [
            'cache_clearing' => [
                'enabled' => true,
                'triggers' => ['cache_error', 'view_error'],
            ],

            'session_regeneration' => [
                'enabled' => true,
                'triggers' => ['auth_error', 'csrf_error'],
            ],

            'database_reconnection' => [
                'enabled' => true,
                'triggers' => ['database_error'],
                'max_attempts' => 3,
            ],

            'service_restart' => [
                'enabled' => false, // Usually requires system permissions
                'triggers' => ['service_unavailable'],
            ],
        ],

        // Recovery attempt limits
        'limits' => [
            'max_attempts_per_error' => 3,
            'max_attempts_per_minute' => 10,
            'cooldown_seconds' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for error notifications to administrators.
    |
    */
    'notifications' => [
        // Enable error notifications
        'enabled' => env('CMS_ERROR_NOTIFICATIONS_ENABLED', true),

        // Notification channels
        'channels' => ['mail', 'slack'], // mail, slack, teams, discord, webhook

        // When to send notifications
        'triggers' => [
            'critical_errors' => true,
            'security_issues' => true,
            'repeated_errors' => true, // Same error multiple times
            'unusual_activity' => true,
        ],

        // Rate limiting for notifications
        'rate_limiting' => [
            'max_notifications_per_hour' => 10,
            'same_error_cooldown_minutes' => 30,
        ],

        // Notification recipients
        'recipients' => [
            'mail' => explode(',', env('CMS_ERROR_NOTIFICATION_EMAILS', '')),
            'slack' => [
                'webhook_url' => env('CMS_SLACK_WEBHOOK_URL'),
                'channel' => env('CMS_SLACK_CHANNEL', '#errors'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for performance monitoring related to error handling.
    |
    */
    'performance' => [
        // Monitor error handling performance
        'enabled' => env('CMS_ERROR_PERFORMANCE_MONITORING', true),

        // Performance thresholds
        'thresholds' => [
            'error_handling_max_time_ms' => 100,
            'logging_max_time_ms' => 50,
            'tracking_max_time_ms' => 200,
        ],

        // Memory usage monitoring
        'memory' => [
            'track_usage' => true,
            'max_memory_mb' => 128,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    |
    | Settings specific to development and testing environments.
    |
    */
    'development' => [
        // Enable additional debugging information
        'debug_mode' => env('CMS_ERROR_DEBUG_MODE', env('APP_DEBUG', false)),

        // Log all errors regardless of level in development
        'log_all_errors' => env('CMS_LOG_ALL_ERRORS', false),

        // Enable error simulation for testing
        'allow_error_simulation' => env('CMS_ALLOW_ERROR_SIMULATION', false),

        // Testing configuration
        'testing' => [
            'use_fake_services' => env('CMS_USE_FAKE_ERROR_SERVICES', false),
            'disable_external_tracking' => env('CMS_DISABLE_EXTERNAL_TRACKING', true),
            'capture_test_errors' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related settings for error handling.
    |
    */
    'security' => [
        // Sanitize sensitive data in logs
        'sanitize_data' => env('CMS_SANITIZE_ERROR_DATA', true),

        // Fields to sanitize
        'sensitive_fields' => [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'private_key',
            'credit_card',
            'ssn',
            'social_security_number',
        ],

        // Log security-related errors separately
        'separate_security_logs' => true,

        // Encrypt sensitive error data
        'encrypt_sensitive_data' => env('CMS_ENCRYPT_SENSITIVE_ERROR_DATA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Configuration
    |--------------------------------------------------------------------------
    |
    | Space for custom error handling configuration specific to your application.
    |
    */
    'custom' => [
        // Add your custom configuration here
        'handlers' => [
            // Custom error handlers
        ],

        'processors' => [
            // Custom log processors
        ],

        'formatters' => [
            // Custom log formatters
        ],
    ],
];
