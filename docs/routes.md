# Route Registry

This document provides a comprehensive overview of all registered routes in the CMS Framework.

## Route Organization

All API routes are organized by module and follow RESTful conventions where applicable.

### Base Prefix

All routes are prefixed with `/api/cms` (configured in the consuming application).

### Middleware

- **Authentication**: Most routes require `auth:sanctum` middleware
- **Authorization**: Enforced through Laravel policies in controllers

---

## Users Module

**Base Path**: `/api/cms`

### User Management

Standard RESTful resource routes:

| Method | URI | Action | Controller Method | Description |
|--------|-----|--------|-------------------|-------------|
| GET | `/users` | index | `UserController@index` | List all users |
| POST | `/users` | store | `UserController@store` | Create a new user |
| GET | `/users/{id}` | show | `UserController@show` | Show a specific user |
| PUT/PATCH | `/users/{id}` | update | `UserController@update` | Update a user |
| DELETE | `/users/{id}` | destroy | `UserController@destroy` | Delete a user |

### Role Management

Standard RESTful resource routes:

| Method | URI | Action | Controller Method | Description |
|--------|-----|--------|-------------------|-------------|
| GET | `/roles` | index | `RoleController@index` | List all roles |
| POST | `/roles` | store | `RoleController@store` | Create a new role |
| GET | `/roles/{id}` | show | `RoleController@show` | Show a specific role |
| PUT/PATCH | `/roles/{id}` | update | `RoleController@update` | Update a role |
| DELETE | `/roles/{id}` | destroy | `RoleController@destroy` | Delete a role |

### Permission Management

Standard RESTful resource routes:

| Method | URI | Action | Controller Method | Description |
|--------|-----|--------|-------------------|-------------|
| GET | `/permissions` | index | `PermissionController@index` | List all permissions |
| POST | `/permissions` | store | `PermissionController@store` | Create a new permission |
| GET | `/permissions/{id}` | show | `PermissionController@show` | Show a specific permission |
| PUT/PATCH | `/permissions/{id}` | update | `PermissionController@update` | Update a permission |
| DELETE | `/permissions/{id}` | destroy | `PermissionController@destroy` | Delete a permission |

**Middleware**: `auth`

---

## Blog Module

**Base Path**: `/api/cms`
**Middleware**: `auth`

### Posts

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/posts` | `PostController@index` | List all posts |
| POST | `/posts` | `PostController@store` | Create a new post |
| GET | `/posts/{id}` | `PostController@show` | Show a specific post |
| PUT | `/posts/{id}` | `PostController@update` | Update a post |
| DELETE | `/posts/{id}` | `PostController@destroy` | Delete a post |

### Post Archives

Special archive routes for filtering posts:

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/posts/archives/date/{year}/{month?}/{day?}` | `PostController@archiveByDate` | Get posts by date |
| GET | `/posts/archives/author/{authorId}` | `PostController@archiveByAuthor` | Get posts by author |
| GET | `/posts/archives/category/{slug}` | `PostController@archiveByCategory` | Get posts by category |
| GET | `/posts/archives/tag/{slug}` | `PostController@archiveByTag` | Get posts by tag |

### Post Categories

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/post-categories` | `PostCategoryController@index` | List all post categories |
| POST | `/post-categories` | `PostCategoryController@store` | Create a new category |
| GET | `/post-categories/{id}` | `PostCategoryController@show` | Show a specific category |
| PUT | `/post-categories/{id}` | `PostCategoryController@update` | Update a category |
| DELETE | `/post-categories/{id}` | `PostCategoryController@destroy` | Delete a category |

### Post Tags

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/post-tags` | `PostTagController@index` | List all post tags |
| POST | `/post-tags` | `PostTagController@store` | Create a new tag |
| GET | `/post-tags/{id}` | `PostTagController@show` | Show a specific tag |
| PUT | `/post-tags/{id}` | `PostTagController@update` | Update a tag |
| DELETE | `/post-tags/{id}` | `PostTagController@destroy` | Delete a tag |

---

## Pages Module

**Base Path**: `/api/cms`
**Middleware**: `auth`

Structure identical to Blog module with pages instead of posts:

- `/pages` - Standard CRUD operations
- `/page-categories` - Category management
- `/page-tags` - Tag management

---

## Content Types Module

**Base Path**: `/api/cms`
**Middleware**: `auth`

### Content Types

Standard RESTful resource routes for managing custom content types.

### Custom Fields

Standard RESTful resource routes for managing custom fields attached to content types.

### Taxonomies

Standard RESTful resource routes for managing taxonomies (categories, tags, etc.).

---

## Notifications Module

**Base Path**: `/api/cms/notifications`
**Middleware**: `auth:sanctum`

| Method | URI | Controller Method | Route Name | Description |
|--------|-----|-------------------|------------|-------------|
| GET | `/` | `NotificationController@index` | `api.notifications.index` | List user notifications |
| GET | `/unread-count` | `NotificationController@unreadCount` | `api.notifications.unreadCount` | Get unread count |
| GET | `/{id}` | `NotificationController@show` | `api.notifications.show` | Show specific notification |
| POST | `/{id}/mark-as-read` | `NotificationController@markAsRead` | `api.notifications.markAsRead` | Mark as read |
| POST | `/{id}/dismiss` | `NotificationController@dismiss` | `api.notifications.dismiss` | Dismiss notification |
| POST | `/mark-all-as-read` | `NotificationController@markAllAsRead` | `api.notifications.markAllAsRead` | Mark all as read |
| POST | `/dismiss-all` | `NotificationController@dismissAll` | `api.notifications.dismissAll` | Dismiss all notifications |

---

## Plugins Module (Experimental)

**Base Path**: `/api/v1/plugins`
**Middleware**: `auth`

| Method | URI | Controller Method | Route Name | Description |
|--------|-----|-------------------|------------|-------------|
| GET | `/` | `PluginsController@index` | `api.plugins.index` | List all plugins |
| GET | `/updates` | `PluginsController@checkUpdates` | `api.plugins.updates` | Check for plugin updates |
| GET | `/{slug}` | `PluginsController@show` | `api.plugins.show` | Show specific plugin |
| POST | `/install` | `PluginsController@install` | `api.plugins.install` | Install plugin (ZIP upload) |
| POST | `/{slug}/activate` | `PluginsController@activate` | `api.plugins.activate` | Activate plugin |
| POST | `/{slug}/deactivate` | `PluginsController@deactivate` | `api.plugins.deactivate` | Deactivate plugin |
| POST | `/{slug}/update` | `PluginsController@update` | `api.plugins.update` | Update plugin |
| DELETE | `/{slug}` | `PluginsController@destroy` | `api.plugins.destroy` | Delete plugin |

---

## Themes Module (Experimental)

**Base Path**: `/api/cms/themes`
**Middleware**: `auth`

Standard RESTful resource routes for theme management.

---

## Settings Module

**Base Path**: `/api/cms/settings`
**Middleware**: `auth`

Standard RESTful resource routes for application settings management.

---

## Route Registration

Routes are registered in each module's `routes/api.php` file and loaded by the module's service provider.

### Example Service Provider Route Registration

```php
public function boot(): void
{
    $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
}
```

### Route Naming Convention

Routes follow Laravel's standard naming convention:

- **API Resources**: `users`, `roles`, `posts`, etc.
- **Named Routes**: `api.{resource}.{action}`
- **Example**: `api.notifications.markAsRead`

### Route Prefixing

Application-level route prefixing is configured in the consuming application's `RouteServiceProvider`:

```php
Route::prefix('api/cms')
    ->middleware('api')
    ->group(base_path('routes/api.php'));
```

---

## Authorization

All routes enforce authorization through Laravel policies:

```php
// In controller
$this->authorize('update', $user);
```

Policies are registered in each module's service provider:

```php
protected $policies = [
    User::class => UserPolicy::class,
    Role::class => RolePolicy::class,
];
```

---

## Middleware Groups

### API Middleware

Standard Laravel API middleware:
- `throttle:api` - Rate limiting (60 requests/minute)
- `auth:sanctum` - Sanctum token authentication

### Custom Middleware

Modules can register custom middleware in their service providers.

---

## Route Caching

For production environments, routes can be cached:

```bash
php artisan route:cache
```

To clear the cache:

```bash
php artisan route:clear
```

---

## Testing Routes

### Using Pest/PHPUnit

```php
test('can list users', function() {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/cms/users');

    $response->assertStatus(200);
});
```

### Using cURL

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://example.com/api/cms/users
```

---

## Future Considerations

### Versioning

Future API versions will use versioned prefixes:

- `/api/v1/cms/...` (current default)
- `/api/v2/cms/...` (future)

### Custom Routes

Applications can register additional routes by publishing and modifying route files.

---

## See Also

- [API Documentation](Api-Readme)
- [Controller Documentation](Controllers)
- [Authorization Policies](Policies)
- [Testing Guide](Testing)
