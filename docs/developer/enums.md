# Enums Developer Guide

## Overview

CMS Framework v1.1.0 introduces several PHP enums to replace magic strings and provide type-safe, self-documenting code throughout the package. All enums are string-backed and include helper methods for labels, validation, and serialization where appropriate.

## Table of Contents

- [ContentStatus](#contentstatus)
- [FieldType](#fieldtype)
- [ColumnType](#columntype)
- [SettingType](#settingtype)
- [UpdateType](#updatetype)

## ContentStatus

**Namespace:** `ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus`

Defines the available content statuses for blog posts and pages.

### Cases

| Case | Value | Label |
|------|-------|-------|
| `Draft` | `'draft'` | Draft |
| `Published` | `'published'` | Published |
| `Scheduled` | `'scheduled'` | Scheduled |
| `Private` | `'private'` | Private |

### Methods

- `label(): string` -- Returns a translatable human-readable label for the status.
- `validationRule(): Enum` -- Returns a Laravel `Rule::enum()` validation rule for use in form requests.

### Usage Examples

**Using in a Form Request:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;

public function rules(): array
{
    return [
        'title'  => ['required', 'string', 'max:255'],
        'status' => ['required', ContentStatus::validationRule()],
    ];
}
```

**Querying by Status:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;

// Get all draft posts
$drafts = Post::where( 'status', ContentStatus::Draft )->get();

// Use the published scope (provided by HasContentStatus trait)
$published = Post::published()->get();
```

**Displaying a Label:**

```php
$post = Post::find( 1 );
echo $post->status->label(); // "Published"
```

**Casting on a Model:**

```php
protected function casts(): array
{
    return [
        'status' => ContentStatus::class,
    ];
}
```

## FieldType

**Namespace:** `ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\FieldType`

Defines the available UI field types for custom fields. Each case represents a different form control that is rendered when editing content.

### Cases

| Case | Value | Label |
|------|-------|-------|
| `Text` | `'text'` | Text |
| `Textarea` | `'textarea'` | Textarea |
| `Number` | `'number'` | Number |
| `Select` | `'select'` | Select |
| `Checkbox` | `'checkbox'` | Checkbox |
| `Radio` | `'radio'` | Radio |
| `Boolean` | `'boolean'` | Boolean |
| `Date` | `'date'` | Date |
| `Datetime` | `'datetime'` | Datetime |
| `Time` | `'time'` | Time |
| `Email` | `'email'` | Email |
| `Url` | `'url'` | URL |
| `Tel` | `'tel'` | Telephone |
| `Color` | `'color'` | Color |
| `File` | `'file'` | File |
| `Image` | `'image'` | Image |

### Methods

- `label(): string` -- Returns a translatable human-readable label for the field type.
- `validationRule(): Enum` -- Returns a Laravel `Rule::enum()` validation rule.

### Usage Examples

**Registering a Custom Field:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\FieldType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ColumnType;

$field = [
    'name'        => 'price',
    'label'       => 'Product Price',
    'type'        => FieldType::Number,
    'column_type' => ColumnType::Decimal,
];
```

**Validating a Field Type Input:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\FieldType;

public function rules(): array
{
    return [
        'field_type' => ['required', FieldType::validationRule()],
    ];
}
```

**Getting the Label for Display:**

```php
$type = FieldType::Email;
echo $type->label(); // "Email"
```

## ColumnType

**Namespace:** `ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ColumnType`

Defines the database column types used when creating custom field columns. Each case maps to a Laravel migration column method.

### Cases

| Case | Value | Label |
|------|-------|-------|
| `String` | `'string'` | String |
| `Text` | `'text'` | Text |
| `Integer` | `'integer'` | Integer |
| `BigInteger` | `'bigInteger'` | Big Integer |
| `Decimal` | `'decimal'` | Decimal |
| `Float` | `'float'` | Float |
| `Double` | `'double'` | Double |
| `Boolean` | `'boolean'` | Boolean |
| `Date` | `'date'` | Date |
| `DateTime` | `'dateTime'` | DateTime |
| `Time` | `'time'` | Time |
| `Json` | `'json'` | JSON |
| `Binary` | `'binary'` | Binary |

### Methods

- `label(): string` -- Returns a translatable human-readable label for the column type.
- `validationRule(): Enum` -- Returns a Laravel `Rule::enum()` validation rule.

### Usage Examples

**Pairing with FieldType for Custom Fields:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\FieldType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ColumnType;

$fields = [
    [
        'name'        => 'rating',
        'label'       => 'Rating',
        'type'        => FieldType::Number,
        'column_type' => ColumnType::Integer,
    ],
    [
        'name'        => 'description',
        'label'       => 'Description',
        'type'        => FieldType::Textarea,
        'column_type' => ColumnType::Text,
    ],
    [
        'name'        => 'metadata',
        'label'       => 'Metadata',
        'type'        => FieldType::Textarea,
        'column_type' => ColumnType::Json,
    ],
];
```

**Validating Column Type Input:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ColumnType;

public function rules(): array
{
    return [
        'column_type' => ['required', ColumnType::validationRule()],
    ];
}
```

## SettingType

**Namespace:** `ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType`

Defines the available data types for stored settings. Unlike the other enums, SettingType centralizes type detection, casting, and serialization logic for the settings system.

### Cases

| Case | Value |
|------|-------|
| `String` | `'string'` |
| `Integer` | `'integer'` |
| `Boolean` | `'boolean'` |
| `Float` | `'float'` |
| `Json` | `'json'` |

### Methods

- `fromValue(mixed $value): self` -- Auto-detects the SettingType from a PHP value. Booleans, integers, and floats are detected by type; arrays and objects become `Json`; everything else defaults to `String`.
- `cast(mixed $value): mixed` -- Casts a raw database string back to its native PHP type. Uses `FILTER_VALIDATE_BOOLEAN` for booleans and `JSON_THROW_ON_ERROR` for JSON. Returns an empty string for null String values and null for other null values.
- `serialize(mixed $value): string` -- Serializes a PHP value for database storage. Booleans become `'1'` or `'0'`, JSON values are encoded with `JSON_THROW_ON_ERROR`, and null String values become an empty string.

### Usage Examples

**Auto-Detecting Type from a Value:**

```php
use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;

$type = SettingType::fromValue( true );       // SettingType::Boolean
$type = SettingType::fromValue( 42 );         // SettingType::Integer
$type = SettingType::fromValue( 3.14 );       // SettingType::Float
$type = SettingType::fromValue( ['a', 'b'] ); // SettingType::Json
$type = SettingType::fromValue( 'hello' );    // SettingType::String
```

**Casting a Stored Value:**

```php
$type  = SettingType::Boolean;
$value = $type->cast( '1' );   // true
$value = $type->cast( '0' );   // false
$value = $type->cast( 'yes' ); // true (via FILTER_VALIDATE_BOOLEAN)

$type  = SettingType::Json;
$value = $type->cast( '{"key":"value"}' ); // ['key' => 'value']
```

**Serializing a Value for Storage:**

```php
$type       = SettingType::Boolean;
$serialized = $type->serialize( true );  // '1'
$serialized = $type->serialize( false ); // '0'

$type       = SettingType::Json;
$serialized = $type->serialize( ['key' => 'value'] ); // '{"key":"value"}'
```

**Full Round-Trip Example:**

```php
use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;

$originalValue = ['theme' => 'dark', 'sidebar' => true];

// Detect type and serialize for storage
$type       = SettingType::fromValue( $originalValue ); // SettingType::Json
$serialized = $type->serialize( $originalValue );       // '{"theme":"dark","sidebar":true}'

// Later, cast back from the database
$restored = $type->cast( $serialized ); // ['theme' => 'dark', 'sidebar' => true]
```

## UpdateType

**Namespace:** `ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType`

Categorizes the types of items that can be checked for updates in the update checker system.

### Cases

| Case | Value |
|------|-------|
| `Application` | `'application'` |
| `Plugin` | `'plugin'` |
| `Theme` | `'theme'` |

### Usage Examples

**Dispatching an Update Check:**

```php
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;

$updateType = UpdateType::Plugin;

// Use the value in update check logic
if ( UpdateType::Plugin === $updateType ) {
    // Check plugin updates
}
```

**Filtering Updates by Type:**

```php
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;

$updates = collect( $allUpdates )->filter(
    fn ( $update ) => UpdateType::Theme === $update->type
);
```
