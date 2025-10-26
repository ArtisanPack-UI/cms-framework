---
title: Notifications
---

# Notifications Module

The Notifications module provides a comprehensive in-app notification system with:
- Declarative registration of notification types with defaults and metadata
- Simple helpers to send notifications to users, roles, or the current user
- Database persistence with user-specific read/dismissed states
- Email notifications with queued delivery
- User preferences for notification types and email delivery
- RESTful API endpoints for managing notifications
- Filter hooks for extending functionality

## Notifications Guides

- [Getting Started](notifications/Getting-Started.md) — Quick intro and sending your first notification
- [Registering Notifications](notifications/Registering-Notifications.md) — Define notification types with defaults, metadata, and email settings
- [Sending Notifications](notifications/Sending-Notifications.md) — Send to users, roles, or current user with overrides
- [Managing Notifications](notifications/Managing-Notifications.md) — Mark as read, dismiss, and retrieve notifications
- [Notification Preferences](notifications/Notification-Preferences.md) — User preferences for notification types and email delivery
- [Hooks and Events](notifications/Hooks-and-Events.md) — Filter and action hooks for extending notifications
- [Database and Migrations](notifications/Database-and-Migrations.md) — Storage schema and pivot table structure
- [API Reference](notifications/API-Reference.md) — Complete reference of all helper functions

## Overview

Notifications are registered via a filter hook and stored in the database with many-to-many relationships to users. Each user can mark notifications as read or dismissed, and can configure preferences for which notification types they want to receive.

The module provides:
- **NotificationManager** — Core logic for registration, sending, and managing notifications
- **Notification Model** — Represents a notification with type, title, content, and metadata
- **NotificationPreference Model** — User preferences for notification types
- **HasNotifications Trait** — Add to User models for notification methods
- **API Endpoints** — RESTful endpoints for frontend integration
- **Email Support** — Queued email delivery with customizable templates

### Quick Example

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use function apRegisterNotification;
use function apSendNotification;
use function apSendNotificationByRole;

// Register a notification type during boot (e.g., a service provider)
apRegisterNotification(
    key: 'post.published',
    title: 'New Post Published',
    content: 'A new post has been published: {title}',
    type: NotificationType::Success,
    sendEmail: true,
    metadata: ['category' => 'content']
);

// Send to specific users with variable substitution
apSendNotification('post.published', [1, 2, 3], [
    'content' => 'A new post has been published: My Amazing Article',
    'metadata' => ['post_id' => 123]
]);

// Send to all users with a specific role
apSendNotificationByRole('post.published', 'editor', [
    'content' => 'A new post has been published: My Amazing Article'
]);

// Send to the current authenticated user
apSendNotificationToCurrentUser('post.published', [
    'content' => 'Your post has been published!'
]);
```

### User Methods

```php
// Get user notifications
$user = auth()->user();
$notifications = $user->systemNotifications;
$unread = $user->unreadSystemNotifications;
$count = $user->unreadSystemNotificationsCount();

// Mark as read
$user->markNotificationAsRead($notificationId);
$user->markAllNotificationsAsRead();

// Dismiss notifications
$user->dismissNotification($notificationId);
$user->dismissAllNotifications();

// Check preferences
if ($user->shouldReceiveNotification('post.published')) {
    // Send notification
}
```

### API Endpoints

```
GET    /api/notifications              - Get user notifications (with limit/unread_only filters)
GET    /api/notifications/{id}         - Get single notification
POST   /api/notifications/{id}/read    - Mark as read
POST   /api/notifications/{id}/dismiss - Dismiss notification
POST   /api/notifications/read-all     - Mark all as read
POST   /api/notifications/dismiss-all  - Dismiss all
GET    /api/notifications/unread-count - Get unread count
```

See the guides above for detailed documentation and examples.
