<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

function grantAllCommentPermissions(): void
{
    Gate::define( 'comments.view', fn () => true );
    Gate::define( 'comments.create', fn () => true );
    Gate::define( 'comments.edit', fn () => true );
    Gate::define( 'comments.editOwn', fn () => true );
    Gate::define( 'comments.delete', fn () => true );
    Gate::define( 'comments.deleteOwn', fn () => true );
    Gate::define( 'comments.moderate', fn () => true );
}

function createCommentTestPost( ?TestUser $author = null ): Post
{
    $author ??= TestUser::factory()->create();
    $title    = fake()->sentence( 6, true );

    return Post::create( [
        'title'        => $title,
        'slug'         => Str::slug( $title ) . '-' . Str::random( 5 ),
        'content'      => fake()->paragraphs( 2, true ),
        'author_id'    => $author->id,
        'status'       => 'published',
        'published_at' => now(),
    ] );
}

test( 'index returns approved top-level comments for a post, paginated', function (): void {
    $post = createCommentTestPost();

    Comment::factory()->for( $post )->count( 3 )->create();
    Comment::factory()->for( $post )->pending()->create();
    Comment::factory()->for( $post )->spam()->create();

    $response = $this->getJson( "/api/v1/comments?post_id={$post->id}" );

    $response->assertSuccessful();
    expect( $response->json( 'data' ) )->toHaveCount( 3 );
    foreach ( $response->json( 'data' ) as $row ) {
        expect( $row['status'] )->toBe( Comment::STATUS_APPROVED );
        expect( $row['post_id'] )->toBe( $post->id );
    }
} );

test( 'index excludes replies by default but includes them via parent_id filter', function (): void {
    $post   = createCommentTestPost();
    $parent = Comment::factory()->for( $post )->create();
    Comment::factory()->for( $post )->replyTo( $parent )->count( 2 )->create();

    $top = $this->getJson( "/api/v1/comments?post_id={$post->id}" );
    expect( $top->json( 'data' ) )->toHaveCount( 1 );

    $replies = $this->getJson( "/api/v1/comments?post_id={$post->id}&parent_id={$parent->id}" );
    expect( $replies->json( 'data' ) )->toHaveCount( 2 );
} );

test( 'show returns a single approved comment to anonymous viewers', function (): void {
    $post    = createCommentTestPost();
    $comment = Comment::factory()->for( $post )->create();

    $response = $this->getJson( "/api/v1/comments/{$comment->id}" );

    $response->assertSuccessful();
    expect( $response->json( 'data.id' ) )->toBe( $comment->id )
        ->and( $response->json( 'data.author.is_guest' ) )->toBeTrue()
        ->and( $response->json( 'data.permalink' ) )->toContain( '#comment-' . $comment->id );
} );

test( 'show rejects anonymous access to a non-approved comment', function (): void {
    $post    = createCommentTestPost();
    $comment = Comment::factory()->for( $post )->pending()->create();

    $response = $this->getJson( "/api/v1/comments/{$comment->id}" );

    $response->assertForbidden();
} );

test( 'store creates a new comment for an authenticated user', function (): void {
    grantAllCommentPermissions();
    $author     = TestUser::factory()->create();
    $post       = createCommentTestPost( $author );
    $commenter  = TestUser::factory()->create();

    $response = $this->actingAs( $commenter )->postJson( '/api/v1/comments', [
        'post_id' => $post->id,
        'content' => 'A new comment from an authenticated user.',
    ] );

    $response->assertCreated();
    expect( Comment::count() )->toBe( 1 );
    expect( $response->json( 'data.content' ) )->toBe( 'A new comment from an authenticated user.' );
    expect( $response->json( 'data.status' ) )->toBe( Comment::STATUS_APPROVED );
} );

test( 'store defaults to pending status for non-moderator users', function (): void {
    // Only grant create, not moderate — the controller should set
    // status=pending rather than auto-approving.
    Gate::define( 'comments.create', fn () => true );
    $post      = createCommentTestPost();
    $commenter = TestUser::factory()->create();

    $response = $this->actingAs( $commenter )->postJson( '/api/v1/comments', [
        'post_id'      => $post->id,
        'author_name'  => 'Jane',
        'author_email' => 'jane@example.com',
        'content'      => 'Pending guest comment',
    ] );

    $response->assertCreated();
    expect( $response->json( 'data.status' ) )->toBe( Comment::STATUS_PENDING );
} );

test( 'update changes the status to approved and stamps approved_at', function (): void {
    grantAllCommentPermissions();
    $user    = TestUser::factory()->create();
    $post    = createCommentTestPost();
    $comment = Comment::factory()->for( $post )->pending()->create();

    $response = $this->actingAs( $user )->putJson( "/api/v1/comments/{$comment->id}", [
        'status' => Comment::STATUS_APPROVED,
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'data.status' ) )->toBe( Comment::STATUS_APPROVED );
    expect( $comment->fresh()->approved_at )->not->toBeNull();
} );

test( 'destroy soft-deletes the comment for authorized users', function (): void {
    grantAllCommentPermissions();
    $user    = TestUser::factory()->create();
    $post    = createCommentTestPost();
    $comment = Comment::factory()->for( $post )->create();

    $response = $this->actingAs( $user )->deleteJson( "/api/v1/comments/{$comment->id}" );

    $response->assertNoContent();
    expect( Comment::query()->withTrashed()->find( $comment->id )->trashed() )->toBeTrue();
} );

test( 'unauthenticated update is rejected', function (): void {
    $post    = createCommentTestPost();
    $comment = Comment::factory()->for( $post )->create();

    $response = $this->putJson( "/api/v1/comments/{$comment->id}", [
        'content' => 'attempted update',
    ] );

    $response->assertUnauthorized();
} );

test( 'non-moderator cannot self-approve via the status field on store', function (): void {
    Gate::define( 'comments.create', fn () => true );
    // Deliberately NOT granting comments.moderate.
    $post      = createCommentTestPost();
    $commenter = TestUser::factory()->create();

    $response = $this->actingAs( $commenter )->postJson( '/api/v1/comments', [
        'post_id' => $post->id,
        'content' => 'Trying to self-approve',
        'status'  => Comment::STATUS_APPROVED,
    ] );

    $response->assertCreated();
    // Server should have ignored the client-supplied status and
    // fallen through to the pending default.
    expect( $response->json( 'data.status' ) )->toBe( Comment::STATUS_PENDING );
} );

test( 'client-supplied user_id is ignored on store', function (): void {
    grantAllCommentPermissions();
    $post                = createCommentTestPost();
    $commenter           = TestUser::factory()->create();
    $impersonationTarget = TestUser::factory()->create();

    $response = $this->actingAs( $commenter )->postJson( '/api/v1/comments', [
        'post_id' => $post->id,
        'content' => 'Trying to impersonate',
        'user_id' => $impersonationTarget->id,
    ] );

    $response->assertCreated();
    expect( $response->json( 'data.user_id' ) )->toBe( $commenter->id );
} );

test( 'public callers cannot request non-approved comments via the status query string', function (): void {
    $post = createCommentTestPost();
    Comment::factory()->for( $post )->count( 2 )->create();
    Comment::factory()->for( $post )->pending()->count( 3 )->create();

    $response = $this->getJson(
        "/api/v1/comments?post_id={$post->id}&status=" . Comment::STATUS_PENDING,
    );

    $response->assertSuccessful();
    // Public should still only see the approved set, regardless of
    // the `status=pending` query string.
    expect( $response->json( 'data' ) )->toHaveCount( 2 );
    foreach ( $response->json( 'data' ) as $row ) {
        expect( $row['status'] )->toBe( Comment::STATUS_APPROVED );
    }
} );

test( 'edit_link is hidden from non-moderator viewers', function (): void {
    $post    = createCommentTestPost();
    $comment = Comment::factory()->for( $post )->create();

    $response = $this->getJson( "/api/v1/comments/{$comment->id}" );

    $response->assertSuccessful();
    expect( $response->json( 'data' ) )->not->toHaveKey( 'edit_link' );
} );

test( 'edit_link is included for moderators', function (): void {
    grantAllCommentPermissions();
    $moderator = TestUser::factory()->create();
    $post      = createCommentTestPost();
    $comment   = Comment::factory()->for( $post )->create();

    $response = $this->actingAs( $moderator )->getJson( "/api/v1/comments/{$comment->id}" );

    $response->assertSuccessful();
    expect( $response->json( 'data.edit_link' ) )->toBeString()
        ->and( $response->json( 'data.edit_link' ) )->toContain( 'comments/' . $comment->id );
} );

test( 'per_page values outside the 1..100 window are clamped', function (): void {
    $post = createCommentTestPost();
    Comment::factory()->for( $post )->count( 5 )->create();

    $tooSmall = $this->getJson( "/api/v1/comments?post_id={$post->id}&per_page=0" );
    expect( $tooSmall->json( 'meta.per_page' ) )->toBe( 1 );

    $tooLarge = $this->getJson( "/api/v1/comments?post_id={$post->id}&per_page=9999" );
    expect( $tooLarge->json( 'meta.per_page' ) )->toBe( 100 );
} );

test( 'public POST /comments is throttled after the guest bucket is exhausted', function (): void {
    // Tighten the guest limit for the duration of this test so we
    // don't have to fire ten requests to prove the limiter is wired.
    addFilter( 'comments.rate-limit.guest', fn () => 2 );

    $post = createCommentTestPost();

    $payload = [
        'post_id'      => $post->id,
        'author_name'  => 'Spammy',
        'author_email' => 'spam@example.com',
        'content'      => 'Comment under throttle',
    ];

    $this->postJson( '/api/v1/comments', $payload )->assertCreated();
    $this->postJson( '/api/v1/comments', $payload )->assertCreated();

    // Third request from the same IP within the minute should trip
    // the throttle and short-circuit before the controller runs.
    $this->postJson( '/api/v1/comments', $payload )->assertStatus( 429 );

    // Clean up the filter so neighbouring tests get the default
    // guest bucket.
    removeAllFilters( 'comments.rate-limit.guest' );
} );

test( 'unauthenticated guest can post a comment with author fields, defaulting to pending', function (): void {
    $post = createCommentTestPost();

    // No `actingAs()` — the guest path runs through
    // CommentPolicy::create's `comments.create.public` filter which
    // defaults to allow.
    $response = $this->postJson( '/api/v1/comments', [
        'post_id'      => $post->id,
        'author_name'  => 'Jane Guest',
        'author_email' => 'jane@example.com',
        'author_url'   => 'https://example.test/jane',
        'content'      => 'A guest comment',
    ] );

    $response->assertCreated();
    expect( $response->json( 'data.author.name' ) )->toBe( 'Jane Guest' )
        ->and( $response->json( 'data.author.is_guest' ) )->toBeTrue()
        ->and( $response->json( 'data.status' ) )->toBe( Comment::STATUS_PENDING )
        ->and( $response->json( 'data.user_id' ) )->toBeNull();
} );

test( 'parent_id must belong to the same post', function (): void {
    grantAllCommentPermissions();
    $user        = TestUser::factory()->create();
    $postA       = createCommentTestPost();
    $postB       = createCommentTestPost();
    $parentOnA   = Comment::factory()->for( $postA )->create();

    // Reply targeted at postB but referencing a parent on postA.
    $response = $this->actingAs( $user )->postJson( '/api/v1/comments', [
        'post_id'   => $postB->id,
        'parent_id' => $parentOnA->id,
        'content'   => 'cross-post reply',
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( [ 'parent_id' ] );
} );

test( 'validation fails when required fields are missing on store', function (): void {
    grantAllCommentPermissions();
    $user = TestUser::factory()->create();

    $response = $this->actingAs( $user )->postJson( '/api/v1/comments', [
        // missing post_id and content
    ] );

    $response->assertStatus( 422 );
    $response->assertJsonValidationErrors( [ 'post_id', 'content' ] );
} );
