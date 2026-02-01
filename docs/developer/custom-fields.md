# Custom Fields Developer Guide

## Overview

Custom fields allow you to add additional data to content types in Digital Shopfront CMS. Unlike traditional meta tables, custom fields are stored as actual database columns for better performance and queryability.

## Table of Contents

- [Understanding Custom Fields](#understanding-custom-fields)
- [Available Field Types](#available-field-types)
- [Registering Custom Fields](#registering-custom-fields)
- [Column Types](#column-types)
- [Migration Generation](#migration-generation)
- [Retrieving and Displaying Values](#retrieving-and-displaying-values)
- [Data Migration](#data-migration)
- [Best Practices](#best-practices)

## Understanding Custom Fields

### Column-Based Storage

Digital Shopfront CMS stores custom fields as actual database columns, not in a meta table:

**Traditional Approach (Meta Table):**
```
posts table: id, title, content
meta table: post_id, meta_key, meta_value
```

**Digital Shopfront CMS Approach (Columns):**
```
posts table: id, title, content, rating, price, featured
```

### Benefits

- **Better Performance**: Direct column access, no joins required
- **Type Safety**: Database-level type enforcement
- **Indexable**: Can create indexes on custom fields
- **Queryable**: Standard SQL queries work
- **Simpler Code**: Access fields as model properties

## Available Field Types

### Text Field

Single-line text input.

```php
[
    'type' => 'text',
    'column_type' => 'string',
]
```

**Use Cases**: Names, titles, short descriptions, URLs

### Textarea Field

Multi-line text input.

```php
[
    'type' => 'textarea',
    'column_type' => 'text',
]
```

**Use Cases**: Long descriptions, notes, addresses

### Number Field

Numeric input.

```php
[
    'type' => 'number',
    'column_type' => 'integer',  // or 'decimal', 'float'
]
```

**Use Cases**: Quantities, ratings, prices

### Boolean Field

True/false toggle.

```php
[
    'type' => 'boolean',
    'column_type' => 'boolean',
]
```

**Use Cases**: Featured flag, published status, enabled/disabled

### Date Field

Date picker.

```php
[
    'type' => 'date',
    'column_type' => 'date',  // or 'datetime', 'timestamp'
]
```

**Use Cases**: Event dates, deadlines, publish dates

### Select Field

Dropdown selection.

```php
[
    'type' => 'select',
    'column_type' => 'string',
    'options' => [
        'choices' => ['Option 1', 'Option 2', 'Option 3'],
    ],
]
```

**Use Cases**: Status, priority, type selection

### Checkbox Field

Multiple selections.

```php
[
    'type' => 'checkbox',
    'column_type' => 'json',
    'options' => [
        'choices' => ['Feature 1', 'Feature 2', 'Feature 3'],
    ],
]
```

**Use Cases**: Multiple options, feature flags

## Registering Custom Fields

### Via Service Provider

```php
<?php

namespace App\Providers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $customFieldManager = app(CustomFieldManager::class);

        $customFieldManager->registerField([
            'name' => 'Price',
            'key' => 'price',
            'type' => 'number',
            'column_type' => 'decimal',
            'description' => 'Product price in USD',
            'content_types' => ['products'],
            'options' => [
                'precision' => 10,
                'scale' => 2,
            ],
            'order' => 1,
            'required' => true,
            'default_value' => '0.00',
        ]);
    }
}
```

### Via Filter Hook

```php
addFilter('ap.contentTypes.registeredCustomFields', function ($fields) {
    $fields['rating'] = [
        'name' => 'Rating',
        'key' => 'rating',
        'type' => 'number',
        'column_type' => 'integer',
        'content_types' => ['products', 'reviews'],
        'options' => [
            'min' => 1,
            'max' => 5,
        ],
        'required' => false,
        'default_value' => 3,
    ];

    return $fields;
});
```

### Via Database/API

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;

$field = CustomField::create([
    'name' => 'SKU',
    'key' => 'sku',
    'type' => 'text',
    'column_type' => 'string',
    'content_types' => ['products'],
    'order' => 1,
    'required' => true,
]);
```

## Column Types

### String

For short text (up to 255 characters).

```php
'column_type' => 'string'
```

**Migration:**
```php
$table->string('field_name')->nullable();
```

### Text

For long text (up to ~65,000 characters).

```php
'column_type' => 'text'
```

**Migration:**
```php
$table->text('field_name')->nullable();
```

### Integer

For whole numbers.

```php
'column_type' => 'integer'
```

**Migration:**
```php
$table->integer('field_name')->nullable();
```

### Decimal

For precise decimal numbers.

```php
'column_type' => 'decimal'
'options' => ['precision' => 10, 'scale' => 2]
```

**Migration:**
```php
$table->decimal('field_name', 10, 2)->nullable();
```

### Boolean

For true/false values.

```php
'column_type' => 'boolean'
```

**Migration:**
```php
$table->boolean('field_name')->default(false);
```

### Date

For dates without time.

```php
'column_type' => 'date'
```

**Migration:**
```php
$table->date('field_name')->nullable();
```

### DateTime

For dates with time.

```php
'column_type' => 'datetime'
```

**Migration:**
```php
$table->datetime('field_name')->nullable();
```

### JSON

For structured data.

```php
'column_type' => 'json'
```

**Migration:**
```php
$table->json('field_name')->nullable();
```

## Migration Generation

### Automatic Migration

When you create a custom field, a migration is automatically generated:

```php
$customFieldManager = app(CustomFieldManager::class);

$field = $customFieldManager->createField([
    'name' => 'Price',
    'key' => 'price',
    'type' => 'number',
    'column_type' => 'decimal',
    'content_types' => ['products'],
    'required' => false,
]);

// Migration automatically created in database/migrations/
```

### Generated Migration Example

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
```

### Manual Migration Generation

Generate a migration manually:

```php
$customFieldManager = app(CustomFieldManager::class);
$field = CustomField::where('key', 'price')->first();

$migrationPath = $customFieldManager->generateMigration($field, 'add');
// Returns: database/migrations/2025_01_13_120000_add_price_to_content_types.php
```

### Running Migrations

```bash
# Run all pending migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Rollback all migrations
php artisan migrate:reset
```

## Retrieving and Displaying Values

### Direct Column Access

Access custom fields as regular model properties:

```php
$product = Product::find(1);

// Get custom field value
$price = $product->price;
$rating = $product->rating;
$featured = $product->featured;

// Set custom field value
$product->price = 29.99;
$product->rating = 5;
$product->featured = true;
$product->save();
```

### In Queries

Use custom fields in queries:

```php
// Where clause
$products = Product::where('price', '>', 100)->get();

// Order by
$products = Product::orderBy('rating', 'desc')->get();

// Select specific fields
$products = Product::select('id', 'title', 'price', 'rating')->get();

// Multiple conditions
$products = Product::where('featured', true)
    ->where('price', '<', 50)
    ->orderBy('rating', 'desc')
    ->get();
```

### In Blade Templates

```blade
<div class="product">
    <h2>{{ $product->title }}</h2>
    <p>Price: ${{ number_format($product->price, 2) }}</p>
    <p>Rating: {{ $product->rating }}/5</p>

    @if($product->featured)
        <span class="badge">Featured</span>
    @endif
</div>
```

### With Eloquent Relationships

```php
// Eager load with custom fields
$products = Product::with('categories')
    ->where('price', '<', 100)
    ->get();

// Filter relationships
$category = Category::find(1);
$products = $category->products()
    ->where('featured', true)
    ->orderBy('price', 'asc')
    ->get();
```

## Data Migration

### Changing Field Types

When changing a custom field's type, data migration may be needed:

#### Example: String to Integer

```php
// Before: price stored as string "29.99"
// After: price should be decimal 29.99

// 1. Create new migration
Schema::table('products', function (Blueprint $table) {
    $table->decimal('price_new', 10, 2)->nullable();
});

// 2. Migrate data
Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        $product->price_new = floatval($product->price);
        $product->save();
    }
});

// 3. Drop old column, rename new
Schema::table('products', function (Blueprint $table) {
    $table->dropColumn('price');
    $table->renameColumn('price_new', 'price');
});
```

### Handling Null Values

```php
// Set default for existing records
Product::whereNull('rating')->update(['rating' => 0]);

// Or in migration
Schema::table('products', function (Blueprint $table) {
    $table->integer('rating')->default(0)->change();
});
```

### Converting JSON to Columns

```php
// Before: metadata JSON {'color': 'red', 'size': 'large'}
// After: color and size columns

Schema::table('products', function (Blueprint $table) {
    $table->string('color')->nullable();
    $table->string('size')->nullable();
});

Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        $metadata = $product->metadata;
        $product->color = $metadata['color'] ?? null;
        $product->size = $metadata['size'] ?? null;
        $product->save();
    }
});
```

## Best Practices

### 1. Use Descriptive Keys

```php
✓ 'key' => 'product_price'
✓ 'key' => 'event_start_date'
✗ 'key' => 'price1'
✗ 'key' => 'field_1'
```

### 2. Choose Appropriate Column Types

```php
// For prices
'column_type' => 'decimal'  // Not 'string' or 'float'

// For flags
'column_type' => 'boolean'  // Not 'integer' or 'string'

// For dates
'column_type' => 'date'     // Not 'string'
```

### 3. Set Sensible Defaults

```php
'default_value' => 0,        // For numbers
'default_value' => false,    // For booleans
'default_value' => '',       // For strings
```

### 4. Use Required Sparingly

```php
'required' => false,  // Most fields should be optional
```

### 5. Add Descriptions

```php
'description' => 'Product price in USD. Enter without currency symbol.',
```

### 6. Order Fields Logically

```php
'order' => 1,  // Display order in forms
'order' => 2,
'order' => 3,
```

### 7. Index Heavy-Use Fields

```php
// In migration
Schema::table('products', function (Blueprint $table) {
    $table->decimal('price', 10, 2)->nullable()->index();
    $table->boolean('featured')->default(false)->index();
});
```

## Examples

### Product Price

```php
$customFieldManager->registerField([
    'name' => 'Price',
    'key' => 'price',
    'type' => 'number',
    'column_type' => 'decimal',
    'description' => 'Product price in USD',
    'content_types' => ['products'],
    'options' => ['precision' => 10, 'scale' => 2],
    'order' => 1,
    'required' => true,
    'default_value' => '0.00',
]);
```

### Event Date

```php
$customFieldManager->registerField([
    'name' => 'Event Date',
    'key' => 'event_date',
    'type' => 'date',
    'column_type' => 'datetime',
    'description' => 'When the event starts',
    'content_types' => ['events'],
    'order' => 1,
    'required' => true,
]);
```

### Featured Flag

```php
$customFieldManager->registerField([
    'name' => 'Featured',
    'key' => 'featured',
    'type' => 'boolean',
    'column_type' => 'boolean',
    'description' => 'Show on homepage',
    'content_types' => ['products', 'posts'],
    'order' => 10,
    'required' => false,
    'default_value' => false,
]);
```

### Product Rating

```php
$customFieldManager->registerField([
    'name' => 'Rating',
    'key' => 'rating',
    'type' => 'number',
    'column_type' => 'integer',
    'description' => 'Product rating (1-5)',
    'content_types' => ['products'],
    'options' => ['min' => 1, 'max' => 5],
    'order' => 5,
    'required' => false,
    'default_value' => 3,
]);
```

## Deleting Custom Fields

### Via Manager

```php
$customFieldManager = app(CustomFieldManager::class);
$customFieldManager->deleteField($fieldId);

// Automatically:
// 1. Removes column from all content type tables
// 2. Generates migration
// 3. Deletes field from database
```

### Warning

Deleting a custom field **permanently removes data**. Always backup before deleting.

## See Also

- [Content Types Developer Guide](Content-Types)
- [Taxonomies Developer Guide](Taxonomies)
- [Hooks Reference](Hooks-Reference)
