<?php

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
});

test('can list content types', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.viewAny')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Blog Posts',
        'slug' => 'posts',
        'table_name' => 'posts',
        'model_class' => 'App\\Models\\Post',
        'public' => true,
        'show_in_admin' => true,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/content-types');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['name', 'slug', 'table_name', 'model_class'],
        ],
    ]);
});

test('can create content type', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.create')
        ->andReturn(true);

    $data = [
        'name' => 'Products',
        'slug' => 'products',
        'table_name' => 'products',
        'model_class' => 'App\\Models\\Product',
        'hierarchical' => false,
        'has_archive' => true,
        'archive_slug' => 'shop',
        'supports' => ['title', 'content', 'featured_image'],
        'public' => true,
        'show_in_admin' => true,
        'icon' => 'fas-shopping-cart',
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/content-types', $data);

    $response->assertCreated();
    $response->assertJsonFragment(['slug' => 'products']);

    expect(ContentType::where('slug', 'products')->exists())->toBeTrue();
});

test('can show single content type', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.view')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Events',
        'slug' => 'events',
        'table_name' => 'events',
        'model_class' => 'App\\Models\\Event',
        'public' => true,
        'show_in_admin' => true,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/content-types/events');

    $response->assertSuccessful();
    $response->assertJsonFragment(['slug' => 'events', 'name' => 'Events']);
});

test('can update content type', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.update')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Portfolios',
        'slug' => 'portfolios',
        'table_name' => 'portfolios',
        'model_class' => 'App\\Models\\Portfolio',
        'public' => true,
        'show_in_admin' => true,
    ]);

    $updateData = [
        'name' => 'Portfolio Items',
        'icon' => 'fas-briefcase',
    ];

    $response = $this->actingAs($this->user)->putJson('/api/v1/content-types/portfolios', $updateData);

    $response->assertSuccessful();
    $response->assertJsonFragment(['name' => 'Portfolio Items']);

    $contentType = ContentType::where('slug', 'portfolios')->first();
    expect($contentType->name)->toBe('Portfolio Items');
    expect($contentType->icon)->toBe('fas-briefcase');
});

test('can delete content type', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.delete')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Testimonials',
        'slug' => 'testimonials',
        'table_name' => 'testimonials',
        'model_class' => 'App\\Models\\Testimonial',
        'public' => true,
        'show_in_admin' => true,
    ]);

    $response = $this->actingAs($this->user)->deleteJson('/api/v1/content-types/testimonials');

    $response->assertNoContent();
    expect(ContentType::where('slug', 'testimonials')->exists())->toBeFalse();
});

test('unauthorized user cannot create content type', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.create')
        ->andReturn(false);

    $data = [
        'name' => 'Products',
        'slug' => 'products',
        'table_name' => 'products',
        'model_class' => 'App\\Models\\Product',
        'public' => true,
        'show_in_admin' => true,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/content-types', $data);

    $response->assertForbidden();
});

test('unauthorized user cannot update content type', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.update')
        ->andReturn(false);

    ContentType::create([
        'name' => 'Events',
        'slug' => 'events',
        'table_name' => 'events',
        'model_class' => 'App\\Models\\Event',
        'public' => true,
        'show_in_admin' => true,
    ]);

    $response = $this->actingAs($this->user)->putJson('/api/v1/content-types/events', ['name' => 'Updated']);

    $response->assertForbidden();
});

test('unauthorized user cannot delete content type', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.delete')
        ->andReturn(false);

    ContentType::create([
        'name' => 'Events',
        'slug' => 'events',
        'table_name' => 'events',
        'model_class' => 'App\\Models\\Event',
        'public' => true,
        'show_in_admin' => true,
    ]);

    $response = $this->actingAs($this->user)->deleteJson('/api/v1/content-types/events');

    $response->assertForbidden();
});

test('returns 404 when content type not found', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.view')
        ->andReturn(true);

    $response = $this->actingAs($this->user)->getJson('/api/v1/content-types/non-existent');

    $response->assertNotFound();
});

test('can get custom fields for content type', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.view')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Products',
        'slug' => 'products',
        'table_name' => 'products',
        'model_class' => 'App\\Models\\Product',
        'public' => true,
        'show_in_admin' => true,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/content-types/products/custom-fields');

    $response->assertSuccessful();
    $response->assertJsonIsArray();
});

test('validation fails when required fields are missing', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.create')
        ->andReturn(true);

    $data = [
        'name' => 'Products',
        // Missing required fields: slug, table_name, model_class
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/content-types', $data);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['slug', 'table_name', 'model_class']);
});

test('content type slug must be unique', function () {
    $this->user->shouldReceive('can')
        ->with('contentTypes.create')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Posts',
        'slug' => 'posts',
        'table_name' => 'posts',
        'model_class' => 'App\\Models\\Post',
        'public' => true,
        'show_in_admin' => true,
    ]);

    $data = [
        'name' => 'Another Posts',
        'slug' => 'posts', // Duplicate slug
        'table_name' => 'other_posts',
        'model_class' => 'App\\Models\\OtherPost',
        'public' => true,
        'show_in_admin' => true,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/content-types', $data);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['slug']);
});
