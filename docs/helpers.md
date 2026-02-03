# Helper Functions Reference

The CMS Framework provides a comprehensive set of helper functions to simplify common tasks. All helper functions are prefixed with `ap` (ArtisanPack) to avoid naming conflicts with other packages.

## Naming Convention

**All helpers follow the `ap` prefix convention:**
- `apRegisterNotification()` - Not `registerNotification()`
- `apGetSetting()` - Not `getSetting()`
- `apAddAdminPage()` - Not `addAdminPage()`

This ensures the framework's global functions won't conflict with other packages or application code.

---

## Admin Helpers

Defined in: `src/Modules/Admin/helpers.php`

### apAddAdminSection()

Register a new admin section in the navigation menu.

```php
apAddAdminSection(string $id, string $title, string $icon, int $order = 100): void
```

**Example:**

```php
apAddAdminSection('marketing', 'Marketing', 'megaphone', 50);
```

### apAddAdminPage()

Register a top-level admin page.

```php
apAddAdminPage(array $config): void
```

**Example:**

```php
apAddAdminPage([
    'id' => 'custom-settings',
    'title' => 'Custom Settings',
    'section' => 'settings',
    'capability' => 'manage-settings',
    'callback' => fn() => view('admin.custom-settings'),
]);
```

### apAddSubAdminPage()

Register a sub-page under an existing admin page.

```php
apAddSubAdminPage(string $parentId, array $config): void
```

**Example:**

```php
apAddSubAdminPage('settings', [
    'id' => 'email-settings',
    'title' => 'Email Settings',
    'capability' => 'manage-settings',
    'callback' => fn() => view('admin.email-settings'),
]);
```

### apGetAdminMenu()

Retrieve the complete admin menu structure.

```php
apGetAdminMenu(): array
```

**Returns:** Array of admin menu sections and pages

---

## Asset Management Helpers

Defined in: `src/Modules/Core/helpers.php`

### Admin Assets

```php
apAdminEnqueueAsset(string $handle, string $src, array $deps = [], string $type = 'script'): void
apAdminDequeueAsset(string $handle, string $type = 'script'): void
apAdminAssets(string $type = 'script'): array
```

**Example:**

```php
// Enqueue admin script
apAdminEnqueueAsset('custom-admin', '/js/admin.js', ['jquery'], 'script');

// Enqueue admin style
apAdminEnqueueAsset('custom-admin-css', '/css/admin.css', [], 'style');

// Get all enqueued admin scripts
$scripts = apAdminAssets('script');

// Dequeue asset
apAdminDequeueAsset('custom-admin', 'script');
```

### Public Assets

```php
apPublicEnqueueAsset(string $handle, string $src, array $deps = [], string $type = 'script'): void
apPublicDequeueAsset(string $handle, string $type = 'script'): void
apPublicAssets(string $type = 'script'): array
```

**Example:**

```php
// Enqueue public script
apPublicEnqueueAsset('theme-script', '/js/theme.js', [], 'script');

// Enqueue public style
apPublicEnqueueAsset('theme-style', '/css/theme.css', [], 'style');
```

### Auth Assets

```php
apAuthEnqueueAsset(string $handle, string $src, array $deps = [], string $type = 'script'): void
apAuthDequeueAsset(string $handle, string $type = 'script'): void
apAuthAssets(string $type = 'script'): array
```

**Example:**

```php
// Enqueue auth page script
apAuthEnqueueAsset('login-script', '/js/auth.js', [], 'script');
```

---

## Notification Helpers

Defined in: `src/Modules/Notifications/helpers.php`

### apRegisterNotification()

Register a new notification type.

```php
apRegisterNotification(
    string $key,
    string $title,
    string $content,
    NotificationType $type = NotificationType::Info,
    bool $sendEmail = false,
    array $metadata = []
): void
```

**Example:**

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

apRegisterNotification(
    'user.welcome',
    'Welcome to the Platform',
    'Thanks for joining us!',
    NotificationType::Success,
    true,
    ['category' => 'onboarding']
);
```

### apSendNotification()

Send a notification to specific users.

```php
apSendNotification(string $key, array $userIds, array $overrides = []): ?Notification
```

**Example:**

```php
apSendNotification('user.welcome', [1, 2, 3], [
    'title' => 'Custom Welcome',
]);
```

### apSendNotificationByRole()

Send a notification to all users with a specific role.

```php
apSendNotificationByRole(string $key, string $role, array $overrides = []): ?Notification
```

**Example:**

```php
apSendNotificationByRole('system.maintenance', 'admin', [
    'title' => 'System Maintenance Scheduled',
]);
```

### apSendNotificationToCurrentUser()

Send a notification to the currently authenticated user.

```php
apSendNotificationToCurrentUser(string $key, array $overrides = []): ?Notification
```

**Example:**

```php
apSendNotificationToCurrentUser('profile.updated', [
    'content' => 'Your profile has been updated successfully.',
]);
```

### apGetNotifications()

Retrieve notifications for a specific user.

```php
apGetNotifications(int $userId, int $limit = 10, bool $unreadOnly = false): Collection
```

**Example:**

```php
// Get latest 10 notifications
$notifications = apGetNotifications($userId, 10);

// Get only unread notifications
$unread = apGetNotifications($userId, 10, true);
```

### apMarkNotificationAsRead()

Mark a notification as read for a user.

```php
apMarkNotificationAsRead(int $notificationId, int $userId): bool
```

**Example:**

```php
apMarkNotificationAsRead($notificationId, auth()->id());
```

### apDismissNotification()

Dismiss a notification for a user.

```php
apDismissNotification(int $notificationId, int $userId): bool
```

**Example:**

```php
apDismissNotification($notificationId, auth()->id());
```

### apMarkAllNotificationsAsRead()

Mark all notifications as read for a user.

```php
apMarkAllNotificationsAsRead(int $userId): int
```

**Returns:** Number of notifications marked as read

**Example:**

```php
$count = apMarkAllNotificationsAsRead(auth()->id());
```

### apDismissAllNotifications()

Dismiss all notifications for a user.

```php
apDismissAllNotifications(int $userId): int
```

**Returns:** Number of notifications dismissed

**Example:**

```php
$count = apDismissAllNotifications(auth()->id());
```

### apGetUnreadNotificationCount()

Get the count of unread notifications for a user.

```php
apGetUnreadNotificationCount(int $userId): int
```

**Example:**

```php
$unreadCount = apGetUnreadNotificationCount(auth()->id());
```

### apGetRegisteredNotifications()

Get all registered notification types.

```php
apGetRegisteredNotifications(): array
```

**Example:**

```php
$allNotifications = apGetRegisteredNotifications();
```

---

## Settings Helpers

Defined in: `src/Modules/Settings/helpers.php`

### apGetSetting()

Retrieve a setting value.

```php
apGetSetting(string $key, mixed $default = null): mixed
```

**Example:**

```php
$siteName = apGetSetting('site_name', 'My Site');
$postsPerPage = apGetSetting('posts_per_page', 10);
```

### apRegisterSetting()

Register a new setting.

```php
apRegisterSetting(string $key, mixed $value, string $type = 'string', string $description = ''): void
```

**Example:**

```php
apRegisterSetting('site_name', 'ArtisanPack CMS', 'string', 'The name of the website');
apRegisterSetting('posts_per_page', 10, 'integer', 'Number of posts per page');
```

### apUpdateSetting()

Update a setting value.

```php
apUpdateSetting(string $key, mixed $value): bool
```

**Example:**

```php
apUpdateSetting('site_name', 'New Site Name');
apUpdateSetting('posts_per_page', 20);
```

---

## User & Role Helpers

Defined in: `src/Modules/Users/helpers.php`

### ap_register_role()

Register a new role.

```php
ap_register_role(string $slug, string $name, string $description = ''): void
```

**Example:**

```php
ap_register_role('moderator', 'Moderator', 'Can moderate content and comments');
```

### ap_register_permission()

Register a new permission.

```php
ap_register_permission(string $slug, string $name, string $description = ''): void
```

**Example:**

```php
ap_register_permission('moderate-comments', 'Moderate Comments', 'Can approve and delete comments');
```

### ap_add_permission_to_role()

Add a permission to a role.

```php
ap_add_permission_to_role(string $roleSlug, string $permissionSlug): void
```

**Example:**

```php
ap_add_permission_to_role('moderator', 'moderate-comments');
```

### apRegisterUserSettingsSection()

Register a user settings section.

```php
apRegisterUserSettingsSection(string $id, string $title, callable $callback, int $order = 100): void
```

**Example:**

```php
apRegisterUserSettingsSection('social-links', 'Social Media Links', function ($user) {
    return view('user.settings.social', compact('user'));
}, 50);
```

---

## Best Practices

### 1. Always Use Helpers Instead of Facades

```php
// Good - using helper
$siteName = apGetSetting('site_name');

// Less ideal - direct manager access
$siteName = app(SettingsManager::class)->get('site_name');
```

### 2. Check Return Values

Some helpers return boolean or null values:

```php
if (apMarkNotificationAsRead($notificationId, $userId)) {
    // Success
} else {
    // Failed - notification or user doesn't exist
}
```

### 3. Type-Safe Settings

When registering settings, specify the correct type:

```php
apRegisterSetting('is_maintenance_mode', false, 'boolean', '...');
apRegisterSetting('max_upload_size', 10240, 'integer', '...');
apRegisterSetting('site_tagline', 'A great site', 'string', '...');
```

### 4. Use Type Hints

PHP 8+ type hints work well with helpers:

```php
function showNotifications(int $userId, int $limit = 10): Collection
{
    return apGetNotifications($userId, $limit);
}
```

---

## Extending Helpers

### Adding Custom Helpers

You can add your own helpers in your application by creating a helper file and autoloading it in `composer.json`:

```json
{
    "autoload": {
        "files": [
            "app/helpers.php"
        ]
    }
}
```

**Important:** Use your own prefix to avoid conflicts:

```php
// app/helpers.php

if (! function_exists('myapp_custom_helper')) {
    function myapp_custom_helper(): string
    {
        return 'Custom functionality';
    }
}
```

---

## See Also

- [Notification System](Notifications)
- [Settings Management](Settings)
- [Admin Pages](Admin-Pages)
- [User Roles & Permissions](Roles-Permissions)
