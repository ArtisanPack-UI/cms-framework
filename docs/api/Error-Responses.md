# Error Responses

The CMS Framework provides a standardized JSON error response format for all API exceptions. Introduced in v1.1.0, every exception that extends `CMSFrameworkException` automatically renders as a consistent JSON payload when thrown during an API request.

## Response Format

All error responses follow this structure:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "A human-readable description of the error."
  }
}
```

- **`code`** -- A machine-readable string identifying the error category.
- **`message`** -- A human-readable description suitable for logging or display.

Some exception types include additional fields. For example, `ValidationException` adds an `errors` object with field-level details.

## Exception Types

### CMSFrameworkException

The base exception class. All other CMS Framework exceptions extend this class.

| Property | Value |
|----------|-------|
| Error Code | `SERVER_ERROR` |
| HTTP Status | `500` |

**Example response:**

```json
{
  "error": {
    "code": "SERVER_ERROR",
    "message": "Something went wrong."
  }
}
```

### NotFoundException

Thrown when a requested resource cannot be found.

| Property | Value |
|----------|-------|
| Error Code | `NOT_FOUND` |
| HTTP Status | `404` |

**Example response:**

```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Model App\\Models\\Post with ID 42 not found."
  }
}
```

The class provides two static constructors for convenience:

```php
// For Eloquent models
throw NotFoundException::model( Post::class, $id );

// For arbitrary resources
throw NotFoundException::resource( 'Page', 'about-us' );
```

### UnauthorizedException

Thrown when the user lacks permission to perform an action.

| Property | Value |
|----------|-------|
| Error Code | `FORBIDDEN` |
| HTTP Status | `403` |

**Example response:**

```json
{
  "error": {
    "code": "FORBIDDEN",
    "message": "You are not authorized to delete posts."
  }
}
```

Static constructors:

```php
throw UnauthorizedException::forAction( 'delete posts' );
throw UnauthorizedException::forResource( 'this post', 'update' );
throw UnauthorizedException::requiresPermission( 'posts.delete' );
```

### ValidationException

Thrown when user input fails validation. Includes an additional `errors` field with per-field error arrays.

| Property | Value |
|----------|-------|
| Error Code | `VALIDATION_ERROR` |
| HTTP Status | `422` |

**Example response:**

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "errors": {
      "title": ["The title field is required."],
      "slug": ["The slug has already been taken."]
    }
  }
}
```

Usage:

```php
throw ValidationException::withErrors(
    'The given data was invalid.',
    [
        'title' => ['The title field is required.'],
        'slug'  => ['The slug has already been taken.'],
    ],
);
```

## How It Works

### The `render()` Method

`CMSFrameworkException` implements Laravel's renderable exception pattern. When the incoming request expects JSON (`$request->expectsJson()`), the exception renders itself as a `JsonResponse` with the appropriate HTTP status code. For non-JSON requests the method returns `null`, allowing Laravel's default exception handler to take over.

```php
public function render( Request $request ): ?JsonResponse
{
    if ( ! $request->expectsJson() ) {
        return null;
    }

    return new JsonResponse(
        $this->buildErrorPayload(),
        $this->statusCode,
    );
}
```

### The `buildErrorPayload()` Method

The base implementation returns the standard `code` and `message` fields. Subclasses override this method to add extra data. For example, `ValidationException` appends the `errors` object:

```php
protected function buildErrorPayload(): array
{
    $payload = parent::buildErrorPayload();

    if ( ! empty( $this->errors ) ) {
        $payload['error']['errors'] = $this->errors;
    }

    return $payload;
}
```

## Creating Custom Exceptions

To create a custom exception that follows the same format:

1. Extend `CMSFrameworkException` (or one of its subclasses).
2. Override `$errorCode` and `$statusCode` as needed.
3. Optionally override `buildErrorPayload()` to include additional fields.

```php
<?php

namespace App\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

class RateLimitException extends CMSFrameworkException
{
    protected string $errorCode = 'RATE_LIMITED';

    protected int $statusCode = 429;

    protected int $retryAfter;

    public static function tooManyRequests( int $retryAfter ): self
    {
        $exception              = new self( 'Too many requests. Please try again later.' );
        $exception->retryAfter  = $retryAfter;

        return $exception;
    }

    protected function buildErrorPayload(): array
    {
        $payload = parent::buildErrorPayload();
        $payload['error']['retry_after'] = $this->retryAfter;

        return $payload;
    }
}
```

This would produce:

```json
{
  "error": {
    "code": "RATE_LIMITED",
    "message": "Too many requests. Please try again later.",
    "retry_after": 60
  }
}
```
