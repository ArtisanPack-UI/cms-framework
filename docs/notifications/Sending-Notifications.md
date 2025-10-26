---
title: Notifications — Sending Notifications
---

# Notifications — Sending Notifications

This guide covers the various ways to send notifications to users, including sending to specific users, roles, and the current user.

## Send to Specific Users

Use `apSendNotification()` to send to an array of user IDs:

```php
use function apSendNotification;

// Send to users 1, 2, and 3
apSendNotification('post.published', [1, 2, 3]);
```

The function returns the created `Notification` instance, or `null` if no users received it (due to preferences):

```php
$notification = apSendNotification('post.published', [1, 2, 3]);

if ($notification) {
    logger()->info("Notification sent", ['id' => $notification->id]);
}
```

## Send to Current User

Send to the authenticated user with `apSendNotificationToCurrentUser()`:

```php
use function apSendNotificationToCurrentUser;

apSendNotificationToCurrentUser('post.published');
```

If no user is authenticated, the function returns `null`:

```php
$notification = apSendNotificationToCurrentUser('welcome');

if ($notification) {
    // Notification sent successfully
} else {
    // No authenticated user
}
```

## Send to Role

Send to all users with a specific role using `apSendNotificationByRole()`:

```php
use function apSendNotificationByRole;

// Send to all editors
apSendNotificationByRole('post.pending_review', 'editor');

// Send to all administrators
apSendNotificationByRole('system.maintenance', 'administrator');
```

## Override Default Values

Pass an `$overrides` array to customize the notification:

```php
apSendNotification('post.published', [1, 2, 3], [
    'title' => 'New Article Published',
    'content' => 'Check out our latest article: "Understanding Laravel Notifications"',
    'metadata' => ['post_id' => 123]
]);
```

Available override keys:
- **title** — Custom title
- **content** — Custom content
- **type** — Override NotificationType
- **send_email** — Override email sending
- **metadata** — Merge or override metadata

### Override Type

Change the notification type:

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

apSendNotification('post.published', [1], [
    'type' => NotificationType::Warning
]);
```

### Override Email Setting

Force email sending or disable it:

```php
// Force email even if registered with sendEmail: false
apSendNotification('post.published', [1], [
    'send_email' => true
]);

// Disable email even if registered with sendEmail: true
apSendNotification('invoice.overdue', [1], [
    'send_email' => false
]);
```

### Merge Metadata

Metadata from registration and overrides are merged:

```php
// Registered with: metadata: ['category' => 'content']

apSendNotification('post.published', [1], [
    'metadata' => [
        'post_id' => 123,
        'author_id' => 5
    ]
]);

// Final metadata:
// [
//     'category' => 'content',
//     'post_id' => 123,
//     'author_id' => 5
// ]
```

## Send Unregistered Notifications

You can send notifications without prior registration. The `key` becomes the title:

```php
apSendNotification('One-Time Alert', [1], [
    'content' => 'This is a one-time notification.',
    'type' => NotificationType::Info
]);
```

When not registered:
- The `key` parameter is used as the default title
- Content defaults to an empty string
- Type defaults to `NotificationType::Info`
- Email is disabled by default

## User Preferences Filtering

When sending notifications, the system automatically filters recipients based on their preferences:

```php
// Even if you send to 100 users, only those who haven't disabled
// this notification type will receive it
apSendNotification('newsletter.weekly', range(1, 100));
```

If all users have disabled the notification, the function returns `null`:

```php
$notification = apSendNotification('newsletter.weekly', [1, 2, 3]);

if (!$notification) {
    // No users received the notification (all opted out)
}
```

See [Notification Preferences](Notification-Preferences.md) for details on how users control their preferences.

## Email Notifications

If `send_email` is `true` (either from registration or override), the system:
1. Filters users who have disabled email for this notification type
2. Queues email jobs for each remaining user
3. Emails are sent asynchronously via Laravel's queue system

```php
// Queue emails for all recipients
apSendNotification('invoice.overdue', [1, 2, 3], [
    'send_email' => true
]);
```

Ensure your queue worker is running:
```bash
php artisan queue:work
```

## Notification Lifecycle

When you send a notification:

1. **Registration Check** — System retrieves registered defaults for the key
2. **Merge Overrides** — Overrides are merged with defaults
3. **Filter by Preferences** — Users who disabled this notification type are excluded
4. **Create Notification** — A `Notification` record is created in the database
5. **Attach Users** — Remaining users are attached via the `notification_user` pivot table
6. **Fire Hook** — The `ap.notifications.sendNotification` action fires
7. **Queue Emails** — If `send_email` is true, emails are queued for users who haven't disabled email

## Practical Examples

### Post Published

```php
public function publishPost(Post $post): void
{
    $post->update(['status' => 'published']);

    // Notify all subscribers
    $subscriberIds = $post->subscribers->pluck('id')->toArray();

    apSendNotification('post.published', $subscriberIds, [
        'title' => 'New Post: ' . $post->title,
        'content' => $post->excerpt,
        'metadata' => [
            'post_id' => $post->id,
            'category' => $post->category->name
        ]
    ]);
}
```

### Comment on User's Post

```php
public function addComment(Post $post, string $content): void
{
    $comment = $post->comments()->create([
        'content' => $content,
        'user_id' => auth()->id()
    ]);

    // Notify post author
    apSendNotification('post.comment', [$post->author_id], [
        'title' => 'New Comment on Your Post',
        'content' => auth()->user()->name . ' commented: "' . Str::limit($content, 50) . '"',
        'metadata' => [
            'post_id' => $post->id,
            'comment_id' => $comment->id
        ]
    ]);
}
```

### System Maintenance

```php
public function scheduleMaintenanceWindow(): void
{
    // Notify all administrators
    apSendNotificationByRole('system.maintenance', 'administrator', [
        'title' => 'Maintenance Scheduled',
        'content' => 'System maintenance is scheduled for tonight at 2:00 AM EST.',
        'send_email' => true
    ]);
}
```

### User Registration

```php
public function registerUser(array $data): User
{
    $user = User::create($data);

    // Send welcome notification
    apSendNotification('user.registered', [$user->id], [
        'title' => 'Welcome, ' . $user->name . '!',
        'content' => 'Your account has been created. Get started by completing your profile.',
        'send_email' => true
    ]);

    return $user;
}
```

### Payment Failed

```php
public function handleFailedPayment(int $userId, Invoice $invoice): void
{
    apSendNotification('payment.failed', [$userId], [
        'title' => 'Payment Failed',
        'content' => "We couldn't process your payment for invoice #{$invoice->number}.",
        'type' => NotificationType::Error,
        'send_email' => true,
        'metadata' => [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total
        ]
    ]);
}
```

### Bulk Notification

```php
public function notifyAllActiveUsers(): void
{
    $userIds = User::where('is_active', true)->pluck('id')->toArray();

    apSendNotification('announcement.new_feature', $userIds, [
        'title' => 'New Feature Released!',
        'content' => 'Check out our new dashboard redesign with improved performance.',
        'type' => NotificationType::Success
    ]);
}
```

## Return Value

All send functions return a `Notification` instance or `null`:

```php
$notification = apSendNotification('key', [1, 2]);

if ($notification) {
    $notification->id;           // Database ID
    $notification->title;        // Notification title
    $notification->content;      // Content
    $notification->type;         // NotificationType enum
    $notification->metadata;     // Array of metadata
    $notification->send_email;   // Boolean
    $notification->created_at;   // Carbon instance
    $notification->users;        // Related users
}
```

## Best Practices

### Check Authentication

Always check if a user is authenticated before using `apSendNotificationToCurrentUser()`:

```php
if (auth()->check()) {
    apSendNotificationToCurrentUser('action.completed');
}
```

### Avoid Spam

Throttle frequent notifications:

```php
$lastNotification = Cache::get("user.{$userId}.last_comment_notification");

if (!$lastNotification || $lastNotification->diffInMinutes(now()) > 5) {
    apSendNotification('post.comment', [$userId]);
    Cache::put("user.{$userId}.last_comment_notification", now(), 3600);
}
```

### Use Queues

For bulk notifications, consider using queued jobs:

```php
dispatch(function () use ($userIds) {
    apSendNotification('newsletter.weekly', $userIds);
})->onQueue('notifications');
```

### Log Failures

Log when notifications fail to send:

```php
$notification = apSendNotification('critical.alert', [$adminId]);

if (!$notification) {
    Log::warning("Failed to send critical alert to admin", ['admin_id' => $adminId]);
}
```

## Next Steps

- Learn about [Managing Notifications](Managing-Notifications.md) to mark as read and dismiss
- Understand [Notification Preferences](Notification-Preferences.md) for user control
- Explore [Hooks and Events](Hooks-and-Events.md) to extend sending behavior
- Review the [API Reference](API-Reference.md) for all available helpers
