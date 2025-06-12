<?php

use ArtisanPackUI\CMSFramework\Features\Users\UsersManager;
use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// User Management Tests
it('can retrieve all users', function () {
    // Create some users
    User::factory()->count(3)->create();

    $usersManager = app(UsersManager::class);
    $users = $usersManager->allUsers();

    $this->assertCount(3, $users);
    $this->assertInstanceOf(User::class, $users->first());
});

it('can find a user by ID', function () {
    $user = User::factory()->create();

    $usersManager = app(UsersManager::class);
    $foundUser = $usersManager->findUser($user->id);

    $this->assertInstanceOf(User::class, $foundUser);
    $this->assertEquals($user->id, $foundUser->id);
});

it('returns null when finding non-existent user', function () {
    $usersManager = app(UsersManager::class);
    $foundUser = $usersManager->findUser(999);

    $this->assertNull($foundUser);
});

it('can create a user', function () {
    $usersManager = app(UsersManager::class);

    $userData = [
        'username' => 'newuser',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'first_name' => 'New',
        'last_name' => 'User',
    ];

    $user = $usersManager->createUser($userData);

    $this->assertInstanceOf(User::class, $user);
    $this->assertEquals('newuser', $user->username);
    $this->assertEquals('newuser@example.com', $user->email);
    $this->assertEquals('New', $user->first_name);
    $this->assertEquals('User', $user->last_name);

    // Password should be hashed
    $this->assertNotEquals('password123', $user->password);
});

it('can update a user', function () {
    $user = User::factory()->create();

    $usersManager = app(UsersManager::class);

    $userData = [
        'first_name' => 'Updated',
        'last_name' => 'Name',
    ];

    $result = $usersManager->updateUser($user, $userData);

    $this->assertTrue($result);

    // Refresh the user from the database
    $user->refresh();

    $this->assertEquals('Updated', $user->first_name);
    $this->assertEquals('Name', $user->last_name);
});

it('can update a user password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('original_password')
    ]);

    $usersManager = app(UsersManager::class);

    $userData = [
        'password' => 'new_password',
    ];

    $result = $usersManager->updateUser($user, $userData);

    $this->assertTrue($result);

    // Refresh the user from the database
    $user->refresh();

    // Password should be hashed and different from the original
    $this->assertNotEquals(bcrypt('original_password'), $user->password);
});

it('can delete a user', function () {
    $user = User::factory()->create();

    $usersManager = app(UsersManager::class);
    $result = $usersManager->deleteUser($user);

    $this->assertTrue($result);
    $this->assertNull(User::find($user->id));
});

// Role Management Tests
it('can retrieve all roles', function () {
    // Create some roles
    Role::factory()->count(3)->create();

    $usersManager = app(UsersManager::class);
    $roles = $usersManager->allRoles();

    $this->assertCount(3, $roles);
    $this->assertInstanceOf(Role::class, $roles->first());
});

it('can find a role by ID', function () {
    $role = Role::factory()->create();

    $usersManager = app(UsersManager::class);
    $foundRole = $usersManager->findRole($role->id);

    $this->assertInstanceOf(Role::class, $foundRole);
    $this->assertEquals($role->id, $foundRole->id);
});

it('returns null when finding non-existent role', function () {
    $usersManager = app(UsersManager::class);
    $foundRole = $usersManager->findRole(999);

    $this->assertNull($foundRole);
});

it('can create a role', function () {
    $usersManager = app(UsersManager::class);

    $roleData = [
        'name' => 'New Role',
        'slug' => 'new-role',
        'description' => 'A new role for testing',
        'capabilities' => ['edit_posts', 'publish_posts'],
    ];

    $role = $usersManager->createRole($roleData);

    $this->assertInstanceOf(Role::class, $role);
    $this->assertEquals('New Role', $role->name);
    $this->assertEquals('new-role', $role->slug);
    $this->assertEquals('A new role for testing', $role->description);
    $this->assertIsArray($role->capabilities);
    $this->assertContains('edit_posts', $role->capabilities);
    $this->assertContains('publish_posts', $role->capabilities);
});

it('can update a role', function () {
    $role = Role::factory()->create();

    $usersManager = app(UsersManager::class);

    $roleData = [
        'name' => 'Updated Role',
        'description' => 'Updated description',
    ];

    $result = $usersManager->updateRole($role, $roleData);

    $this->assertTrue($result);

    // Refresh the role from the database
    $role->refresh();

    $this->assertEquals('Updated Role', $role->name);
    $this->assertEquals('Updated description', $role->description);
});

it('can delete a role', function () {
    $role = Role::factory()->create();

    $usersManager = app(UsersManager::class);
    $result = $usersManager->deleteRole($role);

    $this->assertTrue($result);
    $this->assertNull(Role::find($role->id));
});

// Role Assignment Tests
it('can assign a role to a user', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create();

    $usersManager = app(UsersManager::class);
    $result = $usersManager->assignRole($user, $role);

    $this->assertTrue($result);

    // Refresh the user from the database
    $user->refresh();

    $this->assertEquals($role->id, $user->role_id);
});

it('can remove a role from a user', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create([
        'role_id' => $role->id
    ]);

    $usersManager = app(UsersManager::class);
    $result = $usersManager->removeRole($user);

    $this->assertTrue($result);

    // Refresh the user from the database
    $user->refresh();

    $this->assertNull($user->role_id);
});

// User Settings Tests
it('can get and set user settings through manager', function () {
    $user = User::factory()->create();

    $usersManager = app(UsersManager::class);

    // Set a setting
    $result = $usersManager->setUserSetting($user, 'test_key', 'test_value');
    $this->assertTrue($result);

    // Get the setting
    $value = $usersManager->getUserSetting($user, 'test_key');
    $this->assertEquals('test_value', $value);

    // Get a non-existent setting with default
    $defaultValue = $usersManager->getUserSetting($user, 'non_existent', 'default_value');
    $this->assertEquals('default_value', $defaultValue);
});

it('can delete user settings through manager', function () {
    $user = User::factory()->create();

    $usersManager = app(UsersManager::class);

    // Set a setting
    $usersManager->setUserSetting($user, 'test_key', 'test_value');

    // Delete the setting
    $result = $usersManager->deleteUserSetting($user, 'test_key');
    $this->assertTrue($result);

    // Verify it's gone
    $value = $usersManager->getUserSetting($user, 'test_key');
    $this->assertNull($value);
});
