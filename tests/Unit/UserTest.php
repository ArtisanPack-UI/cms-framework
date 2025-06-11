<?php

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a user', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->assertInstanceOf(User::class, $user);
    $this->assertEquals('testuser', $user->username);
    $this->assertEquals('test@example.com', $user->email);
});

it('can get and set user settings', function () {
    $user = User::factory()->create();

    // Test setting a setting
    $result = $user->setSetting('test_key', 'test_value');
    $this->assertTrue($result);

    // Test getting a setting
    $value = $user->getSetting('test_key');
    $this->assertEquals('test_value', $value);

    // Test getting a non-existent setting with default
    $defaultValue = $user->getSetting('non_existent', 'default_value');
    $this->assertEquals('default_value', $defaultValue);
});

it('can delete user settings', function () {
    $user = User::factory()->create();

    // Set a setting
    $user->setSetting('test_key', 'test_value');

    // Delete the setting
    $result = $user->deleteSetting('test_key');
    $this->assertTrue($result);

    // Verify it's gone
    $value = $user->getSetting('test_key');
    $this->assertNull($value);

    // Try to delete a non-existent setting
    $result = $user->deleteSetting('non_existent');
    $this->assertFalse($result);
});

it('can check if user has capability', function () {
    // Create a role with a capability
    $role = Role::factory()->create([
        'capabilities' => ['edit_posts']
    ]);

    // Create a user with that role
    $user = User::factory()->create([
        'role_id' => $role->id
    ]);

    // Check if user has the capability
    $this->assertTrue($user->can('edit_posts'));

    // Check if user doesn't have a different capability
    $this->assertFalse($user->can('delete_users'));
});

it('returns false for capability check when user has no role', function () {
    $user = User::factory()->create([
        'role_id' => null
    ]);

    $this->assertFalse($user->can('any_capability'));
});

it('has a relationship with role', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create([
        'role_id' => $role->id
    ]);

    $this->assertInstanceOf(Role::class, $user->role);
    $this->assertEquals($role->id, $user->role->id);
});
