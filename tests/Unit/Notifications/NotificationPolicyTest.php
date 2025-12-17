<?php

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Policies\NotificationPolicy;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('viewAny policy allows all authenticated users', function () {
    $user = User::factory()->create();
    $policy = new NotificationPolicy;

    $result = $policy->viewAny($user);

    expect($result)->toBeTrue();
});

test('view policy allows user to view their own notification', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($user->id);

    $policy = new NotificationPolicy;
    $result = $policy->view($user, $notification);

    expect($result)->toBeTrue();
});

test('view policy denies user from viewing others notification', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($otherUser->id);

    $policy = new NotificationPolicy;
    $result = $policy->view($user, $notification);

    expect($result)->toBeFalse();
});

test('update policy allows user to update their own notification', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($user->id);

    $policy = new NotificationPolicy;
    $result = $policy->update($user, $notification);

    expect($result)->toBeTrue();
});

test('update policy denies user from updating others notification', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach($otherUser->id);

    $policy = new NotificationPolicy;
    $result = $policy->update($user, $notification);

    expect($result)->toBeFalse();
});
