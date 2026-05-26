<?php

declare( strict_types=1 );

/**
 * Integration test covering the realistic `core/query` saved-tree shape
 * the visual-editor persists. Asserts the runtime returns the expected
 * ids in the expected order for a representative payload.
 *
 * @since 2.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Services\QueryRuntime;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    $this->author = TestUser::create( [
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $this->guest = TestUser::create( [
        'name'     => 'Guest',
        'email'    => 'guest@example.com',
        'password' => 'password',
    ] );

    $this->runtime = app( QueryRuntime::class );
} );

it( 'resolves a realistic core/query saved-tree payload to the expected ids', function (): void {
    // Two categories: only "laravel" should match the saved query.
    $laravel = PostCategory::create( ['name' => 'Laravel', 'slug' => 'laravel'] );
    $other   = PostCategory::create( ['name' => 'Other', 'slug' => 'other'] );

    // Five posts, three of which belong to "laravel" and the author.
    $noisy   = Post::create( [
        'title'        => 'Noisy A (excluded)',
        'slug'         => 'noisy-a',
        'author_id'    => $this->author->id,
        'status'       => 'published',
        'published_at' => now()->subMinutes( 10 ),
    ] );
    $noisy->categories()->attach( $other->id );

    $first   = Post::create( [
        'title'        => 'Newest Laravel post',
        'slug'         => 'first',
        'author_id'    => $this->author->id,
        'status'       => 'published',
        'published_at' => now()->subMinutes( 20 ),
    ] );
    $first->categories()->attach( $laravel->id );

    $second  = Post::create( [
        'title'        => 'Middle Laravel post',
        'slug'         => 'second',
        'author_id'    => $this->author->id,
        'status'       => 'published',
        'published_at' => now()->subMinutes( 40 ),
    ] );
    $second->categories()->attach( $laravel->id );

    $third   = Post::create( [
        'title'        => 'Oldest Laravel post',
        'slug'         => 'third',
        'author_id'    => $this->author->id,
        'status'       => 'published',
        'published_at' => now()->subMinutes( 60 ),
    ] );
    $third->categories()->attach( $laravel->id );

    $wrongAuthor = Post::create( [
        'title'        => 'Laravel post by other author',
        'slug'         => 'wrong-author',
        'author_id'    => $this->guest->id,
        'status'       => 'published',
        'published_at' => now()->subMinutes( 30 ),
    ] );
    $wrongAuthor->categories()->attach( $laravel->id );

    // Mirrors the shape that visual-editor's `core/query` block persists
    // when the user picks "latest Laravel posts by Author, two per page,
    // skip the newest one, exclude noisy-a".
    $payload = [
        'postType'  => 'post',
        'perPage'   => 2,
        'pages'     => 2,
        'offset'    => 1,
        'author'    => $this->author->id,
        'orderBy'   => 'date',
        'order'     => 'desc',
        'taxQuery'  => [
            'taxonomy' => 'category',
            'terms'    => [$laravel->id],
            'operator' => 'IN',
        ],
        'postNotIn' => [$noisy->id],
    ];

    $result = $this->runtime->resolve( $payload );

    // Author filter drops $wrongAuthor; taxQuery drops $noisy (also covered
    // by postNotIn); offset=1 skips $first (newest survivor). The cap of 2
    // keeps the next two ($second, $third).
    expect( $result->total() )->toBe( 2 )
        ->and( $result->perPage() )->toBe( 2 )
        ->and( array_map( static fn ( Post $post ): int => $post->id, $result->items() ) )
        ->toBe( [$second->id, $third->id] );
} );

it( 'falls back to an empty result when no posts match', function (): void {
    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'perPage'  => 5,
    ] );

    expect( $result->total())->toBe( 0)
        ->and( $result->items())->toBe( []);
});
