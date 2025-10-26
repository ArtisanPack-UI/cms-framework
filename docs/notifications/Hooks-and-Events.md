---
title: Notifications — Hooks and Events
---

# Notifications — Hooks and Events

The Notifications module provides filter and action hooks that allow you to extend and customize behavior.

## Available Hooks

### Filters

| Hook Name | Description | Parameters | Return Type |
|-----------|-------------|------------|-------------|
| `ap.notifications.registeredNotifications` | Filter the array of registered notifications | `$notifications` (array) | array |

### Actions

| Hook Name | Description | Parameters |
|-----------|-------------|------------|
| `ap.notifications.sendNotification` | Fires after a notification has been sent | `$notification` (Notification), `$userIds` (array), `$key` (string) |
| `ap.notifications.readNotification` | Fires after a notification has been marked as read | `$notificationId` (int), `$userId` (int) |
| `ap.notifications.dismissNotification` | Fires after a notification has been dismissed | `$notificationId` (int), `$userId` (int) |

## Filter Hooks

### ap.notifications.registeredNotifications

This filter allows you to register notifications or modify existing registrations.

#### Basic Usage

```php
use function addFilter;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

addFilter('ap.notifications.registeredNotifications', function ($notifications) {
    $notifications['custom.event'] = [
        'title' => 'Custom Event',
        'content' => 'A custom event occurred in your application.',
        'type' => NotificationType::Info,
        'send_email' => false,
        'metadata' => ['source' => 'custom_module']
    ];

    return $notifications;
});
```

#### Modify Existing Notifications

```php
addFilter('ap.notifications.registeredNotifications', function ($notifications) {
    // Override default content for an existing notification
    if (isset($notifications['post.published'])) {
        $notifications['post.published']['content'] = 'Check out the latest post!';
    }

    return $notifications;
}, 20); // Higher priority runs later
```

#### Bulk Registration

```php
addFilter('ap.notifications.registeredNotifications', function ($notifications) {
    $customNotifications = [
        'order.created' => [
            'title' => 'Order Created',
            'content' => 'Your order has been created successfully.',
            'type' => NotificationType::Success,
            'send_email' => true,
            'metadata' => ['module' => 'ecommerce']
        ],
        'order.shipped' => [
            'title' => 'Order Shipped',
            'content' => 'Your order has been shipped.',
            'type' => NotificationType::Info,
            'send_email' => true,
            'metadata' => ['module' => 'ecommerce']
        ],
        'order.delivered' => [
            'title' => 'Order Delivered',
            'content' => 'Your order has been delivered.',
            'type' => NotificationType::Success,
            'send_email' => true,
            'metadata' => ['module' => 'ecommerce']
        ],
    ];

    return array_merge($notifications, $customNotifications);
});
```

#### Conditional Registration

```php
addFilter('ap.notifications.registeredNotifications', function ($notifications) {
    // Only register in production
    if (app()->environment('production')) {
        $notifications['system.critical_error'] = [
            'title' => 'Critical System Error',
            'content' => 'A critical error occurred.',
            'type' => NotificationType::Error,
            'send_email' => true,
            'metadata' => ['priority' => 'critical']
        ];
    }

    return $notifications;
});
```

#### Remove Notifications

```php
addFilter('ap.notifications.registeredNotifications', function ($notifications) {
    // Remove a notification type
    unset($notifications['unwanted.notification']);

    return $notifications;
});
```

## Action Hooks

### ap.notifications.sendNotification

Fires immediately after a notification has been sent to users.

#### Parameters

- `$notification` (Notification) — The created notification instance
- `$userIds` (array) — Array of user IDs that received the notification
- `$key` (string) — The notification key

#### Basic Usage

```php
use function addAction;

addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    // Log the notification
    logger()->info("Notification sent", [
        'key' => $key,
        'id' => $notification->id,
        'recipients' => count($userIds)
    ]);
});
```

#### Send to External Services

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    // Send to Slack for certain notification types
    if ($notification->type === NotificationType::Error) {
        app(SlackNotifier::class)->send(
            "Error notification sent: {$notification->title}",
            $userIds
        );
    }
});
```

#### Track Analytics

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    // Track in analytics
    foreach ($userIds as $userId) {
        Analytics::track($userId, 'notification_received', [
            'notification_key' => $key,
            'notification_type' => $notification->type->value,
            'has_email' => $notification->send_email
        ]);
    }
});
```

#### Broadcast Real-Time Updates

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    // Broadcast to frontend via Laravel Echo
    foreach ($userIds as $userId) {
        broadcast(new NotificationReceived($notification, $userId))->toOthers();
    }
});
```

#### Create Activity Log

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    // Log to activity table
    foreach ($userIds as $userId) {
        Activity::create([
            'user_id' => $userId,
            'type' => 'notification_received',
            'description' => $notification->title,
            'metadata' => [
                'notification_id' => $notification->id,
                'key' => $key
            ]
        ]);
    }
});
```

### ap.notifications.readNotification

Fires after a notification has been marked as read by a user.

#### Parameters

- `$notificationId` (int) — The notification ID
- `$userId` (int) — The user ID who marked it as read

#### Basic Usage

```php
addAction('ap.notifications.readNotification', function ($notificationId, $userId) {
    logger()->info("Notification marked as read", [
        'notification_id' => $notificationId,
        'user_id' => $userId
    ]);
});
```

#### Track Engagement

```php
addAction('ap.notifications.readNotification', function ($notificationId, $userId) {
    $notification = Notification::find($notificationId);

    Analytics::track($userId, 'notification_read', [
        'notification_key' => $notification->metadata['key'] ?? 'unknown',
        'notification_type' => $notification->type->value,
        'time_to_read' => now()->diffInMinutes($notification->created_at)
    ]);
});
```

#### Update User Engagement Score

```php
addAction('ap.notifications.readNotification', function ($notificationId, $userId) {
    $user = User::find($userId);
    $user->increment('engagement_score');
});
```

### ap.notifications.dismissNotification

Fires after a notification has been dismissed by a user.

#### Parameters

- `$notificationId` (int) — The notification ID
- `$userId` (int) — The user ID who dismissed it

#### Basic Usage

```php
addAction('ap.notifications.dismissNotification', function ($notificationId, $userId) {
    logger()->info("Notification dismissed", [
        'notification_id' => $notificationId,
        'user_id' => $userId
    ]);
});
```

#### Track Dismissal Patterns

```php
addAction('ap.notifications.dismissNotification', function ($notificationId, $userId) {
    $notification = Notification::find($notificationId);

    // Track which notification types are frequently dismissed
    Analytics::track($userId, 'notification_dismissed', [
        'notification_type' => $notification->type->value,
        'was_read' => $notification->users()
            ->where('user_id', $userId)
            ->first()->pivot->is_read
    ]);
});
```

#### Suggest Preference Changes

```php
addAction('ap.notifications.dismissNotification', function ($notificationId, $userId) {
    $notification = Notification::find($notificationId);
    $key = $notification->metadata['key'] ?? null;

    if (!$key) return;

    // Track dismissals per notification type
    $dismissals = Cache::increment("user.{$userId}.dismissals.{$key}");

    // After 5 dismissals, suggest disabling this type
    if ($dismissals >= 5) {
        apSendNotificationToCurrentUser('suggestion.disable_notifications', [
            'content' => "We noticed you often dismiss '{$key}' notifications. Would you like to disable them?",
            'metadata' => ['suggested_key' => $key]
        ]);

        Cache::forget("user.{$userId}.dismissals.{$key}");
    }
});
```

## Practical Examples

### Multi-Channel Notifications

Send notifications to multiple channels (database, email, SMS, push):

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    // SMS for critical notifications
    if ($notification->type === NotificationType::Error) {
        foreach ($userIds as $userId) {
            $user = User::find($userId);

            if ($user->phone) {
                SMS::send($user->phone, $notification->content);
            }
        }
    }

    // Push notifications for mobile users
    foreach ($userIds as $userId) {
        $user = User::find($userId);

        if ($user->push_token) {
            PushNotification::send($user->push_token, [
                'title' => $notification->title,
                'body' => $notification->content
            ]);
        }
    }
});
```

### Digest Notifications

Batch notifications into daily/weekly digests:

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    // For low-priority notifications, add to digest instead of sending immediately
    if (in_array($key, ['post.liked', 'post.comment'])) {
        foreach ($userIds as $userId) {
            DigestNotification::create([
                'user_id' => $userId,
                'notification_id' => $notification->id,
                'scheduled_for' => now()->endOfDay() // Send at end of day
            ]);
        }

        // Prevent immediate email
        $notification->update(['send_email' => false]);
    }
});
```

### Notification Rate Limiting

Prevent notification spam:

```php
addFilter('ap.notifications.registeredNotifications', function ($notifications) {
    return $notifications;
});

addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    foreach ($userIds as $userId) {
        $rateLimitKey = "notification_limit.{$userId}.{$key}";

        // Allow max 5 of this notification type per hour
        if (Cache::get($rateLimitKey, 0) >= 5) {
            // Detach this user from the notification
            $notification->users()->detach($userId);
            logger()->warning("Rate limit exceeded", [
                'user_id' => $userId,
                'key' => $key
            ]);
            continue;
        }

        Cache::increment($rateLimitKey);
        Cache::put($rateLimitKey, Cache::get($rateLimitKey), now()->addHour());
    }
});
```

### Notification Templates

Override notification content with templates:

```php
addFilter('ap.notifications.registeredNotifications', function ($notifications) {
    foreach ($notifications as $key => &$notification) {
        // Load from template files if they exist
        $templatePath = resource_path("notifications/{$key}.blade.php");

        if (file_exists($templatePath)) {
            $notification['content'] = view("notifications.{$key}")->render();
        }
    }

    return $notifications;
});
```

### User Mention Notifications

Automatically detect and notify mentioned users:

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    // Detect @mentions in content
    preg_match_all('/@(\w+)/', $notification->content, $mentions);

    if (!empty($mentions[1])) {
        $mentionedUsers = User::whereIn('username', $mentions[1])->pluck('id')->toArray();

        // Send mention notification
        apSendNotification('user.mentioned', $mentionedUsers, [
            'content' => "You were mentioned in: {$notification->title}",
            'metadata' => [
                'source_notification_id' => $notification->id
            ]
        ]);
    }
});
```

### Conditional Email Sending

Override email sending based on user settings or time of day:

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    if ($notification->send_email) {
        $currentHour = now()->hour;

        // Don't send emails between 10 PM and 8 AM (quiet hours)
        if ($currentHour >= 22 || $currentHour < 8) {
            // Reschedule for 8 AM
            SendNotificationEmail::dispatch($notification, $userIds)
                ->delay(now()->setTime(8, 0));

            // Mark as not sent immediately
            $notification->update(['send_email' => false]);
        }
    }
});
```

## Best Practices

### Use Appropriate Hook Types

- **Filters** — Modify data (registered notifications)
- **Actions** — Execute side effects (logging, analytics, external services)

### Set Proper Priorities

```php
// Run early (priority 1-5)
addAction('ap.notifications.sendNotification', 'earlyCallback', 1);

// Run default (priority 10)
addAction('ap.notifications.sendNotification', 'normalCallback');

// Run late (priority 20+)
addAction('ap.notifications.sendNotification', 'lateCallback', 20);
```

### Handle Errors Gracefully

```php
addAction('ap.notifications.sendNotification', function ($notification, $userIds, $key) {
    try {
        // Your logic
        externalService()->notify($userIds);
    } catch (\Exception $e) {
        logger()->error("Failed to send to external service", [
            'error' => $e->getMessage(),
            'notification_id' => $notification->id
        ]);

        // Don't throw - allow other hooks to run
    }
});
```

### Document Custom Hooks

When adding hooks to your own modules, document them:

```php
/**
 * Filters the notification content before sending.
 *
 * @hook custom_module.notification.content
 *
 * @param string $content The notification content.
 * @param Notification $notification The notification instance.
 *
 * @return string Filtered content.
 */
$content = applyFilters('custom_module.notification.content', $content, $notification);
```

## Next Steps

- Review [Database and Migrations](Database-and-Migrations.md) for schema details
- Explore the [API Reference](API-Reference.md) for all available methods
- Learn about [Registering Notifications](Registering-Notifications.md) and [Sending Notifications](Sending-Notifications.md)
