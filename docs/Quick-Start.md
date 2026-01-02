---
title: Quick Start
---

# Quick Start

Get up and running with the CMS Framework in minutes! This guide assumes you've already completed the [[Installation Guide]].

## Creating Your First User

### Via API

Create a user using the REST API:

```bash
curl -X POST http://your-app.test/api/v1/users \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secure-password"
  }'
```

Response:
```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "roles": []
  }
}
```

### Via Tinker

Create a user using Laravel's Tinker:

```php
php artisan tinker

$userModel = config('cms-framework.user_model');
$user = $userModel::create([
    'name' => 'Jane Admin',
    'email' => 'jane@example.com',
    'password' => bcrypt('secure-password')
]);
```

## Setting Up Roles and Permissions

### Create Roles

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;

// Create an admin role
$adminRole = Role::create([
    'name' => 'Administrator',
    'slug' => 'admin'
]);

// Create an editor role
$editorRole = Role::create([
    'name' => 'Editor',
    'slug' => 'editor'
]);
```

### Create Permissions

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;

// Create permissions
$permissions = [
    ['name' => 'Manage Users', 'slug' => 'manage-users'],
    ['name' => 'Edit Content', 'slug' => 'edit-content'],
    ['name' => 'View Reports', 'slug' => 'view-reports'],
];

foreach ($permissions as $permission) {
    Permission::create($permission);
}
```

### Assign Permissions to Roles

```php
// Give admin all permissions
$adminRole->permissions()->attach([1, 2, 3]); // Permission IDs

// Give editor limited permissions
$editorRole->permissions()->attach([2]); // Only edit-content permission
```

### Assign Roles to Users

```php
// Get the user
$userModel = config('cms-framework.user_model');
$user = $userModel::find(1);

// Assign admin role
$user->roles()->attach($adminRole->id);

// Or assign multiple roles
$user->roles()->attach([$adminRole->id, $editorRole->id]);
```

## Checking User Permissions

### In Your Controllers

```php
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        
        // Check if user has specific role
        if (!$user->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }
        
        // Check if user has specific permission
        if (!$user->hasPermissionTo('edit-content')) {
            abort(403, 'Insufficient permissions');
        }
        
        // User has permission, continue...
    }
}
```

### In Blade Templates

```php
@if(auth()->user()->hasRole('admin'))
    <a href="/admin/dashboard">Admin Dashboard</a>
@endif

@if(auth()->user()->hasPermissionTo('manage-users'))
    <a href="/users">Manage Users</a>
@endif
```

### In Routes/Middleware

Create a simple middleware for role checking:

```php
// app/Http/Middleware/CheckRole.php
class CheckRole
{
    public function handle($request, Closure $next, $role)
    {
        if (!$request->user() || !$request->user()->hasRole($role)) {
            abort(403, 'Unauthorized');
        }
        
        return $next($request);
    }
}
```

Use in routes:
```php
Route::group(['middleware' => ['auth', 'role:admin']], function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});
```

## Common API Operations

### List All Users
```bash
curl -X GET http://your-app.test/api/v1/users
```

### Get Specific User
```bash
curl -X GET http://your-app.test/api/v1/users/1
```

### Update User
```bash
curl -X PATCH http://your-app.test/api/v1/users/1 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Smith"
  }'
```

### Delete User
```bash
curl -X DELETE http://your-app.test/api/v1/users/1
```

## Database Seeding

Create a seeder for initial setup:

```php
// database/seeders/CMSSeeder.php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;

class CMSSeeder extends Seeder
{
    public function run()
    {
        // Create roles
        $admin = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $editor = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        
        // Create permissions
        $manageUsers = Permission::create(['name' => 'Manage Users', 'slug' => 'manage-users']);
        $editContent = Permission::create(['name' => 'Edit Content', 'slug' => 'edit-content']);
        
        // Assign permissions
        $admin->permissions()->attach([$manageUsers->id, $editContent->id]);
        $editor->permissions()->attach([$editContent->id]);
        
        // Create admin user
        $userModel = config('cms-framework.user_model');
        $adminUser = $userModel::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password')
        ]);
        
        $adminUser->roles()->attach($admin->id);
    }
}
```

Run the seeder:
```bash
php artisan db:seed --class=CMSSeeder
```

## Next Steps

Now that you have the basics working:

- [[User Management]] - Learn about advanced user management features
- [[Roles and Permissions]] - Deep dive into the RBAC system
- [[User API Reference]] - Complete API documentation
- [[Developer Guide]] - Extend and customize the framework

## Troubleshooting

### Common Issues

**Permission Denied Errors**
Make sure your User model includes the `HasRolesAndPermissions` trait and the user has been assigned appropriate roles.

**API Endpoints Not Working**
Verify that the routes are registered by running `php artisan route:list | grep users`.

**Database Errors**
Ensure migrations have been run: `php artisan migrate:status`

---

*Ready to build more complex functionality? Check out the [[Developer Guide]].*
