---
title: API Authentication with Laravel Sanctum
---

# API Authentication with Laravel Sanctum

The ArtisanPack UI CMS Framework uses Laravel Sanctum for API authentication. This document provides detailed information about how to authenticate with the API using Sanctum.

## Overview

Laravel Sanctum provides a simple way to authenticate single-page applications (SPAs), mobile applications, and simple token-based APIs. The framework includes pre-configured routes, controllers, and policies that work with Sanctum to provide secure API endpoints.

## Authentication Methods

Sanctum offers two primary methods of authentication:

1. **SPA Authentication**: For single-page applications that need to authenticate with a Laravel backend.
2. **API Token Authentication**: For third-party services and mobile applications that need to authenticate with the API.

The ArtisanPack UI CMS Framework primarily uses API Token Authentication.

## API Token Authentication

### Creating API Tokens

To create an API token, you can use the `createToken` method on the User model:

```php
$token = $user->createToken('token-name')->plainTextToken;
```

The `createToken` method accepts a token name as its first argument, which you can use to identify the purpose of the token. The method returns a `NewAccessToken` instance, which has a `plainTextToken` property that you can use to access the token.

### Using API Tokens

To authenticate API requests, you need to include the token in the `Authorization` header of your HTTP request:

```
Authorization: Bearer {your-token}
```

### Token Abilities

When creating a token, you can specify the abilities (or scopes) that the token should have:

```php
$token = $user->createToken('token-name', ['cms:read'])->plainTextToken;
```

The ArtisanPack UI CMS Framework uses the following abilities:

- `cms:read`: Allows reading data from the CMS
- `cms:write`: Allows writing data to the CMS

### Checking Token Abilities

In your controllers or policies, you can check if a token has a specific ability using the `tokenCan` method:

```php
if ($user->tokenCan('cms:read')) {
    // The token has the 'cms:read' ability
}
```

## Policies

The framework includes policies for all models that check if the authenticated user has the necessary abilities and role capabilities to perform the requested action.

For example, the `SettingPolicy` checks if the user has the `cms:read` ability and the appropriate role capability:

```php
public function viewAny(User $user): bool
{
    return $user->tokenCan('cms:read') && $user->role && in_array('viewAny_settings', $user->role->capabilities ?? []);
}
```

## API Endpoints

All API endpoints in the framework are protected by Sanctum authentication. The endpoints are grouped under the `/api/cms` prefix.

### Available Endpoints

- **Users**: `/api/cms/users`
- **Roles**: `/api/cms/roles`
- **Settings**: `/api/cms/settings`

## Testing with Sanctum

When testing API endpoints that are protected by Sanctum, you can use the `Sanctum::actingAs` method to authenticate as a specific user:

```php
use Laravel\Sanctum\Sanctum;

// Authenticate as a user with specific abilities
Sanctum::actingAs($user, ['cms:read']);

// Make a request to a protected endpoint
$response = $this->getJson('/api/cms/settings');
```

## Configuration

The framework includes a pre-configured Sanctum setup. If you need to customize the Sanctum configuration, you can publish the Sanctum configuration file:

```bash
php artisan vendor:publish --tag=sanctum-config
```

This will create a `sanctum.php` configuration file in your application's `config` directory.

## Troubleshooting

### Token Not Working

If your token is not working, check the following:

1. Make sure you're including the token in the `Authorization` header with the `Bearer` prefix.
2. Check that the token has the necessary abilities for the action you're trying to perform.
3. Verify that the user associated with the token has the required role capabilities.

### CSRF Protection

By default, Sanctum's CSRF protection is enabled for SPA authentication. If you're using API token authentication, CSRF protection is not required.

### Stateful Domains

If you're using SPA authentication, make sure your domain is listed in the `stateful` array in the Sanctum configuration.
