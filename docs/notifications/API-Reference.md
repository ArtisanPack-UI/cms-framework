---
title: Notifications — API Reference
---

# Notifications — API Reference

Complete reference for all notification helper functions, model methods, and HTTP endpoints.

## Helper Functions

### apRegisterNotification()

Register a notification type with default values.

#### Signature

```php
function apRegisterNotification(
    string $key,
    string $title,
    string $content,
    NotificationType $type = NotificationType::Info,
    bool $sendEmail = false,
    array $metadata = []
): void
```

#### Parameters

- **$key** (string, required) — Unique identifier for this notification type
- **$title** (string, required) — Default notification title
- **$content** (string, required) — Default notification content/message
- **$type** (NotificationType, optional) — Notification type enum; defaults to `NotificationType::Info`
- **$sendEmail** (bool, optional) — Whether to send email by default; defaults to `false`
- **$metadata** (array, optional) — Additional custom data; defaults to `[]`

#### Return Value

None (void)

#### Example

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use function apRegisterNotification;

apRegisterNotification(
    key: 'post.published',
    title: 'Post Published',
    content: 'Your post has been published successfully.',
    type: NotificationType::Success,
    sendEmail: true,
    metadata: ['category' => 'content']
);
```

#### Since

2.0.0

---

### apSendNotification()

Send a notification to specified users.

#### Signature

```php
function apSendNotification(
    string $key,
    array $userIds,
    array $overrides = []
): ?Notification
```

#### Parameters

- **$key** (string, required) — The registered notification key (or custom title if not registered)
- **$userIds** (array, required) — Array of user IDs to send the notification to
- **$overrides** (array, optional) — Override default values: `title`, `content`, `type`, `send_email`, `metadata`

#### Return Value

`Notification` instance if sent successfully, `null` if no users received it (e.g., all opted out)

#### Example

```php
use function apSendNotification;

$notification = apSendNotification('post.published', [1, 2, 3]);

// With overrides
$notification = apSendNotification('post.published', [1, 2, 3], [
    'title' => 'New Article Published',
    'content' => 'Check out our latest article!',
    'metadata' => ['post_id' => 123]
]);
```

#### Since

2.0.0

---

### apSendNotificationByRole()

Send a notification to all users with a specific role.

#### Signature

```php
function apSendNotificationByRole(
    string $key,
    string $role,
    array $overrides = []
): ?Notification
```

#### Parameters

- **$key** (string, required) — The registered notification key
- **$role** (string, required) — The role name to send to
- **$overrides** (array, optional) — Override default values

#### Return Value

`Notification` instance or `null`

#### Example

```php
use function apSendNotificationByRole;

// Send to all editors
apSendNotificationByRole('post.pending_review', 'editor');

// Send to all administrators with overrides
apSendNotificationByRole('system.maintenance', 'administrator', [
    'content' => 'System maintenance scheduled for tonight.',
    'send_email' => true
]);
```

#### Since

2.0.0

---

### apSendNotificationToCurrentUser()

Send a notification to the currently authenticated user.

#### Signature

```php
function apSendNotificationToCurrentUser(
    string $key,
    array $overrides = []
): ?Notification
```

#### Parameters

- **$key** (string, required) — The registered notification key
- **$overrides** (array, optional) — Override default values

#### Return Value

`Notification` instance or `null` if no user is authenticated

#### Example

```php
use function apSendNotificationToCurrentUser;

// Send to current user
apSendNotificationToCurrentUser('profile.updated');

// With overrides
apSendNotificationToCurrentUser('action.completed', [
    'title' => 'Success!',
    'content' => 'Your action was completed successfully.'
]);
```

#### Since

2.0.0

---

### apGetNotifications()

Get notifications for a specific user.

#### Signature

```php
function apGetNotifications(
    int $userId,
    int $limit = 10,
    bool $unreadOnly = false
): Collection
```

#### Parameters

- **$userId** (int, required) — The user ID
- **$limit** (int, optional) — Maximum number of notifications to retrieve; defaults to `10`
- **$unreadOnly** (bool, optional) — Whether to retrieve only unread notifications; defaults to `false`

#### Return Value

`Illuminate\Support\Collection` of `Notification` instances

#### Example

```php
use function apGetNotifications;

// Get 10 most recent notifications
$notifications = apGetNotifications($userId);

// Get 20 most recent notifications
$notifications = apGetNotifications($userId, 20);

// Get only unread
$unread = apGetNotifications($userId, 10, true);
```

#### Since

2.0.0

---

### apMarkNotificationAsRead()

Mark a notification as read for a user.

#### Signature

```php
function apMarkNotificationAsRead(
    int $notificationId,
    int $userId
): bool
```

#### Parameters

- **$notificationId** (int, required) — The notification ID
- **$userId** (int, required) — The user ID

#### Return Value

`true` if marked as read successfully, `false` otherwise

#### Example

```php
use function apMarkNotificationAsRead;

$success = apMarkNotificationAsRead($notificationId, $userId);

if ($success) {
    echo "Notification marked as read";
}
```

#### Since

2.0.0

---

### apDismissNotification()

Dismiss a notification for a user.

#### Signature

```php
function apDismissNotification(
    int $notificationId,
    int $userId
): bool
```

#### Parameters

- **$notificationId** (int, required) — The notification ID
- **$userId** (int, required) — The user ID

#### Return Value

`true` if dismissed successfully, `false` otherwise

#### Example

```php
use function apDismissNotification;

$success = apDismissNotification($notificationId, $userId);
```

#### Since

2.0.0

---

### apMarkAllNotificationsAsRead()

Mark all notifications as read for a user.

#### Signature

```php
function apMarkAllNotificationsAsRead(int $userId): int
```

#### Parameters

- **$userId** (int, required) — The user ID

#### Return Value

Number of notifications marked as read (int)

#### Example

```php
use function apMarkAllNotificationsAsRead;

$count = apMarkAllNotificationsAsRead($userId);
echo "Marked {$count} notifications as read";
```

#### Since

2.0.0

---

### apDismissAllNotifications()

Dismiss all notifications for a user.

#### Signature

```php
function apDismissAllNotifications(int $userId): int
```

#### Parameters

- **$userId** (int, required) — The user ID

#### Return Value

Number of notifications dismissed (int)

#### Example

```php
use function apDismissAllNotifications;

$count = apDismissAllNotifications($userId);
echo "Dismissed {$count} notifications";
```

#### Since

2.0.0

---

### apGetUnreadNotificationCount()

Get the count of unread notifications for a user.

#### Signature

```php
function apGetUnreadNotificationCount(int $userId): int
```

#### Parameters

- **$userId** (int, required) — The user ID

#### Return Value

Number of unread notifications (int)

#### Example

```php
use function apGetUnreadNotificationCount;

$count = apGetUnreadNotificationCount($userId);
echo "You have {$count} unread notifications";
```

#### Since

2.0.0

---

### apGetRegisteredNotifications()

Get all registered notification types.

#### Signature

```php
function apGetRegisteredNotifications(): array
```

#### Parameters

None

#### Return Value

Associative array of registered notifications keyed by notification key

#### Example

```php
use function apGetRegisteredNotifications;

$registered = apGetRegisteredNotifications();

foreach ($registered as $key => $data) {
    echo $key;              // 'post.published'
    echo $data['title'];    // 'Post Published'
    echo $data['content'];  // 'Your post has been published.'
    echo $data['type'];     // NotificationType::Success
    echo $data['send_email']; // true
    echo $data['metadata']; // ['category' => 'content']
}
```

#### Since

2.0.0

---

## Model Methods

### Notification Model

#### users()

Get the users that this notification belongs to.

```php
public function users(): BelongsToMany
```

**Returns:** Many-to-many relationship with users including pivot data

**Example:**
```php
$notification = Notification::find(1);
foreach ($notification->users as $user) {
    echo $user->pivot->is_read;
    echo $user->pivot->read_at;
}
```

---

#### scopeUnreadForUser()

Scope query to only include unread notifications for a user.

```php
public function scopeUnreadForUser($query, int $userId)
```

**Parameters:**
- `$query` — Query builder instance
- `$userId` — User ID

**Returns:** Query builder

**Example:**
```php
$unread = Notification::unreadForUser($userId)->get();
```

---

#### scopeReadForUser()

Scope query to only include read notifications for a user.

```php
public function scopeReadForUser($query, int $userId)
```

**Example:**
```php
$read = Notification::readForUser($userId)->get();
```

---

#### scopeNotDismissedForUser()

Scope query to only include notifications that are not dismissed for a user.

```php
public function scopeNotDismissedForUser($query, int $userId)
```

**Example:**
```php
$active = Notification::notDismissedForUser($userId)->get();
```

---

#### scopeOfType()

Scope query to filter by notification type.

```php
public function scopeOfType($query, NotificationType $type)
```

**Example:**
```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

$errors = Notification::ofType(NotificationType::Error)->get();
```

---

### HasNotifications Trait (User Model)

#### systemNotifications()

Get all system notifications for the user.

```php
public function systemNotifications(): BelongsToMany
```

**Returns:** Many-to-many relationship with notifications

**Example:**
```php
$user = auth()->user();
$notifications = $user->systemNotifications;
```

---

#### unreadSystemNotifications()

Get unread system notifications for the user.

```php
public function unreadSystemNotifications(): BelongsToMany
```

**Example:**
```php
$unread = $user->unreadSystemNotifications;
```

---

#### notificationPreferences()

Get notification preferences for the user.

```php
public function notificationPreferences(): HasMany
```

**Example:**
```php
$preferences = $user->notificationPreferences;
```

---

#### getNotificationPreference()

Check if the user has a preference for a notification type.

```php
public function getNotificationPreference(string $notificationType): ?NotificationPreference
```

**Parameters:**
- `$notificationType` — The notification type key

**Returns:** `NotificationPreference` or `null`

**Example:**
```php
$preference = $user->getNotificationPreference('post.published');
if ($preference) {
    echo $preference->is_enabled;
    echo $preference->email_enabled;
}
```

---

#### shouldReceiveNotification()

Check if the user should receive a notification type.

```php
public function shouldReceiveNotification(string $notificationType): bool
```

**Parameters:**
- `$notificationType` — The notification type key

**Returns:** `true` if user should receive, `false` otherwise

**Example:**
```php
if ($user->shouldReceiveNotification('post.published')) {
    apSendNotification('post.published', [$user->id]);
}
```

---

#### shouldReceiveNotificationEmail()

Check if the user should receive email for a notification type.

```php
public function shouldReceiveNotificationEmail(string $notificationType): bool
```

**Example:**
```php
if ($user->shouldReceiveNotificationEmail('invoice.overdue')) {
    // Send email
}
```

---

#### markNotificationAsRead()

Mark a notification as read for this user.

```php
public function markNotificationAsRead(int $notificationId): bool
```

**Parameters:**
- `$notificationId` — The notification ID

**Returns:** `true` if successful, `false` otherwise

**Example:**
```php
$success = $user->markNotificationAsRead($notificationId);
```

---

#### dismissNotification()

Dismiss a notification for this user.

```php
public function dismissNotification(int $notificationId): bool
```

**Example:**
```php
$user->dismissNotification($notificationId);
```

---

#### markAllNotificationsAsRead()

Mark all system notifications as read for this user.

```php
public function markAllNotificationsAsRead(): int
```

**Returns:** Number of notifications marked as read

**Example:**
```php
$count = $user->markAllNotificationsAsRead();
```

---

#### dismissAllNotifications()

Dismiss all notifications for this user.

```php
public function dismissAllNotifications(): int
```

**Returns:** Number of notifications dismissed

**Example:**
```php
$count = $user->dismissAllNotifications();
```

---

#### unreadSystemNotificationsCount()

Get the count of unread system notifications for this user.

```php
public function unreadSystemNotificationsCount(): int
```

**Returns:** Count of unread notifications

**Example:**
```php
$count = $user->unreadSystemNotificationsCount();
```

---

## HTTP API Endpoints

The Notifications module provides RESTful API endpoints for frontend integration.

### GET /api/notifications

Get notifications for the authenticated user.

**Query Parameters:**
- `limit` (int, optional) — Max notifications to retrieve (1-100); default: 10
- `unread_only` (bool, optional) — Filter to only unread; default: false

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "type": "success",
      "title": "Post Published",
      "content": "Your post has been published.",
      "metadata": {"post_id": 456},
      "send_email": true,
      "created_at": "2025-10-25T10:30:00.000000Z",
      "is_read": false,
      "read_at": null,
      "is_dismissed": false,
      "dismissed_at": null
    }
  ]
}
```

---

### GET /api/notifications/{id}

Get a single notification.

**Response:**
```json
{
  "data": {
    "id": 123,
    "type": "success",
    "title": "Post Published",
    ...
  }
}
```

**Error Responses:**
- `404` — Notification not found
- `403` — Unauthorized (notification doesn't belong to user)

---

### POST /api/notifications/{id}/read

Mark a notification as read.

**Response:**
```json
{
  "message": "Notification marked as read"
}
```

---

### POST /api/notifications/{id}/dismiss

Dismiss a notification.

**Response:**
```json
{
  "message": "Notification dismissed"
}
```

---

### POST /api/notifications/read-all

Mark all notifications as read for the authenticated user.

**Response:**
```json
{
  "message": "Marked 5 notifications as read",
  "count": 5
}
```

---

### POST /api/notifications/dismiss-all

Dismiss all notifications for the authenticated user.

**Response:**
```json
{
  "message": "Dismissed 10 notifications",
  "count": 10
}
```

---

### GET /api/notifications/unread-count

Get unread notification count for the authenticated user.

**Response:**
```json
{
  "count": 3
}
```

---

## Enums

### NotificationType

Available notification types.

```php
enum NotificationType: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Success = 'success';
    case Info = 'info';
}
```

#### Methods

**label()** — Get human-readable label
```php
NotificationType::Success->label(); // "Success"
```

**icon()** — Get icon identifier
```php
NotificationType::Error->icon(); // "fas.circle-exclamation"
```

**colorClass()** — Get Tailwind CSS color classes
```php
NotificationType::Warning->colorClass(); // "text-yellow-600 dark:text-yellow-400"
```

---

## Manager Class

### NotificationManager

The `NotificationManager` class contains the core notification logic. While you typically use helper functions, you can access the manager directly:

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Managers\NotificationManager;

$manager = app(NotificationManager::class);

// All helper functions proxy to this manager
$manager->sendNotification($key, $userIds, $overrides);
$manager->getUserNotifications($userId, $limit, $unreadOnly);
$manager->markAsRead($notificationId, $userId);
// etc.
```

---

## Policy

### NotificationPolicy

Controls authorization for notification actions. By default, users can only:
- View their own notifications
- Mark their own notifications as read
- Dismiss their own notifications

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Policies\NotificationPolicy;

// The policy is automatically registered
// Authorization happens in controllers and API endpoints
```

---

## Next Steps

- Return to [Getting Started](Getting-Started) for a quick intro
- Learn about [Registering Notifications](Registering-Notifications)
- Explore [Sending Notifications](Sending-Notifications)
- Understand [Managing Notifications](Managing-Notifications)
- Configure [Notification Preferences](Notification-Preferences)
- Extend with [Hooks and Events](Hooks-And-Events)
- Review [Database and Migrations](Database-And-Migrations)
