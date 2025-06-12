# Users and Roles Module

The Users and Roles module provides functionality for managing users, roles, and permissions in the ArtisanPack UI CMS Framework.

## Overview

The Users and Roles module allows you to create, read, update, and delete users and roles in the application. It also provides functionality for assigning roles to users, managing user-specific settings, and checking user capabilities.

## Classes

### User Model

The `User` model represents a user in the application.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Models;
```

#### Properties

- `$factory`: The factory that should be used to instantiate the model.
- `$table`: The table associated with the model, which is 'users'.
- `$fillable`: Array of attributes that are mass assignable, including 'username', 'email', 'password', 'role_id', 'first_name', 'last_name', 'website', 'bio', 'links', and 'settings'.
- `$hidden`: Array of attributes that should be hidden for serialization, including 'password' and 'remember_token'.
- `$casts`: Array of attributes that should be cast to specific types, including 'email_verified_at' as 'datetime', 'password' as 'hashed', 'links' as 'array', and 'settings' as 'array'.

#### Methods

##### role(): BelongsTo
Gets the role that the user belongs to.

**@since** 1.0.0

**@return** BelongsTo The relationship to the Role model.

##### can($abilities, $arguments = []): bool
Checks if the user has a given capability through their assigned role.

**@since** 1.0.0

**@param** Iterable|string $abilities The capability to check for.
**@param** array $arguments Additional arguments for the check.
**@return** bool True if the user has the capability, false otherwise.

##### getSetting(string $key, mixed $default = null): mixed
Gets a user-specific setting.

**@since** 1.0.0

**@param** string $key The setting key to retrieve.
**@param** mixed $default Optional. The default value if the setting is not found. Default null.
**@return** mixed The setting value.

##### setSetting(string $key, mixed $value): bool
Sets a user-specific setting.

**@since** 1.0.0

**@param** string $key The setting key to set.
**@param** mixed $value The value to store.
**@return** bool True if the setting was set and saved, false otherwise.

##### deleteSetting(string $key): bool
Deletes a user-specific setting.

**@since** 1.0.0

**@param** string $key The setting key to delete.
**@return** bool True if the setting was deleted and saved, false otherwise.

### Role Model

The `Role` model represents a role in the application.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Models;
```

#### Properties

- `$factory`: The factory that should be used to instantiate the model.
- `$table`: The table associated with the model, which is 'roles'.
- `$fillable`: Array of attributes that are mass assignable, including 'name', 'slug', 'description', and 'capabilities'.
- `$casts`: Array of attributes that should be cast to specific types, including 'capabilities' as 'array'.

#### Methods

##### users(): HasMany
Gets the users that belong to this role.

**@since** 1.0.0

**@return** HasMany The relationship to the User model.

##### addCapability(string $capability): bool
Adds a capability to the role.

**@since** 1.0.0

**@param** string $capability The capability to add.
**@return** bool True if the capability was added and saved, false otherwise.

##### hasCapability(string $capability): bool
Checks if the role has a given capability.

**@since** 1.0.0

**@param** string $capability The capability to check for.
**@return** bool True if the role has the capability, false otherwise.

##### removeCapability(string $capability): bool
Removes a capability from the role.

**@since** 1.0.0

**@param** string $capability The capability to remove.
**@return** bool True if the capability was removed and saved, false otherwise.

### UsersManager Class

The `UsersManager` class is the main class for managing users and roles.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\Users;
```

#### Methods

##### allUsers(): Collection
Retrieves all users.

**@since** 1.0.0

**@return** Collection A collection of User models.

##### findUser(int $userId): ?User
Finds a user by their ID.

**@since** 1.0.0

**@param** int $userId The ID of the user to find.
**@return** User|null The User model if found, otherwise null.

##### createUser(array $userData): User
Creates a new user.

**@since** 1.0.0

**@param** array $userData The data for the new user.
**@return** User The newly created User model.

##### updateUser(User $user, array $userData): bool
Updates an existing user.

**@since** 1.0.0

**@param** User $user The User model to update.
**@param** array $userData The data to update the user with.
**@return** bool True on success, false on failure.

##### deleteUser(User $user): ?bool
Deletes a user.

**@since** 1.0.0

**@param** User $user The User model to delete.
**@return** bool|null True on success, false if not found, null on error.

##### allRoles(): Collection
Retrieves all roles.

**@since** 1.0.0

**@return** Collection A collection of Role models.

##### findRole(int $roleId): ?Role
Finds a role by its ID.

**@since** 1.0.0

**@param** int $roleId The ID of the role to find.
**@return** Role|null The Role model if found, otherwise null.

##### createRole(array $roleData): Role
Creates a new role.

**@since** 1.0.0

**@param** array $roleData The data for the new role.
**@return** Role The newly created Role model.

##### updateRole(Role $role, array $roleData): bool
Updates an existing role.

**@since** 1.0.0

**@param** Role $role The Role model to update.
**@param** array $roleData The data to update the role with.
**@return** bool True on success, false on failure.

##### deleteRole(Role $role): ?bool
Deletes a role.

**@since** 1.0.0

**@param** Role $role The Role model to delete.
**@return** bool|null True on success, false if not found, null on error.

##### assignRole(User $user, Role $role): bool
Assigns a role to a user.

**@since** 1.0.0

**@param** User $user The user to assign the role to.
**@param** Role $role The role to assign.
**@return** bool True on success, false on failure.

##### removeRole(User $user): bool
Removes a role from a user.

**@since** 1.0.0

**@param** User $user The user to remove the role from.
**@return** bool True on success, false on failure.

##### getUserSetting(User $user, string $key, mixed $default = null): mixed
Gets a user-specific setting.

**@since** 1.0.0

**@param** User $user The user to retrieve the setting for.
**@param** string $key The setting key.
**@param** mixed $default Optional. The default value if the setting is not found. Default null.
**@return** mixed The setting value.

##### setUserSetting(User $user, string $key, mixed $value): bool
Sets a user-specific setting.

**@since** 1.0.0

**@param** User $user The user to set the setting for.
**@param** string $key The setting key.
**@param** mixed $value The value to set.
**@return** bool True if the setting was set and saved, false otherwise.

##### deleteUserSetting(User $user, string $key): bool
Deletes a user-specific setting.

**@since** 1.0.0

**@param** User $user The user to delete the setting from.
**@param** string $key The setting key to delete.
**@return** bool True if the setting was deleted and saved, false otherwise.

## Database Schema

### Users Table

The Users module creates a `users` table in the database with the following columns:

- `id`: Auto-incrementing primary key
- `username`: String column for the username
- `email`: String column for the email address
- `email_verified_at`: Timestamp for when the email was verified
- `password`: String column for the hashed password
- `role_id`: Foreign key to the roles table
- `first_name`: String column for the first name
- `last_name`: String column for the last name
- `website`: String column for the website
- `bio`: Text column for the biography
- `links`: JSON column for storing links
- `settings`: JSON column for storing user-specific settings
- `remember_token`: String column for the remember token
- `created_at`: Timestamp for when the user was created
- `updated_at`: Timestamp for when the user was last updated

### Roles Table

The Roles module creates a `roles` table in the database with the following columns:

- `id`: Auto-incrementing primary key
- `name`: String column for the role name
- `slug`: String column for the role slug
- `description`: Text column for the role description
- `capabilities`: JSON column for storing capabilities
- `created_at`: Timestamp for when the role was created
- `updated_at`: Timestamp for when the role was last updated

## API Endpoints

The Users and Roles module provides RESTful API endpoints for managing users and roles. These endpoints are protected by Laravel Sanctum authentication.

### UserController

The `UserController` provides endpoints for managing users.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Http\Controllers;
```

#### Methods

##### index(): JsonResponse
Lists all users.

**@since** 1.0.0

**@return** JsonResponse A JSON response containing all users.

##### store(UserRequest $request): UserResource
Creates a new user.

**@since** 1.0.0

**@param** UserRequest $request The request containing the user data.
**@return** UserResource The newly created user resource.

##### show(int $id): UserResource
Shows a specific user.

**@since** 1.0.0

**@param** int $id The ID of the user to show.
**@return** UserResource The user resource.

##### update(UserRequest $request, int $id): UserResource
Updates a user.

**@since** 1.0.0

**@param** UserRequest $request The request containing the user data.
**@param** int $id The ID of the user to update.
**@return** UserResource The updated user resource.

##### destroy(int $id): JsonResponse
Deletes a user.

**@since** 1.0.0

**@param** int $id The ID of the user to delete.
**@return** JsonResponse An empty JSON response.

### RoleController

The `RoleController` provides endpoints for managing roles.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Http\Controllers;
```

#### Methods

##### index(): JsonResponse
Lists all roles.

**@since** 1.0.0

**@return** JsonResponse A JSON response containing all roles.

##### store(RoleRequest $request): RoleResource
Creates a new role.

**@since** 1.0.0

**@param** RoleRequest $request The request containing the role data.
**@return** RoleResource The newly created role resource.

##### show(int $id): RoleResource
Shows a specific role.

**@since** 1.0.0

**@param** int $id The ID of the role to show.
**@return** RoleResource The role resource.

##### update(RoleRequest $request, int $id): RoleResource
Updates a role.

**@since** 1.0.0

**@param** RoleRequest $request The request containing the role data.
**@param** int $id The ID of the role to update.
**@return** RoleResource The updated role resource.

##### destroy(int $id): JsonResponse
Deletes a role.

**@since** 1.0.0

**@param** int $id The ID of the role to delete.
**@return** JsonResponse An empty JSON response.

### Authentication

All API endpoints are protected by Laravel Sanctum authentication. To access these endpoints, you need to include a valid Sanctum token in the `Authorization` header of your HTTP request:

```
Authorization: Bearer {your-token}
```

The user associated with the token must have the appropriate role capabilities to perform the requested action. For more information about API authentication, see the [API Authentication](api-authentication.md) documentation.

### Example Requests

#### List all users
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->get('https://your-app.com/api/cms/users');
```

#### Create a new user
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->post('https://your-app.com/api/cms/users', [
        'username' => 'johndoe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
```

#### Get a specific user
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->get('https://your-app.com/api/cms/users/1');
```

#### Update a user
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->put('https://your-app.com/api/cms/users/1', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
```

#### Delete a user
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->delete('https://your-app.com/api/cms/users/1');
```

#### List all roles
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->get('https://your-app.com/api/cms/roles');
```

#### Create a new role
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->post('https://your-app.com/api/cms/roles', [
        'name' => 'Editor',
        'slug' => 'editor',
        'description' => 'Can edit content',
        'capabilities' => ['edit_posts', 'publish_posts'],
    ]);
```

#### Get a specific role
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->get('https://your-app.com/api/cms/roles/1');
```

#### Update a role
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->put('https://your-app.com/api/cms/roles/1', [
        'name' => 'Senior Editor',
        'description' => 'Can edit and publish content',
    ]);
```

#### Delete a role
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->delete('https://your-app.com/api/cms/roles/1');
```

### UserRequest

The `UserRequest` class defines the validation rules for user data.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Http\Requests;
```

#### Methods

##### rules(): array
Returns the validation rules for the request.

**@since** 1.0.0

**@return** array The validation rules.

For store (POST) requests:
- 'username', 'email', and 'password' are required
- 'email' must be a valid email and have a maximum length of 254 characters

For update (PUT/PATCH) requests:
- 'username', 'email', and 'password' are optional ('sometimes')
- 'email' must be a valid email and have a maximum length of 254 characters if provided

For both types of requests:
- 'email_verified_at', 'role_id', 'first_name', 'last_name', 'website', 'bio', 'links', and 'settings' are optional
- 'email_verified_at' must be a valid date if provided
- 'links' and 'settings' must be arrays if provided

##### authorize(): bool
Determines if the user is authorized to make this request.

**@since** 1.0.0

**@return** bool Always returns true.

### RoleRequest

The `RoleRequest` class defines the validation rules for role data.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Http\Requests;
```

#### Methods

##### rules(): array
Returns the validation rules for the request.

**@since** 1.0.0

**@return** array The validation rules.

For store (POST) requests:
- 'name' and 'slug' are required
- 'slug' must be unique in the roles table

For update (PUT/PATCH) requests:
- 'name' and 'slug' are optional ('sometimes')
- 'slug' must be unique in the roles table, ignoring the current role's slug

For both types of requests:
- 'description' and 'capabilities' are optional
- 'capabilities' must be an array if provided

##### authorize(): bool
Determines if the user is authorized to make this request.

**@since** 1.0.0

**@return** bool Always returns true.

### UserResource

The `UserResource` class transforms a User model into an array for API responses.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Http\Resources;
```

#### Methods

##### toArray(Request $request): array
Transforms the resource into an array.

**@since** 1.0.0

**@param** Request $request The request.
**@return** array The transformed resource.

### RoleResource

The `RoleResource` class transforms a Role model into an array for API responses.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Http\Resources;
```

#### Methods

##### toArray(Request $request): array
Transforms the resource into an array.

**@since** 1.0.0

**@param** Request $request The request.
**@return** array The transformed resource.

## Usage

### Creating a User

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$userData = [
    'username' => 'johndoe',
    'email' => 'john@example.com',
    'password' => 'password123',
    'first_name' => 'John',
    'last_name' => 'Doe',
];
$user = $usersManager->createUser($userData);
```

### Finding a User

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$user = $usersManager->findUser(1);
```

### Updating a User

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$user = $usersManager->findUser(1);
$userData = [
    'first_name' => 'Jane',
    'last_name' => 'Doe',
];
$usersManager->updateUser($user, $userData);
```

### Deleting a User

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$user = $usersManager->findUser(1);
$usersManager->deleteUser($user);
```

### Creating a Role

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$roleData = [
    'name' => 'Editor',
    'slug' => 'editor',
    'description' => 'Can edit content',
    'capabilities' => ['edit_posts', 'publish_posts'],
];
$role = $usersManager->createRole($roleData);
```

### Finding a Role

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$role = $usersManager->findRole(1);
```

### Updating a Role

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$role = $usersManager->findRole(1);
$roleData = [
    'name' => 'Senior Editor',
    'description' => 'Can edit and publish content',
];
$usersManager->updateRole($role, $roleData);
```

### Deleting a Role

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$role = $usersManager->findRole(1);
$usersManager->deleteRole($role);
```

### Assigning a Role to a User

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$user = $usersManager->findUser(1);
$role = $usersManager->findRole(1);
$usersManager->assignRole($user, $role);
```

### Removing a Role from a User

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$user = $usersManager->findUser(1);
$usersManager->removeRole($user);
```

### Checking if a User has a Capability

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$user = $usersManager->findUser(1);
if ($user->can('edit_posts')) {
    // User can edit posts
}
```

### Managing User Settings

```php
$usersManager = app(ArtisanPackUI\CMSFramework\Features\Users\UsersManager::class);
$user = $usersManager->findUser(1);

// Set a setting
$usersManager->setUserSetting($user, 'theme', 'dark');

// Get a setting
$theme = $usersManager->getUserSetting($user, 'theme');

// Delete a setting
$usersManager->deleteUserSetting($user, 'theme');
```

## Hooks

### Actions

- `ap.cms.users.created`: Fires after a new user has been created.
  - **@param** User $user The newly created User model.
  - **@param** array $userData The original user data array.

- `ap.cms.users.updated`: Fires after a user has been updated.
  - **@param** User $user The updated User model.
  - **@param** array $userData The data used for the update.

- `ap.cms.users.deleted`: Fires after a user has been deleted.
  - **@param** int $userId The ID of the deleted user.

- `ap.cms.roles.created`: Fires after a new role has been created.
  - **@param** Role $role The newly created Role model.
  - **@param** array $roleData The original role data array.

- `ap.cms.roles.updated`: Fires after a role has been updated.
  - **@param** Role $role The updated Role model.
  - **@param** array $roleData The data used for the update.

- `ap.cms.roles.deleted`: Fires after a role has been deleted.
  - **@param** int $roleId The ID of the deleted role.

- `ap.cms.users.role_assigned`: Fires after a role has been assigned to a user.
  - **@param** User $user The user model.
  - **@param** Role $role The role model.

- `ap.cms.users.role_removed`: Fires after a role has been removed from a user.
  - **@param** User $user The user model.

- `ap.cms.users.user_setting.set`: Fires after a user-specific setting has been set and saved.
  - **@param** string $key The setting key.
  - **@param** mixed $value The value that was set.
  - **@param** User $user The user model instance.

- `ap.cms.users.user_setting.deleted`: Fires after a user-specific setting has been deleted and saved.
  - **@param** string $key The setting key that was deleted.
  - **@param** User $user The user model instance.

### Filters

- `ap.cms.users.all`: Filters the collection of all users.
  - **@param** Collection $users Collection of all users.

- `ap.cms.users.find`: Filters a user found by their ID.
  - **@param** User|null $user The User model if found, otherwise null.
  - **@param** int $userId The ID of the user being searched for.

- `ap.cms.roles.all`: Filters the collection of all roles.
  - **@param** Collection $roles Collection of all roles.

- `ap.cms.roles.find`: Filters a role found by its ID.
  - **@param** Role|null $role The Role model if found, otherwise null.
  - **@param** int $roleId The ID of the role being searched for.

- `ap.cms.users.user_can`: Filters whether a user has a specific capability.
  - **@param** bool $hasCapability Whether the user has the capability. Default false.
  - **@param** string $abilities The capability being checked.
  - **@param** User $user The user model instance.

- `ap.cms.users.user_setting.get`: Filters a user-specific setting value retrieved from the user's settings column.
  - **@param** mixed $value The setting value.
  - **@param** string $key The setting key.
  - **@param** User $user The user model instance.
