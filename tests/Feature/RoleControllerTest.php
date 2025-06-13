<?php

use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\Database\seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses( RefreshDatabase::class );

// Create a user with admin capabilities for authorization
beforeEach( function () {

	// Create an admin user
	$this->admin = User::factory()->create( [
		'role_id' => 3,
	] );

	$this->role = Role::where( 'slug', 'editor' )->first();

} );

it( 'can list all roles', function () {
	// Create some additional roles
	Role::factory()->count( 3 )->create();

	// Act as admin to pass authorization
	$response = $this->actingAs( $this->admin )->getJson( '/api/cms/roles' );

	// Assert response is successful
	$response->assertStatus( 200 );

	// Dump the response for debugging
	$responseData = $response->json();

	// Assert that we have the expected number of roles
	// Instead of assertJsonCount, we'll check the count manually
	$this->assertCount( 6, $responseData['data'] ); // 3 created here + admin role + editor role from beforeEach
} );

it( 'can create a new role', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$roleData = [
		'name'         => 'Test Author',
		'slug'         => 'test-author',
		'description'  => 'Can author content',
		'capabilities' => [ 'create_posts', 'edit_own_posts' ],
	];

	$response = $this->postJson( '/api/cms/roles', $roleData );

	$response->assertStatus( 201 );
	$response->assertJsonFragment( [
		'name'        => 'Test Author',
		'slug'        => 'test-author',
		'description' => 'Can author content',
	] );

	// Verify the role was created in the database
	$this->assertDatabaseHas( 'roles', [
		'name'        => 'Test Author',
		'slug'        => 'test-author',
		'description' => 'Can author content',
	] );
} );

it( 'can show a specific role', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$response = $this->getJson( "/api/cms/roles/{$this->role->id}" );

	$response->assertStatus( 200 );
	$response->assertJsonFragment( [
		'id'          => $this->role->id,
		'name'        => 'Editor',
		'slug'        => 'editor',
		'description' => 'Users who can publish and manage posts including those of other users, and manage categories, links, and comments.',
	] );
} );

it( 'can update a role', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$updateData = [
		'name'         => 'Senior Editor',
		'description'  => 'Can edit and publish content',
		'capabilities' => [ 'edit_posts', 'publish_posts', 'delete_posts' ],
	];

	$response = $this->putJson( "/api/cms/roles/{$this->role->id}", $updateData );

	$response->assertStatus( 200 );
	$response->assertJsonFragment( [
		'id'          => $this->role->id,
		'name'        => 'Senior Editor',
		'description' => 'Can edit and publish content',
	] );

	// Verify the role was updated in the database
	$this->assertDatabaseHas( 'roles', [
		'id'          => $this->role->id,
		'name'        => 'Senior Editor',
		'description' => 'Can edit and publish content',
	] );
} );

it( 'can delete a role', function () {
	$response = $this->actingAs( $this->admin )->deleteJson( "/api/cms/roles/{$this->role->id}" );

	$response->assertStatus( 200 );

	// Verify the role was deleted from the database
	$this->assertDatabaseMissing( 'roles', [
		'id' => $this->role->id,
	] );
} );

it( 'validates required fields when creating a role', function () {
	$response = $this->actingAs( $this->admin )->postJson( '/api/cms/roles', [] );

	$response->assertStatus( 422 );
	$response->assertJsonValidationErrors( [ 'name', 'slug' ] );
} );

it( 'validates unique slug when creating a role', function () {
	$roleData = [
		'name'        => 'Duplicate Editor',
		'slug'        => 'editor', // This slug already exists from the beforeEach
		'description' => 'Another editor role',
	];

	$response = $this->actingAs( $this->admin )->postJson( '/api/cms/roles', $roleData );

	$response->assertStatus( 422 );
	$response->assertJsonValidationErrors( [ 'slug' ] );
} );

it( 'prevents unauthorized users from accessing role endpoints', function () {
	// Create a regular user with no special capabilities
	$regularUser = User::factory()->create();

	// Try to create a role
	$response = $this->actingAs( $regularUser )->postJson( '/api/cms/roles', [
		'name' => 'New Role',
		'slug' => 'new-role',
	] );
	$response->assertStatus( 403 );

	// Try to update a role
	$response = $this->actingAs( $regularUser )->putJson( "/api/cms/roles/{$this->role->id}", [
		'name' => 'Updated Role',
	] );
	$response->assertStatus( 403 );

	// Try to delete a role
	$response = $this->actingAs( $regularUser )->deleteJson( "/api/cms/roles/{$this->role->id}" );
	$response->assertStatus( 403 );
} );

it( 'handles capabilities as an array', function () {
	$roleData = [
		'name'         => 'Contributor',
		'slug'         => 'contributor',
		'description'  => 'Limited contributor',
		'capabilities' => [ 'create_posts' ],
	];

	$response = $this->actingAs( $this->admin )->postJson( '/api/cms/roles', $roleData );

	$response->assertStatus( 201 );

	// Get the created role from the database
	$role = Role::where( 'slug', 'contributor' )->first();

	// Verify capabilities are stored as an array
	$this->assertIsArray( $role->capabilities );
	$this->assertContains( 'create_posts', $role->capabilities );
} );
