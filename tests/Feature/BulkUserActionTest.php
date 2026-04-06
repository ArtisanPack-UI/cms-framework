<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;

/**
 * Grant all user bulk action permissions via Gate.
 */
function grantAllUserPermissions(): void
{
    Gate::define( 'users.delete', fn () => true );
    Gate::define( 'users.manage', fn () => true );
}

// --- Bulk Delete ---

test( 'bulk user delete removes multiple users', function (): void {
    grantAllUserPermissions();
    $actor = TestUser::factory()->create();
    $users = TestUser::factory()->count( 3 )->create();

    $response = $this->actingAs( $actor )->postJson( '/api/v1/users/bulk', [
        'action' => 'delete',
        'ids'    => $users->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 3 );
    expect( $response->json( 'failed' ) )->toBe( 0 );
    expect( $response->json( 'errors' ) )->toBeEmpty();

    foreach ( $users as $user ) {
        expect( TestUser::find( $user->id ) )->toBeNull();
    }
} );

// --- Bulk Activate ---

test( 'bulk user activate sets email_verified_at', function (): void {
    grantAllUserPermissions();
    $actor = TestUser::factory()->create();
    $users = TestUser::factory()->count( 3 )->create( ['email_verified_at' => null] );

    $response = $this->actingAs( $actor )->postJson( '/api/v1/users/bulk', [
        'action' => 'activate',
        'ids'    => $users->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 3 );
    expect( $response->json( 'failed' ) )->toBe( 0 );

    foreach ( $users as $user ) {
        $user->refresh();
        expect( $user->email_verified_at )->not->toBeNull();
    }
} );

// --- Bulk Deactivate ---

test( 'bulk user deactivate clears email_verified_at', function (): void {
    grantAllUserPermissions();
    $actor = TestUser::factory()->create();
    $users = TestUser::factory()->count( 3 )->create( ['email_verified_at' => now()] );

    $response = $this->actingAs( $actor )->postJson( '/api/v1/users/bulk', [
        'action' => 'deactivate',
        'ids'    => $users->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 3 );
    expect( $response->json( 'failed' ) )->toBe( 0 );

    foreach ( $users as $user ) {
        $user->refresh();
        expect( $user->email_verified_at )->toBeNull();
    }
} );

// --- Self-action prevention ---

test( 'bulk user action prevents acting on own account', function (): void {
    grantAllUserPermissions();
    $actor = TestUser::factory()->create();
    $other = TestUser::factory()->create();

    $response = $this->actingAs( $actor )->postJson( '/api/v1/users/bulk', [
        'action' => 'delete',
        'ids'    => [$actor->id, $other->id],
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 1 );
    expect( $response->json( 'failed' ) )->toBe( 1 );
    expect( $response->json( 'errors' ) )->toHaveKey( (string) $actor->id );

    // Actor should still exist
    expect( TestUser::find( $actor->id ) )->not->toBeNull();
    // Other user should be deleted
    expect( TestUser::find( $other->id ) )->toBeNull();
} );

// --- Authorization failures ---

test( 'bulk user delete fails without users.delete permission', function (): void {
    // No permissions granted
    $actor = TestUser::factory()->create();
    $users = TestUser::factory()->count( 2 )->create();

    $response = $this->actingAs( $actor )->postJson( '/api/v1/users/bulk', [
        'action' => 'delete',
        'ids'    => $users->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 0 );
    expect( $response->json( 'failed' ) )->toBe( 2 );
    expect( $response->json( 'errors' ) )->toHaveCount( 2 );

    // Users should still exist
    foreach ( $users as $user ) {
        expect( TestUser::find( $user->id ) )->not->toBeNull();
    }
} );

test( 'bulk user activate fails without users.manage permission', function (): void {
    // Only grant delete, not manage
    Gate::define( 'users.delete', fn () => true );

    $actor = TestUser::factory()->create();
    $users = TestUser::factory()->count( 2 )->create( ['email_verified_at' => null] );

    $response = $this->actingAs( $actor )->postJson( '/api/v1/users/bulk', [
        'action' => 'activate',
        'ids'    => $users->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 0 );
    expect( $response->json( 'failed' ) )->toBe( 2 );

    // Users should still be unverified
    foreach ( $users as $user ) {
        $user->refresh();
        expect( $user->email_verified_at )->toBeNull();
    }
} );

// --- Validation ---

test( 'bulk user action requires action field', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/users/bulk', [
        'ids' => [1],
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['action'] );
} );

test( 'bulk user action requires ids field', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/users/bulk', [
        'action' => 'delete',
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['ids'] );
} );

test( 'bulk user action rejects invalid action', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/users/bulk', [
        'action' => 'invalid',
        'ids'    => [1],
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['action'] );
} );

test( 'bulk user action rejects empty ids array', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/users/bulk', [
        'action' => 'delete',
        'ids'    => [],
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['ids'] );
} );

test( 'bulk user action validates ids exist in database', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/users/bulk', [
        'action' => 'delete',
        'ids'    => [9999],
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['ids.0'] );
} );

// --- Response structure ---

test( 'bulk user action returns correct response structure', function (): void {
    grantAllUserPermissions();
    $actor = TestUser::factory()->create();
    $user  = TestUser::factory()->create();

    $response = $this->actingAs( $actor)->postJson( '/api/v1/users/bulk', [
        'action' => 'delete',
        'ids'    => [$user->id],
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure( ['processed', 'failed', 'errors']);
});
