---
title: Content Types
---

# Content Types

This document provides detailed information about the Content Types feature in the ArtisanPack UI CMS Framework.

## Overview

Content Types allow you to define and manage different types of content within your CMS. The framework comes with built-in content types like posts and pages, but also allows you to create custom content types to suit your specific needs.

Each content type can have its own set of fields, taxonomies, and behaviors, making the CMS highly flexible and adaptable to various use cases.

## Core Components

### ContentType Model

The `ContentType` model represents a user-defined content type within the CMS Framework.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Models;
```

#### Properties

- `$id`: Unique identifier for the content type
- `$handle`: Unique string identifier for the content type (machine name)
- `$label`: Singular human-readable name
- `$label_plural`: Plural human-readable name
- `$slug`: Base slug for URLs related to this content type
- `$definition`: JSON array containing the content type's full schema and properties
- `$created_at`: Timestamp when the content type was created
- `$updated_at`: Timestamp when the content type was last updated

### Content Model

The `Content` model represents a generic content item in the CMS Framework, capable of representing various content types via a 'type' column and 'meta' JSON field.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Models;
```

#### Properties

- `$id`: Unique identifier for the content item
- `$title`: Title of the content item
- `$slug`: URL-friendly slug for the content item
- `$content`: Main content text
- `$type`: Content type handle (e.g., 'post', 'page', 'video')
- `$status`: Publication status (e.g., 'draft', 'published')
- `$author_id`: ID of the user who created the content
- `$parent_id`: ID of the parent content item (for hierarchical content types)
- `$meta`: JSON field for storing type-specific data
- `$published_at`: Timestamp when the content was/will be published
- `$created_at`: Timestamp when the content was created
- `$updated_at`: Timestamp when the content was last updated

#### Key Methods

- `contentTypeDefinition()`: Gets the definition for this content item's type
- `getMeta($key, $default = null)`: Gets a specific meta value from the 'meta' JSON column
- `setMeta($key, $value)`: Sets a specific meta value in the 'meta' JSON column
- `author()`: Gets the author that owns the Content
- `parent()`: Gets the parent content item for hierarchical content types
- `children()`: Gets the child content items for hierarchical content types
- `terms()`: Gets the terms that are assigned to the content
- `scopeOfType($query, $type)`: Scope a query to only include content of a given type
- `scopePublished($query)`: Scope a query to only include published content

### ContentTypeManager

The `ContentTypeManager` class manages the registration and retrieval of content type definitions.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\ContentTypes;
```

#### Key Methods

- `registerContentType($handle, $definition)`: Registers a content type definition
- `getContentType($handle)`: Retrieves a content type definition by its handle
- `allContentTypes()`: Retrieves all registered content type definitions
- `saveUserContentType($handle, $label, $labelPlural, $slug, $definition)`: Adds or updates a user-defined content type in the database
- `deleteUserContentType($handle)`: Deletes a user-defined content type from the database
- `refreshContentTypesCache()`: Refreshes the content types cache

## Using Content Types

### Creating a Content Type

Content types can be created programmatically or through the API:

#### Programmatically

```php
use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;

// Get the ContentTypeManager instance
$manager = ContentTypeManager::instance();

// Define a new content type
$handle = 'product';
$label = 'Product';
$labelPlural = 'Products';
$slug = 'products';
$definition = [
    'fields' => [
        'price' => [
            'type' => 'number',
            'label' => 'Price',
            'required' => true,
        ],
        'sku' => [
            'type' => 'text',
            'label' => 'SKU',
            'required' => true,
        ],
    ],
    'supports' => [
        'featured_image' => true,
        'comments' => false,
    ],
];

// Save the content type
$contentType = $manager->saveUserContentType($handle, $label, $labelPlural, $slug, $definition);
```

#### Through the API

You can create content types through the API using the ContentTypeController:

```http
POST /api/content-types
Content-Type: application/json

{
    "handle": "product",
    "label": "Product",
    "label_plural": "Products",
    "slug": "products",
    "definition": {
        "fields": {
            "price": {
                "type": "number",
                "label": "Price",
                "required": true
            },
            "sku": {
                "type": "text",
                "label": "SKU",
                "required": true
            }
        },
        "supports": {
            "featured_image": true,
            "comments": false
        }
    }
}
```

### Creating Content Items

Once you have defined a content type, you can create content items of that type:

```php
use ArtisanPackUI\CMSFramework\Models\Content;

// Create a new product
$product = new Content();
$product->title = 'Sample Product';
$product->slug = 'sample-product';
$product->content = 'This is a sample product description.';
$product->type = 'product'; // The handle of the content type
$product->status = 'published';
$product->author_id = 1;
$product->setMeta('price', 99.99);
$product->setMeta('sku', 'PROD-001');
$product->save();
```

### Querying Content Items by Type

You can query content items by their type using the `ofType` scope:

```php
// Get all published products
$products = Content::ofType('product')->published()->get();

// Get a specific product
$product = Content::ofType('product')->where('slug', 'sample-product')->first();

// Access meta fields
$price = $product->getMeta('price');
$sku = $product->getMeta('sku');
```

## Content Type Definition Structure

The `definition` field of a content type can include various properties:

```php
[
    'fields' => [
        // Custom fields for this content type
        'field_name' => [
            'type' => 'text|textarea|number|date|select|etc',
            'label' => 'Human-readable label',
            'description' => 'Help text for the field',
            'required' => true|false,
            'default' => 'Default value',
            'options' => [], // For select fields
            // Other field-specific properties
        ],
        // More fields...
    ],
    'supports' => [
        // Core features this content type supports
        'title' => true, // Whether to show the title field
        'editor' => true, // Whether to show the main content editor
        'featured_image' => true|false,
        'excerpt' => true|false,
        'comments' => true|false,
        'revisions' => true|false,
        'hierarchical' => true|false, // Whether content can have parent/child relationships
        // Other supported features
    ],
    'taxonomies' => [
        // Taxonomies associated with this content type
        'category',
        'tag',
        // Custom taxonomies
    ],
    'ui' => [
        // UI configuration for admin screens
        'icon' => 'icon-name', // Icon to use in the admin menu
        'menu_position' => 5, // Position in the admin menu
        'show_in_menu' => true|false,
        // Other UI settings
    ],
    'permissions' => [
        // Permission settings for this content type
        'create' => ['admin', 'editor'],
        'edit' => ['admin', 'editor', 'author'],
        'delete' => ['admin'],
        // Other permission settings
    ],
    'routes' => [
        // Custom route configuration
        'single' => '{slug}',
        'archive' => '{type}',
        // Other route patterns
    ],
]
```

## Best Practices

1. **Use descriptive handles**: Choose content type handles that are descriptive and unique.
2. **Plan your fields carefully**: Think about what fields you need for each content type before creating it.
3. **Use taxonomies**: Leverage taxonomies to categorize and organize your content.
4. **Consider hierarchical relationships**: If your content has parent/child relationships, enable the hierarchical support.
5. **Cache aggressively**: Content type definitions are cached for performance, but consider additional caching for frequently accessed content.

## API Reference

The Content Types feature provides the following API endpoints:

- `GET /api/content-types`: List all content types
- `POST /api/content-types`: Create a new content type
- `GET /api/content-types/{id}`: Get a specific content type
- `PUT /api/content-types/{id}`: Update a content type
- `DELETE /api/content-types/{id}`: Delete a content type

For content items:

- `GET /api/content`: List all content items
- `POST /api/content`: Create a new content item
- `GET /api/content/{id}`: Get a specific content item
- `PUT /api/content/{id}`: Update a content item
- `DELETE /api/content/{id}`: Delete a content item
- `GET /api/content/type/{type}`: List all content items of a specific type

## Related Documentation

- [Overview](overview.md): General overview of the CMS Framework
- [Media](media.md): Documentation on media management, which can be used with content types
