---
title: Configuration
---

# Configuration

The CMS Framework provides flexible configuration options to customize its behavior for your specific application needs.

## Configuration File

The main configuration file is located at `config/cms-framework.php`. You can publish it using:

```bash
php artisan vendor:publish --provider="ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider" --tag="config"
```

## Configuration Options

### User Model

The most important configuration option is specifying your application's User model:

```php
<?php
return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This is the model that your application uses for users. It will be used
    | by the CMS framework to establish relationships and handle user logic.
    | You should update this value to point to your app's User model.
    |
    */
    'user_model' => \App\Models\User::class,
];
```

#### Requirements for User Model

Your User model must:

1. **Include the HasRolesAndPermissions trait:**
```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
    // ...
}
```

2. **Have the required fillable fields:**
```php
protected $fillable = [
    'name',
    'email',
    'password',
    // other fields...
];
```

3. **Use password hashing (usually already included in Laravel's User model):**
```php
protected $hidden = [
    'password',
    'remember_token',
];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
```

## Advanced Configuration

### Custom Route Prefix

By default, the framework registers routes under `/api/v1`. To customize this, you can disable auto-discovery and manually register routes in your `RouteServiceProvider`:

```php
// In your RouteServiceProvider
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\UserController;

Route::prefix('api/v2')->group(function () {
    Route::apiResource('users', UserController::class);
});
```

### Database Table Names

The framework uses Laravel's default naming conventions:
- `users` - Your existing users table
- `roles` - Created by framework migration
- `permissions` - Created by framework migration
- `role_user` - Pivot table for user-role relationships
- `permission_role` - Pivot table for role-permission relationships

To customize table names, you can modify the migrations before running them or extend the models.

### Validation Rules

The UserController uses built-in validation rules:

**For user creation:**
- `name`: required, string, max 255 characters
- `email`: required, string, email format, max 255 characters, unique in users table
- `password`: required, string, minimum 8 characters

**For user updates:**
- All fields are optional (using `sometimes` rule)
- Email uniqueness excludes the current user being updated

To customize validation, extend the UserController or create your own controller.

## Environment Variables

While the framework doesn't define specific environment variables, you can reference them in your configuration:

```php
// config/cms-framework.php
return [
    'user_model' => env('CMS_USER_MODEL', \App\Models\User::class),
];
```

Then in your `.env` file:
```env
CMS_USER_MODEL="App\\Models\\CustomUser"
```

## Multiple User Types

If your application has multiple user types, you can create different configurations:

```php
// config/cms-framework.php
return [
    'user_model' => \App\Models\User::class,
    'admin_model' => \App\Models\Admin::class, // Custom configuration
];
```

## Service Provider Configuration

The framework's service provider automatically:
- Loads migrations from `database/migrations`
- Registers API routes under `/api/v1`
- Publishes configuration files
- Merges configuration with application config

## Testing Configuration

For testing, the framework uses the configured user model. In your tests, ensure you're using the same model:

```php
// In your test setup
$userModel = config('cms-framework.user_model');
$user = $userModel::factory()->create();
```

## Next Steps

- [[Quick Start]] - Get started with basic usage
- [[User Management]] - Learn about user management features
- [[Developer Guide]] - Advanced customization options

## Configuration Examples

### Basic Setup
```php
return [
    'user_model' => \App\Models\User::class,
];
```

### Custom User Model
```php
return [
    'user_model' => \App\Models\CustomUser::class,
];
```

---

*For more advanced configuration options, see the [[Developer Guide]].*
