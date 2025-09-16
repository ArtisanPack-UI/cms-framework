<?php

use ArtisanPackUI\CMSFramework\Modules\Users\Managers\RoleManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

test('role manager can register a new role', function () {
    $roleManager = new RoleManager();

    $role = $roleManager->register('admin', 'Administrator');

    expect($role)->toBeInstanceOf(Role::class);
    expect($role->slug)->toBe('admin');
    expect($role->name)->toBe('Administrator');
    expect($role->exists)->toBeTrue();
});

test('role manager returns existing role if already registered', function () {
    $roleManager = new RoleManager();

    // Create an existing role
    $existingRole = Role::create([
        'slug' => 'manager',
        'name' => 'Manager',
    ]);

    $role = $roleManager->register('manager', 'Updated Manager');

    expect($role->id)->toBe($existingRole->id);
    expect($role->slug)->toBe('manager');
    expect($role->name)->toBe('Manager'); // Should keep original name
});

test('role manager can add permission to role', function () {
    $roleManager = new RoleManager();

    // Create role and permission
    $role = Role::create(['slug' => 'editor', 'name' => 'Editor']);
    $permission = Permission::create(['slug' => 'edit-posts', 'name' => 'Edit Posts']);

    $roleManager->addPermissionToRole('editor', 'edit-posts');

    $role->refresh();
    expect($role->permissions)->toHaveCount(1);
    expect($role->permissions->first()->slug)->toBe('edit-posts');
});

test('role manager throws exception when role not found for adding permission', function () {
    $roleManager = new RoleManager();

    // Create only permission, not role
    Permission::create(['slug' => 'edit-posts', 'name' => 'Edit Posts']);

    expect(fn() => $roleManager->addPermissionToRole('non-existent-role', 'edit-posts'))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('role manager throws exception when permission not found for adding to role', function () {
    $roleManager = new RoleManager();

    // Create only role, not permission
    Role::create(['slug' => 'editor', 'name' => 'Editor']);

    expect(fn() => $roleManager->addPermissionToRole('editor', 'non-existent-permission'))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('role manager can add multiple permissions to same role', function () {
    $roleManager = new RoleManager();

    $role = Role::create(['slug' => 'admin', 'name' => 'Administrator']);
    $permissions = [
        Permission::create(['slug' => 'create-posts', 'name' => 'Create Posts']),
        Permission::create(['slug' => 'edit-posts', 'name' => 'Edit Posts']),
        Permission::create(['slug' => 'delete-posts', 'name' => 'Delete Posts']),
    ];

    foreach ($permissions as $permission) {
        $roleManager->addPermissionToRole('admin', $permission->slug);
    }

    $role->refresh();
    expect($role->permissions)->toHaveCount(3);

    $permissionSlugs = $role->permissions->pluck('slug')->toArray();
    expect($permissionSlugs)->toContain('create-posts');
    expect($permissionSlugs)->toContain('edit-posts');
    expect($permissionSlugs)->toContain('delete-posts');
});

test('role manager does not duplicate permissions when adding same permission twice', function () {
    $roleManager = new RoleManager();

    $role = Role::create(['slug' => 'moderator', 'name' => 'Moderator']);
    $permission = Permission::create(['slug' => 'ban-users', 'name' => 'Ban Users']);

    // Add permission twice
    $roleManager->addPermissionToRole('moderator', 'ban-users');
    $roleManager->addPermissionToRole('moderator', 'ban-users');

    $role->refresh();
    expect($role->permissions)->toHaveCount(1);
});

test('role manager register method works with app container', function () {
    $roleManager = app(RoleManager::class);

    $role = $roleManager->register('contributor', 'Contributor');

    expect($role)->toBeInstanceOf(Role::class);
    expect($role->slug)->toBe('contributor');
    expect($role->name)->toBe('Contributor');
});

test('role manager addPermissionToRole method works with app container', function () {
    $roleManager = app(RoleManager::class);

    $role = Role::create(['slug' => 'author', 'name' => 'Author']);
    $permission = Permission::create(['slug' => 'publish-posts', 'name' => 'Publish Posts']);

    $roleManager->addPermissionToRole('author', 'publish-posts');

    $role->refresh();
    expect($role->permissions->first()->slug)->toBe('publish-posts');
});
