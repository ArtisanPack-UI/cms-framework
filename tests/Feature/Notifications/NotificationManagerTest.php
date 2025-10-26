<?php

use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Jobs\SendNotificationEmail;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Managers\NotificationManager;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registerNotification adds notification to registered list', function () {
    $manager = app(NotificationManager::class);

    $manager->registerNotification(
        'test.notification',
        'Test Title',
        'Test Content',
        NotificationType::Info,
        true,
        ['category' => 'test']
    );

    $registered = $manager->getRegisteredNotifications();

    expect($registered)->toHaveKey('test.notification')
        ->and($registered['test.notification']['title'])->toBe('Test Title')
        ->and($registered['test.notification']['content'])->toBe('Test Content')
        ->and($registered['test.notification']['type'])->toBe(NotificationType::Info)
        ->and($registered['test.notification']['send_email'])->toBeTrue()
        ->and($registered['test.notification']['metadata']['category'])->toBe('test');
});

test('sendNotification creates notification and attaches to users', function () {
    $manager = app(NotificationManager::class);
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $manager->registerNotification(
        'test.notification',
        'Test Title',
        'Test Content'
    );

    $notification = $manager->sendNotification('test.notification', [$user1->id, $user2->id]);

    expect($notification)->toBeInstanceOf(Notification::class)
        ->and($notification->users)->toHaveCount(2)
        ->and($notification->title)->toBe('Test Title')
        ->and($notification->content)->toBe('Test Content');
});

test('sendNotification uses defaults from registered notification', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $manager->registerNotification(
        'test.notification',
        'Default Title',
        'Default Content',
        NotificationType::Warning,
        true,
        ['category' => 'system']
    );

    $notification = $manager->sendNotification('test.notification', [$user->id]);

    expect($notification->title)->toBe('Default Title')
        ->and($notification->content)->toBe('Default Content')
        ->and($notification->type)->toBe(NotificationType::Warning)
        ->and($notification->send_email)->toBeTrue()
        ->and($notification->metadata['category'])->toBe('system');
});

test('sendNotification allows overriding registered defaults', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $manager->registerNotification(
        'test.notification',
        'Default Title',
        'Default Content',
        NotificationType::Info
    );

    $notification = $manager->sendNotification('test.notification', [$user->id], [
        'title' => 'Override Title',
        'content' => 'Override Content',
        'type' => NotificationType::Error,
    ]);

    expect($notification->title)->toBe('Override Title')
        ->and($notification->content)->toBe('Override Content')
        ->and($notification->type)->toBe(NotificationType::Error);
});

test('sendNotification respects user notification preferences', function () {
    $manager = app(NotificationManager::class);
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // User2 has disabled this notification type
    NotificationPreference::factory()->create([
        'user_id' => $user2->id,
        'notification_type' => 'test.notification',
        'is_enabled' => false,
    ]);

    $manager->registerNotification('test.notification', 'Test', 'Content');
    $notification = $manager->sendNotification('test.notification', [$user1->id, $user2->id]);

    // Only user1 should receive the notification
    expect($notification->users)->toHaveCount(1)
        ->and($notification->users->first()->id)->toBe($user1->id);
});

test('sendNotification returns null when no users match preferences', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'test.notification',
        'is_enabled' => false,
    ]);

    $manager->registerNotification('test.notification', 'Test', 'Content');
    $notification = $manager->sendNotification('test.notification', [$user->id]);

    expect($notification)->toBeNull();
});

test('sendNotification queues email job when send_email is true', function () {
    Queue::fake();

    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $manager->registerNotification('test.notification', 'Test', 'Content', NotificationType::Info, true);
    $manager->sendNotification('test.notification', [$user->id]);

    Queue::assertPushed(SendNotificationEmail::class);
});

test('sendNotification does not queue email when send_email is false', function () {
    Queue::fake();

    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $manager->registerNotification('test.notification', 'Test', 'Content', NotificationType::Info, false);
    $manager->sendNotification('test.notification', [$user->id]);

    Queue::assertNotPushed(SendNotificationEmail::class);
});

test('sendNotification respects email preferences', function () {
    Queue::fake();

    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'test.notification',
        'is_enabled' => true,
        'email_enabled' => false,
    ]);

    $manager->registerNotification('test.notification', 'Test', 'Content', NotificationType::Info, true);
    $manager->sendNotification('test.notification', [$user->id]);

    Queue::assertNotPushed(SendNotificationEmail::class);
});

test('sendNotificationByRole sends to users with specific role', function () {
    $manager = app(NotificationManager::class);

    // This test would require role functionality to be implemented
    // Placeholder for role-based notification testing
    expect(true)->toBeTrue();
})->skip('Requires role implementation');

test('sendNotificationToCurrentUser sends to authenticated user', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $this->actingAs($user);

    $manager->registerNotification('test.notification', 'Test', 'Content');
    $notification = $manager->sendNotificationToCurrentUser('test.notification');

    expect($notification)->toBeInstanceOf(Notification::class)
        ->and($notification->users)->toHaveCount(1)
        ->and($notification->users->first()->id)->toBe($user->id);
});

test('sendNotificationToCurrentUser returns null when not authenticated', function () {
    $manager = app(NotificationManager::class);

    $manager->registerNotification('test.notification', 'Test', 'Content');
    $notification = $manager->sendNotificationToCurrentUser('test.notification');

    expect($notification)->toBeNull();
});

test('getUserNotifications retrieves notifications for user', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_dismissed' => false]);

    $notifications = $manager->getUserNotifications($user->id, 10, false);

    expect($notifications)->toHaveCount(2);
});

test('getUserNotifications respects limit parameter', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $notification = Notification::factory()->create();
        $notification->users()->attach($user->id, ['is_dismissed' => false]);
    }

    $notifications = $manager->getUserNotifications($user->id, 3, false);

    expect($notifications)->toHaveCount(3);
});

test('getUserNotifications filters unread when unreadOnly is true', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $readNotification = Notification::factory()->create();
    $unreadNotification = Notification::factory()->create();

    $readNotification->users()->attach($user->id, ['is_read' => true, 'is_dismissed' => false]);
    $unreadNotification->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    $notifications = $manager->getUserNotifications($user->id, 10, true);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->id)->toBe($unreadNotification->id);
});

test('getUserNotifications excludes dismissed notifications', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $activeNotification = Notification::factory()->create();
    $dismissedNotification = Notification::factory()->create();

    $activeNotification->users()->attach($user->id, ['is_dismissed' => false]);
    $dismissedNotification->users()->attach($user->id, ['is_dismissed' => true]);

    $notifications = $manager->getUserNotifications($user->id, 10, false);

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->id)->toBe($activeNotification->id);
});

test('markAsRead marks notification as read for user', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();
    $notification = Notification::factory()->create();

    $notification->users()->attach($user->id, ['is_read' => false]);

    $result = $manager->markAsRead($notification->id, $user->id);

    expect($result)->toBeTrue();

    $pivot = $notification->fresh()->users()->where('user_id', $user->id)->first()->pivot;
    expect($pivot->is_read)->toBeTruthy()
        ->and($pivot->read_at)->not->toBeNull();
});

test('markAsRead returns false for non-existent notification', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $result = $manager->markAsRead(9999, $user->id);

    expect($result)->toBeFalse();
});

test('dismissNotification dismisses notification for user', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();
    $notification = Notification::factory()->create();

    $notification->users()->attach($user->id, ['is_dismissed' => false]);

    $result = $manager->dismissNotification($notification->id, $user->id);

    expect($result)->toBeTrue();

    $pivot = $notification->fresh()->users()->where('user_id', $user->id)->first()->pivot;
    expect($pivot->is_dismissed)->toBeTruthy()
        ->and($pivot->dismissed_at)->not->toBeNull();
});

test('markAllAsRead marks all unread notifications as read', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    $count = $manager->markAllAsRead($user->id);

    expect($count)->toBe(2);
});

test('dismissAll dismisses all active notifications', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_dismissed' => false]);

    $count = $manager->dismissAll($user->id);

    expect($count)->toBe(2);
});

test('getUnreadCount returns correct unread count', function () {
    $manager = app(NotificationManager::class);
    $user = User::factory()->create();

    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();
    $notification3 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);
    $notification3->users()->attach($user->id, ['is_read' => true, 'is_dismissed' => false]);

    $count = $manager->getUnreadCount($user->id);

    expect($count)->toBe(2);
});
