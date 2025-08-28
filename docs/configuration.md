# Configuration Guide

This guide covers all configuration options and environment setup for the ArtisanPack UI CMS Framework.

## Configuration Files

After installation, the CMS Framework creates several configuration files in your `config/` directory:

### Main Configuration (`config/cms.php`)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS Framework Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether the CMS framework is enabled. When disabled,
    | all CMS routes and functionality will be unavailable.
    */
    'enabled' => env('CMS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default User Role
    |--------------------------------------------------------------------------
    |
    | The default role assigned to new users when they register.
    */
    'default_role' => env('CMS_DEFAULT_ROLE', 'subscriber'),

    /*
    |--------------------------------------------------------------------------
    | Allow Registration
    |--------------------------------------------------------------------------
    |
    | Controls whether new users can register accounts.
    */
    'allow_registration' => env('CMS_ALLOW_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Admin Path
    |--------------------------------------------------------------------------
    |
    | The URL path for accessing the admin interface.
    */
    'admin_path' => env('CMS_ADMIN_PATH', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Session Settings
    |--------------------------------------------------------------------------
    */
    'session' => [
        'timeout' => env('CMS_SESSION_TIMEOUT', 120), // minutes
        'remember_duration' => env('CMS_REMEMBER_DURATION', 2628000), // seconds (1 month)
    ],
];
```

### Content Configuration (`config/cms-content.php`)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Types
    |--------------------------------------------------------------------------
    |
    | Define the available content types in your CMS.
    */
    'types' => [
        'post' => [
            'name' => 'Post',
            'plural' => 'Posts',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'comments'],
            'hierarchical' => false,
            'public' => true,
        ],
        'page' => [
            'name' => 'Page',
            'plural' => 'Pages',
            'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
            'hierarchical' => true,
            'public' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Taxonomies
    |--------------------------------------------------------------------------
    |
    | Define taxonomies (categories, tags) for organizing content.
    */
    'taxonomies' => [
        'category' => [
            'name' => 'Category',
            'plural' => 'Categories',
            'hierarchical' => true,
            'content_types' => ['post'],
        ],
        'tag' => [
            'name' => 'Tag',
            'plural' => 'Tags',
            'hierarchical' => false,
            'content_types' => ['post', 'page'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'posts_per_page' => env('CMS_POSTS_PER_PAGE', 10),
        'excerpt_length' => env('CMS_EXCERPT_LENGTH', 55),
        'auto_excerpt' => env('CMS_AUTO_EXCERPT', true),
        'comments_enabled' => env('CMS_COMMENTS_ENABLED', false),
    ],
];
```

### Media Configuration (`config/cms-media.php`)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    */
    'disk' => env('CMS_MEDIA_DISK', 'public'),
    'path' => env('CMS_MEDIA_PATH', 'media'),

    /*
    |--------------------------------------------------------------------------
    | Upload Restrictions
    |--------------------------------------------------------------------------
    */
    'max_file_size' => env('CMS_MEDIA_MAX_SIZE', 10240), // KB
    'allowed_types' => env('CMS_MEDIA_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,mp4,mp3'),
    'image_quality' => env('CMS_IMAGE_QUALITY', 85),

    /*
    |--------------------------------------------------------------------------
    | Image Processing
    |--------------------------------------------------------------------------
    */
    'thumbnails' => [
        'thumbnail' => ['width' => 150, 'height' => 150],
        'medium' => ['width' => 300, 'height' => 300],
        'large' => ['width' => 1024, 'height' => 1024],
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Settings
    |--------------------------------------------------------------------------
    */
    'cdn' => [
        'enabled' => env('CMS_CDN_ENABLED', false),
        'url' => env('CMS_CDN_URL'),
    ],
];
```

### User Management (`config/cms-users.php`)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Roles & Capabilities
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'administrator' => [
            'name' => 'Administrator',
            'capabilities' => ['*'], // All capabilities
        ],
        'editor' => [
            'name' => 'Editor',
            'capabilities' => [
                'read', 'edit_posts', 'publish_posts', 'delete_posts',
                'upload_files', 'moderate_comments',
            ],
        ],
        'author' => [
            'name' => 'Author',
            'capabilities' => [
                'read', 'edit_own_posts', 'publish_own_posts',
                'delete_own_posts', 'upload_files',
            ],
        ],
        'contributor' => [
            'name' => 'Contributor',
            'capabilities' => ['read', 'edit_own_posts', 'delete_own_posts'],
        ],
        'subscriber' => [
            'name' => 'Subscriber',
            'capabilities' => ['read'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'enabled' => env('CMS_2FA_ENABLED', false),
        'issuer' => env('CMS_2FA_ISSUER', config('app.name')),
        'required_for_roles' => ['administrator'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Requirements
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => env('CMS_PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('CMS_PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('CMS_PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('CMS_PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('CMS_PASSWORD_REQUIRE_SYMBOLS', false),
    ],
];
```

## Environment Variables

### Core Settings

```env
# Core CMS Settings
CMS_ENABLED=true
CMS_DEFAULT_ROLE=subscriber
CMS_ALLOW_REGISTRATION=false
CMS_ADMIN_PATH=admin

# Session Management
CMS_SESSION_TIMEOUT=120
CMS_REMEMBER_DURATION=2628000
```

### Content Settings

```env
# Content Configuration
CMS_POSTS_PER_PAGE=10
CMS_EXCERPT_LENGTH=55
CMS_AUTO_EXCERPT=true
CMS_COMMENTS_ENABLED=false
```

### Media Settings

```env
# Media Configuration
CMS_MEDIA_DISK=public
CMS_MEDIA_PATH=media
CMS_MEDIA_MAX_SIZE=10240
CMS_MEDIA_ALLOWED_TYPES=jpg,jpeg,png,gif,pdf,doc,docx
CMS_IMAGE_QUALITY=85

# CDN Settings
CMS_CDN_ENABLED=false
CMS_CDN_URL=
```

### Security Settings

```env
# Two-Factor Authentication
CMS_2FA_ENABLED=true
CMS_2FA_ISSUER="Your App Name"

# Password Requirements
CMS_PASSWORD_MIN_LENGTH=8
CMS_PASSWORD_REQUIRE_UPPERCASE=true
CMS_PASSWORD_REQUIRE_LOWERCASE=true
CMS_PASSWORD_REQUIRE_NUMBERS=true
CMS_PASSWORD_REQUIRE_SYMBOLS=false
```

### PWA Settings

```env
# Progressive Web App
CMS_PWA_ENABLED=true
CMS_PWA_NAME="Your CMS"
CMS_PWA_SHORT_NAME="CMS"
CMS_PWA_THEME_COLOR=#000000
CMS_PWA_BACKGROUND_COLOR=#ffffff
```

## Advanced Configuration

### Custom Content Types

Register custom content types in your `AppServiceProvider`:

```php
use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;

public function boot()
{
    $contentManager = app(ContentTypeManager::class);
    
    $contentManager->register('product', [
        'name' => 'Product',
        'plural' => 'Products',
        'description' => 'E-commerce products',
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'hierarchical' => false,
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'products'],
    ]);
}
```

### Custom Capabilities

Define custom user capabilities:

```php
use ArtisanPackUI\CMSFramework\Features\Users\UserManager;

public function boot()
{
    $userManager = app(UserManager::class);
    
    $userManager->addCapability('manage_products');
    $userManager->addCapability('edit_products');
    $userManager->addCapability('delete_products');
}
```

### Theme Configuration

Configure theme settings:

```php
// config/cms-theme.php
return [
    'active_theme' => env('CMS_ACTIVE_THEME', 'default'),
    'theme_directory' => resource_path('themes'),
    'allow_theme_switching' => env('CMS_ALLOW_THEME_SWITCHING', true),
    'cache_themes' => env('CMS_CACHE_THEMES', true),
];
```

### Plugin Configuration

Configure plugin settings:

```php
// config/cms-plugins.php
return [
    'auto_load' => env('CMS_PLUGINS_AUTO_LOAD', true),
    'plugin_directory' => base_path('plugins'),
    'cache_plugins' => env('CMS_CACHE_PLUGINS', true),
    'allowed_plugins' => [], // Empty array allows all plugins
];
```

## Performance Configuration

### Caching

```env
# Cache Configuration
CMS_CACHE_ENABLED=true
CMS_CACHE_TTL=3600
CMS_CACHE_TAGS=true

# Query Caching
CMS_QUERY_CACHE=true
CMS_QUERY_CACHE_TTL=1800
```

### Database Optimization

```php
// config/cms-database.php
return [
    'optimize_queries' => env('CMS_OPTIMIZE_QUERIES', true),
    'enable_query_log' => env('CMS_ENABLE_QUERY_LOG', false),
    'slow_query_threshold' => env('CMS_SLOW_QUERY_THRESHOLD', 1000), // ms
];
```

## Security Configuration

### API Authentication

```php
// config/cms-api.php
return [
    'auth_guard' => 'sanctum',
    'rate_limiting' => [
        'enabled' => env('CMS_API_RATE_LIMITING', true),
        'max_attempts' => env('CMS_API_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('CMS_API_DECAY_MINUTES', 1),
    ],
    'cors' => [
        'enabled' => env('CMS_API_CORS_ENABLED', true),
        'allowed_origins' => env('CMS_API_ALLOWED_ORIGINS', '*'),
    ],
];
```

### Audit Logging

```php
// config/cms-audit.php
return [
    'enabled' => env('CMS_AUDIT_ENABLED', true),
    'log_level' => env('CMS_AUDIT_LOG_LEVEL', 'info'),
    'events' => [
        'user_login' => true,
        'user_logout' => true,
        'content_created' => true,
        'content_updated' => true,
        'content_deleted' => true,
        'settings_changed' => true,
    ],
];
```

## Troubleshooting Configuration

### Debug Mode

```env
# Development Settings
CMS_DEBUG=true
CMS_LOG_LEVEL=debug
CMS_ENABLE_PROFILER=true
```

### Common Configuration Issues

1. **Cache Issues**: Clear configuration cache after changes
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Permission Issues**: Ensure proper file permissions
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

3. **Environment Variables**: Verify `.env` file is properly formatted

## Next Steps

- [Usage Guide](usage.md) - Learn how to use CMS features
- [API Documentation](api.md) - Integrate with the REST API
- [Performance Guide](performance.md) - Optimize your CMS installation