# API Documentation

The ArtisanPack UI CMS Framework provides a comprehensive REST API for managing content, users, media, and other CMS functionality.

## Authentication

The API uses Laravel Sanctum for authentication. Before making API requests, you need to obtain an authentication token.

### Obtaining an Authentication Token

```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

**Response:**
```json
{
    "token": "1|laravel_sanctum_token_here",
    "user": {
        "id": 1,
        "name": "User Name",
        "email": "user@example.com",
        "role": "editor"
    }
}
```

### Using the Token

Include the token in the `Authorization` header for all subsequent requests:

```http
Authorization: Bearer 1|laravel_sanctum_token_here
```

### Logout

```http
POST /api/auth/logout
Authorization: Bearer {token}
```

## Rate Limiting

API endpoints are rate-limited to prevent abuse:

- **Authenticated requests**: 60 requests per minute
- **Unauthenticated requests**: 10 requests per minute

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests per minute
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Unix timestamp when the rate limit resets

## Content Management

### List Content

Retrieve a paginated list of content items.

```http
GET /api/cms/content
```

**Query Parameters:**
- `type` (string): Filter by content type (e.g., 'post', 'page')
- `status` (string): Filter by status ('published', 'draft', 'trash')
- `author_id` (integer): Filter by author ID
- `search` (string): Search in title and content
- `per_page` (integer): Items per page (default: 15, max: 100)
- `page` (integer): Page number
- `sort` (string): Sort field ('created_at', 'updated_at', 'title')
- `order` (string): Sort order ('asc', 'desc')
- `include` (string): Comma-separated list of relationships ('author', 'taxonomies', 'media')

**Example Request:**
```http
GET /api/cms/content?type=post&status=published&include=author,taxonomies&per_page=10&page=1
```

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "title": "Sample Post",
            "slug": "sample-post",
            "content": "Post content here...",
            "excerpt": "Brief excerpt...",
            "status": "published",
            "type": "post",
            "author_id": 1,
            "created_at": "2023-12-01T10:00:00Z",
            "updated_at": "2023-12-01T10:00:00Z",
            "author": {
                "id": 1,
                "name": "Author Name",
                "email": "author@example.com"
            },
            "taxonomies": [
                {
                    "id": 1,
                    "name": "Technology",
                    "slug": "technology",
                    "type": "category"
                }
            ]
        }
    ],
    "links": {
        "first": "http://example.com/api/cms/content?page=1",
        "last": "http://example.com/api/cms/content?page=3",
        "prev": null,
        "next": "http://example.com/api/cms/content?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 3,
        "per_page": 10,
        "to": 10,
        "total": 25
    }
}
```

### Get Single Content Item

```http
GET /api/cms/content/{id}
```

**Query Parameters:**
- `include` (string): Comma-separated list of relationships

**Response:**
```json
{
    "data": {
        "id": 1,
        "title": "Sample Post",
        "slug": "sample-post",
        "content": "Full post content...",
        "excerpt": "Brief excerpt...",
        "status": "published",
        "type": "post",
        "author_id": 1,
        "meta": {
            "featured": true,
            "custom_field": "custom_value"
        },
        "created_at": "2023-12-01T10:00:00Z",
        "updated_at": "2023-12-01T10:00:00Z"
    }
}
```

### Create Content

```http
POST /api/cms/content
Content-Type: application/json
Authorization: Bearer {token}

{
    "title": "New Post Title",
    "content": "Post content here...",
    "excerpt": "Brief excerpt...",
    "status": "draft",
    "type": "post",
    "meta": {
        "featured": true,
        "custom_field": "value"
    },
    "taxonomies": {
        "category": ["technology", "programming"],
        "tag": ["laravel", "php"]
    }
}
```

**Response:**
```json
{
    "data": {
        "id": 2,
        "title": "New Post Title",
        "slug": "new-post-title",
        "content": "Post content here...",
        "excerpt": "Brief excerpt...",
        "status": "draft",
        "type": "post",
        "author_id": 1,
        "created_at": "2023-12-01T11:00:00Z",
        "updated_at": "2023-12-01T11:00:00Z"
    }
}
```

### Update Content

```http
PUT /api/cms/content/{id}
Content-Type: application/json
Authorization: Bearer {token}

{
    "title": "Updated Title",
    "content": "Updated content...",
    "status": "published"
}
```

### Delete Content

```http
DELETE /api/cms/content/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "message": "Content deleted successfully"
}
```

## Taxonomy Management

### List Taxonomies

```http
GET /api/cms/taxonomies
```

**Query Parameters:**
- `type` (string): Filter by taxonomy type ('category', 'tag')
- `parent_id` (integer): Filter by parent ID
- `search` (string): Search in name and description

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Technology",
            "slug": "technology",
            "description": "Posts about technology",
            "type": "category",
            "parent_id": null,
            "content_count": 15
        }
    ]
}
```

### Create Taxonomy

```http
POST /api/cms/taxonomies
Content-Type: application/json
Authorization: Bearer {token}

{
    "name": "New Category",
    "slug": "new-category",
    "description": "Category description",
    "type": "category",
    "parent_id": null
}
```

### Update Taxonomy

```http
PUT /api/cms/taxonomies/{id}
Authorization: Bearer {token}
```

### Delete Taxonomy

```http
DELETE /api/cms/taxonomies/{id}
Authorization: Bearer {token}
```

## Media Management

### List Media Items

```http
GET /api/cms/media
```

**Query Parameters:**
- `type` (string): Filter by media type ('image', 'video', 'audio', 'document')
- `search` (string): Search in title, alt text, and caption
- `category` (string): Filter by media category
- `tag` (string): Filter by media tag

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "title": "Sample Image",
            "filename": "sample-image.jpg",
            "original_filename": "my-photo.jpg",
            "mime_type": "image/jpeg",
            "size": 1048576,
            "path": "/storage/media/sample-image.jpg",
            "alt_text": "A sample image",
            "caption": "This is a sample image",
            "metadata": {
                "width": 1920,
                "height": 1080,
                "exif": {}
            },
            "urls": {
                "original": "http://example.com/storage/media/sample-image.jpg",
                "thumbnail": "http://example.com/storage/media/thumbnails/sample-image-150x150.jpg",
                "medium": "http://example.com/storage/media/medium/sample-image-300x300.jpg",
                "large": "http://example.com/storage/media/large/sample-image-1024x1024.jpg"
            }
        }
    ]
}
```

### Upload Media

```http
POST /api/cms/media
Content-Type: multipart/form-data
Authorization: Bearer {token}

file: (binary file data)
title: "Image Title"
alt_text: "Alt text for the image"
caption: "Image caption"
categories: "products,featured"
tags: "summer,new-arrival"
```

**Response:**
```json
{
    "data": {
        "id": 2,
        "title": "Image Title",
        "filename": "uploaded-image.jpg",
        "path": "/storage/media/uploaded-image.jpg",
        "alt_text": "Alt text for the image",
        "caption": "Image caption",
        "size": 2048576,
        "mime_type": "image/jpeg",
        "urls": {
            "original": "http://example.com/storage/media/uploaded-image.jpg",
            "thumbnail": "http://example.com/storage/media/thumbnails/uploaded-image-150x150.jpg"
        }
    }
}
```

### Update Media

```http
PUT /api/cms/media/{id}
Content-Type: application/json
Authorization: Bearer {token}

{
    "title": "Updated Title",
    "alt_text": "Updated alt text",
    "caption": "Updated caption"
}
```

### Delete Media

```http
DELETE /api/cms/media/{id}
Authorization: Bearer {token}
```

## User Management

### List Users

```http
GET /api/cms/users
Authorization: Bearer {token}
```

**Query Parameters:**
- `role` (string): Filter by user role
- `search` (string): Search in name and email
- `status` (string): Filter by status ('active', 'inactive')

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "User Name",
            "email": "user@example.com",
            "role": "editor",
            "status": "active",
            "last_login": "2023-12-01T10:00:00Z",
            "created_at": "2023-11-01T10:00:00Z"
        }
    ]
}
```

### Get Single User

```http
GET /api/cms/users/{id}
Authorization: Bearer {token}
```

### Create User

```http
POST /api/cms/users
Content-Type: application/json
Authorization: Bearer {token}

{
    "name": "New User",
    "email": "newuser@example.com",
    "password": "secure-password",
    "role": "author",
    "status": "active"
}
```

### Update User

```http
PUT /api/cms/users/{id}
Authorization: Bearer {token}
```

### Delete User

```http
DELETE /api/cms/users/{id}
Authorization: Bearer {token}
```

## Settings Management

### Get Settings

```http
GET /api/cms/settings
Authorization: Bearer {token}
```

**Query Parameters:**
- `group` (string): Filter by settings group

**Response:**
```json
{
    "data": {
        "site_title": "My CMS Site",
        "posts_per_page": 10,
        "theme_options": {
            "primary_color": "#007cba",
            "secondary_color": "#50575e"
        }
    }
}
```

### Update Settings

```http
PUT /api/cms/settings
Content-Type: application/json
Authorization: Bearer {token}

{
    "site_title": "Updated Site Title",
    "posts_per_page": 15
}
```

### Get Single Setting

```http
GET /api/cms/settings/{key}
Authorization: Bearer {token}
```

### Update Single Setting

```http
PUT /api/cms/settings/{key}
Content-Type: application/json
Authorization: Bearer {token}

{
    "value": "New Setting Value"
}
```

## Dashboard Analytics

### Get Dashboard Stats

```http
GET /api/cms/dashboard/stats
Authorization: Bearer {token}
```

**Response:**
```json
{
    "data": {
        "content": {
            "total": 150,
            "published": 120,
            "draft": 25,
            "trash": 5
        },
        "users": {
            "total": 50,
            "active": 45,
            "inactive": 5
        },
        "media": {
            "total": 300,
            "size_mb": 1250.5
        },
        "recent_activity": [
            {
                "action": "content_created",
                "description": "New post created: Laravel Tips",
                "user": "John Doe",
                "timestamp": "2023-12-01T10:00:00Z"
            }
        ]
    }
}
```

### Get Content Analytics

```http
GET /api/cms/analytics/content
Authorization: Bearer {token}
```

**Query Parameters:**
- `period` (string): Time period ('7d', '30d', '90d', '1y')
- `type` (string): Content type filter

## Search

### Global Search

```http
GET /api/cms/search
```

**Query Parameters:**
- `query` (string): Search query (required)
- `types` (string): Comma-separated content types to search
- `limit` (integer): Maximum results to return

**Response:**
```json
{
    "data": {
        "query": "laravel",
        "results": {
            "content": [
                {
                    "id": 1,
                    "title": "Laravel Tips",
                    "type": "post",
                    "excerpt": "Great tips for Laravel development...",
                    "url": "/posts/laravel-tips"
                }
            ],
            "users": [
                {
                    "id": 2,
                    "name": "Laravel Developer",
                    "email": "dev@laravel.com"
                }
            ],
            "media": [
                {
                    "id": 3,
                    "title": "Laravel Logo",
                    "type": "image",
                    "url": "/storage/media/laravel-logo.png"
                }
            ]
        },
        "total": 3
    }
}
```

## Error Responses

The API uses conventional HTTP response codes and returns JSON error responses:

### Error Response Format

```json
{
    "message": "Error message",
    "errors": {
        "field_name": [
            "Validation error message"
        ]
    }
}
```

### Status Codes

- `200` - OK: Request successful
- `201` - Created: Resource created successfully  
- `400` - Bad Request: Invalid request data
- `401` - Unauthorized: Authentication required
- `403` - Forbidden: Insufficient permissions
- `404` - Not Found: Resource not found
- `422` - Unprocessable Entity: Validation failed
- `429` - Too Many Requests: Rate limit exceeded
- `500` - Internal Server Error: Server error

### Common Validation Errors

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "title": [
            "The title field is required."
        ],
        "email": [
            "The email has already been taken."
        ],
        "password": [
            "The password must be at least 8 characters."
        ]
    }
}
```

## Webhooks

The CMS Framework supports webhooks for real-time notifications of events.

### Configuring Webhooks

```http
POST /api/cms/webhooks
Content-Type: application/json
Authorization: Bearer {token}

{
    "url": "https://your-app.com/webhook",
    "events": ["content.created", "content.updated", "user.created"],
    "secret": "your-webhook-secret"
}
```

### Webhook Events

Available webhook events:
- `content.created` - Content item created
- `content.updated` - Content item updated  
- `content.deleted` - Content item deleted
- `user.created` - User created
- `user.updated` - User updated
- `media.uploaded` - Media file uploaded
- `settings.updated` - Settings changed

### Webhook Payload

```json
{
    "event": "content.created",
    "data": {
        "id": 1,
        "title": "New Post",
        "type": "post",
        "author_id": 1
    },
    "timestamp": "2023-12-01T10:00:00Z"
}
```

## SDK Examples

### JavaScript/Node.js

```javascript
const axios = require('axios');

class CMSClient {
    constructor(baseURL, token) {
        this.client = axios.create({
            baseURL: baseURL,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
    }

    async getContent(params = {}) {
        const response = await this.client.get('/api/cms/content', { params });
        return response.data;
    }

    async createContent(data) {
        const response = await this.client.post('/api/cms/content', data);
        return response.data;
    }

    async uploadMedia(file, metadata = {}) {
        const formData = new FormData();
        formData.append('file', file);
        
        Object.keys(metadata).forEach(key => {
            formData.append(key, metadata[key]);
        });

        const response = await this.client.post('/api/cms/media', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        return response.data;
    }
}

// Usage
const cms = new CMSClient('https://your-cms.com', 'your-token');
const posts = await cms.getContent({ type: 'post', status: 'published' });
```

### PHP/Laravel

```php
use Illuminate\Support\Facades\Http;

class CMSClient
{
    protected $baseUrl;
    protected $token;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    public function getContent(array $params = []): array
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/api/cms/content", $params);

        return $response->json();
    }

    public function createContent(array $data): array
    {
        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/cms/content", $data);

        return $response->json();
    }

    public function uploadMedia(UploadedFile $file, array $metadata = []): array
    {
        $response = Http::withToken($this->token)
            ->attach('file', file_get_contents($file), $file->getClientOriginalName())
            ->post("{$this->baseUrl}/api/cms/media", $metadata);

        return $response->json();
    }
}

// Usage
$cms = new CMSClient('https://your-cms.com', 'your-token');
$posts = $cms->getContent(['type' => 'post', 'status' => 'published']);
```

## Next Steps

- [Usage Guide](usage.md) - Learn how to use CMS features
- [Configuration Guide](configuration.md) - Configure API settings
- [Testing Guide](testing.md) - Test API integrations