<?php

declare( strict_types=1 );

/**
 * Update capability tests.
 *
 * Coverage for #266 — the updates module ships ability names a host
 * application can authorize against before exposing the self-updater over
 * HTTP, and those abilities deny until the host grants them.
 *
 * @since 2.8.0
 */

use ArtisanPackUI\CMSFramework\Modules\Core\Providers\CoreServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCapability;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use ArtisanPackUI\Database\Seeders\PermissionsTableSeeder;
use ArtisanPackUI\Database\Seeders\RolesTableSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

/**
 * Create a user with no roles, and therefore no permissions.
 */
function makeUpdateCapabilityUser( string $email = 'updater@example.com' ): TestUser
{
    return TestUser::create( [
        'name'     => 'Update Operator',
        'email'    => $email,
        'password' => bcrypt( 'password' ),
    ] );
}

/**
 * Grant a user a permission by way of a role, which is how rbac resolves
 * effective permissions.
 */
function grantUpdateCapability( TestUser $user, string $ability ): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => $ability],
        ['name' => 'Ability ' . $ability],
    );

    $role = Role::firstOrCreate(
        ['slug' => 'updater'],
        ['name' => 'Updater'],
    );

    $role->permissions()->syncWithoutDetaching( [$permission->id] );
    $user->roles()->syncWithoutDetaching( [$role->id] );
    $user->flushPermissionCache();
}

test( 'the shipped ability names are stable', function (): void {
    expect( UpdateCapability::PERFORM )->toBe( 'cms.updates.perform' )
        ->and( UpdateCapability::ROLLBACK )->toBe( 'cms.updates.rollback' )
        ->and( UpdateCapability::VIEW )->toBe( 'cms.updates.view' );
} );

test( 'all returns every shipped ability', function (): void {
    expect( UpdateCapability::all() )->toBe( [
        UpdateCapability::PERFORM,
        UpdateCapability::ROLLBACK,
        UpdateCapability::VIEW,
    ] );
} );

test( 'every ability is registered on the gate', function ( string $ability ): void {
    expect( Gate::has( $ability ) )->toBeTrue();
} )->with( UpdateCapability::all() );

test( 'abilities deny by default', function ( string $ability ): void {
    $user = makeUpdateCapabilityUser();

    expect( Gate::forUser( $user )->allows( $ability ) )->toBeFalse();
} )->with( UpdateCapability::all() );

test( 'an rbac permission grants the matching ability and nothing else', function (): void {
    $user = makeUpdateCapabilityUser();

    grantUpdateCapability( $user, UpdateCapability::PERFORM );

    expect( Gate::forUser( $user )->allows( UpdateCapability::PERFORM ) )->toBeTrue()
        ->and( Gate::forUser( $user )->allows( UpdateCapability::ROLLBACK ) )->toBeFalse()
        ->and( Gate::forUser( $user )->allows( UpdateCapability::VIEW ) )->toBeFalse();
} );

test( 'a host definition registered before the framework boots is left alone', function (): void {
    $user = makeUpdateCapabilityUser();

    Gate::define( UpdateCapability::PERFORM, fn ( $authorizable ): bool => true );

    // Re-boot the provider the way a host registering its ability first would
    // have it run: the shipped deny-by-default definition must not clobber the
    // one already on the Gate.
    ( new CoreServiceProvider( app() ) )->boot();

    expect( Gate::forUser( $user )->allows( UpdateCapability::PERFORM ) )->toBeTrue();
} );

test( 'the permissions seeder seeds every update ability', function (): void {
    ( new RolesTableSeeder )->run();
    ( new PermissionsTableSeeder )->run();

    expect( Permission::whereIn( 'slug', UpdateCapability::all() )->count() )
        ->toBe( count( UpdateCapability::all() ) );
} );

test( 'the seeded admin role can perform updates and a fresh user cannot', function (): void {
    ( new RolesTableSeeder )->run();
    ( new PermissionsTableSeeder )->run();

    $admin = makeUpdateCapabilityUser( 'admin@example.com' );
    $admin->roles()->attach( Role::where( 'slug', 'admin' )->firstOrFail()->id );
    $admin->flushPermissionCache();

    $editor = makeUpdateCapabilityUser( 'editor@example.com' );
    $editor->roles()->attach( Role::where( 'slug', 'editor' )->firstOrFail()->id );
    $editor->flushPermissionCache();

    expect( Gate::forUser( $admin )->allows( UpdateCapability::PERFORM ) )->toBeTrue()
        ->and( Gate::forUser( $admin )->allows( UpdateCapability::ROLLBACK ) )->toBeTrue()
        ->and( Gate::forUser( $admin )->allows( UpdateCapability::VIEW ) )->toBeTrue()
        ->and( Gate::forUser( $editor )->allows( UpdateCapability::PERFORM ) )->toBeFalse();
} );
