---
title: Notifications — Getting Started
---

# Notifications — Getting Started

This guide walks you through setting up the notifications module and sending your first notification.

## What is a Notification?

A notification in the CMS Framework is a message sent to one or more users within the application. Notifications can:
- Display in the user interface (e.g., notification bell icon)
- Optionally send email notifications to users
- Be marked as read or dismissed by recipients
- Include metadata for custom data
- Have different types (error, warning, success, info) with associated styling

Notifications are stored in the database and associated with users through a many-to-many relationship, allowing each user to maintain their own read/dismissed state.

## Prerequisites

Run the package migrations to create the required database tables:

```bash
php artisan migrate
```

This creates three tables:
- `notifications` — Stores notification data
- `notification_user` — Pivot table tracking user-specific states (read, dismissed)
- `notification_preferences` — User preferences for notification types

## Add the Trait to Your User Model

The `HasNotifications` trait provides convenient methods for working with notifications. Add it to your User model:

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Concerns\HasNotifications;

class User extends Authenticatable
{
    use HasNotifications;

    // ... rest of your model
}
```

## Register Your First Notification

Register notification types during application boot (e.g., in a service provider):

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use function apRegisterNotification;

public function boot(): void
{
    apRegisterNotification(
        key: 'welcome',
        title: 'Welcome to the Platform',
        content: 'Thank you for joining us! We\'re excited to have you here.',
        type: NotificationType::Success,
        sendEmail: true
    );
}
```

The `key` uniquely identifies this notification type. You'll use this key when sending notifications.

## Send a Notification

Send the notification to specific users:

```php
use function apSendNotification;

// Send to user IDs 1, 2, and 3
apSendNotification('welcome', [1, 2, 3]);
```

Or send to the currently authenticated user:

```php
use function apSendNotificationToCurrentUser;

apSendNotificationToCurrentUser('welcome');
```

## Override Default Values

You can override the registered defaults when sending:

```php
apSendNotification('welcome', [1, 2, 3], [
    'title' => 'Welcome, New Member!',
    'content' => 'Hello! Thanks for signing up. Here\'s what you can do next...',
    'metadata' => ['source' => 'registration_flow']
]);
```

## Retrieve Notifications

Get notifications for a user:

```php
$user = auth()->user();

// All notifications
$allNotifications = $user->systemNotifications;

// Only unread notifications
$unreadNotifications = $user->unreadSystemNotifications;

// Count unread
$unreadCount = $user->unreadSystemNotificationsCount();
```

## Mark as Read

```php
$user = auth()->user();
$notificationId = 123;

// Mark one as read
$user->markNotificationAsRead($notificationId);

// Mark all as read
$user->markAllNotificationsAsRead();
```

## Dismiss Notifications

Dismissed notifications are hidden from the user's notification list:

```php
// Dismiss one notification
$user->dismissNotification($notificationId);

// Dismiss all
$user->dismissAllNotifications();
```

## Notification Types

The module supports four notification types via the `NotificationType` enum:

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

NotificationType::Info     // Blue, info icon (default)
NotificationType::Success  // Green, check icon
NotificationType::Warning  // Yellow, warning icon
NotificationType::Error    // Red, error icon
```

Each type has associated icons and color classes for consistent UI styling.

## Next Steps

- Learn about [Registering Notifications](Registering-Notifications) with metadata and email settings
- Explore [Sending Notifications](Sending-Notifications) to roles and with advanced options
- Understand [Managing Notifications](Managing-Notifications) for read/dismissed state
- Configure [Notification Preferences](Notification-Preferences) for users
- Use the [API Reference](Api-Reference) for complete helper documentation
