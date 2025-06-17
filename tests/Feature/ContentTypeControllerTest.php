<?php

use ArtisanPackUI\CMSFramework\Models\ContentType;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Set up test data before each test
beforeEach(function () {
    // Create an admin user
    $this->admin = User::factory()->create([
        'role_id' => 3,
    ]);

    // Create a regular user for testing
    $this->user = User::factory()->create([
        'role_id' => 1,
    ]);

    // Create a test content type
    $this->contentType = ContentType::factory()->create();
});

it('can list all content types', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    // Create some additional content types
    ContentType::factory()->count(3)->create();

    // Make request to list content types
    $response = $this->getJson('/api/cms/content-types');

    // Assert response is successful
    $response->assertStatus(200);

    // Assert that we have the expected number of content types
    $responseData = $response->json();
    $this->assertCount(4, $responseData['data']); // 3 created here + 1 from beforeEach
});

it('can create a new content type', function () {
    Sanctum::actingAs($this->admin);

    $contentTypeData = [
        'handle' => 'new_type',
        'label' => 'New Type',
        'label_plural' => 'New Types',
        'slug' => 'new-type',
        'definition' => [
            'public' => true,
            'hierarchical' => false,
            'supports' => ['title', 'content', 'author'],
            'fields' => []
        ],
    ];

    $response = $this->postJson('/api/cms/content-types', $contentTypeData);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'handle' => 'new_type',
        'label' => 'New Type',
        'label_plural' => 'New Types',
    ]);

    // Verify the content type was created in the database
    $this->assertDatabaseHas('content_types', [
        'handle' => 'new_type',
        'label' => 'New Type',
        'label_plural' => 'New Types',
    ]);
});

it('can show a specific content type', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $response = $this->getJson("/api/cms/content-types/{$this->contentType->id}");

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->contentType->id,
        'handle' => $this->contentType->handle,
        'label' => $this->contentType->label,
    ]);
});

it('can update a content type', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $updateData = [
        'label' => 'Updated Label',
        'label_plural' => 'Updated Labels',
        'definition' => [
            'public' => false,
            'hierarchical' => true,
            'supports' => ['title', 'content'],
            'fields' => []
        ],
    ];

    $response = $this->putJson("/api/cms/content-types/{$this->contentType->id}", $updateData);

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->contentType->id,
        'label' => 'Updated Label',
        'label_plural' => 'Updated Labels',
    ]);

    // Verify the content type was updated in the database
    $this->assertDatabaseHas('content_types', [
        'id' => $this->contentType->id,
        'label' => 'Updated Label',
        'label_plural' => 'Updated Labels',
    ]);
});

it('can delete a content type', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/cms/content-types/{$this->contentType->id}");

    $response->assertStatus(200);

    // Verify the content type was deleted from the database
    $this->assertDatabaseMissing('content_types', [
        'id' => $this->contentType->id,
    ]);
});

it('prevents unauthorized users from managing content types', function () {
    // Act as regular user with Sanctum
    Sanctum::actingAs($this->user);

    // Try to create a content type
    $contentTypeData = [
        'handle' => 'new_type',
        'label' => 'New Type',
        'label_plural' => 'New Types',
        'slug' => 'new-type',
        'definition' => [
            'public' => true,
            'hierarchical' => false,
            'supports' => ['title', 'content', 'author'],
            'fields' => []
        ],
    ];

    $response = $this->postJson('/api/cms/content-types', $contentTypeData);
    $response->assertStatus(403);

    // Try to update a content type
    $response = $this->putJson("/api/cms/content-types/{$this->contentType->id}", [
        'label' => 'Updated Label',
    ]);
    $response->assertStatus(403);

    // Try to delete a content type
    $response = $this->deleteJson("/api/cms/content-types/{$this->contentType->id}");
    $response->assertStatus(403);
});
