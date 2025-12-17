---
title: Notifications — Notification Preferences
---

# Notifications — Notification Preferences

This guide explains how users can control which notifications they receive and whether to receive email notifications.

## What are Notification Preferences?

Notification preferences allow users to:
- Opt out of specific notification types entirely
- Disable email notifications while keeping in-app notifications
- Maintain granular control over what they receive

Preferences are stored in the `notification_preferences` table with two boolean flags:
- **is_enabled** — Whether to receive in-app notifications for this type
- **email_enabled** — Whether to receive email notifications for this type

## Default Behavior

If a user has **no preference** for a notification type, the system defaults to **enabled**:

```php
// No preference exists for 'post.published'
$user->shouldReceiveNotification('post.published'); // true
$user->shouldReceiveNotificationEmail('post.published'); // true
```

This ensures users receive notifications by default and must explicitly opt out.

## Checking User Preferences

### Using User Model Methods

The `HasNotifications` trait provides convenience methods:

```php
$user = auth()->user();

// Check if user should receive in-app notification
if ($user->shouldReceiveNotification('post.published')) {
    // Send notification
}

// Check if user should receive email
if ($user->shouldReceiveNotificationEmail('post.published')) {
    // Send email
}

// Get the preference object
$preference = $user->getNotificationPreference('post.published');

if ($preference) {
    echo $preference->is_enabled;      // true/false
    echo $preference->email_enabled;   // true/false
}
```

### Retrieving All Preferences

```php
$user = auth()->user();
$preferences = $user->notificationPreferences;

foreach ($preferences as $preference) {
    echo $preference->notification_type;  // e.g., 'post.published'
    echo $preference->is_enabled;         // true/false
    echo $preference->email_enabled;      // true/false
}
```

## Creating and Updating Preferences

### Create a Preference

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;

NotificationPreference::create([
    'user_id' => $userId,
    'notification_type' => 'post.published',
    'is_enabled' => false,        // Disable in-app notifications
    'email_enabled' => false      // Disable email notifications
]);
```

### Update a Preference

```php
$preference = $user->getNotificationPreference('post.published');

if ($preference) {
    $preference->update([
        'is_enabled' => true,
        'email_enabled' => false  // Keep in-app, disable email
    ]);
} else {
    // Create if doesn't exist
    NotificationPreference::create([
        'user_id' => $user->id,
        'notification_type' => 'post.published',
        'is_enabled' => true,
        'email_enabled' => false
    ]);
}
```

### Update or Create

```php
NotificationPreference::updateOrCreate(
    [
        'user_id' => $userId,
        'notification_type' => 'post.published'
    ],
    [
        'is_enabled' => true,
        'email_enabled' => false
    ]
);
```

## Automatic Filtering

When sending notifications, the system **automatically filters** recipients based on preferences:

```php
// Only users who haven't disabled 'newsletter.weekly' will receive it
apSendNotification('newsletter.weekly', [1, 2, 3, 4, 5]);
```

The filtering happens in `NotificationManager`:
1. Check each user's preference for the notification type
2. Exclude users where `is_enabled = false`
3. Send to remaining users
4. If `send_email` is true, filter again for `email_enabled = false`
5. Queue emails only for users with email enabled

## Building a Preferences UI

### Display All Registered Notifications

```php
use function apGetRegisteredNotifications;

$registered = apGetRegisteredNotifications();
$user = auth()->user();

foreach ($registered as $key => $data) {
    $preference = $user->getNotificationPreference($key);

    $isEnabled = $preference ? $preference->is_enabled : true;
    $emailEnabled = $preference ? $preference->email_enabled : true;

    // Display checkboxes for $key
}
```

### Blade Template

```blade
<form method="POST" action="{{ route('preferences.update') }}">
    @csrf

    <h2>Notification Preferences</h2>

    @php
        $registered = apGetRegisteredNotifications();
        $user = auth()->user();
    @endphp

    @foreach($registered as $key => $data)
        @php
            $preference = $user->getNotificationPreference($key);
            $isEnabled = $preference ? $preference->is_enabled : true;
            $emailEnabled = $preference ? $preference->email_enabled : true;
        @endphp

        <div class="preference-row">
            <div class="notification-info">
                <strong>{{ $data['title'] }}</strong>
                <p>{{ $data['content'] }}</p>
                <span class="badge {{ $data['type']->colorClass() }}">
                    {{ $data['type']->label() }}
                </span>
            </div>

            <div class="preference-controls">
                <label>
                    <input
                        type="checkbox"
                        name="preferences[{{ $key }}][is_enabled]"
                        value="1"
                        {{ $isEnabled ? 'checked' : '' }}
                    >
                    In-App Notifications
                </label>

                <label>
                    <input
                        type="checkbox"
                        name="preferences[{{ $key }}][email_enabled]"
                        value="1"
                        {{ $emailEnabled ? 'checked' : '' }}
                    >
                    Email Notifications
                </label>
            </div>
        </div>
    @endforeach

    <button type="submit">Save Preferences</button>
</form>
```

### Controller to Handle Updates

```php
use Illuminate\Http\Request;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;

class NotificationPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $user = auth()->user();
        $preferences = $request->input('preferences', []);

        // Get all registered notification keys
        $registered = apGetRegisteredNotifications();

        foreach ($registered as $key => $data) {
            $isEnabled = isset($preferences[$key]['is_enabled']);
            $emailEnabled = isset($preferences[$key]['email_enabled']);

            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type' => $key
                ],
                [
                    'is_enabled' => $isEnabled,
                    'email_enabled' => $emailEnabled
                ]
            );
        }

        return redirect()->back()->with('success', 'Preferences updated successfully');
    }
}
```

## Livewire Component

```php
use Livewire\Component;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;

class NotificationPreferences extends Component
{
    public $preferences = [];

    public function mount()
    {
        $user = auth()->user();
        $registered = apGetRegisteredNotifications();

        foreach ($registered as $key => $data) {
            $preference = $user->getNotificationPreference($key);

            $this->preferences[$key] = [
                'title' => $data['title'],
                'content' => $data['content'],
                'type' => $data['type'],
                'is_enabled' => $preference ? $preference->is_enabled : true,
                'email_enabled' => $preference ? $preference->email_enabled : true,
            ];
        }
    }

    public function toggleInApp($key)
    {
        $this->preferences[$key]['is_enabled'] = !$this->preferences[$key]['is_enabled'];
        $this->save($key);
    }

    public function toggleEmail($key)
    {
        $this->preferences[$key]['email_enabled'] = !$this->preferences[$key]['email_enabled'];
        $this->save($key);
    }

    protected function save($key)
    {
        NotificationPreference::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'notification_type' => $key
            ],
            [
                'is_enabled' => $this->preferences[$key]['is_enabled'],
                'email_enabled' => $this->preferences[$key]['email_enabled']
            ]
        );

        $this->dispatch('preference-updated');
    }

    public function render()
    {
        return view('livewire.notification-preferences');
    }
}
```

```blade
{{-- resources/views/livewire/notification-preferences.blade.php --}}
<div>
    <h2>Notification Preferences</h2>

    @foreach($preferences as $key => $pref)
        <div class="preference-item">
            <div>
                <strong>{{ $pref['title'] }}</strong>
                <p class="text-sm text-gray-600">{{ $pref['content'] }}</p>
            </div>

            <div class="controls">
                <label>
                    <input
                        type="checkbox"
                        wire:change="toggleInApp('{{ $key }}')"
                        {{ $pref['is_enabled'] ? 'checked' : '' }}
                    >
                    In-App
                </label>

                <label>
                    <input
                        type="checkbox"
                        wire:change="toggleEmail('{{ $key }}')"
                        {{ $pref['email_enabled'] ? 'checked' : '' }}
                    >
                    Email
                </label>
            </div>
        </div>
    @endforeach
</div>
```

## API Endpoints

### Get User Preferences

```php
Route::get('/api/user/notification-preferences', function () {
    $user = auth()->user();
    $registered = apGetRegisteredNotifications();
    $preferences = [];

    foreach ($registered as $key => $data) {
        $preference = $user->getNotificationPreference($key);

        $preferences[$key] = [
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type']->value,
            'is_enabled' => $preference ? $preference->is_enabled : true,
            'email_enabled' => $preference ? $preference->email_enabled : true,
        ];
    }

    return response()->json($preferences);
});
```

### Update Preference

```php
Route::post('/api/user/notification-preferences/{key}', function (Request $request, string $key) {
    $request->validate([
        'is_enabled' => 'required|boolean',
        'email_enabled' => 'required|boolean',
    ]);

    NotificationPreference::updateOrCreate(
        [
            'user_id' => auth()->id(),
            'notification_type' => $key
        ],
        [
            'is_enabled' => $request->boolean('is_enabled'),
            'email_enabled' => $request->boolean('email_enabled')
        ]
    );

    return response()->json(['success' => true]);
});
```

## Practical Examples

### Opt-Out of All Emails

Allow users to disable all email notifications at once:

```php
public function disableAllEmails()
{
    $user = auth()->user();
    $registered = apGetRegisteredNotifications();

    foreach (array_keys($registered) as $key) {
        NotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_type' => $key
            ],
            [
                'is_enabled' => true,     // Keep in-app
                'email_enabled' => false   // Disable email
            ]
        );
    }
}
```

### Reset to Defaults

```php
public function resetToDefaults()
{
    auth()->user()->notificationPreferences()->delete();
}
```

### Preference Groups

Group related notifications for easier management:

```php
$groups = [
    'Content Updates' => ['post.published', 'post.comment', 'post.liked'],
    'Account' => ['user.password_changed', 'user.email_verified'],
    'System' => ['system.maintenance', 'system.update'],
];

foreach ($groups as $groupName => $keys) {
    // Display group with toggle all functionality
}
```

## Best Practices

### Provide Sensible Defaults

Register notification types with appropriate default email settings:

```php
// High priority - email by default
apRegisterNotification(
    key: 'payment.failed',
    title: 'Payment Failed',
    content: '...',
    sendEmail: true
);

// Low priority - in-app only by default
apRegisterNotification(
    key: 'post.liked',
    title: 'Post Liked',
    content: '...',
    sendEmail: false
);
```

### Respect User Choices

Always check preferences before sending:

```php
if (!$user->shouldReceiveNotification('post.comment')) {
    // User opted out, don't send
    return;
}

apSendNotification('post.comment', [$user->id]);
```

The helper functions handle this automatically, but custom implementations should check.

### Offer Granular Control

Allow users to configure both in-app and email separately:

```php
// User can choose:
// - In-app: Yes, Email: Yes (both)
// - In-app: Yes, Email: No (in-app only)
// - In-app: No, Email: No (fully disabled)
```

### Document Notification Purposes

In your preferences UI, clearly explain what each notification type does:

```blade
<div class="notification-description">
    <strong>Post Published</strong>
    <p>Notifies you when a new post is published in categories you follow.</p>
</div>
```

### Warn Before Disabling Critical Notifications

```blade
@if($key === 'security.alert')
    <div class="warning">
        ⚠️ This is a security notification. We recommend keeping it enabled.
    </div>
@endif
```

## Next Steps

- Learn about [Hooks and Events](Hooks-and-Events.md) to extend preference functionality
- Review [Database and Migrations](Database-and-Migrations.md) for schema details
- Use the [API Reference](API-Reference.md) for complete method documentation
- Explore [Sending Notifications](Sending-Notifications.md) to understand how preferences affect delivery
