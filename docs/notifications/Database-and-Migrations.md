---
title: Notifications — Database and Migrations
---

# Notifications — Database and Migrations

This guide explains the database schema used by the Notifications module and provides details on the migrations.

## Database Tables

The Notifications module uses three database tables:

1. **notifications** — Stores notification data
2. **notification_user** — Pivot table linking notifications to users with read/dismissed state
3. **notification_preferences** — User preferences for notification types

## Running Migrations

Run the package migrations to create the required tables:

```bash
php artisan migrate
```

The migrations are located in:
```
src/Modules/Notifications/database/migrations/
```

## Table: notifications

Stores the core notification data shared across all recipients.

### Schema

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint unsigned | No | — | Primary key |
| `type` | enum | No | 'info' | Notification type: error, warning, success, info |
| `title` | string | No | — | Notification title |
| `content` | text | No | — | Notification message content |
| `metadata` | json | Yes | NULL | Additional custom data |
| `send_email` | boolean | No | false | Whether email was sent for this notification |
| `created_at` | timestamp | Yes | — | Creation timestamp |
| `updated_at` | timestamp | Yes | — | Last update timestamp |

### Indexes

- `type` — For filtering by notification type
- `created_at` — For ordering by creation date

### Migration

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['error', 'warning', 'success', 'info'])->default('info');
    $table->string('title');
    $table->text('content');
    $table->json('metadata')->nullable();
    $table->boolean('send_email')->default(false);
    $table->timestamps();

    $table->index('type');
    $table->index('created_at');
});
```

### Example Record

```json
{
  "id": 123,
  "type": "success",
  "title": "Post Published",
  "content": "Your post 'Laravel Best Practices' has been published.",
  "metadata": {
    "post_id": 456,
    "category": "tutorials"
  },
  "send_email": true,
  "created_at": "2025-10-25 10:30:00",
  "updated_at": "2025-10-25 10:30:00"
}
```

## Table: notification_user

Pivot table linking notifications to users with user-specific state.

### Schema

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint unsigned | No | — | Primary key |
| `notification_id` | bigint unsigned | No | — | Foreign key to notifications |
| `user_id` | bigint unsigned | No | — | Foreign key to users |
| `is_read` | boolean | No | false | Whether user has read this notification |
| `read_at` | timestamp | Yes | NULL | When user marked as read |
| `is_dismissed` | boolean | No | false | Whether user has dismissed this |
| `dismissed_at` | timestamp | Yes | NULL | When user dismissed |
| `created_at` | timestamp | Yes | — | When notification was sent to user |
| `updated_at` | timestamp | Yes | — | Last update timestamp |

### Foreign Keys

- `notification_id` — References `notifications.id` with `CASCADE ON DELETE`
- `user_id` — References `users.id` with `CASCADE ON DELETE`

### Indexes

- `user_id, is_read` — For querying unread notifications
- `user_id, is_dismissed` — For querying non-dismissed notifications
- `notification_id, user_id` — For efficient joins and lookups

### Migration

```php
Schema::create('notification_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->boolean('is_read')->default(false);
    $table->timestamp('read_at')->nullable();
    $table->boolean('is_dismissed')->default(false);
    $table->timestamp('dismissed_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'is_read']);
    $table->index(['user_id', 'is_dismissed']);
    $table->index(['notification_id', 'user_id']);
});
```

### Example Records

```
+----+-----------------+---------+---------+----------+--------------+---------------+
| id | notification_id | user_id | is_read | read_at  | is_dismissed | dismissed_at  |
+----+-----------------+---------+---------+----------+--------------+---------------+
| 1  | 123             | 1       | false   | NULL     | false        | NULL          |
| 2  | 123             | 2       | true    | 10:32:00 | false        | NULL          |
| 3  | 123             | 3       | true    | 10:35:00 | true         | 10:40:00      |
+----+-----------------+---------+---------+----------+--------------+---------------+
```

In this example:
- User 1 has not read notification 123
- User 2 has read notification 123 but not dismissed it
- User 3 has read and dismissed notification 123

## Table: notification_preferences

Stores user preferences for notification types.

### Schema

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint unsigned | No | — | Primary key |
| `user_id` | bigint unsigned | No | — | Foreign key to users |
| `notification_type` | string | No | — | The notification key (e.g., 'post.published') |
| `is_enabled` | boolean | No | true | Whether user receives in-app notifications |
| `email_enabled` | boolean | No | true | Whether user receives email notifications |
| `created_at` | timestamp | Yes | — | Creation timestamp |
| `updated_at` | timestamp | Yes | — | Last update timestamp |

### Foreign Keys

- `user_id` — References `users.id` with `CASCADE ON DELETE`

### Indexes

- `user_id, notification_type` — **Unique constraint** to prevent duplicate preferences
- `user_id` — For efficient user preference lookups

### Migration

```php
Schema::create('notification_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('notification_type');
    $table->boolean('is_enabled')->default(true);
    $table->boolean('email_enabled')->default(true);
    $table->timestamps();

    $table->unique(['user_id', 'notification_type']);
    $table->index('user_id');
});
```

### Example Records

```
+----+---------+-------------------+------------+---------------+
| id | user_id | notification_type | is_enabled | email_enabled |
+----+---------+-------------------+------------+---------------+
| 1  | 1       | post.published    | true       | false         |
| 2  | 1       | post.comment      | false      | false         |
| 3  | 2       | newsletter.weekly | true       | true          |
+----+---------+-------------------+------------+---------------+
```

In this example:
- User 1 receives in-app notifications for 'post.published' but not emails
- User 1 has disabled all 'post.comment' notifications
- User 2 receives both in-app and email for 'newsletter.weekly'

## Relationships

### Notification Model

```php
// Many-to-many relationship with users
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'notification_user')
        ->withPivot(['is_read', 'read_at', 'is_dismissed', 'dismissed_at'])
        ->withTimestamps();
}
```

### User Model (via HasNotifications Trait)

```php
// All notifications for this user
public function systemNotifications(): BelongsToMany
{
    return $this->belongsToMany(Notification::class, 'notification_user')
        ->withPivot(['is_read', 'read_at', 'is_dismissed', 'dismissed_at'])
        ->withTimestamps()
        ->orderByDesc('created_at');
}

// Only unread notifications
public function unreadSystemNotifications(): BelongsToMany
{
    return $this->systemNotifications()
        ->wherePivot('is_read', false)
        ->wherePivot('is_dismissed', false);
}

// User preferences
public function notificationPreferences(): HasMany
{
    return $this->hasMany(NotificationPreference::class);
}
```

### NotificationPreference Model

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

## Data Flow

### Creating a Notification

When `apSendNotification()` is called:

1. A record is created in `notifications`:
   ```sql
   INSERT INTO notifications (type, title, content, metadata, send_email)
   VALUES ('success', 'Post Published', 'Your post...', '{"post_id": 123}', true);
   ```

2. Records are created in `notification_user` for each recipient:
   ```sql
   INSERT INTO notification_user (notification_id, user_id, is_read, is_dismissed)
   VALUES (123, 1, false, false),
          (123, 2, false, false),
          (123, 3, false, false);
   ```

### Marking as Read

When `markNotificationAsRead()` is called:

```sql
UPDATE notification_user
SET is_read = true, read_at = NOW(), updated_at = NOW()
WHERE notification_id = 123 AND user_id = 1;
```

### Dismissing a Notification

When `dismissNotification()` is called:

```sql
UPDATE notification_user
SET is_dismissed = true, dismissed_at = NOW(), updated_at = NOW()
WHERE notification_id = 123 AND user_id = 1;
```

## Query Patterns

### Get Unread Count for User

```sql
SELECT COUNT(*)
FROM notification_user
WHERE user_id = 1
  AND is_read = false
  AND is_dismissed = false;
```

### Get Recent Notifications for User

```sql
SELECT n.*, nu.is_read, nu.read_at, nu.is_dismissed, nu.dismissed_at
FROM notifications n
INNER JOIN notification_user nu ON n.id = nu.notification_id
WHERE nu.user_id = 1
  AND nu.is_dismissed = false
ORDER BY n.created_at DESC
LIMIT 10;
```

### Get User Preferences

```sql
SELECT *
FROM notification_preferences
WHERE user_id = 1;
```

## Performance Considerations

### Indexes

The migrations include strategic indexes for common queries:

- **notifications.type** — Filter by type (error, warning, success, info)
- **notifications.created_at** — Order by creation date
- **notification_user(user_id, is_read)** — Query unread notifications
- **notification_user(user_id, is_dismissed)** — Query non-dismissed notifications
- **notification_user(notification_id, user_id)** — Join optimization

### Cascade Deletes

Foreign keys use `cascadeOnDelete()` to automatically clean up:
- Deleting a notification removes all `notification_user` records
- Deleting a user removes all their `notification_user` and `notification_preferences` records

### Cleanup Strategy

Consider periodic cleanup of old dismissed notifications:

```php
// Delete notifications dismissed by all recipients over 90 days ago
Notification::whereDoesntHave('users', function ($q) {
    $q->where('is_dismissed', false);
})
->where('created_at', '<', now()->subDays(90))
->delete();
```

## Extending the Schema

### Adding Custom Columns

If you need additional fields, create a new migration:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('action_url')->nullable()->after('content');
            $table->string('icon')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['action_url', 'icon']);
        });
    }
};
```

Then extend the model:

```php
// In a service provider
Notification::created(function ($notification) {
    // Auto-generate action URLs based on metadata
    if (isset($notification->metadata['post_id'])) {
        $notification->update([
            'action_url' => route('posts.show', $notification->metadata['post_id'])
        ]);
    }
});
```

### Adding Indexes

For specific query patterns, add custom indexes:

```php
Schema::table('notifications', function (Blueprint $table) {
    $table->index(['type', 'created_at']);
});
```

## Database Size Estimation

Approximate storage per record:

- **notifications**: ~500 bytes (varies with content length)
- **notification_user**: ~50 bytes
- **notification_preferences**: ~100 bytes

For 10,000 active users receiving 100 notifications each:
- notifications: 100 notifications × 500 bytes = ~50 KB
- notification_user: 10,000 users × 100 notifications × 50 bytes = ~50 MB
- notification_preferences: 10,000 users × 10 types × 100 bytes = ~10 MB

## Backup Considerations

When backing up your database, consider:

- **notifications**: Contains shared notification content
- **notification_user**: Contains user-specific state (can be recreated if needed)
- **notification_preferences**: Critical user preferences (must be backed up)

## Next Steps

- Review the [API Reference](Api-Reference) for helper functions
- Learn about [Managing Notifications](Managing-Notifications) for querying
- Understand [Notification Preferences](Notification-Preferences) for user control
- Explore [Hooks and Events](Hooks-And-Events) for extending functionality
