<?php

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\ContentType;
use ArtisanPackUI\CMSFramework\Models\Term;
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

    // Create a test content item
    $this->content = Content::factory()->create([
        'author_id' => $this->admin->id,
        'type' => $this->contentType->handle,
    ]);
});

it('can list all content items', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    // Create some additional content items
    Content::factory()->count(3)->create([
        'author_id' => $this->admin->id,
    ]);

    // Make request to list content items
    $response = $this->getJson('/api/cms/content');

    // Assert response is successful
    $response->assertStatus(200);

    // Assert that we have the expected number of content items
    $responseData = $response->json();
    $this->assertCount(4, $responseData['data']); // 3 created here + 1 from beforeEach
});

it('can create a new content item', function () {
    Sanctum::actingAs($this->admin);

    $contentData = [
        'title' => 'New Content',
        'slug' => 'new-content',
        'content' => 'This is the content body',
        'type' => $this->contentType->handle,
        'status' => 'draft',
        'author_id' => $this->admin->id,
        'meta' => [
            'custom_field' => 'custom value',
        ],
    ];

    $response = $this->postJson('/api/cms/content', $contentData);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'title' => 'New Content',
        'slug' => 'new-content',
        'type' => $this->contentType->handle,
    ]);

    // Verify the content was created in the database
    $this->assertDatabaseHas('content', [
        'title' => 'New Content',
        'slug' => 'new-content',
        'type' => $this->contentType->handle,
    ]);
});

it('can show a specific content item', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $response = $this->getJson("/api/cms/content/{$this->content->id}");

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->content->id,
        'title' => $this->content->title,
        'slug' => $this->content->slug,
    ]);
});

it('can update a content item', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $updateData = [
        'title' => 'Updated Title',
        'slug' => 'updated-slug',
        'content' => 'Updated content text',
        'status' => 'published',
        'meta' => [
            'updated_field' => 'updated value',
        ],
    ];

    $response = $this->putJson("/api/cms/content/{$this->content->id}", $updateData);

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->content->id,
        'title' => 'Updated Title',
        'slug' => 'updated-slug',
    ]);

    // Verify the content was updated in the database
    $this->assertDatabaseHas('content', [
        'id' => $this->content->id,
        'title' => 'Updated Title',
        'slug' => 'updated-slug',
        'content' => 'Updated content text',
    ]);
});

it('can delete a content item', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/cms/content/{$this->content->id}");

    $response->assertStatus(200);

    // Verify the content was deleted from the database
    $this->assertDatabaseMissing('content', [
        'id' => $this->content->id,
    ]);
});

it('can associate content with terms', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    // Create terms
    $term1 = Term::factory()->create();
    $term2 = Term::factory()->create();

    $updateData = [
        'title' => 'Content with Terms',
        'terms' => [$term1->id, $term2->id],
    ];

    $response = $this->putJson("/api/cms/content/{$this->content->id}", $updateData);

    $response->assertStatus(200);

    // Verify the relationships in the pivot table
    $this->assertDatabaseHas('term_content', [
        'content_id' => $this->content->id,
        'term_id' => $term1->id,
    ]);

    $this->assertDatabaseHas('term_content', [
        'content_id' => $this->content->id,
        'term_id' => $term2->id,
    ]);
});

it('prevents unauthorized users from managing content', function () {
    // Create content owned by admin
    $adminContent = Content::factory()->create([
        'author_id' => $this->admin->id,
    ]);

    // Create content owned by user
    $userContent = Content::factory()->create([
        'author_id' => $this->user->id,
    ]);

    // Act as regular user with Sanctum
    Sanctum::actingAs($this->user);

    // Regular users should be able to create their own content
    $contentData = [
        'title' => 'User Content',
        'slug' => 'user-content',
        'content' => 'This is user content',
        'type' => $this->contentType->handle,
        'status' => 'draft',
        'author_id' => $this->user->id,
    ];

    $createResponse = $this->postJson('/api/cms/content', $contentData);
    $createResponse->assertStatus(201);

    // Regular users should be able to update their own content
    $updateResponse = $this->putJson("/api/cms/content/{$userContent->id}", [
        'title' => 'Updated User Content',
    ]);
    $updateResponse->assertStatus(200);

    // Regular users should not be able to update content they don't own
    $unauthorizedUpdateResponse = $this->putJson("/api/cms/content/{$adminContent->id}", [
        'title' => 'Unauthorized Update',
    ]);
    $unauthorizedUpdateResponse->assertStatus(403);

    // Regular users should not be able to delete content they don't own
    $unauthorizedDeleteResponse = $this->deleteJson("/api/cms/content/{$adminContent->id}");
    $unauthorizedDeleteResponse->assertStatus(403);
});
