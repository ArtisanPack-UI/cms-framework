<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Grant all post-related permissions via Gate.
 */
function grantAllPostPermissions(): void
{
    Gate::define('posts.view', fn () => true);
    Gate::define('posts.create', fn () => true);
    Gate::define('posts.edit', fn () => true);
    Gate::define('posts.editOwn', fn () => true);
    Gate::define('posts.delete', fn () => true);
    Gate::define('posts.deleteOwn', fn () => true);
    Gate::define('posts.publish', fn () => true);
}

/**
 * Grant only view and edit permissions (no delete/publish).
 */
function grantLimitedPostPermissions(): void
{
    Gate::define('posts.view', fn () => true);
    Gate::define('posts.edit', fn () => true);
    Gate::define('posts.editOwn', fn () => true);
}

/**
 * Create a test post with the given attributes.
 *
 * @param  array<string, mixed>  $attributes
 */
function createTestPost(array $attributes = []): Post
{
    $title = fake()->sentence(6, true);

    return Post::create(array_merge([
        'title'        => $title,
        'slug'         => Str::slug($title).'-'.Str::random(5),
        'content'      => fake()->paragraphs(3, true),
        'excerpt'      => fake()->paragraph(),
        'status'       => ContentStatus::Published->value,
        'published_at' => now(),
    ], $attributes));
}

// --- Bulk Delete ---

test('bulk post delete soft-deletes multiple posts', function (): void {
    grantAllPostPermissions();
    $user = TestUser::factory()->create();

    $posts = collect();
    for ($i = 0; $i < 3; $i++) {
        $posts->push(createTestPost(['author_id' => $user->id]));
    }

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'delete',
        'ids'    => $posts->pluck('id')->toArray(),
    ]);

    $response->assertSuccessful();
    expect($response->json('processed'))->toBe(3);
    expect($response->json('failed'))->toBe(0);
    expect($response->json('errors'))->toBeEmpty();

    foreach ($posts as $post) {
        expect(Post::find($post->id))->toBeNull();
        expect(Post::withTrashed()->find($post->id))->not->toBeNull();
    }
});

// --- Bulk Publish ---

test('bulk post publish sets status to published', function (): void {
    grantAllPostPermissions();
    $user = TestUser::factory()->create();

    $posts = collect();
    for ($i = 0; $i < 3; $i++) {
        $posts->push(createTestPost([
            'author_id'    => $user->id,
            'status'       => ContentStatus::Draft->value,
            'published_at' => null,
        ]));
    }

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'publish',
        'ids'    => $posts->pluck('id')->toArray(),
    ]);

    $response->assertSuccessful();
    expect($response->json('processed'))->toBe(3);
    expect($response->json('failed'))->toBe(0);

    foreach ($posts as $post) {
        $post->refresh();
        expect($post->status)->toBe(ContentStatus::Published);
        expect($post->published_at)->not->toBeNull();
    }
});

// --- Bulk Draft ---

test('bulk post draft sets status to draft', function (): void {
    grantAllPostPermissions();
    $user = TestUser::factory()->create();

    $posts = collect();
    for ($i = 0; $i < 3; $i++) {
        $posts->push(createTestPost([
            'author_id'    => $user->id,
            'status'       => ContentStatus::Published->value,
            'published_at' => now(),
        ]));
    }

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'draft',
        'ids'    => $posts->pluck('id')->toArray(),
    ]);

    $response->assertSuccessful();
    expect($response->json('processed'))->toBe(3);
    expect($response->json('failed'))->toBe(0);

    foreach ($posts as $post) {
        $post->refresh();
        expect($post->status)->toBe(ContentStatus::Draft);
        expect($post->published_at)->toBeNull();
    }
});

// --- Archive is not a valid action ---

test('bulk post action rejects archive as invalid action', function (): void {
    $user = TestUser::factory()->create();
    $post = createTestPost(['author_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'archive',
        'ids'    => [$post->id],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['action']);
});

// --- Authorization failures ---

test('bulk post delete respects per-item authorization', function (): void {
    grantLimitedPostPermissions();
    $user = TestUser::factory()->create();

    $posts = collect();
    for ($i = 0; $i < 2; $i++) {
        $posts->push(createTestPost(['author_id' => $user->id]));
    }

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'delete',
        'ids'    => $posts->pluck('id')->toArray(),
    ]);

    $response->assertSuccessful();
    expect($response->json('processed'))->toBe(0);
    expect($response->json('failed'))->toBe(2);
    expect($response->json('errors'))->toHaveCount(2);
});

test('bulk post publish respects per-item authorization', function (): void {
    grantLimitedPostPermissions();
    $user = TestUser::factory()->create();

    $posts = collect();
    for ($i = 0; $i < 2; $i++) {
        $posts->push(createTestPost([
            'author_id'    => $user->id,
            'status'       => ContentStatus::Draft->value,
            'published_at' => null,
        ]));
    }

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'publish',
        'ids'    => $posts->pluck('id')->toArray(),
    ]);

    $response->assertSuccessful();
    expect($response->json('processed'))->toBe(0);
    expect($response->json('failed'))->toBe(2);
});

// --- Validation ---

test('bulk post action requires action field', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'ids' => [1],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['action']);
});

test('bulk post action requires ids field', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'delete',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['ids']);
});

test('bulk post action rejects invalid action', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'invalid',
        'ids'    => [1],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['action']);
});

test('bulk post action rejects empty ids array', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'delete',
        'ids'    => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['ids']);
});

test('bulk post action validates ids exist in database', function (): void {
    $user = TestUser::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'delete',
        'ids'    => [9999],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['ids.0']);
});

// --- Mixed results ---

test('bulk post action returns mixed results when some items fail authorization', function (): void {
    $owner = TestUser::factory()->create();
    $other = TestUser::factory()->create();

    // Grant delete own only
    Gate::define('posts.view', fn () => true);
    Gate::define('posts.deleteOwn', fn () => true);

    $ownPost   = createTestPost(['author_id' => $owner->id]);
    $otherPost = createTestPost(['author_id' => $other->id]);

    $response = $this->actingAs($owner)->postJson('/api/v1/posts/bulk', [
        'action' => 'delete',
        'ids'    => [$ownPost->id, $otherPost->id],
    ]);

    $response->assertSuccessful();
    expect($response->json('processed'))->toBe(1);
    expect($response->json('failed'))->toBe(1);
    expect($response->json('errors'))->toHaveKey((string) $otherPost->id);
});

// --- Response structure ---

test('bulk post action returns correct response structure', function (): void {
    grantAllPostPermissions();
    $user = TestUser::factory()->create();

    $post = createTestPost(['author_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/posts/bulk', [
        'action' => 'delete',
        'ids'    => [$post->id],
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure(['processed', 'failed', 'errors']);
});
