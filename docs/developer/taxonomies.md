# Taxonomies Developer Guide

## Overview

Taxonomies allow you to categorize and organize content in Digital Shopfront CMS. This guide covers how to register custom taxonomies, configure their behavior, and attach them to content types.

## Table of Contents

- [Understanding Taxonomies](#understanding-taxonomies)
- [Registering Taxonomies](#registering-taxonomies)
- [Hierarchical vs Flat Taxonomies](#hierarchical-vs-flat-taxonomies)
- [Attaching to Content Types](#attaching-to-content-types)
- [Working with Taxonomy Terms](#working-with-taxonomy-terms)
- [Examples](#examples)

## Understanding Taxonomies

### What is a Taxonomy?

A taxonomy is a way to group and classify content. Examples include:
- **Categories** (hierarchical) - Technology > Web Development > Laravel
- **Tags** (flat) - laravel, php, programming
- **Custom** - Product Types, Event Categories, etc.

### Built-in Taxonomies

Digital Shopfront CMS includes these built-in taxonomies:

- `post_categories` - Blog post categories (hierarchical)
- `post_tags` - Blog post tags (flat)
- `page_categories` - Page categories (hierarchical)
- `page_tags` - Page tags (flat)

## Registering Taxonomies

### Via Service Provider

Register taxonomies in your service provider's `boot()` method:

```php
<?php

namespace App\Providers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\TaxonomyManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $taxonomyManager = app(TaxonomyManager::class);

        $taxonomyManager->registerTaxonomy([
            'name' => 'Product Categories',
            'slug' => 'product_categories',
            'content_type_slug' => 'products',
            'description' => 'Organize products into categories',
            'hierarchical' => true,
            'show_in_admin' => true,
            'rest_base' => 'product-categories',
        ]);
    }
}
```

### Via Filter Hook

Register taxonomies using the filter hook (useful for plugins):

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

### Via Database

Create taxonomies through the admin UI or API:

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Taxonomy;

$taxonomy = Taxonomy::create([
    'name' => 'Event Types',
    'slug' => 'event_types',
    'content_type_slug' => 'events',
    'hierarchical' => false,
    'show_in_admin' => true,
]);
```

## Hierarchical vs Flat Taxonomies

### Hierarchical Taxonomies (Categories)

Hierarchical taxonomies support parent-child relationships:

```
Technology
├── Web Development
│   ├── Frontend
│   └── Backend
└── Mobile Development
    ├── iOS
    └── Android
```

**Use Cases:**
- Product categories
- Documentation sections
- Geographic locations
- Department structures

**Example:**

```php
$taxonomyManager->registerTaxonomy([
    'name' => 'Product Categories',
    'slug' => 'product_categories',
    'content_type_slug' => 'products',
    'hierarchical' => true,              // Enable hierarchy
    'show_in_admin' => true,
]);
```

**Creating Hierarchical Terms:**

```php
use App\Models\ProductCategory;

// Create parent category
$technology = ProductCategory::create([
    'name' => 'Technology',
    'slug' => 'technology',
]);

// Create child category
$webDev = ProductCategory::create([
    'name' => 'Web Development',
    'slug' => 'web-development',
    'parent_id' => $technology->id,      // Set parent
]);
```

### Flat Taxonomies (Tags)

Flat taxonomies have no hierarchy:

```
laravel, php, programming, web-development, api
```

**Use Cases:**
- Tags/keywords
- Colors
- Sizes
- Simple classifications

**Example:**

```php
$taxonomyManager->registerTaxonomy([
    'name' => 'Product Tags',
    'slug' => 'product_tags',
    'content_type_slug' => 'products',
    'hierarchical' => false,             // Flat structure
    'show_in_admin' => true,
]);
```

**Creating Flat Terms:**

```php
use App\Models\ProductTag;

ProductTag::create(['name' => 'Featured', 'slug' => 'featured']);
ProductTag::create(['name' => 'On Sale', 'slug' => 'on-sale']);
ProductTag::create(['name' => 'New Arrival', 'slug' => 'new-arrival']);
```

## Attaching to Content Types

### Single Content Type

Attach a taxonomy to one content type:

```php
$taxonomyManager->registerTaxonomy([
    'name' => 'Product Categories',
    'slug' => 'product_categories',
    'content_type_slug' => 'products',   // Single content type
]);
```

### Multiple Content Types

Attach a taxonomy to multiple content types by creating separate taxonomies:

```php
// For products
$taxonomyManager->registerTaxonomy([
    'name' => 'Product Categories',
    'slug' => 'product_categories',
    'content_type_slug' => 'products',
]);

// For services (if you want shared terms, use the same taxonomy)
$taxonomyManager->registerTaxonomy([
    'name' => 'Service Categories',
    'slug' => 'service_categories',
    'content_type_slug' => 'services',
]);
```

### Taxonomy Relationships in Models

Define the relationship in your model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    /**
     * Get the categories for the product.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'product_category_pivots',
            'product_id',
            'product_category_id'
        );
    }

    /**
     * Get the tags for the product.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductTag::class,
            'product_tag_pivots',
            'product_id',
            'product_tag_id'
        );
    }
}
```

## Working with Taxonomy Terms

### Attaching Terms to Content

```php
$product = Product::find(1);

// Attach single category
$product->categories()->attach($categoryId);

// Attach multiple categories
$product->categories()->attach([1, 2, 3]);

// Sync categories (removes old, adds new)
$product->categories()->sync([1, 2, 3]);

// Attach with metadata
$product->categories()->attach($categoryId, ['order' => 1]);
```

### Detaching Terms

```php
// Detach single category
$product->categories()->detach($categoryId);

// Detach all categories
$product->categories()->detach();
```

### Querying by Taxonomy

```php
// Get all products in a category
$products = Product::whereHas('categories', function ($query) use ($categoryId) {
    $query->where('product_categories.id', $categoryId);
})->get();

// Get products with any of these tags
$products = Product::whereHas('tags', function ($query) use ($tagIds) {
    $query->whereIn('product_tags.id', $tagIds);
})->get();

// Get products with all of these tags
$products = Product::whereHas('tags', function ($query) use ($tag1Id) {
    $query->where('product_tags.id', $tag1Id);
})->whereHas('tags', function ($query) use ($tag2Id) {
    $query->where('product_tags.id', $tag2Id);
})->get();
```

### Getting Term Children (Hierarchical)

```php
$category = ProductCategory::find(1);

// Get immediate children
$children = $category->children;

// Get all descendants recursively
$descendants = $category->descendants();

// Get parent
$parent = $category->parent;

// Get all ancestors
$ancestors = $category->ancestors();
```

## Creating Taxonomy Models

### Taxonomy Model Structure

Create a model for your taxonomy:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Get the products in this category.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_category_pivots',
            'product_category_id',
            'product_id'
        );
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id')
            ->orderBy('order');
    }

    /**
     * Get the permalink for the category archive.
     */
    public function getPermalinkAttribute(): string
    {
        return url("/products/category/{$this->slug}");
    }
}
```

### Migration for Taxonomy

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('product_categories')
                ->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('parent_id');
        });

        // Pivot table
        Schema::create('product_category_pivots', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('product_category_id')
                ->constrained()
                ->onDelete('cascade');
            $table->primary(['product_id', 'product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_pivots');
        Schema::dropIfExists('product_categories');
    }
};
```

## Examples

### Product Categories (Hierarchical)

```php
$taxonomyManager->registerTaxonomy([
    'name' => 'Product Categories',
    'slug' => 'product_categories',
    'content_type_slug' => 'products',
    'description' => 'Organize products into hierarchical categories',
    'hierarchical' => true,
    'show_in_admin' => true,
    'rest_base' => 'product-categories',
]);
```

### Product Tags (Flat)

```php
$taxonomyManager->registerTaxonomy([
    'name' => 'Product Tags',
    'slug' => 'product_tags',
    'content_type_slug' => 'products',
    'description' => 'Tag products with keywords',
    'hierarchical' => false,
    'show_in_admin' => true,
    'rest_base' => 'product-tags',
]);
```

### Event Types (Flat)

```php
$taxonomyManager->registerTaxonomy([
    'name' => 'Event Types',
    'slug' => 'event_types',
    'content_type_slug' => 'events',
    'hierarchical' => false,
    'show_in_admin' => true,
]);
```

### Documentation Sections (Hierarchical)

```php
$taxonomyManager->registerTaxonomy([
    'name' => 'Documentation Sections',
    'slug' => 'doc_sections',
    'content_type_slug' => 'docs',
    'hierarchical' => true,
    'show_in_admin' => true,
]);
```

## Best Practices

### 1. Use Descriptive Names

```php
✓ 'name' => 'Product Categories'
✓ 'name' => 'Event Types'
✗ 'name' => 'Categories'
✗ 'name' => 'Types'
```

### 2. Follow Slug Conventions

```php
✓ 'slug' => 'product_categories'
✓ 'slug' => 'event_types'
✗ 'slug' => 'categories'
✗ 'slug' => 'productCategories'
```

### 3. Choose Appropriate Hierarchy

```php
// Use hierarchical for nested structures
'hierarchical' => true   // Categories, sections, locations

// Use flat for simple tags
'hierarchical' => false  // Tags, colors, sizes
```

### 4. Create Indexes

Always index foreign keys and slug columns:

```php
$table->index('slug');
$table->index('parent_id');
```

### 5. Use Pivot Tables

Name pivot tables descriptively:

```php
✓ 'product_category_pivots'
✗ 'category_product'
```

## Retrieving Taxonomies

### Get All Registered Taxonomies

```php
$taxonomyManager = app(TaxonomyManager::class);
$taxonomies = $taxonomyManager->getRegisteredTaxonomies();
```

### Get Taxonomies for Content Type

```php
$taxonomies = $taxonomyManager->getTaxonomiesForContentType('products');
```

### Check if Taxonomy Exists

```php
if ($taxonomyManager->taxonomyExists('product_categories')) {
    // Taxonomy exists
}
```

## See Also

- [Content Types Developer Guide](Content-Types)
- [Custom Fields Developer Guide](Custom-Fields)
- [Hooks Reference](Hooks-Reference)
