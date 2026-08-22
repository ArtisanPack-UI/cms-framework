<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;

/**
 * Authorization coverage for the user resource endpoints.
 *
 * Unlike UserControllerTest ( which bypasses the policy via
 * `Gate::before` to exercise controller wiring ), these tests exercise the
 * UserPolicy itself: guests are rejected, authenticated-but-unprivileged
 * users get 403 on every action, and a user granted the `users.manage` /
 * `users.delete` capabilities succeeds.
 */

/**
 * Grant the capabilities the UserPolicy checks.
 */
function grantAllUserManagementPermissions(): void
{
    Gate::define( 'users.manage', fn () => true );
    Gate::define( 'users.delete', fn () => true );
}

// --- Guests are rejected ---

test( 'guest cannot access any user endpoint', function ( string $method, string $uri ): void {
    $response = $this->json( $method, $uri );

    $response->assertUnauthorized();
} )->with( [
    'index'   => ['GET', '/api/v1/users'],
    'store'   => ['POST', '/api/v1/users'],
    'show'    => ['GET', '/api/v1/users/1'],
    'update'  => ['PUT', '/api/v1/users/1'],
    'destroy' => ['DELETE', '/api/v1/users/1'],
] );

// --- Authenticated but unprivileged users are forbidden ---

test( 'unprivileged user is forbidden from listing users', function (): void {
    $actor = TestUser::factory()->create();

    $this->actingAs( $actor )->getJson( '/api/v1/users' )->assertForbidden();
} );

test( 'unprivileged user is forbidden from creating users', function (): void {
    $actor = TestUser::factory()->create();

    $this->actingAs( $actor )->postJson( '/api/v1/users', [
        'name'     => 'New User',
        'email'    => 'new@example.com',
        'password' => 'password123',
    ] )->assertForbidden();

    expect( TestUser::where( 'email', 'new@example.com' )->exists() )->toBeFalse();
} );

test( 'unprivileged user is forbidden from viewing a user', function (): void {
    $actor  = TestUser::factory()->create();
    $target = TestUser::factory()->create();

    $this->actingAs( $actor )->getJson( "/api/v1/users/{$target->id}" )->assertForbidden();
} );

test( 'unprivileged user is forbidden from updating a user', function (): void {
    $actor  = TestUser::factory()->create();
    $target = TestUser::factory()->create( ['name' => 'Original'] );

    $this->actingAs( $actor )->putJson( "/api/v1/users/{$target->id}", [
        'name' => 'Hijacked',
    ] )->assertForbidden();

    expect( $target->fresh()->name )->toBe( 'Original' );
} );

test( 'unprivileged user is forbidden from deleting a user', function (): void {
    $actor  = TestUser::factory()->create();
    $target = TestUser::factory()->create();

    $this->actingAs( $actor )->deleteJson( "/api/v1/users/{$target->id}" )->assertForbidden();

    expect( TestUser::find( $target->id ) )->not->toBeNull();
} );

// --- Privileged users succeed ---

test( 'privileged user can list users', function (): void {
    grantAllUserManagementPermissions();
    $actor = TestUser::factory()->create();

    $this->actingAs( $actor )->getJson( '/api/v1/users' )->assertOk();
} );

test( 'privileged user can create a user', function (): void {
    grantAllUserManagementPermissions();
    $actor = TestUser::factory()->create();

    $this->actingAs( $actor )->postJson( '/api/v1/users', [
        'name'     => 'Fresh User',
        'email'    => 'fresh@example.com',
        'password' => 'password123',
    ] )->assertCreated();

    expect( TestUser::where( 'email', 'fresh@example.com' )->exists() )->toBeTrue();
} );

test( 'privileged user can view a user', function (): void {
    grantAllUserManagementPermissions();
    $actor  = TestUser::factory()->create();
    $target = TestUser::factory()->create();

    $this->actingAs( $actor )->getJson( "/api/v1/users/{$target->id}" )->assertOk();
} );

test( 'privileged user can update a user', function (): void {
    grantAllUserManagementPermissions();
    $actor  = TestUser::factory()->create();
    $target = TestUser::factory()->create( ['name' => 'Before'] );

    $this->actingAs( $actor )->putJson( "/api/v1/users/{$target->id}", [
        'name' => 'After',
    ] )->assertOk();

    expect( $target->fresh()->name )->toBe( 'After' );
} );

test( 'privileged user can delete a user', function (): void {
    grantAllUserManagementPermissions();
    $actor  = TestUser::factory()->create();
    $target = TestUser::factory()->create();

    $this->actingAs( $actor )->deleteJson( "/api/v1/users/{$target->id}" )->assertNoContent();

    expect( TestUser::find( $target->id ) )->toBeNull();
} );
