<?php

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
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

    // Create a test media tag
    $this->mediaTag = MediaTag::factory()->create();
});

it('can list all media tags', function () {
    // Create some additional media tags
    MediaTag::factory()->count(3)->create();

    // Act as admin to pass authorization
    $response = $this->actingAs($this->admin)->getJson('/api/cms/media-tags');

    // Assert response is successful
    $response->assertStatus(200);

    // Assert that we have the expected number of media tags
    $responseData = $response->json();
    $this->assertCount(4, $responseData['data']); // 3 created here + 1 from beforeEach
});

it('can create a new media tag', function () {
    Sanctum::actingAs($this->admin);
    $tagData = [
        'name' => 'Test Tag',
        'slug' => 'test-tag',
    ];

    $response = $this->postJson('/api/cms/media-tags', $tagData);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'name' => 'Test Tag',
        'slug' => 'test-tag',
    ]);

    // Verify the media tag was created in the database
    $this->assertDatabaseHas('media_tags', [
        'name' => 'Test Tag',
        'slug' => 'test-tag',
    ]);
});

it('can show a specific media tag', function () {
    $response = $this->actingAs($this->admin)->getJson("/api/cms/media-tags/{$this->mediaTag->id}");

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->mediaTag->id,
        'name' => $this->mediaTag->name,
        'slug' => $this->mediaTag->slug,
    ]);
});

it('can update a media tag', function () {
    $updateData = [
        'name' => 'Updated Tag',
        'slug' => 'updated-tag',
    ];

    $response = $this->actingAs($this->admin)->putJson("/api/cms/media-tags/{$this->mediaTag->id}", $updateData);

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->mediaTag->id,
        'name' => 'Updated Tag',
        'slug' => 'updated-tag',
    ]);

    // Verify the media tag was updated in the database
    $this->assertDatabaseHas('media_tags', [
        'id' => $this->mediaTag->id,
        'name' => 'Updated Tag',
        'slug' => 'updated-tag',
    ]);
});

it('can delete a media tag', function () {
    $response = $this->actingAs($this->admin)->deleteJson("/api/cms/media-tags/{$this->mediaTag->id}");

    $response->assertStatus(200);

    // Verify the media tag was deleted from the database
    $this->assertDatabaseMissing('media_tags', [
        'id' => $this->mediaTag->id,
    ]);
});

it('validates required fields when creating a media tag', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/cms/media-tags', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'slug']);
});

it('validates unique slug when creating a media tag', function () {
    // Create a tag with a specific slug
    MediaTag::factory()->create([
        'slug' => 'existing-slug',
    ]);

    // Try to create another tag with the same slug
    $tagData = [
        'name' => 'New Tag',
        'slug' => 'existing-slug',
    ];

    $response = $this->actingAs($this->admin)->postJson('/api/cms/media-tags', $tagData);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['slug']);
});

it('prevents unauthorized users from managing media tags', function () {
    // Create a regular user with no special capabilities
    $regularUser = User::factory()->create();

    // Try to create a media tag
    $response = $this->actingAs($regularUser)->postJson('/api/cms/media-tags', [
        'name' => 'Test Tag',
        'slug' => 'test-tag',
    ]);
    $response->assertStatus(403);

    // Try to update a media tag
    $response = $this->actingAs($regularUser)->putJson("/api/cms/media-tags/{$this->mediaTag->id}", [
        'name' => 'Updated Tag',
    ]);
    $response->assertStatus(403);

    // Try to delete a media tag
    $response = $this->actingAs($regularUser)->deleteJson("/api/cms/media-tags/{$this->mediaTag->id}");
    $response->assertStatus(403);
});