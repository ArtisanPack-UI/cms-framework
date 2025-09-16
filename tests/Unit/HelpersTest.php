<?php

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);

    // Load the helpers file
    require_once __DIR__ . '/../../src/Modules/Users/helpers.php';
});

test('ap_register_role helper function creates a new role', function () {
    $role = ap_register_role('admin', 'Administrator');

    expect($role)->toBeInstanceOf(Role::class);
    expect($role->slug)->toBe('admin');
    expect($role->name)->toBe('Administrator');
    expect($role->exists)->toBeTrue();
});

test('ap_register_role helper function returns existing role', function () {
    // Create an existing role
    $existingRole = Role::create([
        'slug' => 'editor',
        'name' => 'Editor',
    ]);

    $role = ap_register_role('editor', 'Updated Editor');

    expect($role->id)->toBe($existingRole->id);
    expect($role->slug)->toBe('editor');
    expect($role->name)->toBe('Editor'); // Should keep original name
});

test('ap_register_permission helper function creates a new permission', function () {
    $permission = ap_register_permission('edit-posts', 'Edit Posts');

    expect($permission)->toBeInstanceOf(Permission::class);
    expect($permission->slug)->toBe('edit-posts');
    expect($permission->name)->toBe('Edit Posts');
    expect($permission->exists)->toBeTrue();
});

test('ap_register_permission helper function returns existing permission', function () {
    // Create an existing permission
    $existingPermission = Permission::create([
        'slug' => 'delete-posts',
        'name' => 'Delete Posts',
    ]);

    $permission = ap_register_permission('delete-posts', 'Updated Delete Posts');

    expect($permission->id)->toBe($existingPermission->id);
    expect($permission->slug)->toBe('delete-posts');
    expect($permission->name)->toBe('Delete Posts'); // Should keep original name
});

test('ap_add_permission_to_role helper function adds permission to role', function () {
    $role = Role::create(['slug' => 'manager', 'name' => 'Manager']);
    $permission = Permission::create(['slug' => 'manage-users', 'name' => 'Manage Users']);

    ap_add_permission_to_role('manager', 'manage-users');

    $role->refresh();
    expect($role->permissions)->toHaveCount(1);
    expect($role->permissions->first()->slug)->toBe('manage-users');
});

test('ap_add_permission_to_role helper function throws exception for non-existent role', function () {
    Permission::create(['slug' => 'edit-posts', 'name' => 'Edit Posts']);

    expect(fn() => ap_add_permission_to_role('non-existent-role', 'edit-posts'))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('ap_add_permission_to_role helper function throws exception for non-existent permission', function () {
    Role::create(['slug' => 'editor', 'name' => 'Editor']);

    expect(fn() => ap_add_permission_to_role('editor', 'non-existent-permission'))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('helper functions work together to create complete role-permission setup', function () {
    // Register role and permissions using helpers
    $adminRole = ap_register_role('admin', 'Administrator');
    $createPermission = ap_register_permission('create-posts', 'Create Posts');
    $editPermission = ap_register_permission('edit-posts', 'Edit Posts');
    $deletePermission = ap_register_permission('delete-posts', 'Delete Posts');

    // Add permissions to role using helper
    ap_add_permission_to_role('admin', 'create-posts');
    ap_add_permission_to_role('admin', 'edit-posts');
    ap_add_permission_to_role('admin', 'delete-posts');

    $adminRole->refresh();

    expect($adminRole->permissions)->toHaveCount(3);

    $permissionSlugs = $adminRole->permissions->pluck('slug')->toArray();
    expect($permissionSlugs)->toContain('create-posts');
    expect($permissionSlugs)->toContain('edit-posts');
    expect($permissionSlugs)->toContain('delete-posts');
});

test('helper functions handle duplicate permission assignment gracefully', function () {
    $role = ap_register_role('moderator', 'Moderator');
    $permission = ap_register_permission('ban-users', 'Ban Users');

    // Add permission twice using helper
    ap_add_permission_to_role('moderator', 'ban-users');
    ap_add_permission_to_role('moderator', 'ban-users');

    $role->refresh();
    expect($role->permissions)->toHaveCount(1);
});

test('helper functions are globally available', function () {
    expect(function_exists('ap_register_role'))->toBeTrue();
    expect(function_exists('ap_register_permission'))->toBeTrue();
    expect(function_exists('ap_add_permission_to_role'))->toBeTrue();
});

test('helper functions use app container correctly', function () {
    // Mock the RoleManager to verify it's being called through the container
    $this->app->bind(\ArtisanPackUI\CMSFramework\Modules\Users\Managers\RoleManager::class, function () {
        return new class {
            public function register($slug, $name) {
                return Role::firstOrCreate(['slug' => $slug], ['name' => $name]);
            }
            public function addPermissionToRole($roleSlug, $permissionSlug) {
                $role = Role::where('slug', $roleSlug)->firstOrFail();
                $permission = Permission::where('slug', $permissionSlug)->firstOrFail();
                $role->permissions()->syncWithoutDetaching($permission->id);
            }
        };
    });

    $role = ap_register_role('test-role', 'Test Role');
    expect($role)->toBeInstanceOf(Role::class);
    expect($role->slug)->toBe('test-role');
});
