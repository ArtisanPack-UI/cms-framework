---
title: Media Module
---

# Media Module

The Media Module provides functionality for managing media items, including uploading, retrieving, updating, and deleting media files, as well as organizing them with tags and categories.

## Overview

The Media Module allows you to upload, manage, and organize various types of media files (images, videos, audio) in the ArtisanPack UI CMS Framework. It includes features for adding metadata, accessibility attributes, and organizing media through tags and categories.

## Classes

### Media Model

The `Media` model represents a media item in the application.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Models;
```

#### Properties

- `$factory`: The factory that should be used to instantiate the model.
- `$table`: The table associated with the model, which is 'media'.
- `$fillable`: Array of attributes that are mass assignable, including 'user_id', 'file_name', 'mime_type', 'path', 'size', 'alt_text', 'is_decorative', 'caption', and 'metadata'.
- `$casts`: Array of attributes that should be cast to specific types, including 'metadata' as 'array' and 'is_decorative' as 'boolean'.

#### Methods

##### user(): BelongsTo
Gets the user that owns the media.

**@since** 1.0.0

**@return** BelongsTo The relationship to the User model.

##### mediaCategories(): BelongsToMany
Gets the categories associated with the media.

**@since** 1.0.0

**@return** BelongsToMany The relationship to the MediaCategory model.

##### mediaTags(): BelongsToMany
Gets the tags associated with the media.

**@since** 1.0.0

**@return** BelongsToMany The relationship to the MediaTag model.

##### setAltTextAttribute(string $value): void
Sets the alt text attribute.

**@since** 1.0.0

**@param** string $value The alt text to set.
**@return** void

##### setIsDecorativeAttribute(bool $value): void
Sets the is_decorative attribute. If true, it also clears the alt_text.

**@since** 1.0.0

**@param** bool $value Whether the image is decorative.
**@return** void

### MediaTag Model

The `MediaTag` model represents a tag for media items in the database.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Models;
```

#### Properties

- `$factory`: The factory that should be used to instantiate the model.
- `$table`: The table associated with the model, which is 'media_tags'.
- `$fillable`: Array of attributes that are mass assignable, including 'name' and 'slug'.

#### Methods

##### media(): BelongsToMany
Gets the media items associated with this tag.

**@since** 1.0.0

**@return** BelongsToMany The relationship to the Media model.

### MediaCategory Model

The `MediaCategory` model represents a category for media items in the database.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Models;
```

#### Properties

- `$factory`: The factory that should be used to instantiate the model.
- `$table`: The table associated with the model, which is 'media_categories'.
- `$fillable`: Array of attributes that are mass assignable, including 'name' and 'slug'.

#### Methods

##### media(): BelongsToMany
Gets the media items associated with this category.

**@since** 1.0.0

**@return** BelongsToMany The relationship to the Media model.

### MediaManager Class

The `MediaManager` class is the main class for managing media items.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\Media;
```

#### Properties

- `$disk`: The disk to use for media storage.
- `$logger`: The logger instance.

#### Methods

##### __construct(LoggerInterface $logger)
Constructor that initializes the MediaManager with the default storage disk and a logger instance.

**@since** 1.0.0

**@param** LoggerInterface $logger The logger instance.

##### upload(UploadedFile $file, ?string $altText = null, ?string $caption = null, bool $isDecorative = false, array $metadata = []): ?Media
Uploads a new media file and creates a database entry.

**@since** 1.0.0

**@param** UploadedFile $file The uploaded file instance.
**@param** string|null $altText Optional. The alternative text for the media. Default null.
**@param** string|null $caption Optional. The caption for the media item. Default null.
**@param** bool $isDecorative Optional. Whether the image is purely decorative. Default false.
**@param** array $metadata Optional. Additional metadata for the media. Default empty array.
**@return** Media|null The created Media model instance on success, or null on failure.

##### all(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
Retrieves all media items with optional pagination.

**@since** 1.0.0

**@param** int $perPage Optional. The number of media items per page. Default 15.
**@return** \Illuminate\Contracts\Pagination\LengthAwarePaginator A paginated collection of Media models.

##### getMediaByUser(int $userId, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
Retrieves media items uploaded by a specific user with optional pagination.

**@since** 1.0.0

**@param** int $userId The ID of the user whose media to retrieve.
**@param** int $perPage Optional. The number of media items per page. Default 15.
**@return** \Illuminate\Contracts\Pagination\LengthAwarePaginator A paginated collection of Media models.

##### update(int $mediaId, array $data): ?Media
Updates an existing media item's information.

**@since** 1.0.0

**@param** int $mediaId The ID of the media item to update.
**@param** array $data An associative array of data to update.
**@param** string $data['alt_text'] Optional. The alternative text for the media.
**@param** string $data['caption'] Optional. The caption for the media item.
**@param** bool $data['is_decorative'] Optional. Whether the image is purely decorative.
**@param** array $data['metadata'] Optional. Additional metadata for the media.
**@return** Media|null The updated Media model instance on success, or null if not found.

##### get(int $mediaId): ?Media
Retrieves a media item by its ID.

**@since** 1.0.0

**@param** int $mediaId The ID of the media item to retrieve.
**@return** Media|null The Media model instance if found, otherwise null.

##### delete(int $mediaId): bool
Deletes a media item from the database and its corresponding file.

**@since** 1.0.0

**@param** int $mediaId The ID of the media item to delete.
**@return** bool True on successful deletion, false otherwise.

##### getUrl(Media $media): string
Generates a public URL for a media item.

**@since** 1.0.0

**@param** Media $media The Media model instance.
**@return** string The public URL of the media item.

### MediaServiceProvider Class

The `MediaServiceProvider` class provides the service registration and bootstrapping for the media feature.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\Media;
```

#### Methods

##### register(): void
Registers media services.

**@since** 1.0.0

**@return** void

##### boot(): void
Boots media services.

**@since** 1.0.0

**@return** void

## Database Schema

### Media Table

The Media module creates a `media` table in the database with the following columns:

- `id`: Auto-incrementing primary key
- `user_id`: Foreign key to the users table
- `file_name`: String column for the file name
- `mime_type`: String column for the MIME type
- `path`: String column for the storage path
- `size`: Unsigned big integer for the file size in bytes
- `alt_text`: String column for the alternative text (for accessibility)
- `is_decorative`: Boolean column indicating if the image is purely decorative
- `caption`: Text column for the caption
- `metadata`: JSON column for storing additional metadata
- `created_at`: Timestamp for when the media was created
- `updated_at`: Timestamp for when the media was last updated

### Media Tags Table

The Media module creates a `media_tags` table in the database with the following columns:

- `id`: Auto-incrementing primary key
- `name`: String column for the tag name
- `slug`: String column for the tag slug
- `created_at`: Timestamp for when the tag was created
- `updated_at`: Timestamp for when the tag was last updated

### Media Categories Table

The Media module creates a `media_categories` table in the database with the following columns:

- `id`: Auto-incrementing primary key
- `name`: String column for the category name
- `slug`: String column for the category slug
- `created_at`: Timestamp for when the category was created
- `updated_at`: Timestamp for when the category was last updated

## API Endpoints

The Media module provides RESTful API endpoints for managing media items, tags, and categories. These endpoints are protected by Laravel Sanctum authentication.

### MediaController

The `MediaController` provides endpoints for managing media items.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Http\Controllers;
```

#### Methods

##### index(Request $request): JsonResponse
Lists all media items with optional pagination.

**@since** 1.0.0

**@param** Request $request The incoming request.
**@return** JsonResponse A JSON response containing all media items.

##### store(MediaRequest $request): JsonResponse
Creates a new media item.

**@since** 1.0.0

**@param** MediaRequest $request The validated form request.
**@return** JsonResponse A JSON response containing the newly created media item.

##### show(int $mediaId): JsonResponse
Shows a specific media item.

**@since** 1.0.0

**@param** int $mediaId The ID of the media item to show.
**@return** JsonResponse A JSON response containing the media item.

##### update(MediaRequest $request, int $mediaId): JsonResponse
Updates a media item.

**@since** 1.0.0

**@param** MediaRequest $request The validated form request.
**@param** int $mediaId The ID of the media item to update.
**@return** JsonResponse A JSON response containing the updated media item.

##### destroy(int $mediaId): JsonResponse
Deletes a media item.

**@since** 1.0.0

**@param** int $mediaId The ID of the media item to delete.
**@return** JsonResponse A JSON response indicating success or failure.

### MediaRequest

The `MediaRequest` class defines the validation rules for media data.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Http\Requests;
```

#### Methods

##### authorize(): bool
Determines if the user is authorized to make this request.

**@since** 1.0.0

**@return** bool True if authorized, false otherwise.

##### rules(): array
Returns the validation rules for the request.

**@since** 1.0.0

**@return** array The validation rules.

##### prepareForValidation(): void
Prepares the data for validation.

**@since** 1.0.0

**@return** void

## Usage

### Uploading a Media Item

```php
$mediaManager = app(ArtisanPackUI\CMSFramework\Features\Media\MediaManager::class);
$file = $request->file('file');
$altText = 'A beautiful sunset over the mountains';
$caption = 'Sunset in the Rocky Mountains';
$isDecorative = false;
$metadata = ['location' => 'Rocky Mountains', 'date' => '2023-06-15'];

$media = $mediaManager->upload($file, $altText, $caption, $isDecorative, $metadata);
```

### Retrieving All Media Items

```php
$mediaManager = app(ArtisanPackUI\CMSFramework\Features\Media\MediaManager::class);
$media = $mediaManager->all(20); // Get 20 items per page
```

### Retrieving a Specific Media Item

```php
$mediaManager = app(ArtisanPackUI\CMSFramework\Features\Media\MediaManager::class);
$media = $mediaManager->get(1);
```

### Updating a Media Item

```php
$mediaManager = app(ArtisanPackUI\CMSFramework\Features\Media\MediaManager::class);
$updateData = [
    'alt_text' => 'Updated alt text',
    'caption' => 'Updated caption',
    'metadata' => ['location' => 'Updated location']
];
$media = $mediaManager->update(1, $updateData);
```

### Deleting a Media Item

```php
$mediaManager = app(ArtisanPackUI\CMSFramework\Features\Media\MediaManager::class);
$success = $mediaManager->delete(1);
```

### Working with Media Tags

```php
// Create a new media tag
$tag = MediaTag::create([
    'name' => 'Nature',
    'slug' => 'nature'
]);

// Associate a tag with a media item
$media = Media::find(1);
$media->mediaTags()->attach($tag->id);

// Get all media items with a specific tag
$tag = MediaTag::where('slug', 'nature')->first();
$mediaItems = $tag->media;
```

### Working with Media Categories

```php
// Create a new media category
$category = MediaCategory::create([
    'name' => 'Landscapes',
    'slug' => 'landscapes'
]);

// Associate a category with a media item
$media = Media::find(1);
$media->mediaCategories()->attach($category->id);

// Get all media items in a specific category
$category = MediaCategory::where('slug', 'landscapes')->first();
$mediaItems = $category->media;
```

## Accessibility Features

The Media module includes several features to ensure accessibility:

1. **Alt Text**: All media items can have alternative text that describes the content for screen readers.
2. **Decorative Images**: Images that are purely decorative can be marked as such, which will result in empty alt text.
3. **Captions**: Media items can have captions that provide additional context.

### Example: Setting Alt Text

```php
$mediaManager = app(ArtisanPackUI\CMSFramework\Features\Media\MediaManager::class);
$updateData = [
    'alt_text' => 'A person hiking in the mountains with a backpack'
];
$media = $mediaManager->update(1, $updateData);
```

### Example: Marking an Image as Decorative

```php
$mediaManager = app(ArtisanPackUI\CMSFramework\Features\Media\MediaManager::class);
$updateData = [
    'is_decorative' => true
];
$media = $mediaManager->update(1, $updateData);
```

## Security Considerations

The Media module includes several security features:

1. **File Sanitization**: File names are sanitized before storage to prevent path traversal attacks.
2. **MIME Type Validation**: Only allowed MIME types can be uploaded.
3. **Size Limits**: There are limits on the maximum file size that can be uploaded.
4. **Authorization**: Users must have appropriate permissions to upload, edit, or delete media items.

## Integration with Laravel

The Media module is integrated with Laravel through a service provider that registers the MediaManager as a singleton service in the Laravel service container.

```php
// In a service provider
$this->app->singleton(MediaManager::class, function ($app) {
    return new MediaManager($app->make(LoggerInterface::class));
});
```
