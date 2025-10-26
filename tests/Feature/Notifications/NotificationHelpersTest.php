<?php

use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('apRegisterNotification helper registers notification', function () {
    apRegisterNotification(
        'helper.test',
        'Helper Test',
        'Test Content',
        NotificationType::Info,
        true,
        ['category' => 'test']
    );

    $registered = apGetRegisteredNotifications();

    expect($registered)->toHaveKey('helper.test')
        ->and($registered['helper.test']['title'])->toBe('Helper Test');
});

test('apSendNotification helper sends notification to users', function () {
    $user = User::factory()->create();

    apRegisterNotification('helper.test', 'Test', 'Content');
    $notification = apSendNotification('helper.test', [$user->id]);

    expect($notification)->toBeInstanceOf(Notification::class)
        ->and($notification->users)->toHaveCount(1);
});

test('apSendNotificationByRole helper sends by role', function () {
    // This test would require role functionality
    expect(true)->toBeTrue();
})->skip('Requires role implementation');

test('apSendNotificationToCurrentUser helper sends to authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    apRegisterNotification('helper.test', 'Test', 'Content');
    $notification = apSendNotificationToCurrentUser('helper.test');

    expect($notification)->toBeInstanceOf(Notification::class)
        ->and($notification->users->first()->id)->toBe($user->id);
});

test('apSendNotificationToCurrentUser returns null when not authenticated', function () {
    apRegisterNotification('helper.test', 'Test', 'Content');
    $notification = apSendNotificationToCurrentUser('helper.test');

    expect($notification)->toBeNull();
});

test('apGetNotifications helper retrieves user notifications', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($user->id, ['is_dismissed' => false]);

    $notifications = apGetNotifications($user->id, 10, false);

    expect($notifications)->toHaveCount(1);
});

test('apMarkNotificationAsRead helper marks notification as read', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($user->id, ['is_read' => false]);

    $result = apMarkNotificationAsRead($notification->id, $user->id);

    expect($result)->toBeTrue();
});

test('apDismissNotification helper dismisses notification', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($user->id, ['is_dismissed' => false]);

    $result = apDismissNotification($notification->id, $user->id);

    expect($result)->toBeTrue();
});

test('apMarkAllNotificationsAsRead helper marks all as read', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    $count = apMarkAllNotificationsAsRead($user->id);

    expect($count)->toBe(2);
});

test('apDismissAllNotifications helper dismisses all notifications', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_dismissed' => false]);

    $count = apDismissAllNotifications($user->id);

    expect($count)->toBe(2);
});

test('apGetUnreadNotificationCount helper returns unread count', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();

    $notification1->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);
    $notification2->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    $count = apGetUnreadNotificationCount($user->id);

    expect($count)->toBe(2);
});

test('apGetRegisteredNotifications helper returns all registered notifications', function () {
    apRegisterNotification('test.one', 'Test One', 'Content One');
    apRegisterNotification('test.two', 'Test Two', 'Content Two');

    $registered = apGetRegisteredNotifications();

    expect($registered)->toHaveKey('test.one')
        ->and($registered)->toHaveKey('test.two');
});
