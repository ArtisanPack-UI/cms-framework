<?php

declare( strict_types=1 );

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

// Custom permission model that a host might rebind via
// `config(['artisanpack.rbac.models.permission' => CustomPermissionModel::class])`.
// Extends the rbac base directly (not cms-framework's Permission) so it
// represents the worst-case for `resolvePermissionKeys()`: an instance that
// would not satisfy `$permission instanceof Permission`.
class CustomPermissionModel extends ArtisanPackUI\Rbac\Models\Permission
{
    protected $table = 'permissions';
}

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    // Set up test configuration
    config( ['artisanpack.cms-framework.user_model' => RoleTestUser::class] );
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
    $userModel = config( 'artisanpack.cms-framework.user_model', 'App\Models\User' );

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
    expect( $role->permissions->pluck( 'slug' )->toArray() )->toContain( 'edit-posts' );
    expect( $role->permissions->pluck( 'slug' )->toArray() )->toContain( 'delete-posts' );
} );

test( 'resolvePermissionKeys fast-paths instances of the configured rbac permission model', function (): void {
    // Rebind the rbac permission model so callers pass instances of a
    // custom model that does *not* extend the cms-framework Permission
    // subclass. This is the codepath a host using
    // `config(['artisanpack.rbac.models.permission' => CustomPermissionModel::class])`
    // would exercise — without the configured-model fast-path, the
    // instance would fall through to the name/slug DB lookup and get
    // dropped because no row matches the model object as a `where` value.
    config( ['artisanpack.rbac.models.permission' => CustomPermissionModel::class] );

    $role       = new Role;
    $permission = CustomPermissionModel::create( ['name' => 'Edit Posts', 'slug' => 'edit-posts'] );

    // resolvePermissionKeys is protected — invoke it directly so the
    // test is unaffected by Eloquent's BelongsToMany FK derivation,
    // which would otherwise pivot on the custom model's class basename
    // and is orthogonal to the fast-path under test.
    $method = new ReflectionMethod( $role, 'resolvePermissionKeys' );
    $keys   = $method->invoke( $role, [$permission] );

    expect( $keys )->toBe( [$permission->getKey()] );
} );
