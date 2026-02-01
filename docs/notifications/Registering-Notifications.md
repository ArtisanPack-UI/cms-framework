---
title: Notifications — Registering Notifications
---

# Notifications — Registering Notifications

This guide explains how to register notification types with default values, metadata, and email settings.

## Why Register Notifications?

Registering notification types provides several benefits:
- **Reusability** — Define once, use many times with consistent messaging
- **Defaults** — Set default title, content, type, and email settings
- **Centralization** — All notification definitions in one place
- **Flexibility** — Override defaults when sending individual notifications
- **Metadata** — Attach custom data for categorization or filtering

## Basic Registration

Use the `apRegisterNotification()` helper to register a notification type:

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use function apRegisterNotification;

apRegisterNotification(
    key: 'post.published',
    title: 'Post Published',
    content: 'Your post has been successfully published.',
    type: NotificationType::Success,
    sendEmail: false,
    metadata: []
);
```

### Parameters

- **key** (string, required) — Unique identifier for this notification type
- **title** (string, required) — Default title shown to users
- **content** (string, required) — Default message content
- **type** (NotificationType, optional) — Notification type; defaults to `NotificationType::Info`
- **sendEmail** (bool, optional) — Whether to send email by default; defaults to `false`
- **metadata** (array, optional) — Additional data to store with the notification; defaults to `[]`

## Where to Register

Register notifications during application boot. Common locations include:

### Service Provider

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use function apRegisterNotification;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        apRegisterNotification(
            key: 'user.registered',
            title: 'Welcome!',
            content: 'Your account has been created successfully.',
            type: NotificationType::Success,
            sendEmail: true
        );

        apRegisterNotification(
            key: 'payment.failed',
            title: 'Payment Failed',
            content: 'We were unable to process your payment.',
            type: NotificationType::Error,
            sendEmail: true
        );
    }
}
```

### Module Service Provider

For module-specific notifications, register them in the module's service provider:

```php
public function boot(): void
{
    apRegisterNotification(
        key: 'order.shipped',
        title: 'Order Shipped',
        content: 'Your order #{order_number} has been shipped!',
        type: NotificationType::Info,
        sendEmail: true,
        metadata: ['module' => 'ecommerce']
    );
}
```

## Notification Keys

Use dot notation for namespacing notification keys:

```php
// Good examples
'user.registered'
'user.password_reset'
'post.published'
'post.rejected'
'order.shipped'
'order.delivered'
'system.maintenance'

// Avoid generic keys
'notification1'
'alert'
'message'
```

## Notification Types

The `NotificationType` enum provides four types:

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

NotificationType::Info     // Blue styling, info icon
NotificationType::Success  // Green styling, check icon
NotificationType::Warning  // Yellow styling, warning icon
NotificationType::Error    // Red styling, error icon
```

Each type includes helper methods:

```php
$type = NotificationType::Success;

$type->label();      // Returns translated label: "Success"
$type->icon();       // Returns icon identifier: "fas.circle-check"
$type->colorClass(); // Returns CSS classes: "text-green-600 dark:text-green-400"
```

## Using Metadata

Metadata allows you to attach custom data to notifications:

```php
apRegisterNotification(
    key: 'post.comment',
    title: 'New Comment',
    content: 'Someone commented on your post.',
    type: NotificationType::Info,
    sendEmail: false,
    metadata: [
        'category' => 'engagement',
        'priority' => 'low',
        'icon' => 'comment'
    ]
);
```

When sending, you can merge or override metadata:

```php
apSendNotification('post.comment', [1], [
    'metadata' => [
        'post_id' => 123,
        'comment_id' => 456,
        'priority' => 'high' // Override default
    ]
]);
```

The final metadata will be:
```php
[
    'category' => 'engagement',
    'priority' => 'high',        // Overridden
    'icon' => 'comment',
    'post_id' => 123,            // Added
    'comment_id' => 456          // Added
]
```

## Email Notifications

Set `sendEmail: true` to queue email delivery when the notification is sent:

```php
apRegisterNotification(
    key: 'invoice.overdue',
    title: 'Invoice Overdue',
    content: 'Your invoice is now overdue. Please make payment as soon as possible.',
    type: NotificationType::Warning,
    sendEmail: true
);
```

When you send this notification, emails are queued automatically:

```php
// Sends in-app notification AND queues email
apSendNotification('invoice.overdue', [1, 2, 3]);
```

Users can disable email notifications via their [notification preferences](Notification-Preferences).

## Content Placeholders

While the module doesn't provide built-in variable substitution, you can implement placeholders using overrides:

```php
// Register with placeholder
apRegisterNotification(
    key: 'order.shipped',
    title: 'Order Shipped',
    content: 'Order {order_number} has shipped. Tracking: {tracking_number}',
    type: NotificationType::Success,
    sendEmail: true
);

// Replace placeholders when sending
$orderNumber = '12345';
$tracking = 'ABC123XYZ';

apSendNotification('order.shipped', [$userId], [
    'content' => "Order {$orderNumber} has shipped. Tracking: {$tracking}",
    'metadata' => [
        'order_id' => $orderId,
        'tracking_number' => $tracking
    ]
]);
```

## Retrieving Registered Notifications

Get all registered notifications:

```php
use function apGetRegisteredNotifications;

$registered = apGetRegisteredNotifications();

// Returns:
[
    'user.registered' => [
        'title' => 'Welcome!',
        'content' => 'Your account has been created successfully.',
        'type' => NotificationType::Success,
        'send_email' => true,
        'metadata' => []
    ],
    // ... more notifications
]
```

This is useful for:
- Admin interfaces to manage notifications
- User preference screens
- Debugging and introspection

## Registration via Filter Hook

Advanced users can register notifications directly via the filter hook:

```php
use function addFilter;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

addFilter('ap.notifications.registeredNotifications', function ($notifications) {
    $notifications['custom.event'] = [
        'title' => 'Custom Event',
        'content' => 'A custom event occurred.',
        'type' => NotificationType::Info,
        'send_email' => false,
        'metadata' => ['source' => 'custom']
    ];

    return $notifications;
});
```

See [Hooks and Events](Hooks-And-Events) for more details.

## Best Practices

### Use Descriptive Keys
```php
// Good
apRegisterNotification(key: 'subscription.trial_ending', ...);

// Avoid
apRegisterNotification(key: 'notif23', ...);
```

### Set Appropriate Types
Match the notification type to the message severity:
```php
NotificationType::Error   // Failures, errors, critical issues
NotificationType::Warning // Warnings, approaching limits, attention needed
NotificationType::Success // Successful actions, confirmations
NotificationType::Info    // General information, updates
```

### Consider Email Impact
Only enable email for important notifications:
```php
sendEmail: true  // Password resets, payment failures, critical alerts
sendEmail: false // Minor updates, in-app messages, frequent events
```

### Organize by Domain
Group related notifications:
```php
// User domain
'user.registered'
'user.email_verified'
'user.password_changed'

// Content domain
'post.published'
'post.updated'
'post.deleted'
```

## Next Steps

- Learn how to [Send Notifications](Sending-Notifications) to users and roles
- Understand [Managing Notifications](Managing-Notifications) for users
- Configure [Notification Preferences](Notification-Preferences)
- Explore [Hooks and Events](Hooks-And-Events) for advanced customization
