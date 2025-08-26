# ArtisanPack UI CMS Framework - API Examples

This document provides comprehensive examples for using the CMS Framework API with authentication, error handling, and common use cases.

## Table of Contents

1. [Authentication](#authentication)
2. [Content Management](#content-management)
3. [User Management](#user-management)
4. [Media Management](#media-management)
5. [Error Handling](#error-handling)
6. [Rate Limiting](#rate-limiting)
7. [API Versioning](#api-versioning)

---

## Authentication

The CMS Framework uses Laravel Sanctum for API authentication with bearer tokens.

### Login Example

```bash
curl -X POST "http://localhost:8000/api/cms/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "secretpassword123",
    "device_name": "API Client"
  }'
```

**Response:**
```json
{
  "token": "1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz",
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "role_id": 1,
    "first_name": "Admin",
    "last_name": "User"
  },
  "expires_at": null
}
```

### Using Authentication Token

Include the token in the `Authorization` header for all authenticated requests:

```bash
curl -X GET "http://localhost:8000/api/cms/users" \
  -H "Authorization: Bearer 1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz" \
  -H "Content-Type: application/json"
```

### Logout Example

```bash
curl -X POST "http://localhost:8000/api/cms/auth/logout" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json"
```

---

## Content Management

### Create New Content

```bash
curl -X POST "http://localhost:8000/api/cms/content" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Blog Post",
    "content": "This is the content of my first blog post...",
    "excerpt": "A brief summary of the blog post",
    "content_type": "post",
    "status": "published",
    "slug": "my-first-blog-post",
    "meta_title": "My First Blog Post - SEO Optimized",
    "meta_description": "This is an SEO-friendly description",
    "featured_image": "/images/blog/first-post.jpg",
    "author_id": 1,
    "published_at": "2025-08-26T10:00:00Z",
    "terms": [1, 2, 3]
  }'
```

### Get All Content

```bash
curl -X GET "http://localhost:8000/api/cms/content" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json"
```

### Get Specific Content

```bash
curl -X GET "http://localhost:8000/api/cms/content/1" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json"
```

### Update Content

```bash
curl -X PUT "http://localhost:8000/api/cms/content/1" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Blog Post Title",
    "content": "Updated content...",
    "status": "published"
  }'
```

---

## User Management

### Create New User

```bash
curl -X POST "http://localhost:8000/api/cms/users" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "johndoe",
    "email": "john@example.com",
    "password": "securepassword123",
    "first_name": "John",
    "last_name": "Doe",
    "role_id": 2,
    "website": "https://johndoe.com",
    "bio": "Software developer and blogger"
  }'
```

### List All Users

```bash
curl -X GET "http://localhost:8000/api/cms/users" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json"
```

---

## Media Management

### Upload Media File

```bash
curl -X POST "http://localhost:8000/api/cms/media" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -F "file=@/path/to/image.jpg" \
  -F "title=My Image" \
  -F "alt_text=Description of the image" \
  -F "category_id=1" \
  -F "tags[]=nature" \
  -F "tags[]=photography"
```

### Get Media Library

```bash
curl -X GET "http://localhost:8000/api/cms/media" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json"
```

---

## Error Handling

The API returns consistent error responses following RFC 7807 standards.

### Validation Error Example

**Request:**
```bash
curl -X POST "http://localhost:8000/api/cms/users" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "",
    "email": "invalid-email"
  }'
```

**Response (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "username": ["The username field is required."],
    "email": ["The email must be a valid email address."],
    "password": ["The password field is required."]
  }
}
```

### Authentication Error Example

**Response (401):**
```json
{
  "message": "Unauthenticated."
}
```

### Authorization Error Example

**Response (403):**
```json
{
  "message": "This action is unauthorized."
}
```

### Not Found Error Example

**Response (404):**
```json
{
  "message": "No query results for model [ArtisanPackUI\\CMSFramework\\Models\\User] 999"
}
```

---

## Rate Limiting

The API implements different rate limits for different endpoint categories:

### Rate Limit Headers

All responses include rate limiting information:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1693054800
```

### Rate Limit Categories

1. **General API Endpoints**: 60 requests per minute
   - Content CRUD operations
   - Media management
   - Taxonomy operations

2. **Administrative Endpoints**: 30 requests per minute
   - User management
   - Role management
   - Settings management

3. **Upload Endpoints**: 10 requests per minute
   - File uploads
   - Plugin uploads

4. **Authentication Endpoints**: 5 requests per minute
   - Login attempts
   - Token generation

### Rate Limit Exceeded Response

**Response (429):**
```json
{
  "message": "Too Many Attempts."
}
```

---

## API Versioning

### Current Version

The current API version is `v1` and is included in all endpoints:

- Base URL: `http://localhost:8000/api/cms/`
- Version: Implicit v1 (no version prefix required)

### Future Versioning

Future API versions will follow this pattern:
- `http://localhost:8000/api/v2/cms/`
- `http://localhost:8000/api/v3/cms/`

### Version Headers

You can specify the API version using the `Accept` header:

```bash
curl -X GET "http://localhost:8000/api/cms/content" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/vnd.artisanpack.v1+json"
```

---

## SDKs and Tools

### JavaScript/TypeScript SDK Example

```javascript
// Using fetch API
const apiClient = {
  baseURL: 'http://localhost:8000/api/cms',
  token: 'YOUR_TOKEN_HERE',
  
  async request(endpoint, options = {}) {
    const response = await fetch(`${this.baseURL}${endpoint}`, {
      ...options,
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json',
        ...options.headers
      }
    });
    
    if (!response.ok) {
      throw new Error(`API Error: ${response.status}`);
    }
    
    return response.json();
  },
  
  // Content methods
  async getContent() {
    return this.request('/content');
  },
  
  async createContent(data) {
    return this.request('/content', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }
};

// Usage
try {
  const content = await apiClient.getContent();
  console.log(content);
} catch (error) {
  console.error('API Error:', error.message);
}
```

### Python SDK Example

```python
import requests
from typing import Optional, Dict, Any

class CMSApiClient:
    def __init__(self, base_url: str, token: str):
        self.base_url = base_url.rstrip('/')
        self.token = token
        self.headers = {
            'Authorization': f'Bearer {token}',
            'Content-Type': 'application/json'
        }
    
    def request(self, endpoint: str, method: str = 'GET', data: Optional[Dict] = None) -> Dict[str, Any]:
        url = f"{self.base_url}{endpoint}"
        response = requests.request(method, url, headers=self.headers, json=data)
        response.raise_for_status()
        return response.json()
    
    def get_content(self) -> Dict[str, Any]:
        return self.request('/content')
    
    def create_content(self, content_data: Dict) -> Dict[str, Any]:
        return self.request('/content', 'POST', content_data)

# Usage
client = CMSApiClient('http://localhost:8000/api/cms', 'YOUR_TOKEN_HERE')
try:
    content = client.get_content()
    print(content)
except requests.RequestException as e:
    print(f"API Error: {e}")
```

---

## Interactive API Documentation

Access the interactive Swagger UI documentation at:
- **URL**: `http://localhost:8000/api/documentation`
- **Features**: 
  - Try endpoints directly from the browser
  - Authentication testing
  - Request/response examples
  - Schema validation

---

## Support and Resources

- **GitHub Repository**: https://github.com/artisanpack-ui/cms-framework
- **Documentation**: https://docs.artisanpack-ui.com/cms-framework
- **Issues**: https://github.com/artisanpack-ui/cms-framework/issues
- **Community**: https://discord.gg/artisanpack-ui