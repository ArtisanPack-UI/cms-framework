<?php

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);
});

test('can list posts', function () {
    $this->user->shouldReceive('can')
        ->with('posts.viewAny')
        ->andReturn(true);

    Post::create([
        'title' => 'First Post',
        'slug' => 'first-post',
        'author_id' => $this->user->id,
        'status' => 'published',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/posts');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['title', 'slug', 'status', 'author_id'],
        ],
    ]);
});

test('can create post', function () {
    $this->user->shouldReceive('can')
        ->with('posts.create')
        ->andReturn(true);

    $data = [
        'title' => 'New Post',
        'slug' => 'new-post',
        'content' => 'This is the content',
        'excerpt' => 'This is the excerpt',
        'author_id' => $this->user->id,
        'status' => 'draft',
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/posts', $data);

    $response->assertCreated();
    $response->assertJsonFragment(['slug' => 'new-post']);

    expect(Post::where('slug', 'new-post')->exists())->toBeTrue();
});

test('can publish post', function () {
    $this->user->shouldReceive('can')
        ->with('posts.edit')
        ->andReturn(true);

    $this->user->shouldReceive('can')
        ->with('posts.publish')
        ->andReturn(true);

    $post = Post::create([
        'title' => 'Draft Post',
        'slug' => 'draft-post',
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->user)->putJson('/api/v1/posts/'.$post->id, [
        'status' => 'published',
        'published_at' => now(),
    ]);

    $response->assertSuccessful();

    $post->refresh();
    expect($post->status)->toBe('published');
    expect($post->published_at)->not->toBeNull();
});

test('can filter posts by category', function () {
    $this->user->shouldReceive('can')
        ->with('posts.viewAny')
        ->andReturn(true);

    $category = PostCategory::create([
        'name' => 'Technology',
        'slug' => 'technology',
    ]);

    $post1 = Post::create([
        'title' => 'Tech Post',
        'slug' => 'tech-post',
        'author_id' => $this->user->id,
        'status' => 'published',
    ]);
    $post1->categories()->attach($category->id);

    Post::create([
        'title' => 'Other Post',
        'slug' => 'other-post',
        'author_id' => $this->user->id,
        'status' => 'published',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/posts/archives/category/technology');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['slug' => 'tech-post']);
});

test('can get posts by date', function () {
    $this->user->shouldReceive('can')
        ->with('posts.viewAny')
        ->andReturn(true);

    Post::create([
        'title' => 'June 2023 Post',
        'slug' => 'june-2023-post',
        'author_id' => $this->user->id,
        'status' => 'published',
        'published_at' => Carbon::create(2023, 6, 15),
    ]);

    Post::create([
        'title' => 'July 2023 Post',
        'slug' => 'july-2023-post',
        'author_id' => $this->user->id,
        'status' => 'published',
        'published_at' => Carbon::create(2023, 7, 10),
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/posts/archives/date/2023/6');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['slug' => 'june-2023-post']);
});

test('custom fields are stored as columns', function () {
    $this->user->shouldReceive('can')
        ->with('posts.create')
        ->andReturn(true);

    // Add a custom field column to posts table
    \Schema::table('posts', function ($table) {
        $table->string('custom_field')->nullable();
    });

    $data = [
        'title' => 'Post with Custom Field',
        'slug' => 'post-with-custom-field',
        'author_id' => $this->user->id,
        'status' => 'draft',
        'custom_field' => 'Custom Value',
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/posts', $data);

    $response->assertCreated();

    $post = Post::where('slug', 'post-with-custom-field')->first();
    expect($post->custom_field)->toBe('Custom Value');

    // Cleanup
    \Schema::table('posts', function ($table) {
        $table->dropColumn('custom_field');
    });
});

test('unauthorized user cannot create post', function () {
    $this->user->shouldReceive('can')
        ->with('posts.create')
        ->andReturn(false);

    $data = [
        'title' => 'Unauthorized Post',
        'slug' => 'unauthorized-post',
        'author_id' => $this->user->id,
        'status' => 'draft',
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/posts', $data);

    $response->assertForbidden();
});

test('can delete post', function () {
    $this->user->shouldReceive('can')
        ->with('posts.delete')
        ->andReturn(true);

    $post = Post::create([
        'title' => 'Post to Delete',
        'slug' => 'post-to-delete',
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->user)->deleteJson('/api/v1/posts/'.$post->id);

    $response->assertNoContent();
    expect(Post::find($post->id))->toBeNull();
});

test('can filter posts by author', function () {
    $this->user->shouldReceive('can')
        ->with('posts.viewAny')
        ->andReturn(true);

    $author2 = TestUser::create([
        'name' => 'Author Two',
        'email' => 'author2@example.com',
        'password' => 'password',
    ]);

    Post::create([
        'title' => 'Post by Author 1',
        'slug' => 'post-by-author-1',
        'author_id' => $this->user->id,
        'status' => 'published',
    ]);

    Post::create([
        'title' => 'Post by Author 2',
        'slug' => 'post-by-author-2',
        'author_id' => $author2->id,
        'status' => 'published',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/posts/archives/author/'.$this->user->id);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['slug' => 'post-by-author-1']);
});

test('can filter posts by tag', function () {
    $this->user->shouldReceive('can')
        ->with('posts.viewAny')
        ->andReturn(true);

    $tag = PostTag::create([
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);

    $post1 = Post::create([
        'title' => 'Laravel Post',
        'slug' => 'laravel-post',
        'author_id' => $this->user->id,
        'status' => 'published',
    ]);
    $post1->tags()->attach($tag->id);

    Post::create([
        'title' => 'Other Post',
        'slug' => 'other-post',
        'author_id' => $this->user->id,
        'status' => 'published',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/posts/archives/tag/laravel');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['slug' => 'laravel-post']);
});
