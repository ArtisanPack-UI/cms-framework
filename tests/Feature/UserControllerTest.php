<?php

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Check if we need to create a user with admin capabilities for authorization
beforeEach(function () {
    // Create an admin role with all capabilities
    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'slug' => 'admin',
        'capabilities' => ['viewAny_users', 'create_users', 'view_users', 'update_users', 'delete_users']
    ]);

    // Create an admin user
    $this->admin = User::factory()->create([
        'role_id' => $adminRole->id
    ]);

    // Create a regular user for testing
    $this->user = User::factory()->create();
});

it('can list all users', function () {
    // Create some additional users
    User::factory()->count(3)->create();

    // Act as admin to pass authorization
    $response = $this->actingAs($this->admin)->getJson('/api/cms/users');

    // Assert response is successful
    $response->assertStatus(200);

    // Dump the response for debugging
    $responseData = $response->json();

    // Assert that we have the expected number of users
    // Instead of assertJsonCount, we'll check the count manually
    $this->assertCount(5, $responseData['data']); // 3 created here + admin + regular user from beforeEach
});

it('can create a new user', function () {
    $userData = [
        'username' => 'newuser',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'first_name' => 'New',
        'last_name' => 'User',
    ];

    $response = $this->actingAs($this->admin)->postJson('/api/cms/users', $userData);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'username' => 'newuser',
        'email' => 'newuser@example.com',
        'first_name' => 'New',
        'last_name' => 'User',
    ]);

    // Verify the user was created in the database
    $this->assertDatabaseHas('users', [
        'username' => 'newuser',
        'email' => 'newuser@example.com',
    ]);
});

it('can show a specific user', function () {
    $response = $this->actingAs($this->admin)->getJson("/api/cms/users/{$this->user->id}");

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->user->id,
        'username' => $this->user->username,
        'email' => $this->user->email,
    ]);
});

it('can update a user', function () {
    $updateData = [
        'first_name' => 'Updated',
        'last_name' => 'Name',
    ];

    $response = $this->actingAs($this->admin)->putJson("/api/cms/users/{$this->user->id}", $updateData);

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'id' => $this->user->id,
        'first_name' => 'Updated',
        'last_name' => 'Name',
    ]);

    // Verify the user was updated in the database
    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'first_name' => 'Updated',
        'last_name' => 'Name',
    ]);
});

it('can delete a user', function () {
    $response = $this->actingAs($this->admin)->deleteJson("/api/cms/users/{$this->user->id}");

    $response->assertStatus(200);

    // Verify the user was deleted from the database
    $this->assertDatabaseMissing('users', [
        'id' => $this->user->id,
    ]);
});

it('validates required fields when creating a user', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/cms/users', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['username', 'email', 'password']);
});

it('validates email format when creating a user', function () {
    $userData = [
        'username' => 'newuser',
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->actingAs($this->admin)->postJson('/api/cms/users', $userData);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('prevents unauthorized users from accessing user endpoints', function () {
    // Create a regular user with no special capabilities
    $regularUser = User::factory()->create();

    // Try to list all users
    $response = $this->actingAs($regularUser)->getJson('/api/cms/users');
    $response->assertStatus(403);

    // Try to create a user
    $response = $this->actingAs($regularUser)->postJson('/api/cms/users', [
        'username' => 'newuser',
        'email' => 'newuser@example.com',
        'password' => 'password123',
    ]);
    $response->assertStatus(403);

    // Try to view a user
    $response = $this->actingAs($regularUser)->getJson("/api/cms/users/{$this->user->id}");
    $response->assertStatus(403);

    // Try to update a user
    $response = $this->actingAs($regularUser)->putJson("/api/cms/users/{$this->user->id}", [
        'first_name' => 'Updated',
    ]);
    $response->assertStatus(403);

    // Try to delete a user
    $response = $this->actingAs($regularUser)->deleteJson("/api/cms/users/{$this->user->id}");
    $response->assertStatus(403);
});
