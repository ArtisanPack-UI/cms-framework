<?php

use ArtisanPackUI\CMSFramework\Models\Taxonomy;
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

    // Create a test taxonomy
    $this->taxonomy = Taxonomy::factory()->create();
});

it('can list all taxonomies', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    // Create some additional taxonomies
    Taxonomy::factory()->count(3)->create();

    // Make request to list taxonomies
    $response = $this->getJson('/api/cms/taxonomies');

    // Assert response is successful
    $response->assertStatus(200);

    // Assert that we have the expected number of taxonomies
    $responseData = $response->json();
    $this->assertCount(4, $responseData['data']); // 3 created here + 1 from beforeEach
});

it('can create a new taxonomy', function () {
    Sanctum::actingAs($this->admin);

    $taxonomyData = [
        'handle' => 'new_taxonomy',
        'label' => 'New Taxonomy',
        'label_plural' => 'New Taxonomies',
        'content_types' => ['post', 'page'],
        'hierarchical' => true,
    ];

    $response = $this->postJson('/api/cms/taxonomies', $taxonomyData);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'handle' => 'new_taxonomy',
        'label' => 'New Taxonomy',
        'label_plural' => 'New Taxonomies',
    ]);

    // Verify the taxonomy was created in the database
    $this->assertDatabaseHas('taxonomies', [
        'handle' => 'new_taxonomy',
        'label' => 'New Taxonomy',
        'label_plural' => 'New Taxonomies',
        'hierarchical' => true,
    ]);
});

it('can show a specific taxonomy', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $response = $this->getJson("/api/cms/taxonomies/{$this->taxonomy->id}");

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->taxonomy->id,
        'handle' => $this->taxonomy->handle,
        'label' => $this->taxonomy->label,
    ]);
});

it('can update a taxonomy', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $updateData = [
        'label' => 'Updated Label',
        'label_plural' => 'Updated Labels',
        'content_types' => ['post', 'page', 'custom'],
        'hierarchical' => false,
    ];

    $response = $this->putJson("/api/cms/taxonomies/{$this->taxonomy->id}", $updateData);

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->taxonomy->id,
        'label' => 'Updated Label',
        'label_plural' => 'Updated Labels',
    ]);

    // Verify the taxonomy was updated in the database
    $this->assertDatabaseHas('taxonomies', [
        'id' => $this->taxonomy->id,
        'label' => 'Updated Label',
        'label_plural' => 'Updated Labels',
        'hierarchical' => false,
    ]);
});

it('can delete a taxonomy', function () {
    // Act as admin with Sanctum
    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/cms/taxonomies/{$this->taxonomy->id}");

    $response->assertStatus(200);

    // Verify the taxonomy was deleted from the database
    $this->assertDatabaseMissing('taxonomies', [
        'id' => $this->taxonomy->id,
    ]);
});

it('prevents unauthorized users from managing taxonomies', function () {
    // Act as regular user with Sanctum
    Sanctum::actingAs($this->user);

    // Try to create a taxonomy
    $taxonomyData = [
        'handle' => 'new_taxonomy',
        'label' => 'New Taxonomy',
        'label_plural' => 'New Taxonomies',
        'content_types' => ['post', 'page'],
        'hierarchical' => true,
    ];

    $response = $this->postJson('/api/cms/taxonomies', $taxonomyData);
    $response->assertStatus(403);

    // Try to update a taxonomy
    $response = $this->putJson("/api/cms/taxonomies/{$this->taxonomy->id}", [
        'label' => 'Updated Label',
    ]);
    $response->assertStatus(403);

    // Try to delete a taxonomy
    $response = $this->deleteJson("/api/cms/taxonomies/{$this->taxonomy->id}");
    $response->assertStatus(403);
});
