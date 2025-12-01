# Hooks Reference

## Overview

Digital Shopfront CMS provides a comprehensive hook system for extending functionality. This guide documents all available filters and actions with usage examples.

## Table of Contents

- [Filter Hooks](#filter-hooks)
- [Action Hooks](#action-hooks)
- [Usage Examples](#usage-examples)

## Filter Hooks

Filter hooks allow you to modify data before it's used or returned.

### Content Types

#### `ap.contentTypes.registeredContentTypes`

Modify or add to the array of registered content types.

**Parameters:**
- `$contentTypes` (array) - Associative array of content types keyed by slug

**Returns:** array - Modified content types array

**Example:**

```php
addFilter('ap.contentTypes.registeredContentTypes', function ($contentTypes) {
    $contentTypes['products'] = [
        'name' => 'Products',
        'slug' => 'products',
        'table_name' => 'products',
        'model_class' => 'App\\Models\\Product',
        'supports' => ['title', 'content', 'featured_image'],
        'has_archive' => true,
        'public' => true,
        'show_in_admin' => true,
    ];

    return $contentTypes;
});
```

### Custom Fields

#### `ap.contentTypes.registeredCustomFields`

Modify or add to the array of registered custom fields.

**Parameters:**
- `$fields` (array) - Associative array of custom fields keyed by field key

**Returns:** array - Modified custom fields array

**Example:**

```php
addFilter('ap.contentTypes.registeredCustomFields', function ($fields) {
    $fields['price'] = [
        'name' => 'Price',
        'key' => 'price',
        'type' => 'number',
        'column_type' => 'decimal',
        'content_types' => ['products'],
        'required' => true,
    ];

    return $fields;
});
```

### Taxonomies

#### `ap.taxonomies.registeredTaxonomies`

Modify or add to the array of registered taxonomies.

**Parameters:**
- `$taxonomies` (array) - Associative array of taxonomies keyed by slug

**Returns:** array - Modified taxonomies array

**Example:**

```php
addFilter('ap.taxonomies.registeredTaxonomies', function ($taxonomies) {
    $taxonomies['product_brands'] = [
        'name' => 'Brands',
        'slug' => 'product_brands',
        'content_type_slug' => 'products',
        'hierarchical' => false,
        'show_in_admin' => true,
    ];

    return $taxonomies;
});
```

### Admin Menu

#### `ap.admin.menuStructure`

Modify the entire admin menu structure.

**Parameters:**
- `$menuStructure` (array) - Array of menu items with their configuration

**Returns:** array - Modified menu structure

**Example:**

```php
addFilter('ap.admin.menuStructure', function ($menuStructure) {
    // Add a custom menu item
    $menuStructure[] = [
        'title' => 'Analytics',
        'slug' => 'analytics',
        'icon' => 'fas-chart-bar',
        'component' => 'Analytics\\Dashboard',
        'capability' => 'analytics.view',
        'position' => 50,
    ];

    return $menuStructure;
});
```

## Action Hooks

Action hooks allow you to execute code at specific points in the application lifecycle.

### Content Types

#### `ap.contentTypes.created`

Fires after a content type has been created.

**Parameters:**
- `$contentType` (ContentType) - The created content type instance

**Example:**

```php
doAction('ap.contentTypes.created', function ($contentType) {
    Log::info("Content type created: {$contentType->slug}");

    // Create default taxonomies
    if ($contentType->slug === 'products') {
        $taxonomyManager = app(TaxonomyManager::class);
        $taxonomyManager->registerTaxonomy([
            'name' => 'Product Categories',
            'slug' => 'product_categories',
            'content_type_slug' => 'products',
            'hierarchical' => true,
        ]);
    }
});
```

#### `ap.contentTypes.updated`

Fires after a content type has been updated.

**Parameters:**
- `$contentType` (ContentType) - The updated content type instance

**Example:**

```php
addAction('ap.contentTypes.updated', function ($contentType) {
    Log::info("Content type updated: {$contentType->slug}");
    Cache::forget("content_type_{$contentType->slug}");
});
```

#### `ap.contentTypes.deleting`

Fires before a content type is deleted.

**Parameters:**
- `$contentType` (ContentType) - The content type being deleted

**Example:**

```php
addAction('ap.contentTypes.deleting', function ($contentType) {
    // Clean up related data
    $model = $contentType->model_class;
    if (class_exists($model)) {
        $model::truncate();
    }

    Log::warning("Deleting content type: {$contentType->slug}");
});
```

#### `ap.contentTypes.deleted`

Fires after a content type has been deleted.

**Parameters:**
- `$slug` (string) - The slug of the deleted content type

**Example:**

```php
addAction('ap.contentTypes.deleted', function ($slug) {
    Log::info("Content type deleted: {$slug}");
    Cache::forget("content_types");
});
```

### Custom Fields

#### `ap.contentTypes.customFieldCreated`

Fires after a custom field has been created.

**Parameters:**
- `$field` (CustomField) - The created custom field instance

**Example:**

```php
addAction('ap.contentTypes.customFieldCreated', function ($field) {
    Log::info("Custom field created: {$field->key}");

    // Notify administrators
    Notification::send(
        User::role('admin')->get(),
        new CustomFieldCreated($field)
    );
});
```

#### `ap.contentTypes.customFieldUpdated`

Fires after a custom field has been updated.

**Parameters:**
- `$field` (CustomField) - The updated custom field instance

**Example:**

```php
addAction('ap.contentTypes.customFieldUpdated', function ($field) {
    Log::info("Custom field updated: {$field->key}");
    Cache::forget("custom_fields_{$field->key}");
});
```

#### `ap.contentTypes.customFieldDeleting`

Fires before a custom field is deleted.

**Parameters:**
- `$field` (CustomField) - The custom field being deleted

**Example:**

```php
addAction('ap.contentTypes.customFieldDeleting', function ($field) {
    // Backup data before deletion
    $backup = [];
    foreach ($field->content_types as $contentType) {
        $model = ContentType::where('slug', $contentType)->first()?->model_class;
        if ($model && class_exists($model)) {
            $backup[$contentType] = $model::pluck($field->key, 'id')->toArray();
        }
    }

    Storage::put("backups/custom_field_{$field->key}.json", json_encode($backup));
});
```

#### `ap.contentTypes.customFieldDeleted`

Fires after a custom field has been deleted.

**Parameters:**
- `$key` (string) - The key of the deleted custom field

**Example:**

```php
addAction('ap.contentTypes.customFieldDeleted', function ($key) {
    Log::warning("Custom field deleted: {$key}");
});
```

#### `ap.contentTypes.customFieldColumnAdded`

Fires after a custom field column has been added to a table.

**Parameters:**
- `$field` (CustomField) - The custom field
- `$tableName` (string) - The table name where column was added

**Example:**

```php
addAction('ap.contentTypes.customFieldColumnAdded', function ($field, $tableName) {
    Log::info("Column {$field->key} added to {$tableName}");

    // Create index if needed
    if ($field->type === 'number' || $field->type === 'boolean') {
        Schema::table($tableName, function ($table) use ($field) {
            $table->index($field->key);
        });
    }
});
```

#### `ap.contentTypes.customFieldColumnRemoved`

Fires after a custom field column has been removed from a table.

**Parameters:**
- `$field` (CustomField) - The custom field
- `$tableName` (string) - The table name where column was removed

**Example:**

```php
addAction('ap.contentTypes.customFieldColumnRemoved', function ($field, $tableName) {
    Log::info("Column {$field->key} removed from {$tableName}");
});
```

### Blog Posts

#### `ap.blog.post.created`

Fires after a blog post has been created.

**Parameters:**
- `$post` (Post) - The created post instance

**Example:**

```php
addAction('ap.blog.post.created', function ($post) {
    // Notify subscribers
    if ($post->status === 'published') {
        event(new PostPublished($post));
    }

    // Clear cache
    Cache::forget('recent_posts');
});
```

#### `ap.blog.post.updated`

Fires after a blog post has been updated.

**Parameters:**
- `$post` (Post) - The updated post instance

**Example:**

```php
addAction('ap.blog.post.updated', function ($post) {
    // Clear related caches
    Cache::forget("post_{$post->slug}");
    Cache::forget('recent_posts');

    // Update search index
    $post->searchable();
});
```

#### `ap.blog.post.deleted`

Fires after a blog post has been deleted.

**Parameters:**
- `$post` (Post) - The deleted post instance

**Example:**

```php
addAction('ap.blog.post.deleted', function ($post) {
    // Remove from search index
    $post->unsearchable();

    // Clean up related data
    Storage::deleteDirectory("posts/{$post->id}");
});
```

### Pages

#### `ap.pages.page.created`

Fires after a page has been created.

**Parameters:**
- `$page` (Page) - The created page instance

**Example:**

```php
addAction('ap.pages.page.created', function ($page) {
    // Rebuild navigation cache
    Cache::forget('navigation_menu');

    // Create default child pages
    if ($page->template === 'documentation') {
        Page::create([
            'title' => 'Getting Started',
            'slug' => 'getting-started',
            'parent_id' => $page->id,
            'author_id' => $page->author_id,
            'status' => 'draft',
        ]);
    }
});
```

#### `ap.pages.page.updated`

Fires after a page has been updated.

**Parameters:**
- `$page` (Page) - The updated page instance

**Example:**

```php
addAction('ap.pages.page.updated', function ($page) {
    // Clear caches
    Cache::forget("page_{$page->slug}");
    Cache::forget('navigation_menu');
    Cache::forget('sitemap');
});
```

#### `ap.pages.page.deleted`

Fires after a page has been deleted.

**Parameters:**
- `$page` (Page) - The deleted page instance

**Example:**

```php
addAction('ap.pages.page.deleted', function ($page) {
    // Rebuild navigation
    Cache::forget('navigation_menu');
    Cache::forget('sitemap');
});
```

## Usage Examples

### Modifying Content Type Registration

```php
// Add custom metadata to all content types
addFilter('ap.contentTypes.registeredContentTypes', function ($contentTypes) {
    foreach ($contentTypes as $slug => &$contentType) {
        $contentType['metadata'] = array_merge(
            $contentType['metadata'] ?? [],
            ['version' => '1.0', 'author' => 'MyPlugin']
        );
    }

    return $contentTypes;
});
```

### Adding Custom Fields Programmatically

```php
// Add rating field to all product-like content types
addFilter('ap.contentTypes.registeredCustomFields', function ($fields) {
    $productTypes = ['products', 'services', 'courses'];

    foreach ($productTypes as $type) {
        $fields["rating_{$type}"] = [
            'name' => 'Rating',
            'key' => 'rating',
            'type' => 'number',
            'column_type' => 'integer',
            'content_types' => [$type],
            'options' => ['min' => 1, 'max' => 5],
            'default_value' => 3,
        ];
    }

    return $fields;
});
```

### Auto-Creating Taxonomies

```php
// Automatically create taxonomies when content type is created
addAction('ap.contentTypes.created', function ($contentType) {
    $taxonomyManager = app(TaxonomyManager::class);

    // Add categories
    $taxonomyManager->registerTaxonomy([
        'name' => ucfirst($contentType->slug) . ' Categories',
        'slug' => $contentType->slug . '_categories',
        'content_type_slug' => $contentType->slug,
        'hierarchical' => true,
        'show_in_admin' => true,
    ]);

    // Add tags
    $taxonomyManager->registerTaxonomy([
        'name' => ucfirst($contentType->slug) . ' Tags',
        'slug' => $contentType->slug . '_tags',
        'content_type_slug' => $contentType->slug,
        'hierarchical' => false,
        'show_in_admin' => true,
    ]);
});
```

### Cache Invalidation

```php
// Clear all caches when content is modified
$clearCaches = function () {
    Cache::tags(['content'])->flush();
};

addAction('ap.blog.post.created', $clearCaches);
addAction('ap.blog.post.updated', $clearCaches);
addAction('ap.blog.post.deleted', $clearCaches);
addAction('ap.pages.page.created', $clearCaches);
addAction('ap.pages.page.updated', $clearCaches);
addAction('ap.pages.page.deleted', $clearCaches);
```

### Search Index Updates

```php
// Update search index when content changes
addAction('ap.blog.post.created', function ($post) {
    if ($post->status === 'published') {
        SearchIndex::create([
            'type' => 'post',
            'type_id' => $post->id,
            'title' => $post->title,
            'content' => strip_tags($post->content),
            'url' => $post->permalink,
        ]);
    }
});

addAction('ap.blog.post.updated', function ($post) {
    SearchIndex::where('type', 'post')
        ->where('type_id', $post->id)
        ->delete();

    if ($post->status === 'published') {
        SearchIndex::create([
            'type' => 'post',
            'type_id' => $post->id,
            'title' => $post->title,
            'content' => strip_tags($post->content),
            'url' => $post->permalink,
        ]);
    }
});
```

### Notifications

```php
// Notify admins when custom fields are modified
addAction('ap.contentTypes.customFieldCreated', function ($field) {
    Notification::send(
        User::role('admin')->get(),
        new CustomFieldCreated($field)
    );
});

addAction('ap.contentTypes.customFieldDeleted', function ($key) {
    Notification::send(
        User::role('admin')->get(),
        new CustomFieldDeleted($key)
    );
});
```

### Logging

```php
// Log all content type operations
addAction('ap.contentTypes.created', function ($contentType) {
    Activity::log("Content type created: {$contentType->slug}");
});

addAction('ap.contentTypes.updated', function ($contentType) {
    Activity::log("Content type updated: {$contentType->slug}");
});

addAction('ap.contentTypes.deleted', function ($slug) {
    Activity::log("Content type deleted: {$slug}");
});
```

## Hook Naming Conventions

All hooks follow the format: `ap.{module}.{event}` or `ap.{module}.{context}.{event}`

- `ap` - Prefix (ArtisanPack)
- `{module}` - Module name (contentTypes, blog, pages, etc.)
- `{context}` - Optional context (post, page, etc.)
- `{event}` - Event name (created, updated, deleted, etc.)

**Examples:**
- `ap.contentTypes.created`
- `ap.blog.post.created`
- `ap.pages.page.updated`
- `ap.taxonomies.registeredTaxonomies`

## See Also

- [Content Types Developer Guide](content-types.md)
- [Custom Fields Developer Guide](custom-fields.md)
- [Taxonomies Developer Guide](taxonomies.md)
