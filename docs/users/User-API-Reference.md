---
title: User API Reference
---

# User API Reference

Complete API reference for the CMS Framework user management endpoints. All endpoints are prefixed with `/api/v1` and return JSON responses.

## Authentication

The API endpoints do not include authentication by default. You should implement authentication middleware appropriate for your application:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

## Base URL

All API requests should be made to:
```
https://your-app.test/api/v1
```

## Response Format

All responses follow a consistent format using Laravel's API Resource pattern:

### Success Response
```json
{
  "data": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com"
  },
  "meta": {
    "current_page": 1,
    "total": 10
  },
  "links": {
    "first": "http://example.com/api/v1/users?page=1",
    "last": "http://example.com/api/v1/users?page=2"
  }
}
```

### Error Response
```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

## Endpoints

### List Users

Retrieve a paginated list of users with their assigned roles.

#### Request
```http
GET /api/v1/users
```

#### Query Parameters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Page number for pagination |
| `per_page` | integer | 15 | Number of users per page (max 100) |

#### Response
**Status:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "roles": [
        {
          "id": 1,
          "name": "Administrator",
          "slug": "admin"
        }
      ]
    },
    {
      "id": 2,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "roles": []
    }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "per_page": 15,
    "to": 15,
    "total": 42
  },
  "links": {
    "first": "http://your-app.test/api/v1/users?page=1",
    "last": "http://your-app.test/api/v1/users?page=3",
    "prev": null,
    "next": "http://your-app.test/api/v1/users?page=2"
  }
}
```

#### Example Request
```bash
curl -X GET \
  'http://your-app.test/api/v1/users?page=1&per_page=10' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json'
```

---

### Create User

Create a new user in the system.

#### Request
```http
POST /api/v1/users
```

#### Request Body
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secure-password-123"
}
```

#### Validation Rules
| Field | Rules |
|-------|--------|
| `name` | required, string, max:255 |
| `email` | required, email, max:255, unique:users |
| `password` | required, string, min:8 |

#### Response
**Status:** `201 Created`

```json
{
  "data": {
    "id": 3,
    "name": "John Doe",
    "email": "john@example.com",
    "roles": []
  }
}
```

#### Error Response
**Status:** `422 Unprocessable Entity`

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email has already been taken."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

#### Example Request
```bash
curl -X POST \
  http://your-app.test/api/v1/users \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secure-password-123"
  }'
```

---

### Get User

Retrieve a specific user by ID with their assigned roles.

#### Request
```http
GET /api/v1/users/{id}
```

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | User ID |

#### Response
**Status:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "roles": [
      {
        "id": 1,
        "name": "Administrator",
        "slug": "admin"
      },
      {
        "id": 2,
        "name": "Content Editor",
        "slug": "editor"
      }
    ]
  }
}
```

#### Error Response
**Status:** `404 Not Found`

```json
{
  "message": "No query results for model [App\\Models\\User] 999"
}
```

#### Example Request
```bash
curl -X GET \
  http://your-app.test/api/v1/users/1 \
  -H 'Accept: application/json'
```

---

### Update User

Update an existing user. All fields are optional (partial updates supported).

#### Request
```http
PUT /api/v1/users/{id}
PATCH /api/v1/users/{id}
```

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | User ID |

#### Request Body
```json
{
  "name": "Updated Name",
  "email": "newemail@example.com",
  "password": "new-secure-password"
}
```

#### Validation Rules
| Field | Rules |
|-------|--------|
| `name` | sometimes, required, string, max:255 |
| `email` | sometimes, required, email, max:255, unique:users,email,{id} |
| `password` | sometimes, required, string, min:8 |

#### Response
**Status:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "name": "Updated Name",
    "email": "newemail@example.com",
    "roles": [
      {
        "id": 1,
        "name": "Administrator",
        "slug": "admin"
      }
    ]
  }
}
```

#### Error Response
**Status:** `422 Unprocessable Entity`

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

#### Example Request
```bash
curl -X PATCH \
  http://your-app.test/api/v1/users/1 \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "Updated Name"
  }'
```

---

### Delete User

Delete a user from the system.

#### Request
```http
DELETE /api/v1/users/{id}
```

#### Path Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | User ID |

#### Response
**Status:** `204 No Content`

No response body is returned for successful deletions.

#### Error Response
**Status:** `404 Not Found`

```json
{
  "message": "No query results for model [App\\Models\\User] 999"
}
```

#### Example Request
```bash
curl -X DELETE \
  http://your-app.test/api/v1/users/1 \
  -H 'Accept: application/json'
```

## Response Status Codes

| Status Code | Description |
|------------|-------------|
| `200 OK` | Request successful |
| `201 Created` | Resource created successfully |
| `204 No Content` | Resource deleted successfully |
| `400 Bad Request` | Invalid request format |
| `401 Unauthorized` | Authentication required |
| `403 Forbidden` | Access denied |
| `404 Not Found` | Resource not found |
| `422 Unprocessable Entity` | Validation errors |
| `500 Internal Server Error` | Server error |

## Rate Limiting

Consider implementing rate limiting for your API endpoints:

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

## Error Handling

### Validation Errors
Validation errors return a `422 Unprocessable Entity` status with detailed error messages:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required.",
      "The email must be a valid email address."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

### Not Found Errors
When a requested resource doesn't exist:

```json
{
  "message": "No query results for model [App\\Models\\User] 123"
}
```

### Server Errors
Internal server errors return a `500 Internal Server Error` status:

```json
{
  "message": "Server Error"
}
```

## Examples

### Complete User Management Flow

#### 1. Create a new user
```bash
curl -X POST http://your-app.test/api/v1/users \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "password": "secure-password-123"
  }'
```

#### 2. Get the created user
```bash
curl -X GET http://your-app.test/api/v1/users/3
```

#### 3. Update the user
```bash
curl -X PATCH http://your-app.test/api/v1/users/3 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Alice Johnson-Smith"
  }'
```

#### 4. List all users
```bash
curl -X GET "http://your-app.test/api/v1/users?page=1&per_page=10"
```

#### 5. Delete the user
```bash
curl -X DELETE http://your-app.test/api/v1/users/3
```

## Integration with Roles

While the User API endpoints manage basic user data, role assignments are typically handled through the user relationships. Here are some examples:

### Assign Role to User (Programmatic)
```php
$user = User::find(1);
$adminRole = Role::where('slug', 'admin')->first();
$user->roles()->attach($adminRole->id);
```

### Check User Roles via API Response
The API automatically includes role information in user responses:

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "roles": [
      {
        "id": 1,
        "name": "Administrator", 
        "slug": "admin"
      }
    ]
  }
}
```

## Testing the API

### Using Postman

1. **Base URL**: `http://your-app.test/api/v1`
2. **Headers**: 
   - `Accept: application/json`
   - `Content-Type: application/json`
3. **Authentication**: Add appropriate headers for your auth system

### Using PHPUnit

```php
public function test_can_create_user()
{
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123'
    ];
    
    $response = $this->postJson('/api/v1/users', $userData);
    
    $response->assertStatus(201)
             ->assertJsonStructure([
                 'data' => [
                     'id',
                     'name',
                     'email',
                     'roles'
                 ]
             ]);
}
```

### Using HTTP Client Libraries

#### JavaScript (Axios)
```javascript
// Create user
const response = await axios.post('/api/v1/users', {
  name: 'John Doe',
  email: 'john@example.com',
  password: 'secure-password'
});

// Get users
const users = await axios.get('/api/v1/users?page=1');
```

#### PHP (Guzzle)
```php
$client = new \GuzzleHttp\Client([
    'base_uri' => 'http://your-app.test/api/v1/',
]);

$response = $client->post('users', [
    'json' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secure-password'
    ]
]);
```

## Security Considerations

### Input Validation
- All input is validated according to the rules specified
- Email addresses are validated for format and uniqueness
- Passwords are automatically encrypted using `bcrypt()`

### Authentication
- Implement appropriate authentication middleware
- Consider using Laravel Sanctum for API token authentication
- Use HTTPS in production environments

### Rate Limiting
- Implement rate limiting to prevent abuse
- Consider different limits for different endpoints
- Monitor API usage patterns

### Data Exposure
- Sensitive fields like passwords are automatically hidden
- The UserResource controls which data is exposed
- Consider additional filtering for sensitive user data

## Related Documentation

- [[User Management]] - User management concepts and examples
- [[Roles and Permissions]] - Role-based access control system
- [[Configuration]] - API configuration options
- [[Developer Guide]] - Extending the API functionality

---

*For implementation examples, see [[User Management]] and [[Quick Start]].*
