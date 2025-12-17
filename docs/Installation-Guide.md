---
title: Installation Guide
---

# Installation Guide

This guide will walk you through installing and setting up the CMS Framework in your Laravel application.

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- MySQL 8.0+ or PostgreSQL 10.0+

## Installation Steps

### 1. Install via Composer

```bash
composer require artisanpack-ui/cms-framework
```

### 2. Publish Configuration

Publish the configuration file to customize the framework settings:

```bash
php artisan vendor:publish --provider="ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider" --tag="config"
```

This will create a `config/cms-framework.php` file in your application.

### 3. Run Migrations

The package includes migrations for roles, permissions, and pivot tables:

```bash
php artisan migrate
```

This will create the following tables:
- `roles` - Store user roles
- `permissions` - Store individual permissions
- `permission_role` - Many-to-many relationship between permissions and roles
- `role_user` - Many-to-many relationship between roles and users

### 4. Configure Your User Model

Add the `HasRolesAndPermissions` trait to your User model:

```php
<?php

namespace App\Models;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
    
    // Your existing User model code...
}
```

### 5. Update Configuration

Edit the `config/cms-framework.php` file to point to your User model:

```php
<?php
return [
    'user_model' => \App\Models\User::class,
];
```

### 6. Register API Routes

The package automatically registers API routes under the `/api/v1` prefix. If you need to customize this, you can disable auto-discovery and manually register routes.

## Verification

To verify the installation was successful:

1. Check that the tables were created:
```bash
php artisan tinker
>>> \Schema::hasTable('roles')
=> true
>>> \Schema::hasTable('permissions')
=> true
```

2. Test the API endpoints:
```bash
# List users (should return empty pagination)
curl -X GET http://your-app.test/api/v1/users
```

## Next Steps

- [[Configuration]] - Learn about configuration options
- [[Quick Start]] - Create your first user and role
- [[User Management]] - Understand user management features

## Troubleshooting

### Common Issues

**Migration Errors**
If you encounter migration errors, ensure your database connection is properly configured and the database exists.

**Route Conflicts**
If you have existing `/api/v1/users` routes, you may need to customize the route prefix or disable auto-registration.

**User Model Issues**
Make sure your User model includes the `HasRolesAndPermissions` trait and is properly configured in the config file.

## Manual Installation

If you prefer to manually register the service provider:

```php
// config/app.php
'providers' => [
    // Other providers...
    ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider::class,
],
```

---

*Need help? Check the [[Developer Guide]] for advanced configuration options.*
