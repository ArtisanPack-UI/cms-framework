---
title: Roles and Permissions
---

# Roles and Permissions

The CMS Framework implements a flexible Role-Based Access Control (RBAC) system that allows you to manage user permissions through roles. This system provides fine-grained control over what users can do in your application.

## Overview

The RBAC system consists of three main components:

- **Users**: Your application's users (using your configured User model)
- **Roles**: Groups of permissions (e.g., Admin, Editor, Viewer)
- **Permissions**: Individual capabilities (e.g., edit-content, manage-users)

### Relationship Structure

```
Users ←→ Roles ←→ Permissions
```

- Users can have multiple roles
- Roles can have multiple permissions
- Users inherit all permissions from their assigned roles

## Role Model

### Role Structure

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;

// Role attributes
$role = new Role([
    'name' => 'Administrator',  // Human-readable name
    'slug' => 'admin'          // URL-friendly identifier
]);
```

### Creating Roles

#### Basic Role Creation

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;

$adminRole = Role::create([
    'name' => 'Administrator',
    'slug' => 'admin'
]);

$editorRole = Role::create([
    'name' => 'Content Editor',
    'slug' => 'editor'
]);

$viewerRole = Role::create([
    'name' => 'Viewer',
    'slug' => 'viewer'
]);
```

#### Role Creation with Validation

```php
$roleData = [
    'name' => 'Super Admin',
    'slug' => 'super-admin'
];

// Validate slug uniqueness
if (Role::where('slug', $roleData['slug'])->exists()) {
    throw new Exception('Role with this slug already exists');
}

$role = Role::create($roleData);
```

### Managing Roles

#### Finding Roles

```php
// Find by ID
$role = Role::find(1);

// Find by slug
$role = Role::where('slug', 'admin')->first();

// Get all roles
$roles = Role::all();

// Get roles with their permissions
$roles = Role::with('permissions')->get();
```

#### Updating Roles

```php
$role = Role::find(1);
$role->update([
    'name' => 'Super Administrator'
]);
```

#### Deleting Roles

```php
$role = Role::find(1);

// Remove all user assignments first
$role->users()->detach();

// Remove all permission assignments
$role->permissions()->detach();

// Delete the role
$role->delete();
```

## Permission Model

### Permission Structure

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;

// Permission attributes
$permission = new Permission([
    'name' => 'Manage Users',     // Human-readable name
    'slug' => 'manage-users'      // URL-friendly identifier
]);
```

### Creating Permissions

#### Basic Permission Creation

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;

$permissions = [
    ['name' => 'Manage Users', 'slug' => 'manage-users'],
    ['name' => 'Edit Content', 'slug' => 'edit-content'],
    ['name' => 'Delete Content', 'slug' => 'delete-content'],
    ['name' => 'View Reports', 'slug' => 'view-reports'],
    ['name' => 'Manage Settings', 'slug' => 'manage-settings'],
];

foreach ($permissions as $permissionData) {
    Permission::create($permissionData);
}
```

#### Grouped Permission Creation

```php
// Content Management Permissions
$contentPermissions = [
    'Create Content' => 'create-content',
    'Edit Content' => 'edit-content',
    'Delete Content' => 'delete-content',
    'Publish Content' => 'publish-content',
];

// User Management Permissions
$userPermissions = [
    'View Users' => 'view-users',
    'Create Users' => 'create-users',
    'Edit Users' => 'edit-users',
    'Delete Users' => 'delete-users',
];

// Create all permissions
$allPermissions = array_merge($contentPermissions, $userPermissions);

foreach ($allPermissions as $name => $slug) {
    Permission::create([
        'name' => $name,
        'slug' => $slug
    ]);
}
```

### Managing Permissions

#### Finding Permissions

```php
// Find by ID
$permission = Permission::find(1);

// Find by slug
$permission = Permission::where('slug', 'edit-content')->first();

// Get all permissions
$permissions = Permission::all();

// Get permissions with their roles
$permissions = Permission::with('roles')->get();
```

## Assigning Permissions to Roles

### Basic Assignment

```php
$adminRole = Role::where('slug', 'admin')->first();
$editorRole = Role::where('slug', 'editor')->first();

// Get permissions
$manageUsers = Permission::where('slug', 'manage-users')->first();
$editContent = Permission::where('slug', 'edit-content')->first();
$deleteContent = Permission::where('slug', 'delete-content')->first();

// Assign permissions to admin role
$adminRole->permissions()->attach([
    $manageUsers->id,
    $editContent->id,
    $deleteContent->id
]);

// Assign limited permissions to editor role
$editorRole->permissions()->attach([
    $editContent->id
]);
```

### Bulk Permission Assignment

```php
$adminRole = Role::where('slug', 'admin')->first();

// Give admin all permissions
$allPermissionIds = Permission::pluck('id')->toArray();
$adminRole->permissions()->attach($allPermissionIds);
```

### Sync Permissions (Replace All)

```php
$role = Role::find(1);

// Replace all permissions with these specific ones
$newPermissionIds = [1, 2, 3];
$role->permissions()->sync($newPermissionIds);
```

### Remove Permissions from Roles

```php
$role = Role::find(1);

// Remove specific permission
$permission = Permission::where('slug', 'delete-content')->first();
$role->permissions()->detach($permission->id);

// Remove all permissions
$role->permissions()->detach();
```

## User Role Assignment

### Assigning Roles to Users

```php
$userModel = config('cms-framework.user_model');
$user = $userModel::find(1);

$adminRole = Role::where('slug', 'admin')->first();
$editorRole = Role::where('slug', 'editor')->first();

// Assign single role
$user->roles()->attach($adminRole->id);

// Assign multiple roles
$user->roles()->attach([$adminRole->id, $editorRole->id]);
```

### Checking User Roles and Permissions

#### Role Checks

```php
$user = auth()->user();

// Check if user has specific role
if ($user->hasRole('admin')) {
    // User is an admin
}

// Check multiple roles (OR condition)
if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
    // User has admin or super-admin role
}

// Get user's roles
$userRoles = $user->roles; // Collection of Role models
$roleNames = $user->roles->pluck('name'); // ['Administrator', 'Editor']
$roleSlugs = $user->roles->pluck('slug'); // ['admin', 'editor']
```

#### Permission Checks

```php
$user = auth()->user();

// Check if user has specific permission
if ($user->hasPermissionTo('edit-content')) {
    // User can edit content
}

// Check multiple permissions
if ($user->hasPermissionTo('edit-content') && $user->hasPermissionTo('delete-content')) {
    // User can both edit and delete content
}
```

## Practical Usage Examples

### Content Management System Roles

```php
// Create CMS-specific roles and permissions

// Permissions
$permissions = [
    // Content permissions
    'View Content' => 'view-content',
    'Create Content' => 'create-content', 
    'Edit Content' => 'edit-content',
    'Delete Content' => 'delete-content',
    'Publish Content' => 'publish-content',
    
    // User permissions
    'View Users' => 'view-users',
    'Create Users' => 'create-users',
    'Edit Users' => 'edit-users',
    'Delete Users' => 'delete-users',
    
    // System permissions
    'View Dashboard' => 'view-dashboard',
    'Manage Settings' => 'manage-settings',
    'View Reports' => 'view-reports',
];

foreach ($permissions as $name => $slug) {
    Permission::create(['name' => $name, 'slug' => $slug]);
}

// Roles
$superAdmin = Role::create(['name' => 'Super Administrator', 'slug' => 'super-admin']);
$admin = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
$editor = Role::create(['name' => 'Content Editor', 'slug' => 'editor']);
$author = Role::create(['name' => 'Content Author', 'slug' => 'author']);
$viewer = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);

// Assign permissions to roles
$allPermissions = Permission::pluck('id');

// Super Admin gets everything
$superAdmin->permissions()->attach($allPermissions);

// Admin gets most permissions
$adminPermissions = Permission::whereIn('slug', [
    'view-content', 'create-content', 'edit-content', 'delete-content', 'publish-content',
    'view-users', 'create-users', 'edit-users',
    'view-dashboard', 'view-reports'
])->pluck('id');
$admin->permissions()->attach($adminPermissions);

// Editor gets content permissions
$editorPermissions = Permission::whereIn('slug', [
    'view-content', 'create-content', 'edit-content', 'publish-content',
    'view-dashboard'
])->pluck('id');
$editor->permissions()->attach($editorPermissions);

// Author gets limited content permissions
$authorPermissions = Permission::whereIn('slug', [
    'view-content', 'create-content', 'edit-content',
    'view-dashboard'
])->pluck('id');
$author->permissions()->attach($authorPermissions);

// Viewer gets read-only access
$viewerPermissions = Permission::whereIn('slug', [
    'view-content', 'view-dashboard'
])->pluck('id');
$viewer->permissions()->attach($viewerPermissions);
```

### Middleware Integration

```php
// Create middleware for role checking
// app/Http/Middleware/RequireRole.php
class RequireRole
{
    public function handle($request, Closure $next, $role)
    {
        if (!$request->user() || !$request->user()->hasRole($role)) {
            abort(403, 'Insufficient privileges');
        }
        
        return $next($request);
    }
}

// Create middleware for permission checking
// app/Http/Middleware/RequirePermission.php
class RequirePermission
{
    public function handle($request, Closure $next, $permission)
    {
        if (!$request->user() || !$request->user()->hasPermissionTo($permission)) {
            abort(403, 'Insufficient permissions');
        }
        
        return $next($request);
    }
}

// Use in routes
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

Route::middleware(['auth', 'permission:manage-users'])->group(function () {
    Route::resource('admin/users', UserController::class);
});
```

### Blade Template Integration

```php
{{-- Check roles in templates --}}
@if(auth()->user()->hasRole('admin'))
    <div class="admin-panel">
        <h3>Admin Panel</h3>
        <a href="/admin/users">Manage Users</a>
    </div>
@endif

{{-- Check permissions in templates --}}
@if(auth()->user()->hasPermissionTo('edit-content'))
    <button class="btn btn-primary">Edit Content</button>
@endif

@if(auth()->user()->hasPermissionTo('delete-content'))
    <button class="btn btn-danger">Delete Content</button>
@endif

{{-- Show different navigation based on roles --}}
<nav>
    @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-admin'))
        <a href="/admin">Admin Area</a>
    @endif
    
    @if(auth()->user()->hasRole('editor') || auth()->user()->hasRole('author'))
        <a href="/content">Content Management</a>
    @endif
    
    @if(auth()->user()->hasPermissionTo('view-reports'))
        <a href="/reports">Reports</a>
    @endif
</nav>
```

## Database Seeding

### Comprehensive Seeder

```php
// database/seeders/RolesAndPermissionsSeeder.php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Clear existing data
        DB::table('permission_role')->delete();
        DB::table('role_user')->delete();
        Permission::truncate();
        Role::truncate();
        
        // Create permissions
        $permissions = $this->getPermissions();
        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
        
        // Create roles
        $roles = $this->getRoles();
        foreach ($roles as $roleData) {
            $role = Role::create([
                'name' => $roleData['name'],
                'slug' => $roleData['slug']
            ]);
            
            // Attach permissions to role
            $permissionIds = Permission::whereIn('slug', $roleData['permissions'])->pluck('id');
            $role->permissions()->attach($permissionIds);
        }
        
        // Create default admin user
        $this->createDefaultAdmin();
    }
    
    private function getPermissions()
    {
        return [
            ['name' => 'View Dashboard', 'slug' => 'view-dashboard'],
            ['name' => 'Manage Users', 'slug' => 'manage-users'],
            ['name' => 'View Users', 'slug' => 'view-users'],
            ['name' => 'Create Content', 'slug' => 'create-content'],
            ['name' => 'Edit Content', 'slug' => 'edit-content'],
            ['name' => 'Delete Content', 'slug' => 'delete-content'],
            ['name' => 'Publish Content', 'slug' => 'publish-content'],
            ['name' => 'Manage Settings', 'slug' => 'manage-settings'],
            ['name' => 'View Reports', 'slug' => 'view-reports'],
        ];
    }
    
    private function getRoles()
    {
        return [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'permissions' => [
                    'view-dashboard', 'manage-users', 'view-users',
                    'create-content', 'edit-content', 'delete-content', 'publish-content',
                    'manage-settings', 'view-reports'
                ]
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'permissions' => [
                    'view-dashboard', 'view-users',
                    'create-content', 'edit-content', 'delete-content', 'publish-content',
                    'view-reports'
                ]
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'permissions' => [
                    'view-dashboard',
                    'create-content', 'edit-content', 'publish-content'
                ]
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'permissions' => [
                    'view-dashboard',
                    'create-content', 'edit-content'
                ]
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'permissions' => ['view-dashboard']
            ]
        ];
    }
    
    private function createDefaultAdmin()
    {
        $userModel = config('cms-framework.user_model');
        
        $admin = $userModel::create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => bcrypt('password')
        ]);
        
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $admin->roles()->attach($superAdminRole->id);
    }
}
```

## Best Practices

### 1. Use Descriptive Slugs
```php
// Good
'slug' => 'manage-user-accounts'

// Avoid
'slug' => 'mua'
```

### 2. Group Related Permissions
```php
// Content permissions
$contentPerms = ['create-content', 'edit-content', 'delete-content'];

// User permissions  
$userPerms = ['view-users', 'edit-users', 'delete-users'];
```

### 3. Check Permissions, Not Roles
```php
// Good - flexible
if ($user->hasPermissionTo('edit-content')) {
    // Allow editing
}

// Less flexible - tied to specific role
if ($user->hasRole('editor')) {
    // Allow editing
}
```

### 4. Use Database Transactions
```php
DB::transaction(function () {
    $role = Role::create($roleData);
    $role->permissions()->attach($permissionIds);
});
```

### 5. Cache Role/Permission Queries
```php
// Cache expensive permission checks
$userPermissions = Cache::remember("user_{$user->id}_permissions", 3600, function () use ($user) {
    return $user->roles->flatMap->permissions->pluck('slug')->unique();
});
```

## Troubleshooting

### Common Issues

**Permission Check Always Returns False**
- Verify user has roles assigned
- Check that roles have permissions attached
- Ensure permission slug exactly matches

**Role Assignment Not Working**
- Confirm pivot table `role_user` exists
- Check that User model has `HasRolesAndPermissions` trait
- Verify role ID exists before assignment

**Database Constraint Errors**
- Ensure proper foreign key relationships
- Check that referenced roles/permissions exist
- Use transactions for multi-step operations

## Related Documentation

- [[User Management]] - Managing users with roles
- [[User API Reference]] - API endpoints for user/role operations
- [[Developer Guide]] - Advanced customization options

---

*For user-specific operations, see [[User Management]].*
