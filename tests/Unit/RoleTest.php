<?php

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Database\Eloquent\Model;

// Create a test user model that uses the trait
class RoleTestUser extends Model
{
    use HasRolesAndPermissions;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    // Set up test configuration
    config( ['cms-framework.user_model' => RoleTestUser::class] );
} );

test( 'role can be created with fillable attributes', function (): void {
    $role = Role::create( [
        'name' => 'Administrator',
        'slug' => 'admin',
    ] );

    expect( $role->name )->toBe( 'Administrator' );
    expect( $role->slug )->toBe( 'admin' );
    expect( $role->exists )->toBeTrue();
} );

test( 'role has many-to-many relationship with permissions', function (): void {
    $role = Role::create( [
        'name' => 'Editor',
        'slug' => 'editor',
    ] );

    $permission = Permission::create( [
        'name' => 'Edit Posts',
        'slug' => 'edit-posts',
    ] );

    $role->permissions()->attach( $permission );

    expect( $role->permissions )->toHaveCount( 1 );
    expect( $role->permissions->first()->name )->toBe( 'Edit Posts' );
    expect( $role->permissions->first()->slug )->toBe( 'edit-posts' );
} );

test( 'role has many-to-many relationship with users', function (): void {
    $role = Role::create( [
        'name' => 'Manager',
        'slug' => 'manager',
    ] );

    // Get the configured user model
    $userModel = config( 'cms-framework.user_model', 'App\Models\User' );

    // Create a user using the configured model
    $user = $userModel::create( [
        'name'     => 'John Doe',
        'email'    => 'john@example.com',
        'password' => bcrypt( 'password' ),
    ] );

    $role->users()->attach( $user );

    expect( $role->users )->toHaveCount( 1 );
    expect( $role->users->first()->name )->toBe( 'John Doe' );
    expect( $role->users->first()->email )->toBe( 'john@example.com' );
} );

test( 'role fillable attributes are correct', function (): void {
    $role = new Role;

    expect( $role->getFillable() )->toContain( 'name' );
    expect( $role->getFillable() )->toContain( 'slug' );
} );

test( 'role can have multiple permissions', function (): void {
    $role = Role::create( [
        'name' => 'Super Admin',
        'slug' => 'super-admin',
    ] );

    $permissions = [
        Permission::create( ['name' => 'Create Posts', 'slug' => 'create-posts'] ),
        Permission::create( ['name' => 'Edit Posts', 'slug' => 'edit-posts'] ),
        Permission::create( ['name' => 'Delete Posts', 'slug' => 'delete-posts'] ),
    ];

    $role->permissions()->attach( $permissions );

    expect( $role->permissions )->toHaveCount( 3 );
    expect( $role->permissions->pluck( 'slug' )->toArray() )->toContain( 'create-posts' );
    expect( $role->permissions->pluck( 'slug')->toArray())->toContain( 'edit-posts');
    expect( $role->permissions->pluck( 'slug')->toArray())->toContain( 'delete-posts');
});
