---
title: Admin Menu and Pages
---

# Admin Menu and Pages

This guide explains how to create admin menu sections, pages, and subpages, and how routing and authorization are handled.

## Helpers Overview

The following helpers are available globally:

- apAddAdminSection($slug, $title, $order = 99)
- apAddAdminPage($title, $slug, ?$sectionSlug, array $options = [])
- apAddSubAdminPage($title, $slug, $parentSlug, array $options = [])
- apGetAdminMenu(): array

These proxy to the AdminMenuManager and AdminPageManager services.

## Creating Sections

```php
// Create a section that groups related pages
apAddAdminSection('content', 'Content', order: 10);

apAddAdminSection('settings', 'Settings', order: 90);
```

## Adding Top‑Level or Sectioned Pages

```php
// Top‑level page (no section)
apAddAdminPage(
    title: 'Dashboard',
    slug: 'dashboard',
    sectionSlug: null,
    options: [
        // A controller action, closure, or view response
        'action' => [\App\Http\Controllers\Admin\DashboardController::class, 'index'],
        // Icon name used by your UI (consumer defined)
        'icon' => 'fas.gauge',
        // Gate capability needed to view this page
        'capability' => 'access_admin_dashboard',
        // Sort order relative to other items
        'order' => 1,
    ]
);

// Page inside a section
apAddAdminPage(
    title: 'All Posts',
    slug: 'posts',
    sectionSlug: 'content',
    options: [
        'action' => [\App\Http\Controllers\Admin\PostController::class, 'index'],
        'icon' => 'fas.file-lines',
        'capability' => 'view-content',
        'order' => 10,
    ]
);
```

## Adding Subpages

```php
// Subpage names use slashes in their slug, which map to dotted route names
apAddSubAdminPage(
    title: 'Create Post',
    slug: 'posts/create',          // Route path becomes /admin/posts/create
    parentSlug: 'posts',           // Parent menu item slug
    options: [
        'action' => [\App\Http\Controllers\Admin\PostController::class, 'create'],
        'capability' => 'create-content',
        'order' => 20,
        'showInMenu' => true,      // If false, page is routed but hidden from menu
    ]
);
```

## Routing and Authorization

- All admin routes are registered automatically under the `/admin` prefix with the `web` and `auth` middleware.
- Pages with a `capability` option are protected with Laravel's `can:` middleware.
- Route names are automatically created from the slug:
  - `dashboard` → route name `admin.dashboard`
  - `posts/create` → route name `admin.posts.create`

Behind the scenes, AdminPageManager::registerRoutes() creates the routes during application boot.

## Building the Menu for the Current User

Call apGetAdminMenu() to get a capability‑filtered structure, already sorted by `order`:

```php
$menu = apGetAdminMenu();

// Example shape
// [
//   'dashboard' => [
//       'title' => 'Dashboard',
//       'slug' => 'dashboard',
//       'route' => 'admin.dashboard',
//       'order' => 1,
//       'subItems' => [...]
//   ],
//   'content' => [
//       'title' => 'Content',
//       'order' => 10,
//       'items' => [
//           'posts' => [ 'title' => 'All Posts', 'route' => 'admin.posts', ... ]
//       ]
//   ],
// ]
```

The menu is filtered using Laravel Gates. Only items for which the current user passes Gate::allows($capability) are included.

## Service Provider

The AdminServiceProvider registers:
- The managers as singletons (AdminMenuManager and AdminPageManager)
- The `admin.can` alias for CheckAdminCapability middleware
- Route registration after the application boots

This means you only need to call the helpers during your package or app boot to populate the admin UI.
