<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    $this->author = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $this->post = Post::create( [
        'title'     => 'My Post',
        'slug'      => 'my-post',
        'author_id' => $this->author->id,
        'status'    => 'published',
    ] );

    $this->page = Page::create( [
        'title'     => 'Home',
        'slug'      => 'home',
        'author_id' => $this->author->id,
        'status'    => 'published',
    ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.post.permalink' );
    removeAllFilters( 'ap.cmsFramework.page.permalink' );
} );

test( 'post and page keep their default urls with no callback', function (): void {
    expect( $this->post->permalink )->toBe( url( '/blog/my-post' ) )
        ->and( $this->page->permalink )->toBe( url( '/home' ) );
} );

test( 'post filter receives the default url and the post', function (): void {
    $received = null;

    addFilter( 'ap.cmsFramework.post.permalink', function ( string $url, Post $post ) use ( &$received ): string {
        $received = [$url, $post];

        return $url;
    } );

    $this->post->permalink;

    expect( $received[0] )->toBe( url( '/blog/my-post' ) )
        ->and( $received[1]->is( $this->post ) )->toBeTrue();
} );

test( 'post filter replaces the permalink', function (): void {
    addFilter( 'ap.cmsFramework.post.permalink', fn ( string $url, Post $post ): string => url( "/{$post->slug}" ) );

    expect( $this->post->permalink )->toBe( url( '/my-post' ) );
} );

test( 'page filter replaces the permalink', function (): void {
    addFilter( 'ap.cmsFramework.page.permalink', fn ( string $url, Page $page ): string => url( "/pages/{$page->slug}" ) );

    expect( $this->page->permalink )->toBe( url( '/pages/home' ) );
} );

test( 'page filter can return the site root for the homepage', function (): void {
    addFilter( 'ap.cmsFramework.page.permalink', fn ( string $url, Page $page ): string => 'home' === $page->slug ? '/' : $url );

    expect( $this->page->permalink )->toBe( url( '/' ) );
} );

test( 'a root-relative path is made absolute', function (): void {
    addFilter( 'ap.cmsFramework.post.permalink', fn (): string => '/news/my-post' );

    expect( $this->post->permalink )->toBe( url( '/news/my-post' ) );
} );

test( 'an absolute url on another host is kept', function (): void {
    addFilter( 'ap.cmsFramework.post.permalink', fn (): string => 'https://blog.example.com/my-post' );

    expect( $this->post->permalink )->toBe( 'https://blog.example.com/my-post' );
} );

test( 'filters are scoped to their own model', function (): void {
    addFilter( 'ap.cmsFramework.post.permalink', fn (): string => '/filtered' );

    expect( $this->page->permalink )->toBe( url( '/home' ) );
} );

test( 'an unusable filter result falls back to the default url', function ( mixed $result ): void {
    addFilter( 'ap.cmsFramework.post.permalink', fn (): mixed => $result );

    expect( $this->post->permalink )->toBe( url( '/blog/my-post' ) );
} )->with( [
    'null'         => [null],
    'empty string' => [''],
    'false'        => [false],
    'array'        => [['/my-post']],
] );

test( 'urls built on the post permalink follow the filter', function (): void {
    addFilter( 'ap.cmsFramework.post.permalink', fn ( string $url, Post $post ): string => url( "/{$post->slug}" ) );

    $comment = Comment::create( [
        'post_id'   => $this->post->id,
        'author_id' => $this->author->id,
        'content'   => 'A comment',
        'status'    => 'approved',
    ] );

    expect( $this->post->comments_url )->toBe( url( '/my-post' ) . '#comments' )
        ->and( $comment->permalink )->toBe( url( '/my-post' ) . '#comment-' . $comment->id );
} );

test( 'breadcrumbs follow the page filter', function (): void {
    $child = Page::create( [
        'title'     => 'Team',
        'slug'      => 'team',
        'author_id' => $this->author->id,
        'parent_id' => $this->page->id,
        'status'    => 'published',
    ] );

    addFilter( 'ap.cmsFramework.page.permalink', fn ( string $url, Page $page ): string => 'home' === $page->slug ? '/' : $url );

    expect( array_column( $child->breadcrumb, 'url' ) )->toBe( [url( '/' ), url( '/home/team' )] );
} );
