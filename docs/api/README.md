# CMS Framework API Documentation

This directory contains API documentation for all modules in the CMS Framework.

## Overview

The CMS Framework provides a comprehensive REST API for managing content, users, settings, and more. All API endpoints require authentication using Laravel Sanctum.

## Base URL

```
/api/cms
```

## Authentication

All API requests must include a valid Sanctum token in the Authorization header:

```http
Authorization: Bearer YOUR_TOKEN_HERE
```

### Obtaining a Token

```php
$user = User::find(1);
$token = $user->createToken('token-name')->plainTextToken;
```

## Response Format

### Success Response

```json
{
  "data": {
    // Resource data
  }
}
```

### Error Response

```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

## HTTP Status Codes

- `200 OK` - Request succeeded
- `201 Created` - Resource created successfully
- `204 No Content` - Request succeeded with no response body
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Authentication required or failed
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation failed
- `500 Internal Server Error` - Server error

## API Modules

### Core Modules

- **[Users API](users.md)** - User management endpoints
- **[Roles API](roles.md)** - Role and permission management
- **[Settings API](settings.md)** - Application settings management
- **[Notifications API](notifications.md)** - Notification system endpoints

### Content Modules

- **[Content Types API](content-types.md)** - Custom content type management
- **[Blog API](blog.md)** - Blog posts, categories, and tags
- **[Pages API](pages.md)** - Page management

### Extension Modules

- **[Plugins API](plugins.md)** - Plugin lifecycle management (Experimental)
- **[Themes API](themes.md)** - Theme management (Experimental)
- **[Core Updates API](core-updates.md)** - System update management

## Common Patterns

### Pagination

All list endpoints support pagination:

```http
GET /api/cms/posts?page=2&per_page=20
```

Response includes pagination meta:

```json
{
  "data": [...],
  "links": {
    "first": "...",
    "last": "...",
    "prev": "...",
    "next": "..."
  },
  "meta": {
    "current_page": 2,
    "from": 21,
    "last_page": 5,
    "per_page": 20,
    "to": 40,
    "total": 100
  }
}
```

### Filtering

Many endpoints support filtering:

```http
GET /api/cms/posts?status=published&category=news
```

### Sorting

Sort results using the `sort` parameter:

```http
GET /api/cms/posts?sort=-created_at,title
```

Use `-` prefix for descending order.

### Including Relationships

Load related data using the `include` parameter:

```http
GET /api/cms/posts?include=author,categories,tags
```

## Rate Limiting

API requests are rate limited to prevent abuse:

- **Authenticated requests**: 60 requests per minute
- **Unauthenticated requests**: 10 requests per minute

Rate limit headers are included in responses:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 30
```

## Versioning

The API is currently at version 1.0. Future versions will be accessible via:

```
/api/v2/cms/...
```

Version 1 will remain available at the base `/api/cms/` path.

## Examples

### Creating a Post

```bash
curl -X POST https://example.com/api/cms/posts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Post",
    "content": "Post content here",
    "status": "published"
  }'
```

### Updating a User

```bash
curl -X PUT https://example.com/api/cms/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name",
    "email": "newemail@example.com"
  }'
```

### Deleting a Resource

```bash
curl -X DELETE https://example.com/api/cms/posts/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Testing the API

### Using Postman

1. Import the [Postman Collection](../postman/cms-framework.json)
2. Set up environment variables:
   - `base_url`: Your application URL
   - `token`: Your Sanctum token

### Using cURL

See individual endpoint documentation for cURL examples.

### Using PHPUnit

```php
use Tests\TestCase;

class PostApiTest extends TestCase
{
    public function test_can_create_post()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/cms/posts', [
                'title' => 'Test Post',
                'content' => 'Test content',
            ]);

        $response->assertStatus(201);
    }
}
```

## Error Handling

### Validation Errors

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "email": ["The email must be a valid email address."]
  }
}
```

### Authorization Errors

```json
{
  "message": "This action is unauthorized."
}
```

### Not Found Errors

```json
{
  "message": "Model User with ID 999 not found."
}
```

## Security

### CSRF Protection

API routes are exempt from CSRF protection when using token authentication.

### Input Sanitization

All input is automatically sanitized using the ArtisanPackUI Security package.

### Authorization

All endpoints enforce authorization using Laravel policies.

## Support

For issues or questions:

- **GitHub Issues**: https://github.com/artisanpack-ui/cms-framework/issues
- **Documentation**: https://artisanpack.dev/packages/cms-framework
- **Email**: support@artisanpack.com

## Changelog

See [CHANGELOG.md](../../CHANGELOG.md) for API changes and version history.
