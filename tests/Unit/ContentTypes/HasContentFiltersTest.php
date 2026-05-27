<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\Concerns\HasContentFilters;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'BlogManager uses HasContentFilters trait', function (): void {
    expect( in_array( HasContentFilters::class, class_uses_recursive( BlogManager::class ) ) )->toBeTrue();
} );

test( 'PageManager uses HasContentFilters trait', function (): void {
    expect( in_array( HasContentFilters::class, class_uses_recursive( PageManager::class ) ) )->toBeTrue();
} );

test( 'blog manager status filter defaults to published', function (): void {
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

    $manager = new BlogManager;
    $results = $manager->getArchiveQuery()->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'Published Post' );
} );

test( 'blog manager status filter with draft status', function (): void {
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

    $manager = new BlogManager;
    $results = $manager->getArchiveQuery( ['status' => 'draft'] )->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'Draft Post' );
} );

test( 'blog manager status filter with ContentStatus enum', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'     => 'Draft Post',
        'slug'      => 'draft-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    Post::create( [
        'title'        => 'Published Post',
        'slug'         => 'published-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    $manager = new BlogManager;
    $results = $manager->getArchiveQuery( ['status' => ContentStatus::Draft] )->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'Draft Post' );
} );

test( 'blog manager search filter searches title content and excerpt', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'        => 'Laravel Tutorial',
        'slug'         => 'laravel-tutorial',
        'content'      => 'Learn about PHP frameworks',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    Post::create( [
        'title'        => 'Vue Guide',
        'slug'         => 'vue-guide',
        'content'      => 'Frontend framework guide',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    $manager = new BlogManager;
    $results = $manager->getArchiveQuery( ['search' => 'Laravel'] )->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'Laravel Tutorial' );
} );

test( 'blog manager author filter filters by author', function (): void {
    $author1 = TestUser::create( [
        'name'     => 'Author 1',
        'email'    => 'author1@example.com',
        'password' => 'password',
    ] );

    $author2 = TestUser::create( [
        'name'     => 'Author 2',
        'email'    => 'author2@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'        => 'Post by Author 1',
        'slug'         => 'post-author-1',
        'author_id'    => $author1->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    Post::create( [
        'title'        => 'Post by Author 2',
        'slug'         => 'post-author-2',
        'author_id'    => $author2->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    $manager = new BlogManager;
    $results = $manager->getArchiveQuery( ['author' => $author1->id] )->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'Post by Author 1' );
} );

test( 'page manager status filter with draft status', function (): void {
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

    $manager = new PageManager;
    $results = $manager->getPageQuery( ['status' => 'draft'] )->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'Draft Page' );
} );

test( 'page manager search filter searches title content and excerpt', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Page::create( [
        'title'     => 'About Us',
        'slug'      => 'about-us',
        'content'   => 'Learn about our company',
        'author_id' => $user->id,
        'status'    => 'draft',
        'order'     => 1,
    ] );

    Page::create( [
        'title'     => 'Contact',
        'slug'      => 'contact',
        'content'   => 'Get in touch',
        'author_id' => $user->id,
        'status'    => 'draft',
        'order'     => 2,
    ] );

    $manager = new PageManager;
    $results = $manager->getPageQuery( ['status' => 'draft', 'search' => 'About'] )->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'About Us' );
} );

test( 'page manager does not default to published when no status filter', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Page::create( [
        'title'     => 'Published Page',
        'slug'      => 'published-page',
        'author_id' => $user->id,
        'status'    => 'published',
        'order'     => 1,
    ] );

    Page::create( [
        'title'     => 'Draft Page',
        'slug'      => 'draft-page',
        'author_id' => $user->id,
        'status'    => 'draft',
        'order'     => 2,
    ] );

    $manager = new PageManager;
    $results = $manager->getPageQuery()->get();

    expect( $results )->toHaveCount( 2 );
} );

test( 'blog manager status filter falls back to published for invalid status', function (): void {
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

    $manager = new BlogManager;
    $results = $manager->getArchiveQuery( ['status' => 'nonexistent'] )->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'Published Post' );
} );

test( 'blog manager search filter escapes LIKE wildcards', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'        => 'Sale: 50% off everything',
        'slug'         => 'sale-50-percent',
        'content'      => 'Big sale',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    Post::create( [
        'title'        => 'Top 50 items for your home',
        'slug'         => 'top-50-items',
        'content'      => 'Home decor list',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ] );

    $manager = new BlogManager;

    // Searching for literal "50%" should only match the post with "50%" in the title
    $results = $manager->getArchiveQuery( ['search' => '50%'] )->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->title )->toBe( 'Sale: 50% off everything' );
});
