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
- It also applies the standard `can:` middleware to routes created by AdminPageManager when a page has a `capability` configured.

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
