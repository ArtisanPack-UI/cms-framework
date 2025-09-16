---
title: User Management
---

# User Management

The CMS Framework provides comprehensive user management capabilities through a flexible API and integration with your existing User model.

## Overview

User management in the CMS Framework is built around:
- **RESTful API endpoints** for CRUD operations
- **Configurable User model** that works with your existing application
- **Role-based access control** integration
- **Validation and security** best practices

## User Model Requirements

Your User model must include the `HasRolesAndPermissions` trait:

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
    
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
}
```

## User CRUD Operations

### Creating Users

#### Via API
```bash
curl -X POST http://your-app.test/api/v1/users \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secure-password-123"
  }'
```

#### Programmatically
```php
$userModel = config('cms-framework.user_model');
$user = $userModel::create([
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
    'password' => bcrypt('secure-password-123')
]);
```

### Listing Users

#### API with Pagination
```bash
curl -X GET http://your-app.test/api/v1/users
```

Response includes:
- User data with roles
- Pagination metadata (current page, total, etc.)
- 15 users per page (default)

#### Programmatic Access
```php
$userModel = config('cms-framework.user_model');
$users = $userModel::with('roles')->paginate(15);
```

### Retrieving Single User

#### Via API
```bash
curl -X GET http://your-app.test/api/v1/users/1
```

#### Programmatically
```php
$userModel = config('cms-framework.user_model');
$user = $userModel::with('roles')->findOrFail(1);
```

### Updating Users

#### Via API (Partial Updates Supported)
```bash
curl -X PATCH http://your-app.test/api/v1/users/1 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name"
  }'
```

#### Programmatically
```php
$userModel = config('cms-framework.user_model');
$user = $userModel::findOrFail(1);
$user->update([
    'name' => 'Updated Name',
    'email' => 'newemail@example.com'
]);
```

### Deleting Users

#### Via API
```bash
curl -X DELETE http://your-app.test/api/v1/users/1
```

#### Programmatically
```php
$userModel = config('cms-framework.user_model');
$user = $userModel::findOrFail(1);
$user->delete();
```

## Role Management for Users

### Assigning Roles

#### Single Role
```php
$user->roles()->attach($roleId);
```

#### Multiple Roles
```php
$user->roles()->attach([$role1Id, $role2Id, $role3Id]);
```

#### Using Role Slugs
```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;

$adminRole = Role::where('slug', 'admin')->first();
$user->roles()->attach($adminRole->id);
```

### Removing Roles

#### Remove Specific Role
```php
$user->roles()->detach($roleId);
```

#### Remove All Roles
```php
$user->roles()->detach();
```

#### Sync Roles (Replace All)
```php
$user->roles()->sync([$role1Id, $role2Id]); // Removes other roles
```

### Checking User Roles

#### Check Specific Role
```php
if ($user->hasRole('admin')) {
    // User has admin role
}
```

#### Check Multiple Roles
```php
if ($user->hasRole('admin') || $user->hasRole('moderator')) {
    // User has admin OR moderator role
}
```

#### Get All User Roles
```php
$roles = $user->roles; // Collection of Role models
$roleNames = $user->roles->pluck('name'); // Collection of role names
```

## Permission Checking

### Direct Permission Check
```php
if ($user->hasPermissionTo('edit-content')) {
    // User can edit content
}
```

### Getting User Permissions
```php
// Get all permissions through roles
$permissions = $user->roles->flatMap(function ($role) {
    return $role->permissions;
})->unique('id');
```

## Validation Rules

### User Creation
- **name**: Required, string, maximum 255 characters
- **email**: Required, valid email format, maximum 255 characters, unique in users table
- **password**: Required, string, minimum 8 characters

### User Updates
- **name**: Optional, string, maximum 255 characters
- **email**: Optional, valid email format, maximum 255 characters, unique (excluding current user)
- **password**: Optional, string, minimum 8 characters

## Security Considerations

### Password Handling
- Passwords are automatically encrypted using `bcrypt()` in the controller
- Plain text passwords are never stored
- Password updates require the full password (no partial updates)

### Email Uniqueness
- Email addresses must be unique across all users
- Updates check uniqueness excluding the current user
- Case-sensitive email validation

### API Security
- Implement authentication middleware for API endpoints
- Consider rate limiting for user creation/updates
- Validate all input data

## Advanced Usage

### Custom User Factories

```php
// database/factories/UserFactory.php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;

class UserFactory extends Factory
{
    public function withRole($roleSlug)
    {
        return $this->afterCreating(function ($user) use ($roleSlug) {
            $role = Role::where('slug', $roleSlug)->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        });
    }
}

// Usage
$adminUser = User::factory()->withRole('admin')->create();
```

### Bulk Operations

#### Create Multiple Users
```php
$users = [
    ['name' => 'User 1', 'email' => 'user1@example.com', 'password' => bcrypt('password')],
    ['name' => 'User 2', 'email' => 'user2@example.com', 'password' => bcrypt('password')],
];

$userModel = config('cms-framework.user_model');
foreach ($users as $userData) {
    $userModel::create($userData);
}
```

#### Assign Role to Multiple Users
```php
$userIds = [1, 2, 3, 4, 5];
$adminRole = Role::where('slug', 'admin')->first();

foreach ($userIds as $userId) {
    $user = $userModel::find($userId);
    $user->roles()->attach($adminRole->id);
}
```

### Soft Deletes

If your User model uses soft deletes:

```php
class User extends Authenticatable
{
    use SoftDeletes, HasRolesAndPermissions;
}

// Soft delete via API will work automatically
// Restore deleted users
$user = User::withTrashed()->find(1);
$user->restore();

// Force delete
$user->forceDelete();
```

## Best Practices

### 1. Always Use the Configured Model
```php
// Good
$userModel = config('cms-framework.user_model');
$users = $userModel::all();

// Avoid hardcoding
$users = User::all(); // Don't do this
```

### 2. Validate Before Role Assignment
```php
if ($user && $role) {
    $user->roles()->attach($role->id);
}
```

### 3. Use Transactions for Complex Operations
```php
DB::transaction(function () use ($userData, $roleIds) {
    $userModel = config('cms-framework.user_model');
    $user = $userModel::create($userData);
    $user->roles()->attach($roleIds);
});
```

### 4. Handle API Responses Properly
```php
// The API returns UserResource format
$response = Http::post('/api/v1/users', $userData);
$userData = $response->json('data'); // Access the data key
```

## Troubleshooting

### Common Issues

**User Creation Fails**
- Check validation rules are met
- Ensure email is unique
- Verify User model has correct fillable fields

**Role Assignment Not Working**
- Confirm User model has `HasRolesAndPermissions` trait
- Check that role exists before assignment
- Verify role_user pivot table exists

**Permission Checks Return False**
- Ensure user has roles assigned
- Verify roles have permissions attached
- Check permission slug matches exactly

## Related Documentation

- [[Roles and Permissions]] - Detailed RBAC system guide
- [[User API Reference]] - Complete API documentation
- [[Configuration]] - User model configuration options
- [[Developer Guide]] - Extending user functionality

---

*For API endpoint details, see the [[User API Reference]].*
