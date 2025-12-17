# Content Types Developer Guide

## Overview

Content types allow you to define custom post types in Digital Shopfront CMS. This guide covers how to register content types programmatically, configure their behavior, and integrate them with the admin interface.

## Table of Contents

- [Registering Content Types](#registering-content-types)
- [Available Options](#available-options)
- [Table Naming Conventions](#table-naming-conventions)
- [Admin Pages Configuration](#admin-pages-configuration)
- [Best Practices](#best-practices)
- [Examples](#examples)

## Registering Content Types

### Via Service Provider

Register content types in your service provider's `boot()` method:

```php
<?php

namespace App\Providers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $contentTypeManager = app(ContentTypeManager::class);

        $contentTypeManager->register([
            'name' => 'Products',
            'slug' => 'products',
            'table_name' => 'products',
            'model_class' => 'App\\Models\\Product',
            'description' => 'Manage your product catalog',
            'hierarchical' => false,
            'has_archive' => true,
            'archive_slug' => 'shop',
            'supports' => ['title', 'content', 'excerpt', 'featured_image'],
            'public' => true,
            'show_in_admin' => true,
            'icon' => 'fas-shopping-cart',
            'menu_position' => 30,
            'admin_pages' => [
                'listing' => [
                    'enabled' => true,
                    'title' => 'Products',
                    'slug' => 'products',
                    'component' => 'Modules\\Shop\\Livewire\\Products\\Index',
                    'capability' => 'products.view',
                    'icon' => 'fas-shopping-cart',
                    'position' => 30,
                ],
                'create' => [
                    'enabled' => true,
                    'title' => 'Add New Product',
                    'slug' => 'products.create',
                    'component' => 'Modules\\Shop\\Livewire\\Products\\Create',
                    'capability' => 'products.create',
                    'parent_slug' => 'products',
                ],
                'edit' => [
                    'enabled' => true,
                    'title' => 'Edit Product',
                    'slug' => 'products.edit',
                    'component' => 'Modules\\Shop\\Livewire\\Products\\Edit',
                    'capability' => 'products.edit',
                    'parent_slug' => 'products',
                    'hidden' => true,
                ],
            ],
        ]);
    }
}
```

### Via Filter Hook

Register content types using the filter hook (useful for plugins):

```php
addFilter('ap.contentTypes.registeredContentTypes', function ($contentTypes) {
    $contentTypes['products'] = [
        'name' => 'Products',
        'slug' => 'products',
        'table_name' => 'products',
        'model_class' => 'App\\Models\\Product',
        'supports' => ['title', 'content', 'featured_image'],
        'has_archive' => true,
        'archive_slug' => 'shop',
        'public' => true,
        'show_in_admin' => true,
    ];

    return $contentTypes;
});
```

### Via Database

Create content types through the admin UI or via API:

```php
$contentType = ContentType::create([
    'name' => 'Events',
    'slug' => 'events',
    'table_name' => 'events',
    'model_class' => 'App\\Models\\Event',
    'hierarchical' => false,
    'has_archive' => true,
    'public' => true,
    'show_in_admin' => true,
]);
```

## Available Options

### Required Options

| Option | Type | Description |
|--------|------|-------------|
| `name` | string | Display name (e.g., "Blog Posts") |
| `slug` | string | Machine name (e.g., "posts") |
| `table_name` | string | Database table name (e.g., "posts") |
| `model_class` | string | Fully qualified model class |

### Optional Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `description` | string | null | Description shown in admin |
| `hierarchical` | boolean | false | Supports parent-child relationships |
| `has_archive` | boolean | true | Has archive pages |
| `archive_slug` | string | null | Archive URL slug (e.g., "blog") |
| `supports` | array | null | Supported features |
| `metadata` | array | null | Additional custom settings |
| `public` | boolean | true | Visible on frontend |
| `show_in_admin` | boolean | true | Show in admin menu |
| `icon` | string | null | FontAwesome icon (e.g., "fas-newspaper") |
| `menu_position` | integer | null | Admin menu position |
| `admin_pages` | array | null | Admin page configurations |

### Supports Options

The `supports` array defines which features are available:

```php
'supports' => [
    'title',           // Title field
    'content',         // Content editor
    'excerpt',         // Excerpt field
    'featured_image',  // Featured image
    'author',          // Author assignment
    'comments',        // Comments (if implemented)
    'revisions',       // Revisions (if implemented)
]
```

## Table Naming Conventions

Follow these conventions when naming your content type tables:

### 1. Use Snake Case

```php
✓ 'table_name' => 'products'
✓ 'table_name' => 'product_reviews'
✗ 'table_name' => 'Products'
✗ 'table_name' => 'productReviews'
```

### 2. Use Plural Forms

```php
✓ 'table_name' => 'products'
✓ 'table_name' => 'events'
✗ 'table_name' => 'product'
✗ 'table_name' => 'event'
```

### 3. Taxonomy Tables

Prefix taxonomy tables with the content type:

```php
✓ 'product_categories'
✓ 'product_tags'
✓ 'event_types'
✗ 'categories'
✗ 'tags'
```

### 4. Pivot Tables

Use descriptive names for pivot tables:

```php
✓ 'post_category_pivots'
✓ 'product_tag_pivots'
✗ 'category_post'
✗ 'tag_product'
```

## Admin Pages Configuration

Admin pages allow automatic registration of CRUD interfaces in the admin menu.

### Configuration Structure

```php
'admin_pages' => [
    'listing' => [
        'enabled' => true,
        'title' => 'Products',                              // Menu title
        'slug' => 'products',                               // Route slug
        'component' => 'Modules\\Shop\\Livewire\\Products\\Index',
        'capability' => 'products.view',                    // Required capability
        'icon' => 'fas-shopping-cart',                      // FontAwesome icon
        'position' => 30,                                   // Menu position
        'parent_slug' => null,                              // Top-level menu
    ],
    'create' => [
        'enabled' => true,
        'title' => 'Add New',
        'slug' => 'products.create',
        'component' => 'Modules\\Shop\\Livewire\\Products\\Create',
        'capability' => 'products.create',
        'parent_slug' => 'products',                        // Child of listing
    ],
    'edit' => [
        'enabled' => true,
        'title' => 'Edit Product',
        'slug' => 'products.edit',
        'component' => 'Modules\\Shop\\Livewire\\Products\\Edit',
        'capability' => 'products.edit',
        'parent_slug' => 'products',
        'hidden' => true,                                   // Don't show in menu
    ],
]
```

### Routes

Routes are automatically registered based on admin pages configuration:

- Listing: `/admin/{slug}`
- Create: `/admin/{slug}/create`
- Edit: `/admin/{slug}/{id}/edit`

## Best Practices

### 1. Consistent Naming

Use consistent naming across slug, table, and model:

```php
// Good
'slug' => 'products',
'table_name' => 'products',
'model_class' => 'App\\Models\\Product',

// Bad
'slug' => 'product',
'table_name' => 'product_items',
'model_class' => 'App\\Models\\ProductModel',
```

### 2. Use Capabilities Format

Follow the `feature.capability` format:

```php
✓ 'products.view'
✓ 'products.create'
✓ 'products.edit'
✗ 'view_products'
✗ 'can_edit_product'
```

### 3. Set Appropriate Defaults

Configure sensible defaults for your content type:

```php
'hierarchical' => false,        // Only true if needed
'has_archive' => true,          // Most content types need archives
'public' => true,               // Unless it's internal only
'show_in_admin' => true,        // Unless managing via code only
```

### 4. Create Migration First

Always create the database table before registering the content type:

```bash
php artisan make:migration create_products_table
```

### 5. Use Admin Pages

Configure admin pages to automatically create admin UI:

```php
'admin_pages' => [
    'listing' => ['enabled' => true, ...],
    'create' => ['enabled' => true, ...],
    'edit' => ['enabled' => true, ...],
]
```

## Examples

### Simple Content Type (Events)

```php
$contentTypeManager->register([
    'name' => 'Events',
    'slug' => 'events',
    'table_name' => 'events',
    'model_class' => 'App\\Models\\Event',
    'description' => 'Manage upcoming events',
    'supports' => ['title', 'content', 'excerpt', 'featured_image'],
    'has_archive' => true,
    'archive_slug' => 'events',
    'public' => true,
    'show_in_admin' => true,
    'icon' => 'fas-calendar',
    'menu_position' => 25,
]);
```

### Hierarchical Content Type (Documentation)

```php
$contentTypeManager->register([
    'name' => 'Documentation',
    'slug' => 'docs',
    'table_name' => 'docs',
    'model_class' => 'App\\Models\\Doc',
    'hierarchical' => true,              // Enable parent-child
    'has_archive' => true,
    'archive_slug' => 'documentation',
    'supports' => ['title', 'content'],
    'public' => true,
    'show_in_admin' => true,
    'icon' => 'fas-book',
]);
```

### Internal Content Type (Form Submissions)

```php
$contentTypeManager->register([
    'name' => 'Form Submissions',
    'slug' => 'submissions',
    'table_name' => 'form_submissions',
    'model_class' => 'App\\Models\\FormSubmission',
    'hierarchical' => false,
    'has_archive' => false,              // No public archives
    'public' => false,                   // Admin only
    'show_in_admin' => true,
    'icon' => 'fas-envelope',
    'supports' => [],                    // Custom fields only
]);
```

## Retrieving Content Types

### Get All Registered Content Types

```php
$contentTypeManager = app(ContentTypeManager::class);
$contentTypes = $contentTypeManager->getRegisteredContentTypes();
```

### Get Specific Content Type

```php
$contentType = $contentTypeManager->getContentType('products');
```

### Check if Content Type Exists

```php
if ($contentTypeManager->contentTypeExists('products')) {
    // Content type exists
}
```

### Get Model Instance

```php
$contentType = ContentType::where('slug', 'products')->first();
$modelInstance = $contentType->getModelInstance();
```

### Check Supported Features

```php
if ($contentType->supportsFeature('featured_image')) {
    // Content type supports featured images
}
```

## See Also

- [Custom Fields Developer Guide](custom-fields.md)
- [Taxonomies Developer Guide](taxonomies.md)
- [Hooks Reference](hooks-reference.md)
