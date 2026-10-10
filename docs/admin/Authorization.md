---
title: Admin Authorization
---

# Admin Authorization

Admin pages can be protected using Laravel Gates and middleware. The Admin module integrates capability checks into route registration and provides a middleware alias you can use anywhere in your app.

## Capability Checks via Gates

Define Gate abilities (capabilities) in your AuthServiceProvider or policies:

```php
// app/Providers/AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot()
{
    Gate::define('access_admin_dashboard', function ($user) {
        return $user->hasPermissionTo('access_admin_dashboard');
    });

    Gate::define('view-content', function ($user) {
        return $user->hasPermissionTo('view-content');
    });
}
```

Users get permissions through roles using the HasRolesAndPermissions trait.

## Middleware Integration

- The Admin module registers an alias `admin.can` for the CheckAdminCapability middleware.
- It also applies the standard `can:` middleware to every route created by AdminPageManager. A page registered without a `capability` gets `can:access_admin_dashboard`.

### Using `admin.can` middleware manually

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'admin.can:access_admin_dashboard'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('admin.dashboard');
    });
```

The middleware delegates to Gate::authorize($capability) and will return a 403 response if the user is unauthorized.

## Automatic Protection for Admin Pages

When you register pages via helpers (apAddAdminPage or apAddSubAdminPage) and include a `capability` in the options, routing will automatically apply Laravel's `can:` middleware:

```php
apAddAdminPage('Dashboard', 'dashboard', null, [
    'action' => [\App\Http\Controllers\Admin\DashboardController::class, 'index'],
    'capability' => 'access_admin_dashboard',
]);
```

No additional route configuration is required—the page will be protected automatically.

## Admin Page Middleware Stack

*Since 2.13.0.* The base middleware on every AdminPageManager route comes from
`cms.admin.middleware` ( default `['web', 'auth']` ), with the page's
`can:{capability}` gate always appended. Before 2.13.0 the stack was
hard-coded to `web` + `auth`, so plugin admin pages skipped any extra checks
the host applied to its own admin routes.

Publish the config and add your host's checks:

```bash
php artisan vendor:publish --tag=cms-admin-config
```

```php
// config/cms/admin.php
return [
    'middleware' => [ 'web', 'auth', 'verified', 'two-factor' ],
];
```

Or adjust it at runtime with the `ap.cmsFramework.admin.middleware` filter:

```php
addFilter( 'ap.cmsFramework.admin.middleware', function ( array $middleware ): array {
    $middleware[] = 'verified';

    return $middleware;
} );
```

Register the filter before the application finishes booting ( for example in
a service provider's `boot()` ), because admin routes are registered in a
`booted` callback. Non-string and empty entries are dropped; if nothing is
left, the stack falls back to `web` + `auth`, so a misconfiguration never
leaves admin pages unauthenticated. Keep an authentication middleware in the
list.
