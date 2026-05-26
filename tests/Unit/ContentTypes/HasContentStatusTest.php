<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasContentStatus;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'post model uses HasContentStatus trait', function (): void {
    expect( in_array( HasContentStatus::class, class_uses_recursive( Post::class ) ) )->toBeTrue();
} );

test( 'page model uses HasContentStatus trait', function (): void {
    expect( in_array( HasContentStatus::class, class_uses_recursive( Page::class ) ) )->toBeTrue();
} );

test( 'scopePublished filters published posts via trait', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'        => 'Published Post',
        'slug'         => 'published-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    Post::create( [
        'title'     => 'Draft Post',
        'slug'      => 'draft-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    Post::create( [
        'title'        => 'Future Post',
        'slug'         => 'future-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->addDay(),
    ] );

    $published = Post::published()->get();

    expect( $published )->toHaveCount( 1 );
    expect( $published->first()->title )->toBe( 'Published Post' );
} );

test( 'scopePublished filters published pages via trait', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Page::create( [
        'title'        => 'Published Page',
        'slug'         => 'published-page',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
        'order'        => 1,
    ] );

    Page::create( [
        'title'     => 'Draft Page',
        'slug'      => 'draft-page',
        'author_id' => $user->id,
        'status'    => 'draft',
        'order'     => 2,
    ] );

    $published = Page::published()->get();

    expect( $published )->toHaveCount( 1 );
    expect( $published->first()->title )->toBe( 'Published Page' );
} );

test( 'scopeDraft filters draft content via trait', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'     => 'Published Post',
        'slug'      => 'published-post',
        'author_id' => $user->id,
        'status'    => 'published',
    ] );

    Post::create( [
        'title'     => 'Draft Post',
        'slug'      => 'draft-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    $drafts = Post::draft()->get();

    expect( $drafts )->toHaveCount( 1 );
    expect( $drafts->first()->title )->toBe( 'Draft Post' );
} );

test( 'isPublished returns true for published content via trait', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'        => 'Published Post',
        'slug'         => 'published-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    expect( $post->isPublished() )->toBeTrue();
} );

test( 'isPublished returns false for draft content via trait', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'     => 'Draft Post',
        'slug'      => 'draft-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    expect( $post->isPublished() )->toBeFalse();
} );

test( 'isPublished returns false for future published content via trait', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'        => 'Future Post',
        'slug'         => 'future-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->addDay(),
    ] );

    expect( $post->isPublished() )->toBeFalse();
} );

test( 'isPublished returns true for published content with null published_at via trait', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'        => 'Published Post',
        'slug'         => 'published-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => null,
    ] );

    expect( $post->isPublished() )->toBeTrue();
} );

test( 'scopePublished includes content with null published_at via trait', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'        => 'Published No Date',
        'slug'         => 'published-no-date',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => null,
    ] );

    $published = Post::published()->get();

    expect( $published )->toHaveCount( 1 );
    expect( $published->first()->title )->toBe( 'Published No Date' );
});
