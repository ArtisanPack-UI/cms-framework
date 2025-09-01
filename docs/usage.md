# Usage Guide

This comprehensive guide covers how to use all features of the ArtisanPack UI CMS Framework.

## Getting Started

Once installed and configured, the CMS Framework provides a powerful set of tools for managing content, users, and website functionality.

### Accessing the Admin Interface

The admin interface is available at `/admin` (or your configured admin path):

```
https://your-domain.com/admin
```

## Content Management

### Working with Content Types

The CMS Framework supports flexible content types. Here's how to work with them:

#### Registering Custom Content Types

```php
use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;

// In a service provider or configuration file
$contentManager = app(ContentTypeManager::class);

$contentManager->register('product', [
    'name' => 'Product',
    'plural' => 'Products',
    'description' => 'E-commerce products',
    'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
    'hierarchical' => false,
    'public' => true,
    'has_archive' => true,
    'menu_position' => 5,
    'menu_icon' => 'dashicons-products',
    'rewrite' => ['slug' => 'products'],
]);
```

#### Creating Content Programmatically

```php
use ArtisanPackUI\CMSFramework\Features\Content\ContentManager;

$contentManager = app(ContentManager::class);

// Create a new post
$post = $contentManager->create([
    'title' => 'My First Post',
    'content' => 'This is the content of my post.',
    'excerpt' => 'A brief excerpt...',
    'status' => 'published',
    'type' => 'post',
    'author_id' => auth()->id(),
    'meta' => [
        'custom_field' => 'custom_value',
        'featured' => true,
    ],
]);

// Update existing content
$contentManager->update($post->id, [
    'title' => 'Updated Title',
    'content' => 'Updated content...',
]);

// Delete content
$contentManager->delete($post->id);
```

#### Querying Content

```php
use ArtisanPackUI\CMSFramework\Models\Content;

// Get published posts
$posts = Content::where('type', 'post')
    ->where('status', 'published')
    ->orderBy('created_at', 'desc')
    ->paginate(10);

// Get content with taxonomies
$posts = Content::with(['taxonomies', 'media'])
    ->where('type', 'post')
    ->get();

// Search content
$results = Content::search('search term')
    ->where('type', 'post')
    ->get();
```

### Working with Taxonomies

Taxonomies help organize your content with categories and tags.

#### Creating Taxonomies

```php
use ArtisanPackUI\CMSFramework\Features\Taxonomies\TaxonomyManager;

$taxonomyManager = app(TaxonomyManager::class);

// Create a category
$category = $taxonomyManager->create('category', [
    'name' => 'Technology',
    'slug' => 'technology',
    'description' => 'Posts about technology',
    'parent_id' => null, // For hierarchical taxonomies
]);

// Create a tag
$tag = $taxonomyManager->create('tag', [
    'name' => 'Laravel',
    'slug' => 'laravel',
    'description' => 'Posts about Laravel framework',
]);
```

#### Assigning Taxonomies to Content

```php
// Assign taxonomies when creating content
$post = $contentManager->create([
    'title' => 'Laravel Tips',
    'content' => 'Some Laravel tips...',
    'type' => 'post',
    'taxonomies' => [
        'category' => ['technology'],
        'tag' => ['laravel', 'php', 'web-development'],
    ],
]);

// Or assign taxonomies to existing content
$contentManager->assignTaxonomies($post->id, [
    'category' => ['technology', 'programming'],
    'tag' => ['laravel'],
]);
```

## User Management

### Working with Users and Roles

The CMS Framework provides comprehensive user management with role-based permissions.

#### Creating Users

```php
use ArtisanPackUI\CMSFramework\Features\Users\UserManager;

$userManager = app(UserManager::class);

// Create a new user
$user = $userManager->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secure-password',
    'role' => 'editor',
]);

// Create an admin user
$admin = $userManager->createAdmin([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => 'admin-password',
]);
```

#### Managing User Roles

```php
// Assign role to user
$userManager->assignRole($user->id, 'author');

// Check user capabilities
if ($userManager->userCan($user->id, 'edit_posts')) {
    // User can edit posts
}

// Get users by role
$editors = $userManager->getUsersByRole('editor');
```

#### Custom Capabilities

```php
// Add custom capabilities
$userManager->addCapability('manage_products');
$userManager->addCapability('view_analytics');

// Assign capabilities to roles
$userManager->addCapabilityToRole('shop_manager', 'manage_products');

// Check custom capabilities
if ($userManager->userCan($user->id, 'manage_products')) {
    // User can manage products
}
```

### Two-Factor Authentication

Enable and manage 2FA for enhanced security:

```php
use ArtisanPackUI\CMSFramework\Features\Auth\TwoFactorAuth;

$twoFactorAuth = app(TwoFactorAuth::class);

// Enable 2FA for a user
$secret = $twoFactorAuth->enable($user);

// Generate QR code for authenticator app
$qrCode = $twoFactorAuth->generateQrCode($user, $secret);

// Verify 2FA code
$isValid = $twoFactorAuth->verify($user, $code);

// Disable 2FA
$twoFactorAuth->disable($user);
```

## Media Management

### Uploading and Managing Media

```php
use ArtisanPackUI\CMSFramework\Features\Media\MediaManager;

$mediaManager = app(MediaManager::class);

// Upload a file
$media = $mediaManager->upload($request->file('upload'), [
    'alt_text' => 'Description of the image',
    'caption' => 'Optional caption',
    'title' => 'Media title',
]);

// Upload with categories and tags
$media = $mediaManager->upload($request->file('upload'), [
    'alt_text' => 'Product image',
    'categories' => ['products', 'featured'],
    'tags' => ['summer-collection', 'new-arrival'],
]);

// Get media by category
$featuredMedia = $mediaManager->getByCategory('featured');

// Search media
$searchResults = $mediaManager->search('product');
```

### Working with Image Sizes

```php
// Get different image sizes
$thumbnail = $mediaManager->getImageSize($media, 'thumbnail');
$medium = $mediaManager->getImageSize($media, 'medium');
$large = $mediaManager->getImageSize($media, 'large');

// Generate custom size
$custom = $mediaManager->generateImageSize($media, [
    'width' => 500,
    'height' => 300,
    'crop' => true,
]);
```

## Admin Interface Customization

### Adding Admin Pages

```php
use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesManager;

$adminManager = app(AdminPagesManager::class);

// Add a simple admin page
$adminManager->addPage([
    'title' => 'Custom Settings',
    'menu_title' => 'Settings',
    'slug' => 'custom-settings',
    'capability' => 'manage_options',
    'callback' => function() {
        return view('admin.custom-settings');
    },
    'icon' => 'dashicons-admin-generic',
    'position' => 80,
]);

// Add a submenu page
$adminManager->addSubmenuPage([
    'parent_slug' => 'custom-settings',
    'title' => 'Advanced Settings',
    'menu_title' => 'Advanced',
    'slug' => 'advanced-settings',
    'capability' => 'manage_options',
    'callback' => function() {
        return view('admin.advanced-settings');
    },
]);
```

### Dashboard Widgets

```php
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;

$widgetManager = app(DashboardWidgetsManager::class);

// Add a dashboard widget
$widgetManager->add('recent_activity', [
    'title' => 'Recent Activity',
    'callback' => function() {
        $activities = collect([
            'User John Doe logged in',
            'New post "Laravel Tips" published',
            'Comment approved on "Getting Started"',
        ]);
        
        return view('widgets.recent-activity', compact('activities'));
    },
    'position' => 'normal',
    'priority' => 'high',
]);

// Add a widget with configuration
$widgetManager->add('site_stats', [
    'title' => 'Site Statistics',
    'callback' => function() {
        $stats = [
            'total_posts' => Content::where('type', 'post')->count(),
            'total_users' => User::count(),
            'total_comments' => Comment::count(),
        ];
        
        return view('widgets.site-stats', compact('stats'));
    },
    'configurable' => true,
    'config_callback' => function() {
        return view('widgets.site-stats-config');
    },
]);
```

## Settings Management

### Working with Settings

```php
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;

$settingsManager = app(SettingsManager::class);

// Register settings
$settingsManager->register('site_title', 'My Awesome Site');
$settingsManager->register('posts_per_page', 10);
$settingsManager->register('theme_options', [
    'primary_color' => '#007cba',
    'secondary_color' => '#50575e',
]);

// Get settings
$siteTitle = $settingsManager->get('site_title');
$postsPerPage = $settingsManager->get('posts_per_page', 5); // with default

// Update settings
$settingsManager->set('site_title', 'New Site Title');
$settingsManager->set('theme_options', [
    'primary_color' => '#ff0000',
    'secondary_color' => '#000000',
]);

// Get all settings
$allSettings = $settingsManager->all();
```

### Creating Settings Pages

```php
// Register a settings page
$settingsManager->addPage([
    'title' => 'Theme Settings',
    'menu_title' => 'Theme',
    'slug' => 'theme-settings',
    'sections' => [
        'colors' => [
            'title' => 'Color Settings',
            'description' => 'Customize your theme colors',
            'fields' => [
                'primary_color' => [
                    'type' => 'color',
                    'label' => 'Primary Color',
                    'default' => '#007cba',
                ],
                'secondary_color' => [
                    'type' => 'color',
                    'label' => 'Secondary Color',
                    'default' => '#50575e',
                ],
            ],
        ],
        'layout' => [
            'title' => 'Layout Settings',
            'fields' => [
                'sidebar_position' => [
                    'type' => 'select',
                    'label' => 'Sidebar Position',
                    'options' => [
                        'left' => 'Left',
                        'right' => 'Right',
                        'none' => 'No Sidebar',
                    ],
                    'default' => 'right',
                ],
            ],
        ],
    ],
]);
```

## Themes and Plugins

### Working with Themes

```php
use ArtisanPackUI\CMSFramework\Features\Themes\ThemeManager;

$themeManager = app(ThemeManager::class);

// Get active theme
$activeTheme = $themeManager->getActiveTheme();

// Switch theme
$themeManager->activate('my-custom-theme');

// Get available themes
$themes = $themeManager->getAvailable();

// Get theme information
$themeInfo = $themeManager->getThemeInfo('my-theme');
```

### Working with Plugins

```php
use ArtisanPackUI\CMSFramework\Features\Plugins\PluginManager;

$pluginManager = app(PluginManager::class);

// Get active plugins
$activePlugins = $pluginManager->getActive();

// Activate a plugin
$pluginManager->activate('my-plugin');

// Deactivate a plugin
$pluginManager->deactivate('my-plugin');

// Get plugin information
$pluginInfo = $pluginManager->getPluginInfo('my-plugin');
```

## Notifications

### Sending Notifications

```php
use ArtisanPackUI\CMSFramework\Features\Notifications\NotificationManager;

$notificationManager = app(NotificationManager::class);

// Send a notification to a user
$notificationManager->send($user, 'Welcome to our site!', [
    'type' => 'success',
    'action' => [
        'text' => 'Get Started',
        'url' => '/getting-started',
    ],
]);

// Send to multiple users
$notificationManager->sendToUsers([$user1, $user2], 'New feature available!');

// Send to users with specific role
$notificationManager->sendToRole('editor', 'New content guidelines available');

// Send system notification
$notificationManager->sendSystem('Maintenance scheduled for tonight', [
    'type' => 'warning',
    'dismissible' => false,
]);
```

### Managing Notification Templates

```php
// Register notification templates
$notificationManager->registerTemplate('welcome_email', [
    'subject' => 'Welcome to {{ site_name }}',
    'content' => 'Hi {{ user_name }}, welcome to our site!',
    'variables' => ['site_name', 'user_name'],
]);

// Send using template
$notificationManager->sendTemplate($user, 'welcome_email', [
    'site_name' => $settingsManager->get('site_title'),
    'user_name' => $user->name,
]);
```

## Progressive Web App (PWA)

### Enabling PWA Features

```php
use ArtisanPackUI\CMSFramework\Features\PWA\PWAManager;

$pwaManager = app(PWAManager::class);

// Configure PWA settings
$pwaManager->configure([
    'name' => 'My CMS App',
    'short_name' => 'CMS',
    'description' => 'A powerful CMS application',
    'theme_color' => '#007cba',
    'background_color' => '#ffffff',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'icons' => [
        '192x192' => '/icons/icon-192.png',
        '512x512' => '/icons/icon-512.png',
    ],
]);

// Generate manifest
$manifest = $pwaManager->generateManifest();

// Register service worker
$pwaManager->registerServiceWorker('/sw.js');
```

## Audit Logging

### Tracking User Actions

```php
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;

$auditLogger = app(AuditLogger::class);

// Log user actions
$auditLogger->log('content_created', [
    'user_id' => auth()->id(),
    'content_id' => $post->id,
    'content_type' => 'post',
    'title' => $post->title,
]);

// Log system events
$auditLogger->logSystem('backup_completed', [
    'backup_file' => 'backup-2023-12-01.sql',
    'size' => '15MB',
]);

// Query audit logs
$logs = $auditLogger->getLogs([
    'user_id' => auth()->id(),
    'event' => 'content_created',
    'date_from' => now()->subDays(7),
]);
```

## Hooks and Filters (Eventy Integration)

### Using Hooks

```php
use TorMorten\Eventy\Facades\Eventy;

// Add action hooks
Eventy::addAction('ap.cms.content.created', function($content) {
    // Do something when content is created
    Log::info("Content created: {$content->title}");
});

Eventy::addAction('ap.cms.user.login', function($user) {
    // Track user login
    $user->update(['last_login' => now()]);
});

// Add filter hooks
Eventy::addFilter('ap.cms.content.save', function($data) {
    // Modify content before saving
    $data['slug'] = Str::slug($data['title']);
    return $data;
});

// Trigger actions
Eventy::action('ap.cms.content.created', $content);

// Apply filters
$contentData = Eventy::filter('ap.cms.content.save', $data);
```

## API Integration

### Using the REST API

The CMS Framework provides a comprehensive REST API:

```php
// Get content via API
$response = Http::get('/api/cms/content', [
    'type' => 'post',
    'status' => 'published',
    'per_page' => 10,
]);

// Create content via API
$response = Http::post('/api/cms/content', [
    'title' => 'New Post',
    'content' => 'Post content...',
    'type' => 'post',
    'status' => 'published',
]);

// Update content via API
$response = Http::put("/api/cms/content/{$contentId}", [
    'title' => 'Updated Title',
]);

// Delete content via API
$response = Http::delete("/api/cms/content/{$contentId}");
```

For detailed API documentation, see the [API Documentation](api.md).

## Best Practices

### Performance Optimization

1. **Use Caching**: Enable content and query caching
2. **Optimize Database Queries**: Use eager loading and query optimization
3. **Image Optimization**: Use appropriate image sizes and formats
4. **CDN Integration**: Use CDN for static assets

### Security Best Practices

1. **Keep Updated**: Regularly update the CMS Framework
2. **Strong Passwords**: Enforce strong password policies
3. **Two-Factor Authentication**: Enable 2FA for admin users
4. **Regular Backups**: Implement automated backup solutions
5. **Security Headers**: Configure appropriate security headers

### Development Best Practices

1. **Use Hooks**: Extend functionality using the Eventy hook system
2. **Custom Content Types**: Create specific content types for your needs
3. **Testing**: Write tests for custom functionality
4. **Documentation**: Document custom implementations

## Troubleshooting

### Common Issues

1. **Performance Issues**: Check database queries and enable caching
2. **Permission Errors**: Verify user roles and capabilities
3. **Theme Issues**: Check theme compatibility and file permissions
4. **Plugin Conflicts**: Deactivate plugins one by one to identify conflicts

### Debug Mode

Enable debug mode for development:

```env
CMS_DEBUG=true
CMS_LOG_LEVEL=debug
```

## Next Steps

- [API Documentation](api.md) - Complete REST API reference
- [Performance Guide](performance.md) - Optimization strategies
- [Contributing Guide](contributing.md) - Contribute to the project