<?php

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can access system notifications relationship', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($user->id);

    expect($user->systemNotifications)->toHaveCount(1)
        ->and($user->systemNotifications->first()->id)->toBe($notification->id);
});

test('user can access unread system notifications', function () {
    $user = User::factory()->create();
    $readNotification = Notification::factory()->create();
    $unreadNotification = Notification::factory()->create();

    $readNotification->users()->attach($user->id, ['is_read' => true, 'is_dismissed' => false]);
    $unreadNotification->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    expect($user->unreadSystemNotifications)->toHaveCount(1)
        ->and($user->unreadSystemNotifications->first()->id)->toBe($unreadNotification->id);
});

test('user can access notification preferences', function () {
    $user = User::factory()->create();
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'user.registered',
    ]);
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'user.login.failed',
    ]);

    expect($user->notificationPreferences)->toHaveCount(2);
});

test('user can get notification preference for type', function () {
    $user = User::factory()->create();
    $preference = NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'test.type',
    ]);

    $result = $user->getNotificationPreference('test.type');

    expect($result)->toBeInstanceOf(NotificationPreference::class)
        ->and($result->id)->toBe($preference->id);
});

test('shouldReceiveNotification returns true when no preference exists', function () {
    $user = User::factory()->create();

    expect($user->shouldReceiveNotification('test.type'))->toBeTrue();
});

test('shouldReceiveNotification respects user preference', function () {
    $user = User::factory()->create();
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'test.type',
        'is_enabled' => false,
    ]);

    expect($user->shouldReceiveNotification('test.type'))->toBeFalse();
});

test('shouldReceiveNotificationEmail returns true when no preference exists', function () {
    $user = User::factory()->create();

    expect($user->shouldReceiveNotificationEmail('test.type'))->toBeTrue();
});

test('shouldReceiveNotificationEmail respects email preference', function () {
    $user = User::factory()->create();
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'test.type',
        'email_enabled' => false,
    ]);

    expect($user->shouldReceiveNotificationEmail('test.type'))->toBeFalse();
});

test('user can mark notification as read', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($user->id, ['is_read' => false]);

    $result = $user->markNotificationAsRead($notification->id);

    expect($result)->toBeTrue();
    $pivot = $notification->fresh()->users()->where('user_id', $user->id)->first()->pivot;
    expect($pivot->is_read)->toBeTruthy();
});

test('user can dismiss notification', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($user->id, ['is_dismissed' => false]);

    $result = $user->dismissNotification($notification->id);

    expect($result)->toBeTrue();
    $pivot = $notification->fresh()->users()->where('user_id', $user->id)->first()->pivot;
    expect($pivot->is_dismissed)->toBeTruthy();
});

test('user can mark all notifications as read', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    $count = $user->markAllNotificationsAsRead();

    expect($count)->toBe(2);
});

test('user can dismiss all notifications', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_dismissed' => false]);

    $count = $user->dismissAllNotifications();

    expect($count)->toBe(2);
});

test('user can get unread notifications count', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    $count = $user->unreadSystemNotificationsCount();

    expect($count)->toBe(2);
});
