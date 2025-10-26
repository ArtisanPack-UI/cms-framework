---
title: Notifications — Managing Notifications
---

# Notifications — Managing Notifications

This guide covers retrieving, reading, and dismissing notifications for users.

## Notification States

Each user has their own state for every notification they receive, tracked in the `notification_user` pivot table:

- **Unread** — `is_read = false, is_dismissed = false`
- **Read** — `is_read = true, is_dismissed = false`
- **Dismissed** — `is_dismissed = true` (hidden from user's list)

## Retrieving Notifications

### Using User Model Methods

The `HasNotifications` trait provides convenient relationship methods:

```php
$user = auth()->user();

// All notifications for this user
$all = $user->systemNotifications;

// Only unread notifications
$unread = $user->unreadSystemNotifications;

// Count unread
$count = $user->unreadSystemNotificationsCount();
```

### Using Helper Functions

```php
use function apGetNotifications;
use function apGetUnreadNotificationCount;

// Get notifications for user ID 1
$notifications = apGetNotifications($userId, $limit = 10, $unreadOnly = false);

// Get only unread
$unread = apGetNotifications($userId, $limit = 10, $unreadOnly = true);

// Get unread count
$count = apGetUnreadNotificationCount($userId);
```

### Accessing Notification Data

```php
$user = auth()->user();

foreach ($user->systemNotifications as $notification) {
    echo $notification->id;           // Notification ID
    echo $notification->title;        // Title
    echo $notification->content;      // Content
    echo $notification->type->label(); // "Success", "Error", etc.
    echo $notification->metadata;     // Array of custom data
    echo $notification->created_at;   // Carbon timestamp

    // Access pivot data (user-specific state)
    echo $notification->pivot->is_read;
    echo $notification->pivot->read_at;
    echo $notification->pivot->is_dismissed;
    echo $notification->pivot->dismissed_at;
}
```

## Marking as Read

### Mark Single Notification

Using the User model:

```php
$user = auth()->user();
$notificationId = 123;

$success = $user->markNotificationAsRead($notificationId);

if ($success) {
    // Notification marked as read
} else {
    // Failed (notification not found or doesn't belong to user)
}
```

Using helper functions:

```php
use function apMarkNotificationAsRead;

apMarkNotificationAsRead($notificationId, $userId);
```

### Mark All as Read

Using the User model:

```php
$user = auth()->user();
$count = $user->markAllNotificationsAsRead();

echo "Marked {$count} notifications as read";
```

Using helper functions:

```php
use function apMarkAllNotificationsAsRead;

$count = apMarkAllNotificationsAsRead($userId);
```

### Read State

When marked as read:
- `is_read` is set to `true`
- `read_at` is set to current timestamp
- The notification remains visible in the user's list
- The `ap.notifications.readNotification` action fires

## Dismissing Notifications

Dismissing removes a notification from the user's visible list:

### Dismiss Single Notification

Using the User model:

```php
$user = auth()->user();
$notificationId = 123;

$success = $user->dismissNotification($notificationId);

if ($success) {
    // Notification dismissed
}
```

Using helper functions:

```php
use function apDismissNotification;

apDismissNotification($notificationId, $userId);
```

### Dismiss All Notifications

Using the User model:

```php
$user = auth()->user();
$count = $user->dismissAllNotifications();

echo "Dismissed {$count} notifications";
```

Using helper functions:

```php
use function apDismissAllNotifications;

$count = apDismissAllNotifications($userId);
```

### Dismissed State

When dismissed:
- `is_dismissed` is set to `true`
- `dismissed_at` is set to current timestamp
- The notification is hidden from queries by default
- The `ap.notifications.dismissNotification` action fires

## Query Scopes

The `Notification` model includes helpful query scopes:

### Unread for User

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;

$unread = Notification::unreadForUser($userId)->get();
```

### Read for User

```php
$read = Notification::readForUser($userId)->get();
```

### Not Dismissed for User

```php
$active = Notification::notDismissedForUser($userId)->get();
```

### By Type

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

$errors = Notification::ofType(NotificationType::Error)
    ->notDismissedForUser($userId)
    ->get();
```

### Combining Scopes

```php
// Get unread error notifications
$unreadErrors = Notification::unreadForUser($userId)
    ->ofType(NotificationType::Error)
    ->orderByDesc('created_at')
    ->limit(5)
    ->get();
```

## Practical Examples

### Display Notification Bell Icon

```blade
@php
    $unreadCount = auth()->user()->unreadSystemNotificationsCount();
@endphp

<div class="notification-bell">
    <i class="fas fa-bell"></i>
    @if($unreadCount > 0)
        <span class="badge">{{ $unreadCount }}</span>
    @endif
</div>
```

### Notification Dropdown

```blade
@php
    $notifications = auth()->user()->systemNotifications()->limit(5)->get();
@endphp

<div class="notifications-dropdown">
    @forelse($notifications as $notification)
        <div class="notification-item {{ $notification->pivot->is_read ? 'read' : 'unread' }}">
            <div class="icon {{ $notification->type->colorClass() }}">
                <i class="{{ $notification->type->icon() }}"></i>
            </div>
            <div class="content">
                <h4>{{ $notification->title }}</h4>
                <p>{{ $notification->content }}</p>
                <small>{{ $notification->created_at->diffForHumans() }}</small>
            </div>
            <div class="actions">
                @unless($notification->pivot->is_read)
                    <button wire:click="markAsRead({{ $notification->id }})">
                        Mark as read
                    </button>
                @endunless
                <button wire:click="dismiss({{ $notification->id }})">
                    Dismiss
                </button>
            </div>
        </div>
    @empty
        <div class="no-notifications">
            No notifications
        </div>
    @endforelse

    @if($notifications->count() > 0)
        <div class="notification-actions">
            <button wire:click="markAllAsRead">Mark all as read</button>
            <button wire:click="dismissAll">Clear all</button>
        </div>
    @endif
</div>
```

### Livewire Component

```php
use Livewire\Component;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;

class NotificationDropdown extends Component
{
    public function markAsRead($notificationId)
    {
        auth()->user()->markNotificationAsRead($notificationId);
        $this->dispatch('notification-updated');
    }

    public function dismiss($notificationId)
    {
        auth()->user()->dismissNotification($notificationId);
        $this->dispatch('notification-updated');
    }

    public function markAllAsRead()
    {
        auth()->user()->markAllNotificationsAsRead();
        $this->dispatch('notification-updated');
    }

    public function dismissAll()
    {
        auth()->user()->dismissAllNotifications();
        $this->dispatch('notification-updated');
    }

    public function render()
    {
        return view('livewire.notification-dropdown', [
            'notifications' => auth()->user()->systemNotifications()->limit(10)->get(),
            'unreadCount' => auth()->user()->unreadSystemNotificationsCount()
        ]);
    }
}
```

### API Controller Actions

```php
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function markAsRead(Request $request, int $id)
    {
        $success = $request->user()->markNotificationAsRead($id);

        return response()->json([
            'success' => $success,
            'unread_count' => $request->user()->unreadSystemNotificationsCount()
        ]);
    }

    public function dismiss(Request $request, int $id)
    {
        $success = $request->user()->dismissNotification($id);

        return response()->json(['success' => $success]);
    }

    public function markAllAsRead(Request $request)
    {
        $count = $request->user()->markAllNotificationsAsRead();

        return response()->json([
            'count' => $count,
            'unread_count' => 0
        ]);
    }
}
```

## Pagination

For large notification lists, use pagination:

```php
$notifications = Notification::notDismissedForUser($userId)
    ->orderByDesc('created_at')
    ->paginate(20);

return view('notifications.index', compact('notifications'));
```

## Filtering by Type

Allow users to filter by notification type:

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;

$type = request('type');

$query = Notification::notDismissedForUser($userId);

if ($type) {
    $query->ofType(NotificationType::from($type));
}

$notifications = $query->orderByDesc('created_at')->paginate(20);
```

## Soft Delete vs Dismiss

The module uses **dismissal** rather than deletion:

- Dismissed notifications remain in the database
- Useful for audit trails and analytics
- Users can potentially "undo" dismissals (with custom implementation)

If you need to permanently delete:

```php
// Delete dismissed notifications older than 90 days
Notification::whereHas('users', function ($q) {
    $q->where('is_dismissed', true)
        ->where('dismissed_at', '<', now()->subDays(90));
})->delete();
```

## Real-Time Updates

For real-time notification updates, combine with Laravel Echo and broadcasting:

```php
// In your event
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationSent implements ShouldBroadcast
{
    public function __construct(public Notification $notification, public array $userIds)
    {
    }

    public function broadcastOn(): array
    {
        return array_map(
            fn($userId) => new PrivateChannel("users.{$userId}"),
            $this->userIds
        );
    }
}
```

```javascript
// Frontend
Echo.private(`users.${userId}`)
    .listen('NotificationSent', (e) => {
        // Update notification count
        updateNotificationBadge();
        // Show toast
        showNotificationToast(e.notification);
    });
```

## Best Practices

### Auto-Mark as Read

Consider automatically marking notifications as read when the user views them:

```php
public function show(Notification $notification)
{
    $user = auth()->user();

    if (!$notification->pivot->is_read) {
        $user->markNotificationAsRead($notification->id);
    }

    return view('notifications.show', compact('notification'));
}
```

### Cleanup Old Notifications

Schedule a command to clean up old dismissed notifications:

```php
// In App\Console\Kernel
protected function schedule(Schedule $schedule)
{
    $schedule->command('notifications:cleanup')->monthly();
}
```

```php
// Command
public function handle()
{
    $deleted = Notification::whereDoesntHave('users', function ($q) {
        $q->where('is_dismissed', false);
    })->where('created_at', '<', now()->subMonths(6))->delete();

    $this->info("Deleted {$deleted} old notifications");
}
```

### Cache Unread Count

Cache the unread count to reduce database queries:

```php
public function unreadCount()
{
    return Cache::remember(
        "user.{$userId}.unread_notifications",
        300, // 5 minutes
        fn() => apGetUnreadNotificationCount($userId)
    );
}

// Invalidate cache when marking as read
public function markAsRead($notificationId)
{
    $success = auth()->user()->markNotificationAsRead($notificationId);

    if ($success) {
        Cache::forget("user.{$userId}.unread_notifications");
    }

    return $success;
}
```

## Next Steps

- Configure [Notification Preferences](Notification-Preferences.md) for user control
- Use the [API Reference](API-Reference.md) to explore all available methods
- Learn about [Hooks and Events](Hooks-and-Events.md) to extend functionality
- Review [Database and Migrations](Database-and-Migrations.md) for schema details
