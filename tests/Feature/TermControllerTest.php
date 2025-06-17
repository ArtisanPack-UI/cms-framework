<?php

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\ContentType;
use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses( RefreshDatabase::class );

// Set up test data before each test
beforeEach( function () {
	// Create roles with specific capabilities
	$adminRole = \ArtisanPackUI\CMSFramework\Models\Role::factory()->create( [
		'name' => 'Admin',
		'capabilities' => ['manage_terms'],
	] );

	$userRole = \ArtisanPackUI\CMSFramework\Models\Role::factory()->create( [
		'name' => 'User',
		'capabilities' => [],
	] );

	// Create an admin user
	$this->admin = User::factory()->create( [
		'role_id' => $adminRole->id,
	] );

	// Create a regular user for testing
	$this->user = User::factory()->create( [
		'role_id' => $userRole->id,
	] );

	// Create a test taxonomy
	$this->taxonomy = Taxonomy::factory()->create( [
		'hierarchical' => true,
	] );

	// Create a test term
	$this->term = Term::factory()->create( [
		'taxonomy_id' => $this->taxonomy->id,
	] );

	// Create a test content type
	$this->contentType = ContentType::factory()->create( [ 'handle' => 'posts' ] );
	$this->contentType = ContentType::factory()->create( [ 'handle' => 'pages' ] );
} );

it( 'can list all terms', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	// Create some additional terms
	Term::factory()->count( 3 )->create( [
		'taxonomy_id' => $this->taxonomy->id,
	] );

	// Make request to list terms
	$response = $this->getJson( '/api/cms/terms' );

	// Assert response is successful
	$response->assertStatus( 200 );

	// Assert that we have the expected number of terms
	$responseData = $response->json();
	$this->assertCount( 4, $responseData['data'] ); // 3 created here + 1 from beforeEach
} );

it( 'can create a new term', function () {
	Sanctum::actingAs( $this->admin );

	$termData = [
		'name'        => 'New Term',
		'slug'        => 'new-term',
		'taxonomy_id' => $this->taxonomy->id,
	];

	$response = $this->postJson( '/api/cms/terms', $termData );

	$response->assertStatus( 201 );
	$response->assertJsonFragment( [
		'name' => 'New Term',
		'slug' => 'new-term',
	] );

	// Verify the term was created in the database
	$this->assertDatabaseHas( 'terms', [
		'name'        => 'New Term',
		'slug'        => 'new-term',
		'taxonomy_id' => $this->taxonomy->id,
	] );
} );

it( 'can create a hierarchical term', function () {
	Sanctum::actingAs( $this->admin );

	$termData = [
		'name'        => 'Child Term',
		'slug'        => 'child-term',
		'taxonomy_id' => $this->taxonomy->id,
		'parent_id'   => $this->term->id,
	];

	$response = $this->postJson( '/api/cms/terms', $termData );

	$response->assertStatus( 201 );
	$response->assertJsonFragment( [
		'name'      => 'Child Term',
		'slug'      => 'child-term',
		'parent_id' => $this->term->id,
	] );

	// Verify the term was created in the database
	$this->assertDatabaseHas( 'terms', [
		'name'        => 'Child Term',
		'slug'        => 'child-term',
		'taxonomy_id' => $this->taxonomy->id,
		'parent_id'   => $this->term->id,
	] );
} );

it( 'can show a specific term', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$response = $this->getJson( "/api/cms/terms/{$this->term->id}" );

	$response->assertStatus( 200 );
	$response->assertJsonFragment( [
		'id'   => $this->term->id,
		'name' => $this->term->name,
		'slug' => $this->term->slug,
	] );
} );

it( 'can update a term', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$updateData = [
		'name'        => 'Updated Name',
		'slug'        => 'updated-slug',
		'taxonomy_id' => $this->taxonomy->id,
	];

	$response = $this->putJson( "/api/cms/terms/{$this->term->id}", $updateData );

	$response->assertStatus( 200 );
	$response->assertJsonFragment( [
		'id'   => $this->term->id,
		'name' => 'Updated Name',
		'slug' => 'updated-slug',
	] );

	// Verify the term was updated in the database
	$this->assertDatabaseHas( 'terms', [
		'id'   => $this->term->id,
		'name' => 'Updated Name',
		'slug' => 'updated-slug',
	] );
} );

it( 'can delete a term', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$response = $this->deleteJson( "/api/cms/terms/{$this->term->id}" );

	$response->assertStatus( 200 );

	// Verify the term was deleted from the database
	$this->assertDatabaseMissing( 'terms', [
		'id' => $this->term->id,
	] );
} );

it( 'can associate terms with content', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	// Create content
	$content = Content::factory()->create( [
		'author_id' => $this->admin->id,
	] );

	// Associate term with content
	$content->terms()->attach( $this->term->id );

	// Verify the relationship in the pivot table
	$this->assertDatabaseHas( 'term_content', [
		'term_id'    => $this->term->id,
		'content_id' => $content->id,
	] );

	// Verify the relationship is accessible through the model
	$this->assertTrue( $this->term->content->contains( $content ) );
	$this->assertTrue( $content->terms->contains( $this->term ) );
} );

it( 'prevents unauthorized users from managing terms', function () {
	// Act as regular user with Sanctum
	Sanctum::actingAs( $this->user );

	// Try to create a term
	$termData = [
		'name'        => 'New Term',
		'slug'        => 'new-term',
		'taxonomy_id' => $this->taxonomy->id,
	];

	$response = $this->postJson( '/api/cms/terms', $termData );
	$response->assertStatus( 403 );

	// Try to update a term
	$response = $this->putJson( "/api/cms/terms/{$this->term->id}", [
		'name'        => 'Updated Name',
		'slug'        => $this->term->slug,
		'taxonomy_id' => $this->taxonomy->id,
	] );
	$response->assertStatus( 403 );

	// Try to delete a term
	$response = $this->deleteJson( "/api/cms/terms/{$this->term->id}" );
	$response->assertStatus( 403 );
} );
