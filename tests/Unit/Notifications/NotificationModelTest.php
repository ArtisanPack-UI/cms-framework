<?php

use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can create notification', function () {
    $notification = Notification::factory()->create([
        'title' => 'Test Notification',
        'content' => 'Test content',
        'type' => NotificationType::Info,
    ]);

    expect($notification)->toBeInstanceOf(Notification::class)
        ->and($notification->title)->toBe('Test Notification')
        ->and($notification->content)->toBe('Test content')
        ->and($notification->type)->toBe(NotificationType::Info);
});

test('notification has correct fillable attributes', function () {
    $notification = new Notification;
    $fillable = $notification->getFillable();

    expect($fillable)->toContain('type')
        ->and($fillable)->toContain('title')
        ->and($fillable)->toContain('content')
        ->and($fillable)->toContain('metadata')
        ->and($fillable)->toContain('send_email');
});

test('notification casts type to NotificationType enum', function () {
    $notification = Notification::factory()->create([
        'type' => NotificationType::Success,
    ]);

    expect($notification->type)->toBeInstanceOf(NotificationType::class)
        ->and($notification->type)->toBe(NotificationType::Success);
});

test('notification casts metadata to array', function () {
    $metadata = ['category' => 'system', 'priority' => 'high'];
    $notification = Notification::factory()->create([
        'metadata' => $metadata,
    ]);

    expect($notification->metadata)->toBeArray()
        ->and($notification->metadata)->toBe($metadata);
});

test('notification casts send_email to boolean', function () {
    $notification = Notification::factory()->create([
        'send_email' => true,
    ]);

    expect($notification->send_email)->toBeBool()
        ->and($notification->send_email)->toBeTrue();
});

test('notification can be attached to users', function () {
    $notification = Notification::factory()->create();
    $user = User::factory()->create();

    $notification->users()->attach($user->id);

    expect($notification->users)->toHaveCount(1)
        ->and($notification->users->first()->id)->toBe($user->id);
});

test('notification user pivot includes read and dismissed status', function () {
    $notification = Notification::factory()->create();
    $user = User::factory()->create();

    $notification->users()->attach($user->id, [
        'is_read' => true,
        'read_at' => now(),
        'is_dismissed' => false,
    ]);

    $pivot = $notification->users->first()->pivot;

    expect($pivot->is_read)->toBeTruthy()
        ->and($pivot->read_at)->not->toBeNull()
        ->and($pivot->is_dismissed)->toBeFalsy();
});

test('scopeUnreadForUser returns only unread notifications for user', function () {
    $user = User::factory()->create();
    $readNotification = Notification::factory()->create();
    $unreadNotification = Notification::factory()->create();

    $readNotification->users()->attach($user->id, ['is_read' => true, 'is_dismissed' => false]);
    $unreadNotification->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    $unreadNotifications = Notification::unreadForUser($user->id)->get();

    expect($unreadNotifications)->toHaveCount(1)
        ->and($unreadNotifications->first()->id)->toBe($unreadNotification->id);
});

test('scopeUnreadForUser excludes dismissed notifications', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();

    $notification->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => true]);

    $unreadNotifications = Notification::unreadForUser($user->id)->get();

    expect($unreadNotifications)->toHaveCount(0);
});

test('scopeReadForUser returns only read notifications for user', function () {
    $user = User::factory()->create();
    $readNotification = Notification::factory()->create();
    $unreadNotification = Notification::factory()->create();

    $readNotification->users()->attach($user->id, ['is_read' => true, 'is_dismissed' => false]);
    $unreadNotification->users()->attach($user->id, ['is_read' => false, 'is_dismissed' => false]);

    $readNotifications = Notification::readForUser($user->id)->get();

    expect($readNotifications)->toHaveCount(1)
        ->and($readNotifications->first()->id)->toBe($readNotification->id);
});

test('scopeNotDismissedForUser returns only non-dismissed notifications', function () {
    $user = User::factory()->create();
    $activeNotification = Notification::factory()->create();
    $dismissedNotification = Notification::factory()->create();

    $activeNotification->users()->attach($user->id, ['is_dismissed' => false]);
    $dismissedNotification->users()->attach($user->id, ['is_dismissed' => true]);

    $activeNotifications = Notification::notDismissedForUser($user->id)->get();

    expect($activeNotifications)->toHaveCount(1)
        ->and($activeNotifications->first()->id)->toBe($activeNotification->id);
});

test('scopeOfType filters notifications by type', function () {
    $errorNotification = Notification::factory()->error()->create();
    $warningNotification = Notification::factory()->warning()->create();

    $errorNotifications = Notification::ofType(NotificationType::Error)->get();

    expect($errorNotifications)->toHaveCount(1)
        ->and($errorNotifications->first()->id)->toBe($errorNotification->id);
});

test('factory creates notification with default values', function () {
    $notification = Notification::factory()->create();

    expect($notification->type)->toBeInstanceOf(NotificationType::class)
        ->and($notification->title)->not->toBeNull()
        ->and($notification->content)->not->toBeNull()
        ->and($notification->send_email)->toBeBool();
});

test('factory error state creates error notification', function () {
    $notification = Notification::factory()->error()->create();

    expect($notification->type)->toBe(NotificationType::Error);
});

test('factory warning state creates warning notification', function () {
    $notification = Notification::factory()->warning()->create();

    expect($notification->type)->toBe(NotificationType::Warning);
});

test('factory success state creates success notification', function () {
    $notification = Notification::factory()->success()->create();

    expect($notification->type)->toBe(NotificationType::Success);
});

test('factory info state creates info notification', function () {
    $notification = Notification::factory()->info()->create();

    expect($notification->type)->toBe(NotificationType::Info);
});

test('factory withEmail state enables email sending', function () {
    $notification = Notification::factory()->withEmail()->create();

    expect($notification->send_email)->toBeTrue();
});

test('factory withoutEmail state disables email sending', function () {
    $notification = Notification::factory()->withoutEmail()->create();

    expect($notification->send_email)->toBeFalse();
});
