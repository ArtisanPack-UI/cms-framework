<?php

use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'permission manager can register a new permission', function (): void {
    $permissionManager = new PermissionManager;

    $permission = $permissionManager->register( 'edit-posts', 'Edit Posts' );

    expect( $permission )->toBeInstanceOf( Permission::class );
    expect( $permission->slug )->toBe( 'edit-posts' );
    expect( $permission->name )->toBe( 'Edit Posts' );
    expect( $permission->exists )->toBeTrue();
} );

test( 'permission manager returns existing permission if already registered', function (): void {
    $permissionManager = new PermissionManager;

    // Create an existing permission
    $existingPermission = Permission::create( [
        'slug' => 'delete-posts',
        'name' => 'Delete Posts',
    ] );

    $permission = $permissionManager->register( 'delete-posts', 'Updated Delete Posts' );

    expect( $permission->id )->toBe( $existingPermission->id );
    expect( $permission->slug )->toBe( 'delete-posts' );
    expect( $permission->name )->toBe( 'Delete Posts' ); // Should keep original name
} );

test( 'permission manager can register multiple permissions', function (): void {
    $permissionManager = new PermissionManager;

    $permissions = [
        $permissionManager->register( 'create-posts', 'Create Posts' ),
        $permissionManager->register( 'edit-posts', 'Edit Posts' ),
        $permissionManager->register( 'delete-posts', 'Delete Posts' ),
        $permissionManager->register( 'publish-posts', 'Publish Posts' ),
    ];

    expect( $permissions )->toHaveCount( 4 );

    foreach ( $permissions as $permission ) {
        expect( $permission )->toBeInstanceOf( Permission::class );
        expect( $permission->exists )->toBeTrue();
    }

    expect( Permission::count() )->toBe( 4 );
} );

test( 'permission manager register method works with app container', function (): void {
    $permissionManager = app( PermissionManager::class );

    $permission = $permissionManager->register( 'manage-users', 'Manage Users' );

    expect( $permission )->toBeInstanceOf( Permission::class );
    expect( $permission->slug )->toBe( 'manage-users' );
    expect( $permission->name )->toBe( 'Manage Users' );
} );

test( 'permission manager handles empty slug gracefully', function (): void {
    $permissionManager = new PermissionManager;

    $permission = $permissionManager->register( '', 'Empty Slug Permission' );

    expect( $permission )->toBeInstanceOf( Permission::class );
    expect( $permission->slug )->toBe( '' );
    expect( $permission->name )->toBe( 'Empty Slug Permission' );
} );

test( 'permission manager handles empty name gracefully', function (): void {
    $permissionManager = new PermissionManager;

    $permission = $permissionManager->register( 'empty-name', '' );

    expect( $permission )->toBeInstanceOf( Permission::class );
    expect( $permission->slug )->toBe( 'empty-name' );
    expect( $permission->name )->toBe( '' );
} );

test( 'permission manager register returns same instance for duplicate calls', function (): void {
    $permissionManager = new PermissionManager;

    $permission1 = $permissionManager->register( 'view-dashboard', 'View Dashboard' );
    $permission2 = $permissionManager->register( 'view-dashboard', 'View Dashboard Updated' );

    expect( $permission1->id )->toBe( $permission2->id );
    expect( $permission1->name )->toBe( 'View Dashboard' ); // Original name preserved
    expect( $permission2->name )->toBe( 'View Dashboard' ); // Original name preserved
} );

test( 'permission manager works with special characters in slug and name', function (): void {
    $permissionManager = new PermissionManager;

    $permission = $permissionManager->register( 'manage-user@settings', 'Manage User\'s Settings & Preferences' );

    expect( $permission )->toBeInstanceOf( Permission::class );
    expect( $permission->slug )->toBe( 'manage-user@settings' );
    expect( $permission->name )->toBe( 'Manage User\'s Settings & Preferences' );
} );

test( 'permission manager registers permissions with long names', function (): void {
    $permissionManager = new PermissionManager;

    $longName = 'This is a very long permission name that contains multiple words and describes a complex permission that might be used in a real application with detailed requirements and specifications';

    $permission = $permissionManager->register( 'long-permission', $longName);

    expect( $permission)->toBeInstanceOf( Permission::class);
    expect( $permission->slug)->toBe( 'long-permission');
    expect( $permission->name)->toBe( $longName);
});
