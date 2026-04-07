<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Grant all page-related permissions via Gate.
 */
function grantAllPagePermissions(): void
{
    Gate::define( 'pages.view', fn () => true );
    Gate::define( 'pages.create', fn () => true );
    Gate::define( 'pages.edit', fn () => true );
    Gate::define( 'pages.editOwn', fn () => true );
    Gate::define( 'pages.delete', fn () => true );
    Gate::define( 'pages.deleteOwn', fn () => true );
    Gate::define( 'pages.publish', fn () => true );
}

/**
 * Grant only view and edit permissions (no delete/publish).
 */
function grantLimitedPagePermissions(): void
{
    Gate::define( 'pages.view', fn () => true );
    Gate::define( 'pages.edit', fn () => true );
    Gate::define( 'pages.editOwn', fn () => true );
}

/**
 * Create a test page with the given attributes.
 *
 * @param  array<string, mixed>  $attributes
 */
function createTestPage( array $attributes = [] ): Page
{
    $title = fake()->sentence( 4, true );

    return Page::create( array_merge( [
        'title'        => $title,
        'slug'         => Str::slug( $title ) . '-' . Str::random( 5 ),
        'content'      => fake()->paragraphs( 3, true ),
        'excerpt'      => fake()->paragraph(),
        'status'       => ContentStatus::Published->value,
        'published_at' => now(),
        'order'        => 0,
        'template'     => 'default',
    ], $attributes ) );
}

// --- Bulk Delete ---

test( 'bulk page delete soft-deletes multiple pages', function (): void {
    grantAllPagePermissions();
    $user = TestUser::factory()->create();

    $pages = collect();
    for ( $i = 0; $i < 3; $i++ ) {
        $pages->push( createTestPage( ['author_id' => $user->id] ) );
    }

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'delete',
        'ids'    => $pages->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 3 );
    expect( $response->json( 'failed' ) )->toBe( 0 );
    expect( $response->json( 'errors' ) )->toBeEmpty();

    foreach ( $pages as $page ) {
        expect( Page::find( $page->id ) )->toBeNull();
        expect( Page::withTrashed()->find( $page->id ) )->not->toBeNull();
    }
} );

// --- Bulk Publish ---

test( 'bulk page publish sets status to published', function (): void {
    grantAllPagePermissions();
    $user = TestUser::factory()->create();

    $pages = collect();
    for ( $i = 0; $i < 3; $i++ ) {
        $pages->push( createTestPage( [
            'author_id'    => $user->id,
            'status'       => ContentStatus::Draft->value,
            'published_at' => null,
        ] ) );
    }

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'publish',
        'ids'    => $pages->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 3 );
    expect( $response->json( 'failed' ) )->toBe( 0 );

    foreach ( $pages as $page ) {
        $page->refresh();
        expect( $page->status )->toBe( ContentStatus::Published );
        expect( $page->published_at )->not->toBeNull();
    }
} );

// --- Bulk Draft ---

test( 'bulk page draft sets status to draft', function (): void {
    grantAllPagePermissions();
    $user = TestUser::factory()->create();

    $pages = collect();
    for ( $i = 0; $i < 3; $i++ ) {
        $pages->push( createTestPage( [
            'author_id'    => $user->id,
            'status'       => ContentStatus::Published->value,
            'published_at' => now(),
        ] ) );
    }

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'draft',
        'ids'    => $pages->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 3 );
    expect( $response->json( 'failed' ) )->toBe( 0 );

    foreach ( $pages as $page ) {
        $page->refresh();
        expect( $page->status )->toBe( ContentStatus::Draft );
        expect( $page->published_at )->toBeNull();
    }
} );

// --- Authorization failures ---

test( 'bulk page delete respects per-item authorization', function (): void {
    grantLimitedPagePermissions();
    $user = TestUser::factory()->create();

    $pages = collect();
    for ( $i = 0; $i < 2; $i++ ) {
        $pages->push( createTestPage( ['author_id' => $user->id] ) );
    }

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'delete',
        'ids'    => $pages->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 0 );
    expect( $response->json( 'failed' ) )->toBe( 2 );
    expect( $response->json( 'errors' ) )->toHaveCount( 2 );
} );

test( 'bulk page publish respects per-item authorization', function (): void {
    grantLimitedPagePermissions();
    $user = TestUser::factory()->create();

    $pages = collect();
    for ( $i = 0; $i < 2; $i++ ) {
        $pages->push( createTestPage( [
            'author_id'    => $user->id,
            'status'       => ContentStatus::Draft->value,
            'published_at' => null,
        ] ) );
    }

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'publish',
        'ids'    => $pages->pluck( 'id' )->toArray(),
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 0 );
    expect( $response->json( 'failed' ) )->toBe( 2 );
} );

// --- Validation ---

test( 'bulk page action requires action field', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'ids' => [1],
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['action'] );
} );

test( 'bulk page action requires ids field', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'delete',
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['ids'] );
} );

test( 'bulk page action rejects invalid action', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'archive',
        'ids'    => [1],
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['action'] );
} );

test( 'bulk page action rejects empty ids array', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'delete',
        'ids'    => [],
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['ids'] );
} );

test( 'bulk page action validates ids exist in database', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'delete',
        'ids'    => [9999],
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( ['ids.0'] );
} );

// --- Mixed results ---

test( 'bulk page action returns mixed results when some items fail authorization', function (): void {
    $owner = TestUser::factory()->create();
    $other = TestUser::factory()->create();

    // Grant delete own only
    Gate::define( 'pages.view', fn () => true );
    Gate::define( 'pages.deleteOwn', fn () => true );

    $ownPage   = createTestPage( ['author_id' => $owner->id] );
    $otherPage = createTestPage( ['author_id' => $other->id] );

    $response = $this->actingAs( $owner )->postJson( '/api/v1/pages/bulk', [
        'action' => 'delete',
        'ids'    => [$ownPage->id, $otherPage->id],
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'processed' ) )->toBe( 1 );
    expect( $response->json( 'failed' ) )->toBe( 1 );
    expect( $response->json( 'errors' ) )->toHaveKey( (string) $otherPage->id );
} );

// --- Response structure ---

test( 'bulk page action returns correct response structure', function (): void {
    grantAllPagePermissions();
    $user = TestUser::factory()->create();

    $page = createTestPage( ['author_id' => $user->id] );

    $response = $this->actingAs( $user )->postJson( '/api/v1/pages/bulk', [
        'action' => 'delete',
        'ids'    => [$page->id],
    ] );

    $response->assertSuccessful();
    $response->assertJsonStructure( ['processed', 'failed', 'errors'] );
} );
