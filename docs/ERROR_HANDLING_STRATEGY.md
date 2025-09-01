# CMS Framework - Comprehensive Error Handling & Logging Strategy

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Exception Hierarchy](#exception-hierarchy)
4. [Services](#services)
5. [Configuration](#configuration)
6. [Artisan Commands](#artisan-commands)
7. [Middleware](#middleware)
8. [Usage Examples](#usage-examples)
9. [Testing](#testing)
10. [Best Practices](#best-practices)
11. [Troubleshooting](#troubleshooting)

## Overview

The CMS Framework implements a comprehensive error handling and logging strategy designed to provide robust error management, detailed logging, audit trails, and recovery mechanisms for Laravel applications. The system is built with scalability, security, and developer experience in mind.

### Key Features

- **Structured Exception Hierarchy**: Domain-specific exceptions with rich context
- **Multi-Channel Logging**: Separate logs for errors, audit trails, and security events
- **Error Tracking Integration**: Support for Sentry, Bugsnag, Rollbar, and custom providers
- **Automatic Recovery**: Built-in error recovery mechanisms
- **Performance Monitoring**: Track error handling performance
- **Security-First**: Data sanitization and secure logging practices
- **Developer Tools**: Comprehensive Artisan commands for log management and analysis

## Architecture

The error handling system is built around several core components:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Exceptions    │    │    Services      │    │   Middleware    │
│                 │    │                  │    │                 │
│ • CMSException  │───▶│ • ErrorTracking  │◀───│ • ErrorHandling │
│ • AuthException │    │ • StructuredLog  │    │                 │
│ • ContentExc... │    │ • AuditLogger    │    └─────────────────┘
│ • MediaExc...   │    │                  │              │
│ • PluginExc...  │    └──────────────────┘              │
│ • UserExc...    │             │                        │
└─────────────────┘             ▼                        ▼
         │              ┌──────────────────┐    ┌─────────────────┐
         │              │  Configuration   │    │    Commands     │
         │              │                  │    │                 │
         └─────────────▶│ • Tracking       │    │ • LogView       │
                        │ • Logging        │    │ • LogAnalysis   │
                        │ • Audit          │    │ • LogCleanup    │
                        │ • Recovery       │    │ • ErrorTesting  │
                        │ • Notifications  │    │                 │
                        └──────────────────┘    └─────────────────┘
```

## Exception Hierarchy

### Base Exception: `CMSException`

All CMS-specific exceptions extend from the base `CMSException` class, which provides:

- Rich context support
- Custom error response rendering
- Integration with tracking services
- Structured logging support

```php
use ArtisanPackUI\CMSFramework\Exceptions\CMSException;

throw new CMSException(
    message: 'Something went wrong',
    code: 500,
    previous: null,
    context: ['user_id' => auth()->id(), 'action' => 'update_content']
);
```

### Specialized Exceptions

#### AuthorizationException
For permission and access control errors:

```php
use ArtisanPackUI\CMSFramework\Exceptions\AuthorizationException;

throw new AuthorizationException(
    message: 'Access denied to resource',
    permission: 'content.edit',
    code: 403,
    context: ['resource_id' => $contentId, 'user_roles' => $userRoles]
);
```

#### ContentException
For content management errors:

```php
use ArtisanPackUI\CMSFramework\Exceptions\ContentException;

throw new ContentException(
    message: 'Content validation failed',
    contentId: $content->id,
    errorType: 'validation_failed',
    code: 422,
    context: ['validation_errors' => $validator->errors()]
);
```

#### MediaException
For file and media handling errors:

```php
use ArtisanPackUI\CMSFramework\Exceptions\MediaException;

throw new MediaException(
    message: 'File upload failed',
    fileName: $file->getClientOriginalName(),
    errorType: 'upload_failed',
    code: 422,
    context: ['file_size' => $file->getSize(), 'max_size' => $maxSize]
);
```

#### PluginException
For plugin system errors:

```php
use ArtisanPackUI\CMSFramework\Exceptions\PluginException;

throw new PluginException(
    message: 'Plugin activation failed',
    pluginName: $pluginName,
    errorType: 'activation_failed',
    code: 500,
    context: ['dependencies' => $missingDeps]
);
```

#### UserException
For user management errors:

```php
use ArtisanPackUI\CMSFramework\Exceptions\UserException;

throw new UserException(
    message: 'User registration failed',
    action: 'registration',
    code: 400,
    context: ['validation_errors' => $errors]
);
```

## Services

### ErrorTrackingService

Handles error tracking with external services and internal logging:

```php
use ArtisanPackUI\CMSFramework\Services\ErrorTrackingService;

$errorTracker = app(ErrorTrackingService::class);

// Track an error
$errorTracker->trackError($exception, $context);

// Get tracked error details
$errorId = $errorTracker->getLastTrackedErrorId();
$errorDetails = $errorTracker->getTrackedError($errorId);

// Attempt recovery
$errorTracker->attemptRecovery($exception, $context);
```

### StructuredLoggerService

Provides structured logging with consistent formatting:

```php
use ArtisanPackUI\CMSFramework\Services\StructuredLoggerService;

$logger = app(StructuredLoggerService::class);

// Log an error with structured context
$logger->logError($exception, $context, 'error');

// Log custom structured data
$logger->logStructured('info', 'User action performed', [
    'user_id' => auth()->id(),
    'action' => 'content_published',
    'content_id' => $content->id,
]);
```

### AuditLoggerService

Handles audit logging for compliance and security:

```php
use ArtisanPackUI\CMSFramework\Services\AuditLoggerService;

$auditLogger = app(AuditLoggerService::class);

// Log error handling event
$auditLogger->logErrorHandling([
    'event' => 'error_occurred',
    'user_id' => auth()->id(),
    'error_type' => 'authorization_exception',
    'severity' => 'warning',
    'handled' => true,
    'context' => $context,
]);
```

## Configuration

The error handling system is configured via `config/cms-error-handling.php`. Key configuration sections:

### Error Tracking

```php
'tracking' => [
    'enabled' => env('CMS_ERROR_TRACKING_ENABLED', true),
    'provider' => env('CMS_ERROR_TRACKING_PROVIDER', 'sentry'),
    'providers' => [
        'sentry' => [
            'dsn' => env('SENTRY_LARAVEL_DSN'),
            'sample_rate' => 1.0,
        ],
    ],
],
```

### Logging Channels

```php
'logging' => [
    'channels' => [
        'cms' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cms/cms.log'),
            'level' => 'debug',
            'days' => 14,
        ],
        'errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cms/errors.log'),
            'level' => 'error',
            'days' => 30,
        ],
        'audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cms/audit.log'),
            'level' => 'info',
            'days' => 90,
        ],
    ],
],
```

### Security Settings

```php
'security' => [
    'sanitize_data' => true,
    'sensitive_fields' => [
        'password', 'token', 'api_key', 'secret',
    ],
    'separate_security_logs' => true,
],
```

## Artisan Commands

### View Error Logs

```bash
# View recent error logs
php artisan cms:error-logs:view

# View specific number of lines
php artisan cms:error-logs:view --lines=50

# Filter by log level
php artisan cms:error-logs:view --level=error

# Follow logs in real-time
php artisan cms:error-logs:view --follow

# Search for specific content
php artisan cms:error-logs:view --search="user_id:123"
```

### Analyze Error Logs

```bash
# Analyze errors from last hour
php artisan cms:error-logs:analyze --period=1h

# Generate detailed report
php artisan cms:error-logs:analyze --detailed

# Export analysis to file
php artisan cms:error-logs:analyze --export=json --output=/path/to/report.json

# Analyze specific error types
php artisan cms:error-logs:analyze --type=cms_exception
```

### Clean Up Logs

```bash
# Clean up old logs (default: 30 days)
php artisan cms:error-logs:cleanup

# Specify retention period
php artisan cms:error-logs:cleanup --days=7

# Compress instead of delete
php artisan cms:error-logs:cleanup --compress

# Dry run to see what would be cleaned
php artisan cms:error-logs:cleanup --dry-run
```

### Test Error Handling

```bash
# Test all error types
php artisan cms:error-logs:test

# Test specific error type
php artisan cms:error-logs:test --type=auth

# Generate multiple test errors
php artisan cms:error-logs:test --count=5

# Test with recovery mechanisms
php artisan cms:error-logs:test --test-recovery

# Verify logs after testing
php artisan cms:error-logs:test --verify-logs
```

## Middleware

The `ErrorHandlingMiddleware` automatically catches and processes exceptions:

### Registration

Add to your middleware stack in `bootstrap/app.php`:

```php
use ArtisanPackUI\CMSFramework\Http\Middleware\ErrorHandlingMiddleware;

$app->middleware([
    ErrorHandlingMiddleware::class,
]);
```

### Functionality

The middleware automatically:
- Captures exceptions
- Logs them through the structured logger
- Tracks them via the error tracking service
- Creates audit log entries
- Attempts recovery when appropriate
- Returns appropriate error responses

## Usage Examples

### Basic Error Handling in Controllers

```php
<?php

namespace App\Http\Controllers;

use ArtisanPackUI\CMSFramework\Exceptions\ContentException;
use ArtisanPackUI\CMSFramework\Services\StructuredLoggerService;

class ContentController extends Controller
{
    public function update(Request $request, Content $content)
    {
        try {
            // Validate and update content
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'body' => 'required|string',
            ]);
            
            $content->update($validated);
            
            return response()->json(['success' => true]);
            
        } catch (ValidationException $e) {
            // Throw domain-specific exception with context
            throw new ContentException(
                message: 'Content validation failed',
                contentId: $content->id,
                errorType: 'validation_failed',
                code: 422,
                context: [
                    'user_id' => auth()->id(),
                    'validation_errors' => $e->errors(),
                    'request_data' => $request->except(['password', 'token']),
                ]
            );
        }
    }
}
```

### Custom Error Handling in Services

```php
<?php

namespace App\Services;

use ArtisanPackUI\CMSFramework\Exceptions\MediaException;
use ArtisanPackUI\CMSFramework\Services\AuditLoggerService;

class MediaService
{
    public function __construct(
        private AuditLoggerService $auditLogger
    ) {}

    public function uploadFile(UploadedFile $file): Media
    {
        try {
            // Validate file
            if ($file->getSize() > config('media.max_file_size')) {
                throw new MediaException(
                    message: 'File size exceeds limit',
                    fileName: $file->getClientOriginalName(),
                    errorType: 'file_too_large',
                    code: 422,
                    context: [
                        'file_size' => $file->getSize(),
                        'max_size' => config('media.max_file_size'),
                        'user_id' => auth()->id(),
                    ]
                );
            }
            
            // Process upload
            $media = Media::create([
                'filename' => $file->store('uploads'),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);
            
            // Log successful upload
            $this->auditLogger->logEvent([
                'event' => 'media_uploaded',
                'user_id' => auth()->id(),
                'media_id' => $media->id,
                'filename' => $media->original_name,
            ]);
            
            return $media;
            
        } catch (Exception $e) {
            // Log upload failure
            $this->auditLogger->logEvent([
                'event' => 'media_upload_failed',
                'user_id' => auth()->id(),
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}
```

## Testing

### Running Error Handling Tests

```bash
# Run all error handling tests
php artisan test --filter=ErrorHandling

# Run specific test
php artisan test --filter=test_cms_exception_handling

# Run with coverage
php artisan test --filter=ErrorHandling --coverage
```

### Writing Custom Tests

```php
<?php

namespace Tests\Feature;

use ArtisanPackUI\CMSFramework\Exceptions\CMSException;
use ArtisanPackUI\CMSFramework\Services\ErrorTrackingService;

class CustomErrorHandlingTest extends TestCase
{
    public function test_custom_error_scenario(): void
    {
        // Arrange
        $service = app(ErrorTrackingService::class);
        $exception = new CMSException('Test error');
        
        // Act
        $result = $service->trackError($exception, ['test' => true]);
        
        // Assert
        $this->assertTrue($result);
        $this->assertNotNull($service->getLastTrackedErrorId());
    }
}
```

## Best Practices

### 1. Use Domain-Specific Exceptions

Always use the appropriate domain-specific exception rather than generic exceptions:

```php
// ❌ Don't do this
throw new Exception('User not found');

// ✅ Do this
throw new UserException(
    message: 'User not found',
    action: 'user_lookup',
    code: 404,
    context: ['user_id' => $userId]
);
```

### 2. Provide Rich Context

Include relevant context information with exceptions:

```php
throw new ContentException(
    message: 'Content publication failed',
    contentId: $content->id,
    errorType: 'publication_failed',
    context: [
        'user_id' => auth()->id(),
        'content_status' => $content->status,
        'publication_date' => $content->published_at,
        'user_permissions' => auth()->user()->permissions,
    ]
);
```

### 3. Sanitize Sensitive Data

Never log sensitive information:

```php
// ❌ Don't include passwords or tokens
$context = [
    'user_data' => $request->all(), // May contain passwords
];

// ✅ Exclude sensitive fields
$context = [
    'user_data' => $request->except(['password', 'password_confirmation', 'token']),
];
```

### 4. Use Structured Logging

Log data in a structured format for better analysis:

```php
$logger->logStructured('info', 'User action', [
    'event' => 'content_created',
    'user_id' => auth()->id(),
    'content_id' => $content->id,
    'content_type' => $content->type,
    'timestamp' => now()->toISOString(),
]);
```

### 5. Monitor Performance

Keep error handling lightweight:

```php
// ❌ Avoid heavy operations in error handling
catch (Exception $e) {
    $this->heavyDatabaseOperation(); // Don't do this
    throw new CMSException($e->getMessage());
}

// ✅ Keep it simple
catch (Exception $e) {
    throw new CMSException(
        message: $e->getMessage(),
        context: ['original_error' => $e->getFile() . ':' . $e->getLine()]
    );
}
```

## Troubleshooting

### Common Issues

#### 1. Logs Not Writing

**Problem**: Error logs are not being written to files.

**Solutions**:
- Check file permissions on log directories
- Verify log channel configuration
- Ensure storage/logs directory exists
- Check disk space

```bash
# Check permissions
ls -la storage/logs/

# Create directories if missing
mkdir -p storage/logs/cms

# Set correct permissions
chmod -R 755 storage/logs/
```

#### 2. Error Tracking Not Working

**Problem**: Errors are not being sent to external tracking services.

**Solutions**:
- Verify API keys and configuration
- Check network connectivity
- Review rate limiting settings
- Ensure tracking is enabled

```php
// Check configuration
php artisan config:show cms-error-handling.tracking
```

#### 3. Performance Issues

**Problem**: Error handling is causing performance problems.

**Solutions**:
- Review error handling performance configuration
- Reduce context data size
- Optimize log formatters
- Consider async processing

```php
// Monitor performance
php artisan cms:error-logs:analyze --performance
```

### Debug Mode

Enable debug mode for additional information:

```bash
# Enable debug mode
export CMS_ERROR_DEBUG_MODE=true

# Test error handling
php artisan cms:error-logs:test --type=cms --with-context
```

### Log Analysis

Use the analysis command to identify issues:

```bash
# Analyze recent errors
php artisan cms:error-logs:analyze --period=1h --detailed

# Look for patterns
php artisan cms:error-logs:analyze --group-by=error_type

# Export for external analysis
php artisan cms:error-logs:analyze --export=json --output=analysis.json
```

---

For more information, see the [API Documentation](API_DOCUMENTATION.md) and [Configuration Reference](CONFIGURATION.md).