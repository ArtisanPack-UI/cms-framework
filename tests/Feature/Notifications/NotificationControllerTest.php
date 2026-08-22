<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;

uses( Illuminate\Foundation\Testing\RefreshDatabase::class );

test( 'index endpoint coerces string limit query param without TypeError', function (): void {
    $user = User::factory()->create();

    for ( $i = 0; $i < 3; $i++ ) {
        $notification = Notification::factory()->create();
        $notification->users()->attach( $user->id, ['is_read' => false, 'is_dismissed' => false] );
    }

    $response = $this->actingAs( $user, 'sanctum' )
        ->getJson( '/api/v1/notifications?limit=2' );

    $response->assertOk();
    expect( $response->json( 'data' ) )->toHaveCount( 2 );
} );

test( 'index endpoint uses default limit when none provided', function (): void {
    $user = User::factory()->create();

    for ( $i = 0; $i < 5; $i++ ) {
        $notification = Notification::factory()->create();
        $notification->users()->attach( $user->id, ['is_read' => false, 'is_dismissed' => false] );
    }

    $response = $this->actingAs( $user, 'sanctum' )
        ->getJson( '/api/v1/notifications' );

    $response->assertOk();
    expect( $response->json( 'data' ) )->toHaveCount( 5 );
} );

test( 'index endpoint rejects non-integer limit values', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs( $user, 'sanctum' )
        ->getJson( '/api/v1/notifications?limit=abc' );

    $response->assertStatus( 422 );
} );

test( 'show returns the caller\'s own notification', function (): void {
    $user         = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->users()->attach( $user->id, ['is_read' => false, 'is_dismissed' => false] );

    $this->actingAs( $user, 'sanctum' )
        ->getJson( "/api/v1/notifications/{$notification->id}" )
        ->assertOk()
        ->assertJsonPath( 'data.id', $notification->id );
} );

test( 'show returns 404, not 403, for a notification belonging to another user', function (): void {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $notification = Notification::factory()->create();
    $notification->users()->attach( $other->id, ['is_read' => false, 'is_dismissed' => false] );

    // 404 (not 403) so the response cannot be used to enumerate notification ids.
    $this->actingAs( $user, 'sanctum' )
        ->getJson( "/api/v1/notifications/{$notification->id}" )
        ->assertNotFound();
} );

test( 'show returns 404 for a missing notification', function (): void {
    $user = User::factory()->create();

    $this->actingAs( $user, 'sanctum' )
        ->getJson( '/api/v1/notifications/999999' )
        ->assertNotFound();
} );
