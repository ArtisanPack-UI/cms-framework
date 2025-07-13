# ArtisanPack UI CMS Framework

A Laravel package that adds back end support for building a CMS with any front end framework. This package provides a comprehensive set of features for content management, user management, authentication, and more.

## Features

- **Content Management**: Content types, taxonomies, and media management
- **Admin Interface**: Admin pages, dashboard widgets, and settings management
- **User Management**: User roles, permissions, and profiles
- **Authentication**: Two-factor authentication with Laravel Sanctum integration
- **Notifications**: Comprehensive notification system
- **Themes & Plugins**: Support for themes and plugins
- **PWA Support**: Progressive Web App features
- **Audit Logging**: Track changes and user actions

## Installation

You can install the CMS Framework package by running the following composer command:

```bash
composer require artisanpack-ui/cms-framework
```

After installation, publish the configuration file:

```bash
php artisan vendor:publish --tag=cms-config
```

Run the migrations to set up the database tables:

```bash
php artisan migrate
```

## Usage

### Content Types

Register a custom content type:

```php
use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;

app(ContentTypeManager::class)->register('product', [
    'name' => 'Product',
    'plural' => 'Products',
    'description' => 'Products for the store',
    'supports' => ['title', 'editor', 'thumbnail'],
]);
```

### Admin Pages

Register a custom admin page:

```php
use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesManager;

app(AdminPagesManager::class)->addPage([
    'title' => 'Custom Settings',
    'slug' => 'custom-settings',
    'callback' => function() {
        return view('custom.settings');
    }
]);
```

### Settings

Register and retrieve settings:

```php
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;

// Register a setting
app(SettingsManager::class)->register('site_name', 'My Awesome Site');

// Get a setting
$siteName = app(SettingsManager::class)->get('site_name');
```

### Customization with Eventy

The CMS Framework uses the Eventy system for hooks and filters, allowing for extensive customization:

```php
use TorMorten\Eventy\Facades\Eventy;

// Add a filter
Eventy::addFilter('ap.cms.migrations.directories', function($directories) {
    $directories[] = __DIR__ . '/database/migrations';
    return $directories;
});

// Add an action
Eventy::addAction('ap.cms.after_content_save', function($content) {
    // Do something after content is saved
});
```

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing
guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
