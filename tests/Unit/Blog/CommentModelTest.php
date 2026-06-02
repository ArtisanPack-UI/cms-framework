<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->artisan( 'migrate', [ '--database' => 'testing' ] );
} );

function makeCommentTestAuthor(): TestUser
{
    return TestUser::create( [
        'name'     => 'Test Author',
        'email'    => 'author-' . uniqid() . '@example.com',
        'password' => 'password',
    ] );
}

function makeCommentTestPost( TestUser $author ): Post
{
    return Post::create( [
        'title'     => 'A post with comments',
        'slug'      => 'a-post-with-comments-' . uniqid(),
        'content'   => 'Body',
        'author_id' => $author->id,
        'status'    => 'published',
    ] );
}

test( 'a comment can be created against a post', function (): void {
    $post = makeCommentTestPost( makeCommentTestAuthor() );

    $comment = Comment::create( [
        'post_id'      => $post->id,
        'author_name'  => 'Jane Doe',
        'author_email' => 'jane@example.com',
        'content'      => 'Great post!',
        'status'       => Comment::STATUS_APPROVED,
        'approved_at'  => now(),
    ] );

    expect( $comment->id )->toBeInt()
        ->and( $comment->post_id )->toBe( $post->id )
        ->and( $comment->content )->toBe( 'Great post!' )
        ->and( $comment->status )->toBe( Comment::STATUS_APPROVED );
} );

test( 'guest author accessor returns a normalized shape from author_* columns', function (): void {
    $post = makeCommentTestPost( makeCommentTestAuthor() );

    $comment = Comment::create( [
        'post_id'      => $post->id,
        'author_name'  => 'Jane Doe',
        'author_email' => 'jane@example.com',
        'author_url'   => 'https://example.test/jane',
        'content'      => 'Hello',
        'status'       => Comment::STATUS_APPROVED,
    ] );

    expect( $comment->author->name )->toBe( 'Jane Doe' )
        ->and( $comment->author->url )->toBe( 'https://example.test/jane' )
        ->and( $comment->author->is_guest )->toBeTrue();
} );

test( 'authenticated author accessor returns the related user', function (): void {
    $author      = makeCommentTestAuthor();
    $post        = makeCommentTestPost( $author );
    $commenter   = TestUser::create( [
        'name'     => 'Alice Commenter',
        'email'    => 'alice@example.com',
        'password' => 'password',
    ] );

    $comment = Comment::create( [
        'post_id' => $post->id,
        'user_id' => $commenter->id,
        'content' => 'A registered-user comment',
        'status'  => Comment::STATUS_APPROVED,
    ] );

    expect( $comment->author->id )->toBe( $commenter->id )
        ->and( $comment->author->name )->toBe( 'Alice Commenter' );
} );

test( 'avatar_url falls back to a gravatar derived from author_email', function (): void {
    $post = makeCommentTestPost( makeCommentTestAuthor() );

    $comment = Comment::create( [
        'post_id'      => $post->id,
        'author_name'  => 'Jane Doe',
        'author_email' => 'JANE@example.com  ',
        'content'      => 'Hello',
        'status'       => Comment::STATUS_APPROVED,
    ] );

    $expectedHash = md5( 'jane@example.com' );
    expect( $comment->avatar_url )->toContain( $expectedHash )
        ->and( $comment->avatar_url )->toStartWith( 'https://www.gravatar.com/avatar/' );
} );

test( 'permalink builds against the post permalink', function (): void {
    $post    = makeCommentTestPost( makeCommentTestAuthor() );
    $comment = Comment::create( [
        'post_id'     => $post->id,
        'author_name' => 'X',
        'content'     => 'C',
        'status'      => Comment::STATUS_APPROVED,
    ] );

    expect( $comment->permalink )->toBe( $post->permalink . '#comment-' . $comment->id );
} );

test( 'reply_link adds the replytocom query string', function (): void {
    $post    = makeCommentTestPost( makeCommentTestAuthor() );
    $comment = Comment::create( [
        'post_id'     => $post->id,
        'author_name' => 'X',
        'content'     => 'C',
        'status'      => Comment::STATUS_APPROVED,
    ] );

    expect( $comment->reply_link )->toContain( 'replytocom=' . $comment->id );
} );

test( 'approved scope filters to approved status', function (): void {
    $post = makeCommentTestPost( makeCommentTestAuthor() );

    Comment::factory()->for( $post )->create();
    Comment::factory()->for( $post )->pending()->create();
    Comment::factory()->for( $post )->spam()->create();

    $approved = Comment::approved()->get();

    expect( $approved )->toHaveCount( 1 )
        ->and( $approved->first()->status )->toBe( Comment::STATUS_APPROVED );
} );

test( 'topLevel scope filters out replies', function (): void {
    $post   = makeCommentTestPost( makeCommentTestAuthor() );
    $parent = Comment::factory()->for( $post )->create();
    Comment::factory()->for( $post )->replyTo( $parent )->create();

    expect( Comment::topLevel()->count() )->toBe( 1 )
        ->and( Comment::query()->count() )->toBe( 2 );
} );

test( 'replies relation returns child comments', function (): void {
    $post   = makeCommentTestPost( makeCommentTestAuthor() );
    $parent = Comment::factory()->for( $post )->create();
    Comment::factory()->for( $post )->replyTo( $parent )->count( 3 )->create();

    expect( $parent->replies()->count() )->toBe( 3 );
} );

test( 'post comments relation returns only approved by default', function (): void {
    $post = makeCommentTestPost( makeCommentTestAuthor() );

    Comment::factory()->for( $post )->count( 2 )->create();
    Comment::factory()->for( $post )->pending()->create();
    Comment::factory()->for( $post )->spam()->create();

    expect( $post->comments()->count() )->toBe( 2 );
} );

test( 'post comments_count accessor returns the approved total', function (): void {
    $post = makeCommentTestPost( makeCommentTestAuthor() );

    Comment::factory()->for( $post )->count( 4 )->create();
    Comment::factory()->for( $post )->pending()->create();

    expect( $post->comments_count )->toBe( 4 );
} );

test( 'post comments_url returns permalink with #comments anchor', function (): void {
    $post = makeCommentTestPost( makeCommentTestAuthor() );
    expect( $post->comments_url )->toBe( $post->permalink . '#comments' );
} );
