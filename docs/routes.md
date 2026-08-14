# Route Registry

This document provides a comprehensive overview of all registered routes in the CMS Framework.

## Route Organization

All API routes are organized by module and follow RESTful conventions where applicable.

### Base Prefix

All routes are prefixed with `/api/cms` (configured in the consuming application).

### Middleware

- **Authentication**: Most routes require `auth:sanctum` middleware
- **Authorization**: Enforced through Laravel policies in controllers, and — since *2.8.0* — through `can:` permission middleware on the extension-management and AI routes. The plugin mutating routes require `manage-plugins`, the theme mutating routes require `manage-themes`, and the AI endpoints require `cms.ai.use`. All three are deny-by-default and seeded to the `admin` role; a non-privileged authenticated user gets `403`.

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
| POST | `/users/bulk` | bulk | `UserController@bulk` | Bulk user actions |

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
| POST | `/posts/bulk` | `PostController@bulk` | Bulk post actions |

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
- `/pages/bulk` - Bulk page actions (delete, publish, draft)
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
**Middleware**: `auth`. Since *2.8.0*, the mutating routes (`install`, `{slug}/activate`, `{slug}/deactivate`, `{slug}/update`, and `{slug}` DELETE) additionally require `can:manage-plugins`. The read routes (`index`, `updates`, `show`) stay auth-only.

| Method | URI | Controller Method | Route Name | Middleware | Description |
|--------|-----|-------------------|------------|------------|-------------|
| GET | `/` | `PluginsController@index` | `api.plugins.index` | `auth` | List all plugins |
| GET | `/updates` | `PluginsController@checkUpdates` | `api.plugins.updates` | `auth` | Check for plugin updates |
| GET | `/{slug}` | `PluginsController@show` | `api.plugins.show` | `auth` | Show specific plugin |
| POST | `/install` | `PluginsController@install` | `api.plugins.install` | `auth`, `can:manage-plugins` | Install plugin (ZIP upload) |
| POST | `/{slug}/activate` | `PluginsController@activate` | `api.plugins.activate` | `auth`, `can:manage-plugins` | Activate plugin |
| POST | `/{slug}/deactivate` | `PluginsController@deactivate` | `api.plugins.deactivate` | `auth`, `can:manage-plugins` | Deactivate plugin |
| POST | `/{slug}/update` | `PluginsController@update` | `api.plugins.update` | `auth`, `can:manage-plugins` | Update plugin |
| DELETE | `/{slug}` | `PluginsController@destroy` | `api.plugins.destroy` | `auth`, `can:manage-plugins` | Delete plugin |

**Error shape**: since *2.8.0*, every failing action endpoint returns `422` with Laravel's standard `errors` bag — keyed by `plugin_zip` for the install upload and by `slug` for the actions taken against an installed plugin — so Inertia consumers get field-level errors. The one exception is activating a plugin whose `min_host_version` the host does not satisfy, which keeps its dedicated `409` response and its structured `code` / `required_version` / `host_version` payload.

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

### Site Settings *(2.0.0)*

**Base Path**: `/api/v1/settings/site`
**Middleware**: `auth:sanctum`

WP-shape envelope over the built-in `site.*` settings (title, tagline, URL, logo, icon).

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/site` | `SiteSettingController@show` | Read the WP-shape site-meta envelope |
| PUT | `/site` | `SiteSettingController@update` | Partial update (omit fields to leave unchanged) |

See [[settings/Site Settings]] for the envelope shape.

---

## Site Editor Module *(2.0.0)*

**Base Path**: `/api/v1/`
**Middleware**: `auth:sanctum`

The Site Editor module exposes WP-shape endpoints for templates, parts, patterns, global styles, and menus. See [[site-editor/API Reference]] for the full listing and response shapes.

### Templates and Template Parts

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/templates` | List resolved templates (file + DB merged) |
| GET | `/templates/{slug}` | Show one resolved template |
| POST | `/templates` | Create DB-stored template |
| PUT | `/templates/{slug}` | Upsert DB-stored template |
| DELETE | `/templates/{slug}` | Revert (DB row deletion) |
| GET | `/template-parts` | List template parts |
| GET | `/template-parts/{slug}` | Show one template part |
| POST | `/template-parts` | Create DB-stored template part |
| PUT | `/template-parts/{slug}` | Upsert |
| DELETE | `/template-parts/{slug}` | Revert |

### Patterns

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/blocks` | List synced user patterns (`wp_block` shape) |
| GET | `/blocks/{slug}` | Show one synced pattern |
| POST | `/blocks` | Create synced user pattern |
| PUT | `/blocks/{slug}` | Upsert synced user pattern |
| DELETE | `/blocks/{slug}` | Delete synced user pattern |
| GET | `/block-patterns/patterns` | List theme + user-source unsynced patterns merged |
| GET | `/block-patterns/patterns/{slug}` | Show one unsynced pattern |
| POST | `/block-patterns/patterns` | Create unsynced user pattern |
| PUT | `/block-patterns/patterns/{slug}` | Upsert (403 for theme slugs) |
| DELETE | `/block-patterns/patterns/{slug}` | Delete (403 for theme slugs) |

### Global Styles

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/global-styles` | Resolved styles for the active theme |
| PUT | `/global-styles` | Create or update the user customization row |
| DELETE | `/global-styles` | Revert to file-only authority |
| GET | `/global-styles/variations` | List theme-shipped variations |
| GET | `/global-styles/css` | Emit resolved CSS as `text/css` |

### Menus

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/menus` | List menus for the active theme |
| POST | `/menus` | Create a menu |
| GET | `/menus/{id_or_slug}` | Show one menu |
| PUT | `/menus/{id_or_slug}` | Update menu metadata |
| DELETE | `/menus/{id_or_slug}` | Delete menu (cascades to items and assignments) |
| GET | `/menu-items?menus={id}` | List items for a menu |
| POST | `/menu-items` | Create item |
| GET | `/menu-items/{id}` | Show item |
| PUT | `/menu-items/{id}` | Update item |
| DELETE | `/menu-items/{id}` | Delete item (cascades to children) |
| GET | `/menu-locations` | List declared locations + assignments |
| PUT | `/menu-locations/{location}` | Assign a menu to a location |
| DELETE | `/menu-locations/{location}` | Unassign |

---

## Themes Upload *(2.0.0)*

**Base Path**: `/api/v1/themes`
**Middleware**: `auth:sanctum`. Since *2.8.0*, the mutating routes (`POST /` upload, `{slug}/activate`, `{slug}/update`) additionally require `can:manage-themes`. The read routes (`GET /themes`, `GET /themes/updates`, `GET /themes/{slug}`) stay auth-only.

| Method | URI | Middleware | Description |
|--------|-----|------------|-------------|
| POST | `/` | `auth:sanctum`, `can:manage-themes` | Upload a theme ZIP archive (multipart `theme` field) |
| POST | `/{slug}/activate` | `auth:sanctum`, `can:manage-themes` | Activate a theme |
| POST | `/{slug}/update` | `auth:sanctum`, `can:manage-themes` | Update an installed theme in place *(2.8.0)* |

See [[themes/Installing From Zip]] for usage and validation behavior.

## AI Module *(2.3.0)*

**Base Path**: `/api/v1/cms/ai`
**Middleware**: `auth:sanctum`. Since *2.8.0*, every AI endpoint additionally requires `can:cms.ai.use` — a deny-by-default ability seeded to the `admin` role. The `AiTools` Livewire component enforces the same ability. See [[AI-Features]] for the endpoint listing.

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
