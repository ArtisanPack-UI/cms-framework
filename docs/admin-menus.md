---
title: Admin Menus
---

# Admin Menus

This document provides detailed information about the Admin Menus feature in the ArtisanPack UI CMS Framework.

## Overview

The Admin Menus feature allows you to register and manage administrative pages and menus within your CMS. It provides a structured way to organize your admin interface, with support for top-level menu items and nested subpages. The feature handles routing, permissions, and rendering of both Blade views and Livewire components.

## Core Components

### AdminPagesManager

The `AdminPagesManager` class is responsible for registering, organizing, and routing admin pages and subpages.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\AdminPages;
```

#### Properties

- `$menuItems`: An array of registered admin menu items, keyed by slug.

#### Key Methods

- `registerPage($title, $slug, $icon, $view, $component, $permission)`: Registers a top-level admin menu page.
- `registerSubPage($parentSlug, $title, $slug, $view, $component, $permission)`: Registers a subpage under a top-level admin menu item.
- `registerRoutes()`: Registers Laravel routes for all registered admin pages and subpages.
- `getMenuItems()`: Retrieves all registered menu items, with support for filtering through Eventy.

## Using Admin Menus

### Registering a Top-Level Menu Page

You can register a top-level menu page using the `registerPage` method:

```php
use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesManager;
use Illuminate\Support\Facades\App;

// Get the AdminPagesManager instance
$manager = App::make(AdminPagesManager::class);

// Register a page with a Blade view
$manager->registerPage(
    'Dashboard',         // Title
    'dashboard',         // Slug
    'dashboard-icon',    // Icon
    'admin.dashboard',   // Blade view
    null,                // Livewire component (null in this case)
    'access-dashboard'   // Required permission
);

// Register a page with a Livewire component
$manager->registerPage(
    'Users',
    'users',
    'users-icon',
    null,
    'App\\Http\\Livewire\\Admin\\Users',
    'manage-users'
);
```

### Registering a Subpage

You can register a subpage under a top-level menu item using the `registerSubPage` method:

```php
// Register a subpage under the 'users' menu item
$manager->registerSubPage(
    'users',                 // Parent slug
    'Add New User',          // Title
    'add-user',              // Slug
    'admin.users.add',       // Blade view
    null,                    // Livewire component (null in this case)
    'create-users'           // Required permission
);

// Register a subpage with a Livewire component
$manager->registerSubPage(
    'users',
    'Edit User',
    'edit-user',
    null,
    'App\\Http\\Livewire\\Admin\\EditUser',
    'edit-users'
);
```

### Registering Routes

The routes for all registered admin pages and subpages are automatically registered when the `registerRoutes` method is called. This is typically done in the `boot` method of the `CMSFrameworkServiceProvider`:

```php
// In CMSFrameworkServiceProvider.php
public function boot(): void
{
    // ...
    app(AdminPagesManager::class)->registerRoutes();
    // ...
}
```

### Accessing Admin Pages

Once registered, admin pages can be accessed at URLs based on their slugs:

- Top-level pages: `/{admin_path}/{page_slug}`
- Subpages: `/{admin_path}/{parent_slug}/{subpage_slug}`

Where `{admin_path}` is the base admin path configured in `config('cms.admin_path')` (defaults to 'admin').

### Route Names

The routes are named for easy reference in your application:

- Top-level pages: `admin.{page_slug}`
- Subpages: `admin.{parent_slug}.{subpage_slug}`

For example:
```php
// Generate URL to the users page
route('admin.users');

// Generate URL to the add user subpage
route('admin.users.add-user');
```

## Customizing Admin Menus

### Using the Eventy Filter

You can use the Eventy filter system to modify the registered menu items:

```php
use TorMorten\Eventy\Facades\Eventy;

// Add or modify menu items
Eventy::addFilter('ap.cms.admin.menuItems', function($menuItems) {
    // Add a new menu item
    $menuItems['custom-page'] = [
        'title' => 'Custom Page',
        'slug' => 'custom-page',
        'icon' => 'custom-icon',
        'view' => 'admin.custom',
        'component' => null,
        'permission' => 'access-custom',
        'subpages' => [],
    ];

    // Modify an existing menu item
    if (isset($menuItems['dashboard'])) {
        $menuItems['dashboard']['title'] = 'Home';
    }

    return $menuItems;
});
```

## Best Practices

1. **Use descriptive slugs**: Choose menu item slugs that are descriptive and URL-friendly.
2. **Organize logically**: Group related functionality under the same top-level menu item using subpages.
3. **Set appropriate permissions**: Always set appropriate permissions to control access to admin pages.
4. **Use icons consistently**: Choose icons that clearly represent the purpose of each menu item.
5. **Prefer Livewire for complex interfaces**: For complex, interactive admin interfaces, use Livewire components instead of Blade views.

## Related Documentation

- [Overview](overview.md): General overview of the CMS Framework
- [Dashboard Widgets](dashboard-widgets.md): Documentation on dashboard widgets, which can be used within admin pages
- [Users](users.md): Documentation on user management and permissions
- [Custom CMS Implementation](custom-cms-implementation.md): Guide on implementing admin pages in a custom CMS application
- [Implementing Dashboard Widgets](implementing-dashboard-widgets.md): Detailed examples of implementing dashboard widgets in a custom CMS application
