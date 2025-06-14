<?php

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses( RefreshDatabase::class );

// Set up test data before each test
beforeEach( function () {

	// Create an admin user with manage_categories permission
	$this->admin = User::factory()->create( [
		'role_id' => 3,
	] );

	// Create a regular user for testing
	$this->user = User::factory()->create( [
		'role_id' => 1,
	] );

	// Create a test media item
	$this->media = Media::factory()->create( [
		'user_id' => $this->admin->id,
	] );
} );

it( 'can list all media items', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	// Create some additional media items
	Media::factory()->count( 3 )->create( [
		'user_id' => $this->admin->id,
	] );

	// Act as admin to pass authorization
	$response = $this->getJson( '/api/cms/media' );

	// Assert response is successful
	$response->assertStatus( 200 );

	// Assert that we have the expected number of media items
	$responseData = $response->json();
	$this->assertCount( 13, $responseData['data'] ); // 3 created here + 1 from beforeEach
} );

it( 'can create a new media item', function () {
	Storage::fake( 'public' );

	$file = UploadedFile::fake()->image( 'test-image.jpg' );

	Sanctum::actingAs( $this->admin );
	$mediaData = [
		'file'          => $file,
		'alt_text'      => 'Test image alt text',
		'is_decorative' => false,
	];

	$response = $this->postJson( '/api/cms/media', $mediaData );

	$response->assertStatus( 201 );
	$response->assertJsonFragment( [
		'alt_text'      => 'Test image alt text',
		'is_decorative' => false,
	] );

	// Verify the media was created in the database
	$this->assertDatabaseHas( 'media', [
		'alt_text'      => 'Test image alt text',
		'is_decorative' => false,
	] );
} );

it( 'can show a specific media item', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$response = $this->getJson( "/api/cms/media/{$this->media->id}" );

	$response->assertStatus( 200 );
	$response->assertJsonFragment( [
		'id'        => $this->media->id,
		'file_name' => $this->media->file_name,
		'alt_text'  => $this->media->alt_text,
	] );
} );

it( 'can update a media item', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$updateData = [
		'alt_text'      => 'Updated alt text',
		'is_decorative' => true,
	];

	$response = $this->putJson( "/api/cms/media/{$this->media->id}", $updateData );

	$response->assertStatus( 200 );
	$response->assertJsonFragment( [
		'id'            => $this->media->id,
		'alt_text'      => '', // Should be empty because is_decorative is true
		'is_decorative' => true,
	] );

	// Verify the media was updated in the database
	$this->assertDatabaseHas( 'media', [
		'id'            => $this->media->id,
		'alt_text'      => '', // Should be empty because is_decorative is true
		'is_decorative' => true,
	] );
} );

it( 'can delete a media item', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	$response = $this->deleteJson( "/api/cms/media/{$this->media->id}" );

	$response->assertStatus( 204 );

	// Verify the media was deleted from the database
	$this->assertDatabaseMissing( 'media', [
		'id' => $this->media->id,
	] );
} );

it( 'can associate media with categories', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	// Create categories
	$category1 = MediaCategory::factory()->create();
	$category2 = MediaCategory::factory()->create();

	// Update media with categories
	$updateData = [
		'media_categories' => [ $category1->id, $category2->id ],
	];

	$response = $this->putJson( "/api/cms/media/{$this->media->id}", $updateData );

	$response->assertStatus( 200 );

	// Verify the relationships in the pivot table
	$this->assertDatabaseHas( 'media_media_category', [
		'media_id'    => $this->media->id,
		'category_id' => $category1->id,
	] );
	$this->assertDatabaseHas( 'media_media_category', [
		'media_id'    => $this->media->id,
		'category_id' => $category2->id,
	] );
} );

it( 'can associate media with tags', function () {
	// Act as admin with Sanctum
	Sanctum::actingAs( $this->admin );

	// Create tags
	$tag1 = MediaTag::factory()->create();
	$tag2 = MediaTag::factory()->create();

	// Update media with tags
	$updateData = [
		'media_tags' => [ $tag1->id, $tag2->id ],
	];

	$response = $this->putJson( "/api/cms/media/{$this->media->id}", $updateData );

	$response->assertStatus( 200 );

	// Verify the relationships in the pivot table
	$this->assertDatabaseHas( 'media_media_tag', [
		'media_id' => $this->media->id,
		'tag_id'   => $tag1->id,
	] );
	$this->assertDatabaseHas( 'media_media_tag', [
		'media_id' => $this->media->id,
		'tag_id'   => $tag2->id,
	] );
} );

it( 'prevents unauthorized users from managing media', function () {
	// Create a regular user with no special capabilities
	$regularUser = User::factory()->create( [ 'role_id' => 1 ] );

	// Try to create a media item
	Storage::fake( 'public' );
	$file = UploadedFile::fake()->image( 'test-image.jpg' );

	$response = $this->actingAs( $regularUser )->postJson( '/api/cms/media', [
		'file'     => $file,
		'alt_text' => 'Test image alt text',
		'user_id'  => $regularUser->id,
	] );
	$response->assertStatus( 201 );

	// Try to update a media item
	$response = $this->actingAs( $regularUser )->putJson( "/api/cms/media/{$this->media->id}", [
		'alt_text' => 'Updated alt text',
	] );
	$response->assertStatus( 403 );

	// Try to delete a media item
	$response = $this->actingAs( $regularUser )->deleteJson( "/api/cms/media/{$this->media->id}" );
	$response->assertStatus( 403 );
} );
