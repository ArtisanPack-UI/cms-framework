<?php

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Set up test data before each test
beforeEach(function () {
    // Create an admin user with manage_categories permission
    $this->admin = User::factory()->create([
        'role_id' => 3, // Assuming role_id 3 is admin with all permissions
    ]);

    // Create a regular user for testing
    $this->user = User::factory()->create();

    // Create a test media category
    $this->mediaCategory = MediaCategory::factory()->create();
});

it('can list all media categories', function () {
    // Create some additional media categories
    MediaCategory::factory()->count(3)->create();

    // Act as admin to pass authorization
    $response = $this->actingAs($this->admin)->getJson('/api/cms/media-categories');

    // Assert response is successful
    $response->assertStatus(200);

    // Assert that we have the expected number of media categories
    $responseData = $response->json();
    $this->assertCount(4, $responseData['data']); // 3 created here + 1 from beforeEach
});

it('can create a new media category', function () {
    Sanctum::actingAs($this->admin);
    $categoryData = [
        'name' => 'Test Category',
        'slug' => 'test-category',
    ];

    $response = $this->postJson('/api/cms/media-categories', $categoryData);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'name' => 'Test Category',
        'slug' => 'test-category',
    ]);

    // Verify the media category was created in the database
    $this->assertDatabaseHas('media_categories', [
        'name' => 'Test Category',
        'slug' => 'test-category',
    ]);
});

it('can show a specific media category', function () {
    $response = $this->actingAs($this->admin)->getJson("/api/cms/media-categories/{$this->mediaCategory->id}");

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->mediaCategory->id,
        'name' => $this->mediaCategory->name,
        'slug' => $this->mediaCategory->slug,
    ]);
});

it('can update a media category', function () {
    $updateData = [
        'name' => 'Updated Category',
        'slug' => 'updated-category',
    ];

    $response = $this->actingAs($this->admin)->putJson("/api/cms/media-categories/{$this->mediaCategory->id}", $updateData);

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->mediaCategory->id,
        'name' => 'Updated Category',
        'slug' => 'updated-category',
    ]);

    // Verify the media category was updated in the database
    $this->assertDatabaseHas('media_categories', [
        'id' => $this->mediaCategory->id,
        'name' => 'Updated Category',
        'slug' => 'updated-category',
    ]);
});

it('can delete a media category', function () {
    $response = $this->actingAs($this->admin)->deleteJson("/api/cms/media-categories/{$this->mediaCategory->id}");

    $response->assertStatus(200);

    // Verify the media category was deleted from the database
    $this->assertDatabaseMissing('media_categories', [
        'id' => $this->mediaCategory->id,
    ]);
});

it('validates required fields when creating a media category', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/cms/media-categories', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'slug']);
});

it('validates unique slug when creating a media category', function () {
    // Create a category with a specific slug
    MediaCategory::factory()->create([
        'slug' => 'existing-slug',
    ]);

    // Try to create another category with the same slug
    $categoryData = [
        'name' => 'New Category',
        'slug' => 'existing-slug',
    ];

    $response = $this->actingAs($this->admin)->postJson('/api/cms/media-categories', $categoryData);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['slug']);
});

it('prevents unauthorized users from managing media categories', function () {
    // Create a regular user with no special capabilities
    $regularUser = User::factory()->create();

    // Try to create a media category
    $response = $this->actingAs($regularUser)->postJson('/api/cms/media-categories', [
        'name' => 'Test Category',
        'slug' => 'test-category',
    ]);
    $response->assertStatus(403);

    // Try to update a media category
    $response = $this->actingAs($regularUser)->putJson("/api/cms/media-categories/{$this->mediaCategory->id}", [
        'name' => 'Updated Category',
    ]);
    $response->assertStatus(403);

    // Try to delete a media category
    $response = $this->actingAs($regularUser)->deleteJson("/api/cms/media-categories/{$this->mediaCategory->id}");
    $response->assertStatus(403);
});