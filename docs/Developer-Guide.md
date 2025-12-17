---
title: Developer Guide
---

# Developer Guide

This guide provides advanced information for developers who want to extend, customize, or build upon the CMS Framework. It covers architecture patterns, extension points, and best practices for creating robust content management systems.

## Architecture Overview

The CMS Framework follows Laravel's modular architecture patterns:

```
src/
├── Modules/
│   └── Users/
│       ├── Http/
│       │   ├── Controllers/
│       │   └── Resources/
│       ├── Models/
│       │   └── Concerns/
│       ├── Managers/
│       ├── Providers/
│       └── routes/
├── CMSFrameworkServiceProvider.php
└── ...
```

### Key Components

- **Service Providers**: Bootstrap and register framework components
- **Modules**: Self-contained feature sets (Users, Content, etc.)
- **Managers**: Business logic abstraction layer
- **Models**: Eloquent models with relationships and concerns
- **Controllers**: API endpoint handlers
- **Resources**: API response transformations

## Extending the Framework

### Creating Custom Modules

Follow the existing Users module structure to create new modules:

```php
// src/Modules/Content/ContentServiceProvider.php
<?php

namespace ArtisanPackUI\CMSFramework\Modules\Content;

use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
    }
    
    public function register()
    {
        // Register services
    }
}
```

### Adding Custom Controllers

Extend the base functionality with your own controllers:

```php
// src/Modules/Content/Http/Controllers/ContentController.php
<?php

namespace ArtisanPackUI\CMSFramework\Modules\Content\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ContentController extends Controller
{
    public function index()
    {
        // Implementation
    }
}
```

### Custom Models with RBAC

Create models that integrate with the role-permission system:

```php
// src/Modules/Content/Models/Post.php
<?php

namespace ArtisanPackUI\CMSFramework\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'title',
        'content',
        'status',
        'author_id',
    ];
    
    public function author(): BelongsTo
    {
        $userModel = config('cms-framework.user_model');
        return $this->belongsTo($userModel, 'author_id');
    }
    
    // Scope for checking user permissions
    public function scopeVisibleTo($query, $user)
    {
        if ($user->hasPermissionTo('view-all-content')) {
            return $query;
        }
        
        return $query->where('author_id', $user->id);
    }
}
```

## Customization Patterns

### Extending User Functionality

#### Adding User Profile Fields

Create a migration to extend the users table:

```php
// database/migrations/add_profile_fields_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProfileFieldsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'bio', 'avatar', 'last_login_at']);
        });
    }
}
```

#### Extending User Model

Add methods to your User model:

```php
// app/Models/User.php
class User extends Authenticatable
{
    use HasRolesAndPermissions;
    
    protected $fillable = [
        'name', 'email', 'password', 'phone', 'bio', 'avatar'
    ];
    
    public function getAvatarUrlAttribute()
    {
        return $this->avatar 
            ? Storage::url($this->avatar)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name);
    }
    
    public function updateLastLogin()
    {
        $this->update(['last_login_at' => now()]);
    }
    
    public function isOnline()
    {
        return $this->last_login_at && $this->last_login_at->gt(now()->subMinutes(5));
    }
}
```

### Custom API Controllers

Override default behavior by extending the framework controllers:

```php
// app/Http/Controllers/CustomUserController.php
<?php

namespace App\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\UserController as BaseUserController;
use Illuminate\Http\Request;

class CustomUserController extends BaseUserController
{
    public function store(Request $request)
    {
        // Add custom validation
        $request->validate([
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:500',
        ]);
        
        // Call parent method
        $response = parent::store($request);
        
        // Add custom logic (e.g., send welcome email)
        $user = $response->resource;
        Mail::to($user->email)->send(new WelcomeEmail($user));
        
        return $response;
    }
    
    public function index()
    {
        // Add custom filtering
        $userModel = config('cms-framework.user_model');
        $users = $userModel::with(['roles'])
            ->when(request('role'), function ($query, $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('slug', $role);
                });
            })
            ->when(request('status'), function ($query, $status) {
                if ($status === 'online') {
                    $query->where('last_login_at', '>', now()->subMinutes(5));
                }
            })
            ->paginate(15);
        
        return UserResource::collection($users);
    }
}
```

Update your routes to use the custom controller:

```php
// routes/api.php
Route::apiResource('users', CustomUserController::class);
```

### Custom Permissions and Middleware

#### Dynamic Permission Creation

Create permissions programmatically based on models:

```php
// app/Services/PermissionService.php
<?php

namespace App\Services;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;

class PermissionService
{
    public function createModelPermissions($modelName)
    {
        $actions = ['view', 'create', 'edit', 'delete'];
        $modelSlug = str($modelName)->kebab()->plural();
        
        foreach ($actions as $action) {
            Permission::firstOrCreate([
                'slug' => "{$action}-{$modelSlug}"
            ], [
                'name' => ucfirst($action) . ' ' . str($modelName)->plural(),
            ]);
        }
    }
}
```

#### Advanced Permission Middleware

Create middleware that checks model-specific permissions:

```php
// app/Http/Middleware/CheckModelPermission.php
<?php

namespace App\Http\Middleware;

use Closure;

class CheckModelPermission
{
    public function handle($request, Closure $next, $model, $action = 'view')
    {
        $user = $request->user();
        $modelSlug = str($model)->kebab()->plural();
        $permission = "{$action}-{$modelSlug}";
        
        if (!$user || !$user->hasPermissionTo($permission)) {
            abort(403, "Missing permission: {$permission}");
        }
        
        return $next($request);
    }
}
```

Use in routes:

```php
Route::middleware(['auth', 'model.permission:post,edit'])->group(function () {
    Route::put('/posts/{post}', [PostController::class, 'update']);
});
```

## Advanced Integration Patterns

### Event-Driven Architecture

Listen for user events and trigger actions:

```php
// app/Listeners/UserEventListener.php
<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;

class UserEventListener
{
    public function handleLogin(Login $event)
    {
        $event->user->updateLastLogin();
        
        // Log user activity
        activity()
            ->performedOn($event->user)
            ->log('User logged in');
    }
    
    public function handleRegistration(Registered $event)
    {
        // Assign default role
        $defaultRole = Role::where('slug', 'user')->first();
        if ($defaultRole) {
            $event->user->roles()->attach($defaultRole->id);
        }
        
        // Send welcome email
        Mail::to($event->user)->send(new WelcomeEmail($event->user));
    }
}
```

Register the listener:

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    Login::class => [
        UserEventListener::class . '@handleLogin',
    ],
    Registered::class => [
        UserEventListener::class . '@handleRegistration',
    ],
];
```

### API Response Customization

Create custom API resources for different contexts:

```php
// app/Http/Resources/DetailedUserResource.php
<?php

namespace App\Http\Resources;

use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\UserResource;

class DetailedUserResource extends UserResource
{
    public function toArray($request)
    {
        return array_merge(parent::toArray($request), [
            'profile' => [
                'phone' => $this->phone,
                'bio' => $this->bio,
                'avatar_url' => $this->avatar_url,
                'last_login_at' => $this->last_login_at,
                'is_online' => $this->isOnline(),
            ],
            'permissions' => $this->getAllPermissions(),
            'statistics' => [
                'posts_count' => $this->posts()->count(),
                'comments_count' => $this->comments()->count(),
            ],
        ]);
    }
    
    private function getAllPermissions()
    {
        return $this->roles
            ->flatMap->permissions
            ->unique('id')
            ->pluck('slug');
    }
}
```

### Database Query Optimization

Use eager loading and query optimization:

```php
// app/Http/Controllers/OptimizedUserController.php
public function index()
{
    $userModel = config('cms-framework.user_model');
    
    $users = $userModel::select([
            'id', 'name', 'email', 'created_at', 'last_login_at'
        ])
        ->with([
            'roles:id,name,slug',
            'roles.permissions:id,name,slug'
        ])
        ->withCount(['posts', 'comments'])
        ->when(request('search'), function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->latest()
        ->paginate(15);
    
    return DetailedUserResource::collection($users);
}
```

### Cache Integration

Implement caching for expensive operations:

```php
// app/Services/UserPermissionCache.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class UserPermissionCache
{
    public function getUserPermissions($user)
    {
        return Cache::tags(['user-permissions', "user-{$user->id}"])
            ->remember("user-{$user->id}-permissions", 3600, function () use ($user) {
                return $user->roles
                    ->flatMap->permissions
                    ->unique('id')
                    ->pluck('slug')
                    ->toArray();
            });
    }
    
    public function clearUserPermissions($user)
    {
        Cache::tags(["user-{$user->id}"])->flush();
    }
    
    public function clearAllUserPermissions()
    {
        Cache::tags(['user-permissions'])->flush();
    }
}
```

Use in your User model:

```php
// app/Models/User.php
public function hasPermissionTo(string $permission): bool
{
    $userPermissions = app(UserPermissionCache::class)->getUserPermissions($this);
    return in_array($permission, $userPermissions);
}
```

## Testing Extensions

### Feature Testing

Test your custom functionality:

```php
// tests/Feature/CustomUserTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;

class CustomUserTest extends TestCase
{
    public function test_user_can_be_created_with_profile_data()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '+1234567890',
            'bio' => 'Test bio',
        ];
        
        $response = $this->postJson('/api/v1/users', $userData);
        
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'name', 'email', 'profile' => [
                             'phone', 'bio', 'avatar_url'
                         ]
                     ]
                 ]);
    }
    
    public function test_user_permissions_are_cached()
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->roles()->attach($role->id);
        
        // First call should hit database
        $permissions = app(UserPermissionCache::class)->getUserPermissions($user);
        
        // Second call should use cache
        $cachedPermissions = app(UserPermissionCache::class)->getUserPermissions($user);
        
        $this->assertEquals($permissions, $cachedPermissions);
    }
}
```

### Unit Testing

Test individual components:

```php
// tests/Unit/UserPermissionTest.php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;

class UserPermissionTest extends TestCase
{
    public function test_user_has_permission_through_role()
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        
        $this->assertTrue($user->hasPermissionTo($permission->slug));
    }
    
    public function test_user_without_role_has_no_permissions()
    {
        $user = User::factory()->create();
        $permission = Permission::factory()->create();
        
        $this->assertFalse($user->hasPermissionTo($permission->slug));
    }
}
```

## Performance Optimization

### Database Indexing

Add appropriate indexes for common queries:

```sql
-- For user role queries
CREATE INDEX idx_role_user_user_id ON role_user(user_id);
CREATE INDEX idx_role_user_role_id ON role_user(role_id);

-- For permission role queries  
CREATE INDEX idx_permission_role_role_id ON permission_role(role_id);
CREATE INDEX idx_permission_role_permission_id ON permission_role(permission_id);

-- For user searches
CREATE INDEX idx_users_name ON users(name);
CREATE INDEX idx_users_email ON users(email);
```

### Query Optimization

Use database views for complex queries:

```sql
-- Create a view for user permissions
CREATE VIEW user_permissions AS
SELECT 
    u.id as user_id,
    u.name as user_name,
    u.email as user_email,
    p.slug as permission_slug,
    p.name as permission_name
FROM users u
JOIN role_user ru ON u.id = ru.user_id  
JOIN roles r ON ru.role_id = r.id
JOIN permission_role pr ON r.id = pr.role_id
JOIN permissions p ON pr.permission_id = p.id;
```

### Eager Loading Strategies

Define relationship loading patterns:

```php
// app/Models/User.php
protected $with = ['roles']; // Always load roles

public function scopeWithPermissions($query)
{
    return $query->with(['roles.permissions']);
}

public function scopeWithStats($query)
{
    return $query->withCount(['posts', 'comments', 'logins']);
}
```

## Security Best Practices

### Input Sanitization

Always sanitize input data:

```php
// app/Http/Requests/CreateUserRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255|regex:/^[\pL\s\-]+$/u',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|regex:/^[\+]?[0-9\s\-\(\)]{10,20}$/',
            'bio' => 'nullable|string|max:1000',
        ];
    }
    
    protected function prepareForValidation()
    {
        $this->merge([
            'name' => strip_tags($this->name),
            'bio' => strip_tags($this->bio),
        ]);
    }
}
```

### Rate Limiting

Implement comprehensive rate limiting:

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];

protected $routeMiddleware = [
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    'throttle.login' => \App\Http\Middleware\LoginThrottleMiddleware::class,
];
```

Custom throttling for sensitive operations:

```php
// app/Http/Middleware/LoginThrottleMiddleware.php
public function handle($request, Closure $next, $maxAttempts = 5, $decayMinutes = 1)
{
    $key = $this->resolveRequestSignature($request);
    $maxAttempts = (int) $maxAttempts;
    
    if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
        throw $this->buildException($key, $maxAttempts);
    }
    
    $this->limiter->hit($key, $decayMinutes * 60);
    
    return $next($request);
}
```

## Deployment Considerations

### Environment Configuration

Create environment-specific configurations:

```php
// config/cms-framework.php
return [
    'user_model' => env('CMS_USER_MODEL', \App\Models\User::class),
    'cache_permissions' => env('CMS_CACHE_PERMISSIONS', true),
    'permission_cache_ttl' => env('CMS_PERMISSION_CACHE_TTL', 3600),
    'api_rate_limit' => env('CMS_API_RATE_LIMIT', 60),
    'enable_soft_deletes' => env('CMS_ENABLE_SOFT_DELETES', false),
];
```

### Monitoring and Logging

Implement comprehensive logging:

```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    // Log all permission checks
    Event::listen('cms.permission.checked', function ($user, $permission, $result) {
        Log::info('Permission checked', [
            'user_id' => $user->id,
            'permission' => $permission,
            'result' => $result,
            'ip' => request()->ip(),
        ]);
    });
}
```

## Related Documentation

- [[Installation Guide]] - Getting started with the framework
- [[Configuration]] - Framework configuration options
- [[User Management]] - User management features
- [[Roles and Permissions]] - RBAC system details
- [[User API Reference]] - API endpoint documentation

---

*For basic usage, start with the [[Quick Start]] guide.*
