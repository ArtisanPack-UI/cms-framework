<?php

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can create notification preference', function () {
    $user = User::factory()->create();
    $preference = NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'user.registered',
        'is_enabled' => true,
        'email_enabled' => false,
    ]);

    expect($preference)->toBeInstanceOf(NotificationPreference::class)
        ->and($preference->user_id)->toBe($user->id)
        ->and($preference->notification_type)->toBe('user.registered')
        ->and($preference->is_enabled)->toBeTrue()
        ->and($preference->email_enabled)->toBeFalse();
});

test('notification preference has correct fillable attributes', function () {
    $preference = new NotificationPreference;
    $fillable = $preference->getFillable();

    expect($fillable)->toContain('user_id')
        ->and($fillable)->toContain('notification_type')
        ->and($fillable)->toContain('is_enabled')
        ->and($fillable)->toContain('email_enabled');
});

test('notification preference casts is_enabled to boolean', function () {
    $preference = NotificationPreference::factory()->create([
        'is_enabled' => true,
    ]);

    expect($preference->is_enabled)->toBeBool()
        ->and($preference->is_enabled)->toBeTrue();
});

test('notification preference casts email_enabled to boolean', function () {
    $preference = NotificationPreference::factory()->create([
        'email_enabled' => false,
    ]);

    expect($preference->email_enabled)->toBeBool()
        ->and($preference->email_enabled)->toBeFalse();
});

test('notification preference belongs to user', function () {
    $user = User::factory()->create();
    $preference = NotificationPreference::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($preference->user)->toBeInstanceOf(User::class)
        ->and($preference->user->id)->toBe($user->id);
});

test('user can have multiple notification preferences', function () {
    $user = User::factory()->create();

    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'user.registered',
    ]);
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'user.login.failed',
    ]);
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'system.error',
    ]);

    expect($user->notificationPreferences)->toHaveCount(3);
});

test('unique constraint prevents duplicate preferences for same user and type', function () {
    $user = User::factory()->create();

    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => 'user.registered',
    ]);

    // Attempt to create duplicate should throw exception
    expect(function () use ($user) {
        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => 'user.registered',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

test('factory enabled state creates enabled preference', function () {
    $preference = NotificationPreference::factory()->enabled()->create();

    expect($preference->is_enabled)->toBeTrue();
});

test('factory disabled state creates disabled preference', function () {
    $preference = NotificationPreference::factory()->disabled()->create();

    expect($preference->is_enabled)->toBeFalse();
});

test('factory emailEnabled state enables email notifications', function () {
    $preference = NotificationPreference::factory()->emailEnabled()->create();

    expect($preference->email_enabled)->toBeTrue();
});

test('factory emailDisabled state disables email notifications', function () {
    $preference = NotificationPreference::factory()->emailDisabled()->create();

    expect($preference->email_enabled)->toBeFalse();
});
