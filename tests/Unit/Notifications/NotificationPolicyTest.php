<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Policies\NotificationPolicy;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Tests\Support\PlainUser;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;

uses( Illuminate\Foundation\Testing\RefreshDatabase::class );

/**
 * Grant the `notifications.manage` capability to the given user through a role.
 */
function grantNotificationsManage( User $user ): void
{
    $role       = Role::create( ['name' => 'Notifications Manager', 'slug' => 'notifications-manager'] );
    $permission = Permission::create( ['name' => 'Manage Notifications', 'slug' => 'notifications.manage'] );

    $role->permissions()->attach( $permission );
    $user->roles()->attach( $role );
}

test( 'viewAny policy allows all authenticated users', function (): void {
    $user   = User::factory()->create();
    $policy = new NotificationPolicy;

    $result = $policy->viewAny( $user );

    expect( $result )->toBeTrue();
} );

test( 'view policy allows user to view their own notification', function (): void {
    $user         = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach( $user->id );

    $policy = new NotificationPolicy;
    $result = $policy->view( $user, $notification );

    expect( $result )->toBeTrue();
} );

test( 'view policy denies user from viewing others notification', function (): void {
    $user         = User::factory()->create();
    $otherUser    = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach( $otherUser->id );

    $policy = new NotificationPolicy;
    $result = $policy->view( $user, $notification );

    expect( $result )->toBeFalse();
} );

test( 'update policy allows user to update their own notification', function (): void {
    $user         = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach( $user->id );

    $policy = new NotificationPolicy;
    $result = $policy->update( $user, $notification );

    expect( $result )->toBeTrue();
} );

test( 'update policy denies user from updating others notification', function (): void {
    $user         = User::factory()->create();
    $otherUser    = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach( $otherUser->id );

    $policy = new NotificationPolicy;
    $result = $policy->update( $user, $notification );

    expect( $result )->toBeFalse();
} );

test( 'create policy allows user whose role grants notifications.manage', function (): void {
    $user = User::factory()->create();
    grantNotificationsManage( $user );

    $policy = new NotificationPolicy;

    expect( $policy->create( $user ) )->toBeTrue();
} );

test( 'create policy denies user without notifications.manage', function (): void {
    $user = User::factory()->create();

    $policy = new NotificationPolicy;

    expect( $policy->create( $user ) )->toBeFalse();
} );

test( 'delete policy allows user whose role grants notifications.manage', function (): void {
    $user         = User::factory()->create();
    $notification = Notification::factory()->create();
    grantNotificationsManage( $user );

    $policy = new NotificationPolicy;

    expect( $policy->delete( $user, $notification ) )->toBeTrue();
} );

test( 'delete policy denies user without notifications.manage', function (): void {
    $user         = User::factory()->create();
    $notification = Notification::factory()->create();

    $policy = new NotificationPolicy;

    expect( $policy->delete( $user, $notification ) )->toBeFalse();
} );

test( 'create and delete degrade to denial for a host user model without an RBAC trait', function (): void {
    $created      = User::factory()->create();
    $user         = PlainUser::findOrFail( $created->id );
    $notification = Notification::factory()->create();

    $policy = new NotificationPolicy;

    expect( $policy->create( $user ) )->toBeFalse();
    expect( $policy->delete( $user, $notification ) )->toBeFalse();
} );
