# Audit Logging Module

The Audit Logging module provides functionality for logging authentication-related events and user activities within the ArtisanPack UI CMS Framework.

## Overview

The Audit Logging module allows you to track and record various authentication events and user activities in your application. It provides a comprehensive logging system that captures details such as login attempts, logouts, password changes, and other significant user actions for security monitoring and auditing purposes.

## Classes

### AuditLogger Class

The `AuditLogger` class is the main class for logging authentication events and user activities.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\Audit;
```

#### Methods

##### logLogin(Authenticatable $user): AuditLog
Logs a successful login event.

**@since** 1.1.0

**@param** Authenticatable $user The authenticated user.
**@return** AuditLog The created audit log entry.

##### logLoginFailed(string $credentials): AuditLog
Logs a failed login attempt.

**@since** 1.1.0

**@param** string $credentials The credentials used in the failed attempt (e.g., email).
**@return** AuditLog The created audit log entry.

##### logLogout(Authenticatable $user): AuditLog
Logs a user logout event.

**@since** 1.1.0

**@param** Authenticatable $user The user who logged out.
**@return** AuditLog The created audit log entry.

##### logPasswordChange(Authenticatable $user): AuditLog
Logs a password change event.

**@since** 1.1.0

**@param** Authenticatable $user The user whose password was changed.
**@return** AuditLog The created audit log entry.

##### logActivity(string $action, string $description, string $status = 'info', ?int $userId = null): AuditLog
Logs a generic user activity.

**@since** 1.1.0

**@param** string $action The action performed (e.g., 'settings_updated', 'post_created').
**@param** string $description A detailed description of the activity.
**@param** string $status Optional. The status of the action (e.g., 'success', 'failed', 'info'). Default 'info'.
**@param** int|null $userId Optional. The ID of the user performing the action. Default null.
**@return** AuditLog The created audit log entry.

##### createLog(string $action, string $message, string $status, ?int $userId = null): AuditLog
Creates a new audit log entry in the database.

**@since** 1.1.0
**@access** private

**@param** string $action The type of action.
**@param** string $message A descriptive message for the log.
**@param** string $status The status of the action.
**@param** int|null $userId Optional. The ID of the user associated with the action. Default null.
**@return** AuditLog The created audit log entry.

### AuditLog Model

The `AuditLog` model represents an entry in the audit log, storing details of user activities and system events.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Models;
```

#### Properties

- `$table`: The table associated with the model, which is 'audit_logs'.
- `$fillable`: Array of attributes that are mass assignable, including 'user_id', 'action', 'message', 'ip_address', 'user_agent', and 'status'.

#### Methods

##### user(): BelongsTo
Get the user that owns the audit log.

**@since** 1.1.0

**@return** BelongsTo The relationship to the User model.

## Database Schema

### Audit Logs Table

The Audit Logging module creates an `audit_logs` table in the database with the following columns:

- `id`: Auto-incrementing primary key
- `user_id`: Foreign key to the users table (nullable)
- `action`: String column for the type of action (e.g., 'login_success', 'password_changed')
- `message`: Text column for a descriptive message
- `ip_address`: String column for the IP address from which the action originated
- `user_agent`: String column for the user agent string of the client
- `status`: String column for the status of the action (e.g., 'success', 'failed', 'info')
- `created_at`: Timestamp for when the log entry was created
- `updated_at`: Timestamp for when the log entry was last updated

## Usage

### Logging a Successful Login

```php
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;

$auditLogger = new AuditLogger();
$auditLogger->logLogin($user);
```

### Logging a Failed Login Attempt

```php
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;

$auditLogger = new AuditLogger();
$auditLogger->logLoginFailed('user@example.com');
```

### Logging a Logout

```php
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;

$auditLogger = new AuditLogger();
$auditLogger->logLogout($user);
```

### Logging a Password Change

```php
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;

$auditLogger = new AuditLogger();
$auditLogger->logPasswordChange($user);
```

### Logging a Custom Activity

```php
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;

$auditLogger = new AuditLogger();
$auditLogger->logActivity(
    'settings_updated',
    'User updated site settings',
    'success',
    $user->id
);
```

### Logging a System Activity (No User)

```php
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;

$auditLogger = new AuditLogger();
$auditLogger->logActivity(
    'system_maintenance',
    'Scheduled maintenance performed',
    'info'
);
```

## Integration with Authentication

The Audit Logging module integrates seamlessly with Laravel's authentication system. You can hook into authentication events to automatically log login and logout events:

```php
// In a service provider or middleware

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;

Event::listen(Login::class, function ($event) {
    $auditLogger = new AuditLogger();
    $auditLogger->logLogin($event->user);
});

Event::listen(Failed::class, function ($event) {
    $auditLogger = new AuditLogger();
    $auditLogger->logLoginFailed($event->credentials['email'] ?? 'unknown');
});

Event::listen(Logout::class, function ($event) {
    $auditLogger = new AuditLogger();
    $auditLogger->logLogout($event->user);
});
```

## Querying Audit Logs

You can query the audit logs using the `AuditLog` model:

```php
use ArtisanPackUI\CMSFramework\Models\AuditLog;

// Get all audit logs
$logs = AuditLog::all();

// Get logs for a specific user
$userLogs = AuditLog::where('user_id', $userId)->get();

// Get logs for a specific action
$loginLogs = AuditLog::where('action', 'login_success')->get();

// Get logs with a specific status
$failedLogs = AuditLog::where('status', 'failed')->get();

// Get logs within a date range
$recentLogs = AuditLog::whereBetween('created_at', [$startDate, $endDate])->get();
```

## Security Considerations

The Audit Logging module automatically sanitizes all input data to prevent XSS attacks and other security vulnerabilities. It uses the `Security` class from the ArtisanPack UI Security package to sanitize all data before storing it in the database.

## Best Practices

1. **Log Meaningful Events**: Focus on logging security-relevant events and significant user actions.
2. **Include Contextual Information**: Provide enough detail in log messages to understand what happened.
3. **Use Appropriate Status Levels**: Use 'success', 'failed', and 'info' status levels appropriately.
4. **Regularly Review Logs**: Implement a process for regularly reviewing audit logs to detect suspicious activities.
5. **Set Up Log Retention Policies**: Determine how long audit logs should be kept and implement appropriate retention policies.