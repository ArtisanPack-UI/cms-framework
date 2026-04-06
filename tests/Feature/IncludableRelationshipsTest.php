<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;

/**
 * Grant a permission capability via Gate for testing authorization.
 */
function grantRolePermission( string $permission ): void
{
    Gate::define( $permission, fn () => true );
}

/**
 * Create a test user with an attached role.
 */
function createUserWithRole( string $roleState = 'admin' ): TestUser
{
    $user = TestUser::factory()->create();
    $role = Role::factory()->{$roleState}()->create();
    $user->roles()->attach( $role );

    return $user;
}

/**
 * Create a role with an attached permission.
 *
 * @return array{role: Role, permission: Permission}
 */
function createRoleWithPermission(): array
{
    $role       = Role::factory()->admin()->create();
    $permission = Permission::create( ['name' => 'Edit Posts', 'slug' => 'edit-posts'] );
    $role->permissions()->attach( $permission );

    return ['role' => $role, 'permission' => $permission];
}

/**
 * Create an authenticated user with role management permission granted.
 */
function authenticatedRoleManager(): TestUser
{
    $user = TestUser::factory()->create();
    grantRolePermission( 'roles.manage' );

    return $user;
}

// --- User Controller: include parameter tests ---

test( 'user index returns roles by default when no include param', function (): void {
    $user = createUserWithRole();

    $response = $this->getJson( '/api/v1/users' );

    $response->assertSuccessful();
    expect( $response->json( 'data.0.roles' ) )->toHaveCount( 1 );
    expect( $response->json( 'data.0.roles.0.slug' ) )->toBe( 'admin' );
} );

test( 'user index loads only requested includes', function (): void {
    $user = createUserWithRole();

    $response = $this->getJson( '/api/v1/users?include=roles' );

    $response->assertSuccessful();
    expect( $response->json( 'data.0.roles' ) )->toHaveCount( 1 );
} );

test( 'user index returns empty roles when include is empty string', function (): void {
    $user = createUserWithRole();

    $response = $this->getJson( '/api/v1/users?include=' );

    $response->assertSuccessful();
    expect( $response->json( 'data.0' ) )->not->toHaveKey( 'roles' );
} );

test( 'user index ignores invalid include values', function (): void {
    TestUser::factory()->create();

    $response = $this->getJson( '/api/v1/users?include=nonexistent,invalid' );

    $response->assertSuccessful();
    expect( $response->json( 'data.0' ) )->not->toHaveKey( 'roles' );
} );

test( 'user index filters out invalid includes but keeps valid ones', function (): void {
    $user = createUserWithRole();

    $response = $this->getJson( '/api/v1/users?include=roles,nonexistent' );

    $response->assertSuccessful();
    expect( $response->json( 'data.0.roles' ) )->toHaveCount( 1 );
} );

test( 'user show loads requested includes', function (): void {
    $user = createUserWithRole( 'editor' );

    $response = $this->getJson( "/api/v1/users/{$user->id}?include=roles" );

    $response->assertSuccessful();
    expect( $response->json( 'data.roles' ) )->toHaveCount( 1 );
    expect( $response->json( 'data.roles.0.slug' ) )->toBe( 'editor' );
} );

test( 'user show omits relationships with empty include', function (): void {
    $user = createUserWithRole();

    $response = $this->getJson( "/api/v1/users/{$user->id}?include=" );

    $response->assertSuccessful();
    expect( $response->json( 'data' ) )->not->toHaveKey( 'roles' );
} );

test( 'user store loads requested includes', function (): void {
    $response = $this->postJson( '/api/v1/users?include=roles', [
        'name'     => 'Test User',
        'email'    => 'test@example.com',
        'password' => 'password123',
    ] );

    $response->assertStatus( 201 );
    expect( $response->json( 'data.roles' ) )->toBeArray();
} );

test( 'user update loads requested includes', function (): void {
    $user = createUserWithRole();

    $response = $this->putJson( "/api/v1/users/{$user->id}?include=roles", [
        'name' => 'Updated Name',
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'data.roles' ) )->toHaveCount( 1 );
} );

// --- Role Controller: include parameter tests ---

test( 'role index returns permissions by default when no include param', function (): void {
    $actor                          = authenticatedRoleManager();
    ['role' => $role]               = createRoleWithPermission();

    $response = $this->actingAs( $actor )->getJson( '/api/v1/roles' );

    $response->assertSuccessful();
    expect( $response->json( 'data.0.permissions' ) )->toHaveCount( 1 );
    expect( $response->json( 'data.0.permissions.0.slug' ) )->toBe( 'edit-posts' );
} );

test( 'role index loads only requested includes', function (): void {
    $actor = authenticatedRoleManager();
    createRoleWithPermission();

    $response = $this->actingAs( $actor )->getJson( '/api/v1/roles?include=permissions' );

    $response->assertSuccessful();
    expect( $response->json( 'data.0.permissions' ) )->toHaveCount( 1 );
} );

test( 'role index omits permissions with empty include', function (): void {
    $actor = authenticatedRoleManager();
    createRoleWithPermission();

    $response = $this->actingAs( $actor )->getJson( '/api/v1/roles?include=' );

    $response->assertSuccessful();
    expect( $response->json( 'data.0' ) )->not->toHaveKey( 'permissions' );
} );

test( 'role show loads requested includes', function (): void {
    $actor                          = authenticatedRoleManager();
    ['role' => $role]               = createRoleWithPermission();

    $response = $this->actingAs( $actor )->getJson( "/api/v1/roles/{$role->id}?include=permissions" );

    $response->assertSuccessful();
    expect( $response->json( 'data.permissions' ) )->toHaveCount( 1 );
} );

test( 'role store loads requested includes', function (): void {
    $actor = authenticatedRoleManager();

    $response = $this->actingAs( $actor )->postJson( '/api/v1/roles?include=permissions', [
        'name' => 'New Role',
        'slug' => 'new-role',
    ] );

    $response->assertStatus( 201 );
    expect( $response->json( 'data.permissions' ) )->toBeArray();
} );

test( 'role update loads requested includes', function (): void {
    $actor                          = authenticatedRoleManager();
    ['role' => $role]               = createRoleWithPermission();

    $response = $this->actingAs( $actor )->putJson( "/api/v1/roles/{$role->id}?include=permissions", [
        'name' => 'Updated Admin',
    ] );

    $response->assertSuccessful();
    expect( $response->json( 'data.permissions' ) )->toHaveCount( 1 );
});

test( 'role index ignores invalid include values', function (): void {
    $actor = authenticatedRoleManager();
    Role::factory()->admin()->create();

    $response = $this->actingAs( $actor)->getJson( '/api/v1/roles?include=nonexistent');

    $response->assertSuccessful();
    expect( $response->json( 'data.0'))->not->toHaveKey( 'permissions');
});
