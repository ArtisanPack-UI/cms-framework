<?php

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Carbon\Carbon;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'post can be created with required attributes', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'     => 'Test Post',
        'slug'      => 'test-post',
        'content'   => 'This is test content',
        'excerpt'   => 'Test excerpt',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    expect( $post )->toBeInstanceOf( Post::class );
    expect( $post->title )->toBe( 'Test Post' );
    expect( $post->slug )->toBe( 'test-post' );
    expect( $post->status )->toBe( 'draft' );
    expect( $post->author_id )->toBe( $user->id );
} );

test( 'published scope returns only published posts', function (): void {
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
        'title'        => 'Scheduled Post',
        'slug'         => 'scheduled-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->addDay(),
    ] );

    $publishedPosts = Post::published()->get();

    expect( $publishedPosts )->toHaveCount( 1 );
    expect( $publishedPosts->first()->title )->toBe( 'Published Post' );
} );

test( 'draft scope returns only draft posts', function (): void {
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

    $draftPosts = Post::draft()->get();

    expect( $draftPosts )->toHaveCount( 1 );
    expect( $draftPosts->first()->title )->toBe( 'Draft Post' );
} );

test( 'by author scope filters posts by author', function (): void {
    $author1 = TestUser::create( [
        'name'     => 'Author One',
        'email'    => 'author1@example.com',
        'password' => 'password',
    ] );

    $author2 = TestUser::create( [
        'name'     => 'Author Two',
        'email'    => 'author2@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'     => 'Post by Author 1',
        'slug'      => 'post-by-author-1',
        'author_id' => $author1->id,
        'status'    => 'published',
    ] );

    Post::create( [
        'title'     => 'Post by Author 2',
        'slug'      => 'post-by-author-2',
        'author_id' => $author2->id,
        'status'    => 'published',
    ] );

    $author1Posts = Post::byAuthor( $author1->id )->get();

    expect( $author1Posts )->toHaveCount( 1 );
    expect( $author1Posts->first()->title )->toBe( 'Post by Author 1' );
} );

test( 'by category scope filters posts by category', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $category = PostCategory::create( [
        'name' => 'Technology',
        'slug' => 'technology',
    ] );

    $post = Post::create( [
        'title'     => 'Tech Post',
        'slug'      => 'tech-post',
        'author_id' => $user->id,
        'status'    => 'published',
    ] );

    $post->categories()->attach( $category->id );

    Post::create( [
        'title'     => 'Other Post',
        'slug'      => 'other-post',
        'author_id' => $user->id,
        'status'    => 'published',
    ] );

    $techPosts = Post::byCategory( $category->id )->get();

    expect( $techPosts )->toHaveCount( 1 );
    expect( $techPosts->first()->title )->toBe( 'Tech Post' );
} );

test( 'by tag scope filters posts by tag', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $tag = PostTag::create( [
        'name' => 'Laravel',
        'slug' => 'laravel',
    ] );

    $post = Post::create( [
        'title'     => 'Laravel Post',
        'slug'      => 'laravel-post',
        'author_id' => $user->id,
        'status'    => 'published',
    ] );

    $post->tags()->attach( $tag->id );

    Post::create( [
        'title'     => 'Other Post',
        'slug'      => 'other-post',
        'author_id' => $user->id,
        'status'    => 'published',
    ] );

    $laravelPosts = Post::byTag( $tag->id )->get();

    expect( $laravelPosts )->toHaveCount( 1 );
    expect( $laravelPosts->first()->title )->toBe( 'Laravel Post' );
} );

test( 'by year scope filters posts by year', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'        => '2023 Post',
        'slug'         => '2023-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => Carbon::create( 2023, 6, 15 ),
    ] );

    Post::create( [
        'title'        => '2024 Post',
        'slug'         => '2024-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => Carbon::create( 2024, 3, 10 ),
    ] );

    $posts2023 = Post::byYear( 2023 )->get();

    expect( $posts2023 )->toHaveCount( 1 );
    expect( $posts2023->first()->title )->toBe( '2023 Post' );
} );

test( 'by month scope filters posts by month and year', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    Post::create( [
        'title'        => 'June Post',
        'slug'         => 'june-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => Carbon::create( 2023, 6, 15 ),
    ] );

    Post::create( [
        'title'        => 'July Post',
        'slug'         => 'july-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => Carbon::create( 2023, 7, 10 ),
    ] );

    $junePosts = Post::byMonth( 2023, 6 )->get();

    expect( $junePosts )->toHaveCount( 1 );
    expect( $junePosts->first()->title )->toBe( 'June Post' );
} );

test( 'by date scope filters posts by specific date', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $date = Carbon::create( 2023, 6, 15 );

    Post::create( [
        'title'        => 'June 15 Post',
        'slug'         => 'june-15-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => $date,
    ] );

    Post::create( [
        'title'        => 'June 16 Post',
        'slug'         => 'june-16-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => Carbon::create( 2023, 6, 16 ),
    ] );

    $postsOnDate = Post::byDate( $date )->get();

    expect( $postsOnDate )->toHaveCount( 1 );
    expect( $postsOnDate->first()->title )->toBe( 'June 15 Post' );
} );

test( 'is published method returns true for published posts', function (): void {
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

test( 'is published method returns false for draft posts', function (): void {
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

test( 'is published method returns false for scheduled posts', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'        => 'Scheduled Post',
        'slug'         => 'scheduled-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now()->addDay(),
    ] );

    expect( $post->isPublished() )->toBeFalse();
} );

test( 'post has author relationship', function (): void {
    $user = TestUser::create( [
        'name'     => 'John Doe',
        'email'    => 'john@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'     => 'Test Post',
        'slug'      => 'test-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    expect( $post->author )->toBeInstanceOf( TestUser::class );
    expect( $post->author->name )->toBe( 'John Doe' );
} );

test( 'post has categories relationship', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'     => 'Test Post',
        'slug'      => 'test-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    $category = PostCategory::create( [
        'name' => 'News',
        'slug' => 'news',
    ] );

    $post->categories()->attach( $category->id );

    expect( $post->categories )->toHaveCount( 1 );
    expect( $post->categories->first()->name )->toBe( 'News' );
} );

test( 'post has tags relationship', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'     => 'Test Post',
        'slug'      => 'test-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    $tag = PostTag::create( [
        'name' => 'PHP',
        'slug' => 'php',
    ] );

    $post->tags()->attach( $tag->id );

    expect( $post->tags )->toHaveCount( 1 );
    expect( $post->tags->first()->name )->toBe( 'PHP' );
} );

test( 'permalink attribute generates correct url', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'     => 'My Blog Post',
        'slug'      => 'my-blog-post',
        'author_id' => $user->id,
        'status'    => 'published',
    ] );

    expect( $post->permalink )->toContain( '/blog/my-blog-post' );
} );

test( 'post casts published at as datetime', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $publishedAt = now();

    $post = Post::create( [
        'title'        => 'Test Post',
        'slug'         => 'test-post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => $publishedAt,
    ] );

    expect( $post->published_at )->toBeInstanceOf( Carbon::class );
    expect( $post->published_at->format( 'Y-m-d H:i' ) )->toBe( $publishedAt->format( 'Y-m-d H:i' ) );
} );

test( 'post casts metadata as array', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $metadata = ['views' => 100, 'featured' => true];

    $post = Post::create( [
        'title'     => 'Test Post',
        'slug'      => 'test-post',
        'author_id' => $user->id,
        'status'    => 'published',
        'metadata'  => $metadata,
    ] );

    expect( $post->metadata )->toBeArray();
    expect( $post->metadata )->toBe( $metadata );
} );

test( 'post uses soft deletes', function (): void {
    $user = TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ]);

    $post = Post::create( [
        'title'     => 'Test Post',
        'slug'      => 'test-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ]);

    $postId = $post->id;

    $post->delete();

    expect( Post::find( $postId))->toBeNull();
    expect( Post::withTrashed()->find( $postId))->not->toBeNull();
});
