# Exception Hierarchy

The CMS Framework uses a structured exception hierarchy to provide consistent error handling throughout the application.

## Base Exception

All framework exceptions extend from `CMSFrameworkException`:

```php
use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

try {
    // Framework code
} catch (CMSFrameworkException $e) {
    // Catch any framework exception
}
```

## Common Exceptions

### ValidationException

Thrown when validation fails for user input or data.

```php
use ArtisanPackUI\CMSFramework\Exceptions\ValidationException;

throw ValidationException::withErrors('Validation failed', [
    'title' => ['The title field is required.'],
    'email' => ['The email must be a valid email address.'],
]);

// Access validation errors
try {
    // Code that might throw validation exception
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    if ($e->hasError('title')) {
        // Handle title error
    }
}
```

### NotFoundException

Thrown when a requested resource cannot be found.

```php
use ArtisanPackUI\CMSFramework\Exceptions\NotFoundException;

// For models
throw NotFoundException::model(Post::class, $id);

// For generic resources
throw NotFoundException::resource('Theme', 'dark-mode');
```

### UnauthorizedException

Thrown when a user attempts to perform an action they're not authorized for.

```php
use ArtisanPackUI\CMSFramework\Exceptions\UnauthorizedException;

// For actions
throw UnauthorizedException::forAction('delete posts');

// For resources
throw UnauthorizedException::forResource('Post', 'delete');

// For permissions
throw UnauthorizedException::requiresPermission('manage-content');
```

## Module-Specific Exceptions

### Core Updates Module

**UpdateException** - Thrown during update operations

```php
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;

throw UpdateException::versionCheckFailed('Network timeout');
throw UpdateException::downloadFailed($url);
throw UpdateException::backupFailed($path);
throw UpdateException::rollbackFailed($reason);
```

### Plugins Module

**PluginInstallationException** - Thrown during plugin installation

```php
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginInstallationException;

throw PluginInstallationException::extractionFailed($slug);
throw PluginInstallationException::alreadyInstalled($slug);
```

**PluginNotFoundException** - Thrown when a plugin is not found

```php
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginNotFoundException;

throw new PluginNotFoundException("Plugin '{$slug}' not found.");
```

**PluginValidationException** - Thrown when plugin validation fails

```php
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginValidationException;

throw new PluginValidationException("Invalid plugin manifest.");
```

**PluginUpdateException** - Thrown during plugin updates

```php
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;

throw new PluginUpdateException("Update failed for plugin '{$slug}'.");
```

### Themes Module

**ThemeNotFoundException** - Thrown when a theme is not found

```php
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeNotFoundException;

throw new ThemeNotFoundException("Theme '{$slug}' not found.");
```

## Exception Hierarchy Tree

```
Exception (PHP)
└── CMSFrameworkException
    ├── ValidationException
    ├── NotFoundException
    ├── UnauthorizedException
    └── Module Exceptions
        ├── UpdateException (Core/Updates)
        ├── PluginInstallationException (Plugins)
        ├── PluginNotFoundException (Plugins)
        ├── PluginValidationException (Plugins)
        ├── PluginUpdateException (Plugins)
        └── ThemeNotFoundException (Themes)
```

## Best Practices

### 1. Use Specific Exceptions

Always throw the most specific exception available:

```php
// Good - specific exception
throw NotFoundException::model(User::class, $id);

// Bad - generic exception
throw new CMSFrameworkException("User not found");
```

### 2. Provide Context

Include relevant context in exception messages:

```php
// Good - includes context
throw UpdateException::downloadFailed("https://example.com/update.zip");

// Bad - vague message
throw new UpdateException("Download failed");
```

### 3. Use Static Factory Methods

Leverage static factory methods for consistency:

```php
// Good - using factory method
throw ValidationException::withErrors('Invalid input', $errors);

// Less ideal - direct instantiation
$e = new ValidationException('Invalid input');
$e->errors = $errors;
throw $e;
```

### 4. Catch Specific Exceptions

Catch the most specific exception type when possible:

```php
// Good - specific catch
try {
    $manager->sendNotification($key, $userIds);
} catch (NotFoundException $e) {
    // Handle not found case
} catch (ValidationException $e) {
    // Handle validation errors
} catch (CMSFrameworkException $e) {
    // Handle other framework exceptions
}
```

### 5. Document Thrown Exceptions

Always document which exceptions a method might throw:

```php
/**
 * Send a notification to users.
 *
 * @throws NotFoundException If the notification key is not registered.
 * @throws ValidationException If the user IDs are invalid.
 * @throws UnauthorizedException If the user lacks permission.
 */
public function sendNotification(string $key, array $userIds): Notification
{
    // ...
}
```

## Creating Custom Exceptions

When creating module-specific exceptions, extend from `CMSFrameworkException`:

```php
<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\YourModule\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

class YourModuleException extends CMSFrameworkException
{
    public static function operationFailed(string $operation): self
    {
        return new self("Operation failed: {$operation}");
    }
}
```

## Exception Handling in Controllers

In controllers, catch framework exceptions and return appropriate responses:

```php
use ArtisanPackUI\CMSFramework\Exceptions\NotFoundException;
use ArtisanPackUI\CMSFramework\Exceptions\UnauthorizedException;
use ArtisanPackUI\CMSFramework\Exceptions\ValidationException;

public function update(Request $request, int $id)
{
    try {
        $post = $this->manager->update($id, $request->all());
        return response()->json($post);
    } catch (NotFoundException $e) {
        return response()->json(['error' => $e->getMessage()], 404);
    } catch (UnauthorizedException $e) {
        return response()->json(['error' => $e->getMessage()], 403);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'errors' => $e->getErrors(),
        ], 422);
    }
}
```

## See Also

- [Error Handling Documentation](error-handling.md)
- [Testing Exception Scenarios](testing.md#exception-testing)
- [API Error Responses](api.md#error-responses)
