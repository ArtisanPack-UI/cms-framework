<?php

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

test('permission can be created with fillable attributes', function () {
    $permission = Permission::create([
        'name' => 'Manage Users',
        'slug' => 'manage-users',
    ]);

    expect($permission->name)->toBe('Manage Users');
    expect($permission->slug)->toBe('manage-users');
    expect($permission->exists)->toBeTrue();
});

test('permission has many-to-many relationship with roles', function () {
    $permission = Permission::create([
        'name' => 'Edit Content',
        'slug' => 'edit-content',
    ]);

    $role = Role::create([
        'name' => 'Content Editor',
        'slug' => 'content-editor',
    ]);

    $permission->roles()->attach($role);

    expect($permission->roles)->toHaveCount(1);
    expect($permission->roles->first()->name)->toBe('Content Editor');
    expect($permission->roles->first()->slug)->toBe('content-editor');
});

test('permission fillable attributes are correct', function () {
    $permission = new Permission;

    expect($permission->getFillable())->toContain('name');
    expect($permission->getFillable())->toContain('slug');
});

test('permission can belong to multiple roles', function () {
    $permission = Permission::create([
        'name' => 'View Dashboard',
        'slug' => 'view-dashboard',
    ]);

    $roles = [
        Role::create(['name' => 'Admin', 'slug' => 'admin']),
        Role::create(['name' => 'Manager', 'slug' => 'manager']),
        Role::create(['name' => 'Editor', 'slug' => 'editor']),
    ];

    $permission->roles()->attach($roles);

    expect($permission->roles)->toHaveCount(3);
    expect($permission->roles->pluck('slug')->toArray())->toContain('admin');
    expect($permission->roles->pluck('slug')->toArray())->toContain('manager');
    expect($permission->roles->pluck('slug')->toArray())->toContain('editor');
});

test('permission can be detached from roles', function () {
    $permission = Permission::create([
        'name' => 'Delete Posts',
        'slug' => 'delete-posts',
    ]);

    $role = Role::create([
        'name' => 'Moderator',
        'slug' => 'moderator',
    ]);

    $permission->roles()->attach($role);
    expect($permission->roles)->toHaveCount(1);

    $permission->roles()->detach($role);
    expect($permission->fresh()->roles)->toHaveCount(0);
});

test('permission relationship works both ways', function () {
    $permission = Permission::create([
        'name' => 'Publish Content',
        'slug' => 'publish-content',
    ]);

    $role = Role::create([
        'name' => 'Publisher',
        'slug' => 'publisher',
    ]);

    // Attach via permission
    $permission->roles()->attach($role);

    // Check both sides of the relationship
    expect($permission->roles->first()->slug)->toBe('publisher');
    expect($role->fresh()->permissions->first()->slug)->toBe('publish-content');
});
