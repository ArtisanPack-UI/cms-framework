<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\CMSFramework\Modules\Blog\Services\QueryRuntime;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Str;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    $this->user = TestUser::create( [
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $this->runtime = app( QueryRuntime::class );
} );

function publishedPost( int $userId, string $title, ?string $publishedAt = null, ?int $offsetMinutes = null ): Post
{
    $publishedAt = $publishedAt ?? now()->subDay()->toDateTimeString();

    if ( null !== $offsetMinutes ) {
        $publishedAt = now()->subMinutes( $offsetMinutes )->toDateTimeString();
    }

    return Post::create( [
        'title'        => $title,
        'slug'         => Str::slug( $title ) . '-' . uniqid(),
        'content'      => 'Body for ' . $title,
        'author_id'    => $userId,
        'status'       => 'published',
        'published_at' => $publishedAt,
    ] );
}

function publishedPage( int $userId, string $title, ?int $parent = null, int $order = 0 ): Page
{
    return Page::create( [
        'title'        => $title,
        'slug'         => Str::slug( $title ) . '-' . uniqid(),
        'content'      => 'Body for ' . $title,
        'author_id'    => $userId,
        'status'       => 'published',
        'published_at' => now()->subDay()->toDateTimeString(),
        'parent_id'    => $parent,
        'order'        => $order,
    ] );
}

it( 'resolves the post archive when postType is post', function (): void {
    $first  = publishedPost( $this->user->id, 'First', null, 60 );
    $second = publishedPost( $this->user->id, 'Second', null, 30 );

    $result = $this->runtime->resolve( ['postType' => 'post'] );

    expect( $result->total() )->toBe( 2 )
        ->and( $result->items()[0]->id )->toBe( $second->id )
        ->and( $result->items()[1]->id )->toBe( $first->id );
} );

it( 'resolves the page archive when postType is page', function (): void {
    $page = publishedPage( $this->user->id, 'About' );

    $result = $this->runtime->resolve( ['postType' => 'page'] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $page->id );
} );

it( 'caps perPage to the documented maximum', function (): void {
    for ( $i = 0; $i < 3; $i++ ) {
        publishedPost( $this->user->id, "Post {$i}" );
    }

    $result = $this->runtime->resolve( ['postType' => 'post', 'perPage' => 999999] );

    expect( $result->perPage() )->toBe( 100 );
} );

it( 'caps total result count when pages is set', function (): void {
    for ( $i = 0; $i < 5; $i++ ) {
        publishedPost( $this->user->id, "Post {$i}" );
    }

    $result = $this->runtime->resolve( ['postType' => 'post', 'perPage' => 100, 'pages' => 2] );

    expect( $result->total() )->toBe( 2 )
        ->and( $result->perPage() )->toBe( 2 );
} );

it( 'applies offset before paginating', function (): void {
    $first  = publishedPost( $this->user->id, 'First', null, 60 );
    $second = publishedPost( $this->user->id, 'Second', null, 30 );

    $result = $this->runtime->resolve( ['postType' => 'post', 'offset' => 1] );

    // Offset means "skip these, treat the rest as the result set". With
    // 2 published posts and offset=1, the older post is the one and
    // only result and `total()` reflects that.
    expect( $result->total() )->toBe( 1 )
        ->and( count( $result->items() ) )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $first->id );
} );

it( 'restricts results with postIn', function (): void {
    $keep = publishedPost( $this->user->id, 'Keep' );
    publishedPost( $this->user->id, 'Skip' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'postIn' => [$keep->id]] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $keep->id );
} );

it( 'excludes results with postNotIn', function (): void {
    $keep = publishedPost( $this->user->id, 'Keep' );
    $skip = publishedPost( $this->user->id, 'Skip' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'postNotIn' => [$skip->id]] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $keep->id );
} );

it( 'merges exclude into postNotIn', function (): void {
    $keep    = publishedPost( $this->user->id, 'Keep' );
    $skipA   = publishedPost( $this->user->id, 'Skip A' );
    $skipB   = publishedPost( $this->user->id, 'Skip B' );

    $result = $this->runtime->resolve( [
        'postType'  => 'post',
        'postNotIn' => [$skipA->id],
        'exclude'   => [$skipB->id],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $keep->id );
} );

it( 'restricts pages by parent ids', function (): void {
    $rootA  = publishedPage( $this->user->id, 'Root A' );
    $rootB  = publishedPage( $this->user->id, 'Root B' );
    $child  = publishedPage( $this->user->id, 'Child', $rootA->id );

    $result = $this->runtime->resolve( ['postType' => 'page', 'parents' => [$rootA->id]] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $child->id );
} );

it( 'orders posts by title asc', function (): void {
    $alpha = publishedPost( $this->user->id, 'Alpha' );
    $beta  = publishedPost( $this->user->id, 'Beta' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'orderBy' => 'title', 'order' => 'asc'] );

    expect( $result->items()[0]->id )->toBe( $alpha->id )
        ->and( $result->items()[1]->id )->toBe( $beta->id );
} );

it( 'orders posts by date desc by default', function (): void {
    $older = publishedPost( $this->user->id, 'Older', null, 120 );
    $newer = publishedPost( $this->user->id, 'Newer', null, 5 );

    $result = $this->runtime->resolve( ['postType' => 'post'] );

    expect( $result->items()[0]->id )->toBe( $newer->id )
        ->and( $result->items()[1]->id )->toBe( $older->id );
} );

it( 'orders pages by menu_order when requested', function (): void {
    $last  = publishedPage( $this->user->id, 'Last', null, 30 );
    $first = publishedPage( $this->user->id, 'First', null, 1 );

    $result = $this->runtime->resolve( [
        'postType' => 'page',
        'orderBy'  => 'menu_order',
        'order'    => 'asc',
    ] );

    expect( $result->items()[0]->id )->toBe( $first->id )
        ->and( $result->items()[1]->id )->toBe( $last->id );
} );

it( 'falls back to date when posts are ordered by menu_order', function (): void {
    $older = publishedPost( $this->user->id, 'Older', null, 120 );
    $newer = publishedPost( $this->user->id, 'Newer', null, 5 );

    $result = $this->runtime->resolve( ['postType' => 'post', 'orderBy' => 'menu_order', 'order' => 'desc'] );

    expect( $result->items()[0]->id )->toBe( $newer->id )
        ->and( $result->items()[1]->id )->toBe( $older->id );
} );

it( 'filters by author', function (): void {
    $other = TestUser::create( [
        'name'     => 'Other',
        'email'    => 'other@example.com',
        'password' => 'password',
    ] );

    $mine = publishedPost( $this->user->id, 'Mine' );
    publishedPost( $other->id, 'Theirs' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'author' => $this->user->id] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $mine->id );
} );

it( 'searches across title content excerpt', function (): void {
    $matched = publishedPost( $this->user->id, 'About distributed cache patterns' );
    publishedPost( $this->user->id, 'Pets' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'search' => 'distributed'] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $matched->id );
} );

it( 'filters posts by category through taxQuery IN', function (): void {
    $cat   = PostCategory::create( ['name' => 'Laravel', 'slug' => 'laravel'] );
    $other = PostCategory::create( ['name' => 'PHP', 'slug' => 'php'] );

    $tagged = publishedPost( $this->user->id, 'Eloquent tips' );
    $tagged->categories()->attach( $cat->id );

    $untagged = publishedPost( $this->user->id, 'Random' );
    $untagged->categories()->attach( $other->id );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'category', 'terms' => [$cat->id], 'operator' => 'IN'],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $tagged->id );
} );

it( 'filters posts by post_tag through taxQuery IN', function (): void {
    $tag    = PostTag::create( ['name' => 'Cache', 'slug' => 'cache'] );
    $tagged = publishedPost( $this->user->id, 'Caching' );
    $tagged->tags()->attach( $tag->id );

    publishedPost( $this->user->id, 'Untagged' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'post_tag', 'terms' => [$tag->id]],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $tagged->id );
} );

it( 'ignores taxQuery operators outside the V1 IN subset', function (): void {
    $cat = PostCategory::create( ['name' => 'Laravel', 'slug' => 'laravel'] );
    $a   = publishedPost( $this->user->id, 'A' );
    $a->categories()->attach( $cat->id );
    publishedPost( $this->user->id, 'B' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'category', 'terms' => [$cat->id], 'operator' => 'NOT IN'],
    ] );

    // `NOT IN` is not implemented in V1 — the runtime drops the
    // constraint entirely, so both posts come back.
    expect( $result->total() )->toBe( 2 );
} );

it( 'throws when an unknown postType is requested', function (): void {
    $this->runtime->resolve( ['postType' => 'something-unregistered'] );
} )->throws( InvalidArgumentException::class, 'unknown post type');
