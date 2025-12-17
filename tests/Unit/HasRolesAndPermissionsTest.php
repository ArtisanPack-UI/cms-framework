<?php

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Create a test user model that uses the trait
class TestUser extends Model
{
    use HasRolesAndPermissions;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);

    // Set up test configuration
    config(['cms-framework.user_model' => TestUser::class]);
});

test('trait provides roles relationship', function () {
    $user = TestUser::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    expect($user->roles())->toBeInstanceOf(BelongsToMany::class);
});

test('user can have roles attached', function () {
    $user = TestUser::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $adminRole = Role::create([
        'name' => 'Administrator',
        'slug' => 'admin',
    ]);

    $user->roles()->attach($adminRole);

    expect($user->roles)->toHaveCount(1);
    expect($user->roles->first()->slug)->toBe('admin');
});

test('hasRole method returns true when user has the role', function () {
    $user = TestUser::create([
        'name' => 'Bob Smith',
        'email' => 'bob@example.com',
        'password' => bcrypt('password'),
    ]);

    $managerRole = Role::create([
        'name' => 'Manager',
        'slug' => 'manager',
    ]);

    $user->roles()->attach($managerRole);

    expect($user->hasRole('manager'))->toBeTrue();
});

test('hasRole method returns false when user does not have the role', function () {
    $user = TestUser::create([
        'name' => 'Alice Johnson',
        'email' => 'alice@example.com',
        'password' => bcrypt('password'),
    ]);

    expect($user->hasRole('admin'))->toBeFalse();
});

test('hasPermissionTo method returns true when user has permission through role', function () {
    $user = TestUser::create([
        'name' => 'Charlie Brown',
        'email' => 'charlie@example.com',
        'password' => bcrypt('password'),
    ]);

    $editorRole = Role::create([
        'name' => 'Editor',
        'slug' => 'editor',
    ]);

    $editPermission = Permission::create([
        'name' => 'Edit Posts',
        'slug' => 'edit-posts',
    ]);

    $editorRole->permissions()->attach($editPermission);
    $user->roles()->attach($editorRole);

    expect($user->hasPermissionTo('edit-posts'))->toBeTrue();
});

test('hasPermissionTo method returns false when user does not have permission', function () {
    $user = TestUser::create([
        'name' => 'David Wilson',
        'email' => 'david@example.com',
        'password' => bcrypt('password'),
    ]);

    $viewerRole = Role::create([
        'name' => 'Viewer',
        'slug' => 'viewer',
    ]);

    $user->roles()->attach($viewerRole);

    expect($user->hasPermissionTo('delete-posts'))->toBeFalse();
});

test('user can have multiple roles with different permissions', function () {
    $user = TestUser::create([
        'name' => 'Eva Green',
        'email' => 'eva@example.com',
        'password' => bcrypt('password'),
    ]);

    // Create roles
    $editorRole = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $moderatorRole = Role::create(['name' => 'Moderator', 'slug' => 'moderator']);

    // Create permissions
    $editPermission = Permission::create(['name' => 'Edit Posts', 'slug' => 'edit-posts']);
    $deletePermission = Permission::create(['name' => 'Delete Comments', 'slug' => 'delete-comments']);

    // Assign permissions to roles
    $editorRole->permissions()->attach($editPermission);
    $moderatorRole->permissions()->attach($deletePermission);

    // Assign roles to user
    $user->roles()->attach([$editorRole->id, $moderatorRole->id]);

    expect($user->hasRole('editor'))->toBeTrue();
    expect($user->hasRole('moderator'))->toBeTrue();
    expect($user->hasPermissionTo('edit-posts'))->toBeTrue();
    expect($user->hasPermissionTo('delete-comments'))->toBeTrue();
});

test('hasPermissionTo works with role that has multiple permissions', function () {
    $user = TestUser::create([
        'name' => 'Frank Miller',
        'email' => 'frank@example.com',
        'password' => bcrypt('password'),
    ]);

    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

    $permissions = [
        Permission::create(['name' => 'Create Posts', 'slug' => 'create-posts']),
        Permission::create(['name' => 'Edit Posts', 'slug' => 'edit-posts']),
        Permission::create(['name' => 'Delete Posts', 'slug' => 'delete-posts']),
    ];

    $adminRole->permissions()->attach($permissions);
    $user->roles()->attach($adminRole);

    expect($user->hasPermissionTo('create-posts'))->toBeTrue();
    expect($user->hasPermissionTo('edit-posts'))->toBeTrue();
    expect($user->hasPermissionTo('delete-posts'))->toBeTrue();
    expect($user->hasPermissionTo('non-existent-permission'))->toBeFalse();
});
