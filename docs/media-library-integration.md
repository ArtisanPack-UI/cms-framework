# Media Library Integration Guide

## Overview

The ArtisanPack UI CMS Framework has been decoupled from the media library functionality to provide greater flexibility and modularity. This guide explains how to integrate the separate `artisanpack-ui/media-library` package with your CMS implementation.

## Installation

First, install the media library package:

```bash
composer require artisanpack-ui/media-library
```

## Configuration

### 1. Service Provider Registration

Register the MediaServiceProvider in your application's `config/app.php` or `bootstrap/providers.php`:

```php
// bootstrap/providers.php (Laravel 11+)
return [
    // Other providers...
    ArtisanPackUI\MediaLibrary\MediaServiceProvider::class,
];
```

### 2. CMS Manager Integration

To integrate the media manager with your CMS, update your CMS configuration to include the media manager binding:

```php
// In your CMSManager or service provider
use ArtisanPackUI\MediaLibrary\Contracts\MediaManagerInterface;
use ArtisanPackUI\MediaLibrary\MediaManager;

// Bind the media manager
$this->app->singleton(MediaManagerInterface::class, function ($app) {
    return new MediaManager($app->make(LoggerInterface::class));
});

// Update your CMS feature managers array
protected array $featureManagers = [
    'settings' => SettingsManagerInterface::class,
    'plugins' => PluginManagerInterface::class,
    'users' => UserManagerInterface::class,
    'media' => MediaManagerInterface::class, // Add this line
    'content' => ContentManagerInterface::class,
    'taxonomy' => TaxonomyManagerInterface::class,
    // ... other managers
];
```

### 3. Database Migrations

Run the media library migrations:

```bash
php artisan migrate
```

### 4. Configuration Files

Publish and configure the media library configuration:

```bash
php artisan vendor:publish --provider="ArtisanPackUI\MediaLibrary\MediaServiceProvider" --tag="config"
```

Update your CMS configuration to include media settings:

```php
// config/cms.php or your CMS configuration file
'media' => [
    'disk' => env('MEDIA_DISK', 'public'),
    'directory' => env('MEDIA_DIRECTORY', 'media'),
    'max_file_size' => env('MEDIA_MAX_FILE_SIZE', 10240), // KB
    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'doc', 'docx'],
],
```

## Usage Examples

### Basic Media Upload

```php
use ArtisanPackUI\MediaLibrary\Facades\Media;

// Upload a file
$media = Media::upload(
    $uploadedFile,
    'Alternative text for accessibility',
    'Media caption',
    false, // is decorative
    ['custom_field' => 'value'] // additional metadata
);
```

### Retrieving Media

```php
// Get all media with pagination
$mediaItems = Media::getAll(20); // 20 items per page

// Get media by user
$userMedia = Media::getByUser($userId, 15);

// Get specific media item
$media = Media::get($mediaId);
```

### Media Management

```php
// Update media
$updatedMedia = Media::update($mediaId, [
    'alt_text' => 'Updated alt text',
    'caption' => 'Updated caption',
    'is_decorative' => false,
]);

// Delete media
$success = Media::delete($mediaId);

// Get media URL
$url = Media::getUrl($media);
```

## Authorization & Policies

If you need authorization for media operations, create and register policies:

```php
// app/Policies/MediaPolicy.php
use ArtisanPackUI\MediaLibrary\Models\Media;

class MediaPolicy
{
    public function view(User $user, Media $media): bool
    {
        return true; // Implement your logic
    }

    public function create(User $user): bool
    {
        return $user->can('upload_media');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->id === $media->user_id || $user->can('edit_all_media');
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->id === $media->user_id || $user->can('delete_all_media');
    }
}
```

Register the policy in your `AuthServiceProvider`:

```php
use ArtisanPackUI\MediaLibrary\Models\Media;

protected $policies = [
    Media::class => MediaPolicy::class,
    // ... other policies
];
```

## Frontend Integration

### API Routes

The media library provides API routes for frontend integration:

```
GET    /api/media              - List media items
POST   /api/media              - Upload new media
GET    /api/media/{id}         - Get specific media
PUT    /api/media/{id}         - Update media
DELETE /api/media/{id}         - Delete media
```

### JavaScript/Frontend Usage

```javascript
// Upload media via AJAX
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('alt_text', 'Alt text');
formData.append('caption', 'Caption');

fetch('/api/media', {
    method: 'POST',
    body: formData,
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    }
})
.then(response => response.json())
.then(data => {
    console.log('Media uploaded:', data);
});
```

## Validation

The media library includes built-in validation. You can customize validation rules by extending the media requests:

```php
// app/Http/Requests/CustomMediaRequest.php
use ArtisanPackUI\MediaLibrary\Http\Requests\MediaRequest;

class CustomMediaRequest extends MediaRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'custom_field' => 'required|string|max:255',
        ]);
    }
}
```

## Events

The media library dispatches events that you can listen to:

```php
// Listen for media events
Event::listen(MediaUploaded::class, function ($event) {
    // Handle media upload
    Log::info('Media uploaded: ' . $event->media->file_name);
});

Event::listen(MediaDeleted::class, function ($event) {
    // Handle media deletion
    Log::info('Media deleted: ' . $event->mediaId);
});
```

## Advanced Configuration

### Custom Storage Disks

Configure custom storage for media files:

```php
// config/filesystems.php
'disks' => [
    'media' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
    ],
],
```

### Custom Media Models

Extend the base media model for custom functionality:

```php
// app/Models/CustomMedia.php
use ArtisanPackUI\MediaLibrary\Models\Media;

class CustomMedia extends Media
{
    protected $fillable = [
        ...parent::getFillable(),
        'custom_field',
    ];

    public function customMethod()
    {
        // Your custom functionality
    }
}
```

## Troubleshooting

### Common Issues

1. **Missing migrations**: Ensure you've run `php artisan migrate` after installing the package.

2. **Storage permissions**: Verify that your storage disk has proper write permissions.

3. **File size limits**: Check your PHP configuration for `upload_max_filesize` and `post_max_size`.

4. **CORS issues**: Configure CORS properly if accessing the API from a different domain.

### Debug Mode

Enable debug logging for media operations:

```php
// config/media.php
'debug' => env('MEDIA_DEBUG', false),
```

## Testing

The media library includes comprehensive test coverage. Run tests with:

```bash
php artisan test --filter=Media
```

## Support

For issues related to the media library integration, please:

1. Check the [media library documentation](https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-media-library)
2. Review the [CMS framework documentation](https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework)
3. Submit issues to the respective GitLab repositories

## Migration from Integrated Media

If you're migrating from a version where media was integrated into the CMS framework:

1. Install the separate media library package
2. Update your imports to use the new namespace: `ArtisanPackUI\MediaLibrary\`
3. Update your configuration files as described above
4. Run any necessary database migrations
5. Update your frontend code to use the new API endpoints

The media library maintains backward compatibility with the previous API, so most of your existing code should work without changes.