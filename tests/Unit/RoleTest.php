<?php

use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a role', function () {
    $role = Role::factory()->create([
        'name' => 'Editor',
        'slug' => 'editor',
        'description' => 'Can edit content',
        'capabilities' => ['edit_posts', 'publish_posts']
    ]);

    $this->assertInstanceOf(Role::class, $role);
    $this->assertEquals('Editor', $role->name);
    $this->assertEquals('editor', $role->slug);
    $this->assertEquals('Can edit content', $role->description);
    $this->assertIsArray($role->capabilities);
    $this->assertContains('edit_posts', $role->capabilities);
    $this->assertContains('publish_posts', $role->capabilities);
});

it('can check if role has capability', function () {
    $role = Role::factory()->create([
        'capabilities' => ['edit_posts', 'publish_posts']
    ]);

    $this->assertTrue($role->hasCapability('edit_posts'));
    $this->assertTrue($role->hasCapability('publish_posts'));
    $this->assertFalse($role->hasCapability('delete_users'));
});

it('can add capability to role', function () {
    $role = Role::factory()->create([
        'capabilities' => ['edit_posts']
    ]);

    // Add a new capability
    $result = $role->addCapability('publish_posts');
    $this->assertTrue($result);

    // Verify it was added
    $this->assertTrue($role->hasCapability('publish_posts'));

    // Try to add the same capability again
    $result = $role->addCapability('edit_posts');
    $this->assertFalse($result); // Should return false since it already exists
});

it('can remove capability from role', function () {
    $role = Role::factory()->create([
        'capabilities' => ['edit_posts', 'publish_posts']
    ]);

    // Remove a capability
    $result = $role->removeCapability('edit_posts');
    $this->assertTrue($result);

    // Verify it was removed
    $this->assertFalse($role->hasCapability('edit_posts'));
    $this->assertTrue($role->hasCapability('publish_posts'));

    // Try to remove a non-existent capability
    $result = $role->removeCapability('non_existent');
    $this->assertFalse($result);
});

it('has a relationship with users', function () {
    $role = Role::factory()->create();

    // Create users with this role
    $user1 = User::factory()->create(['role_id' => $role->id]);
    $user2 = User::factory()->create(['role_id' => $role->id]);

    // Check the relationship
    $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $role->users);
    $this->assertCount(2, $role->users);
    $this->assertTrue($role->users->contains($user1));
    $this->assertTrue($role->users->contains($user2));
});

it('handles empty capabilities array', function () {
    $role = Role::factory()->create([
        'capabilities' => []
    ]);

    $this->assertIsArray($role->capabilities);
    $this->assertEmpty($role->capabilities);
    $this->assertFalse($role->hasCapability('any_capability'));
});

it('handles null capabilities', function () {
    // Create a role without specifying capabilities
    $role = new Role();
    $role->name = 'Test Role';
    $role->slug = 'test-role';
    $role->description = 'Test description';
    $role->save();

    // Refresh the model to ensure we're getting the data from the database
    $role->refresh();

    // Test that the hasCapability method handles null capabilities correctly
    $this->assertFalse($role->hasCapability('any_capability'));
});
