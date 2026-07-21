<?php

declare( strict_types=1 );

/**
 * Feature coverage for the Post model lifecycle hooks introduced in 2.5.0
 * (issue #196 / Wave 5).
 *
 * Proves each of the five hook fire sites emits with the correct payload and
 * only under the intended conditions — notably that `.published` fires solely
 * on a transition into {@see ContentStatus::Published}, and that `.trashed`
 * fires on soft delete but not on force delete.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.5.0
 */

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Str;

/**
 * Build a Post with sensible defaults so each test can override only the
 * attribute it cares about (status / published_at).
 *
 * @param  array<string, mixed>  $attributes
 */
function createLifecyclePost( array $attributes = [] ): Post
{
    $title = fake()->sentence( 6, true );
    $user  = TestUser::factory()->create();

    return Post::create( array_merge( [
        'title'        => $title,
        'slug'         => Str::slug( $title ) . '-' . Str::random( 5 ),
        'content'      => fake()->paragraph(),
        'excerpt'      => fake()->sentence(),
        'author_id'    => $user->id,
        'status'       => ContentStatus::Draft->value,
        'published_at' => null,
    ], $attributes ) );
}

afterEach( function (): void {
    removeAllActions( 'ap.cmsFramework.post.saving' );
    removeAllActions( 'ap.cmsFramework.post.saved' );
    removeAllActions( 'ap.cmsFramework.post.published' );
    removeAllActions( 'ap.cmsFramework.post.trashed' );
    removeAllActions( 'ap.cmsFramework.post.restored' );
} );

it( 'fires ap.cmsFramework.post.saving before the record is written', function (): void {
    $seen = [];

    addAction( 'ap.cmsFramework.post.saving', function ( Post $post ) use ( & $seen ): void {
        $seen[] = $post->exists;
    } );

    createLifecyclePost();

    expect( $seen )->toHaveCount( 1 );
    expect( $seen[ 0 ] )->toBeFalse();
} );

it( 'fires ap.cmsFramework.post.saved after the record is written', function (): void {
    $received = null;

    addAction( 'ap.cmsFramework.post.saved', function ( Post $post ) use ( & $received ): void {
        $received = $post;
    } );

    $post = createLifecyclePost();

    expect( $received )->not->toBeNull();
    expect( $received->id )->toBe( $post->id );
    expect( $received->exists )->toBeTrue();
} );

it( 'fires ap.cmsFramework.post.published when a draft transitions to published', function (): void {
    $callCount = 0;
    addAction( 'ap.cmsFramework.post.published', function () use ( & $callCount ): void {
        $callCount++;
    } );

    $post = createLifecyclePost();
    expect( $callCount )->toBe( 0 );

    $post->status       = ContentStatus::Published;
    $post->published_at = now();
    $post->save();

    expect( $callCount )->toBe( 1 );
} );

it( 'fires ap.cmsFramework.post.published when a post is created directly as published', function (): void {
    $callCount = 0;
    addAction( 'ap.cmsFramework.post.published', function () use ( & $callCount ): void {
        $callCount++;
    } );

    createLifecyclePost( [
        'status'       => ContentStatus::Published->value,
        'published_at' => now(),
    ] );

    expect( $callCount )->toBe( 1 );
} );

it( 'does not fire ap.cmsFramework.post.published on subsequent saves of an already-published post', function (): void {
    $post = createLifecyclePost( [
        'status'       => ContentStatus::Published->value,
        'published_at' => now(),
    ] );

    $callCount = 0;
    addAction( 'ap.cmsFramework.post.published', function () use ( & $callCount ): void {
        $callCount++;
    } );

    $post->title = 'Post-publish title tweak';
    $post->save();

    expect( $callCount )->toBe( 0 );
} );

it( 'does not fire ap.cmsFramework.post.published when transitioning away from published', function (): void {
    $post = createLifecyclePost( [
        'status'       => ContentStatus::Published->value,
        'published_at' => now(),
    ] );

    $callCount = 0;
    addAction( 'ap.cmsFramework.post.published', function () use ( & $callCount ): void {
        $callCount++;
    } );

    $post->status = ContentStatus::Draft;
    $post->save();

    expect( $callCount )->toBe( 0 );
} );

it( 'fires ap.cmsFramework.post.trashed on soft delete', function (): void {
    $post = createLifecyclePost();

    $received = null;
    addAction( 'ap.cmsFramework.post.trashed', function ( Post $post ) use ( & $received ): void {
        $received = $post;
    } );

    $post->delete();

    expect( $received )->not->toBeNull();
    expect( $received->id )->toBe( $post->id );
    expect( $received->trashed() )->toBeTrue();
} );

it( 'does not fire ap.cmsFramework.post.trashed on force delete', function (): void {
    $post = createLifecyclePost();

    $callCount = 0;
    addAction( 'ap.cmsFramework.post.trashed', function () use ( & $callCount ): void {
        $callCount++;
    } );

    $post->forceDelete();

    expect( $callCount )->toBe( 0 );
} );

it( 'fires ap.cmsFramework.post.restored on restore', function (): void {
    $post = createLifecyclePost();
    $post->delete();

    $received = null;
    addAction( 'ap.cmsFramework.post.restored', function ( Post $post ) use ( & $received ): void {
        $received = $post;
    } );

    $post->restore();

    expect( $received )->not->toBeNull();
    expect( $received->id )->toBe( $post->id );
    expect( $received->trashed() )->toBeFalse();
} );
