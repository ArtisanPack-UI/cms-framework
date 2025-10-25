<?php
/**
 * Feature Tests for the Settings API Endpoints.
 *
 * Verifies API routes, authorization, validation, and controller responses.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Settings\Tests\Feature
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Tests\Feature;

use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use function Pest\Laravel\{actingAs, getJson, postJson, putJson, deleteJson};

// --- FIX 1: Import the eval'd User class ---
use App\Models\User;

uses( RefreshDatabase::class ); // Use RefreshDatabase trait

beforeEach( function () {
	$this->artisan( 'migrate', [ '--database' => 'testing' ] );

	// Set up configuration
	config( [ 'cms-framework.user_model' => 'App\Models\User' ] );

	// Create a test user model class if it doesn't exist
	if ( ! class_exists( 'App\\Models\\User' ) ) {
		eval( '
            namespace App\\Models {
                // --- FIX 2: Extend the correct Authenticatable class for `actingAs` ---
                use Illuminate\\Foundation\\Auth\\User as Authenticatable;
                use ArtisanPackUI\\CMSFramework\\Modules\\Users\\Models\\Concerns\\HasRolesAndPermissions;
                
                class User extends Authenticatable { // <-- Must extend Authenticatable
                    use HasRolesAndPermissions;
                    
                    protected $table = "users"; // Explicitly set table name
                    protected $fillable = ["name", "email", "password"];
                    protected $hidden = ["password"];
                }
            }
        ' );
	}

	// Now this works because of the `use App\Models\User;` at the top
	$this->user = User::create( [
									'name'     => 'Test User',
									'email'    => 'test@example.com',
									'password' => bcrypt( 'password' ),
								] );

	// Assign role (this part is fine)
	$role = Role::create( [ 'name' => 'Admin', 'slug' => 'admin' ] );
	$this->user->roles()->attach( $role );
} );

// Helper function within the test file scope
function grantPermission( string $permission ): void
{
	addFilter( $permission, fn( $perm ) => $perm, 10, 0 );
	// --- FIX 3: Ensure Gate type-hint uses the imported User class ---
	Gate::define( $permission, fn( User $user ) => true );
}

test( 'unauthenticated user cannot get settings', function () {
	getJson( '/api/v1/settings' )
		->assertUnauthorized();
} );

test( 'user without permission cannot list settings', function () {
	actingAs( $this->user )
		->getJson( '/api/v1/settings' )
		->assertForbidden();
} );

test( 'user with permission can list settings', function () {
	grantPermission( 'settings.manage' ); // Assumes 'settings.manage' grants viewAny via filter default
	Setting::create( [ 'key' => 'test-1', 'value' => 'value-1' ] );
	Setting::create( [ 'key' => 'test-2', 'value' => 'value-2' ] );

	actingAs( $this->user )
		->getJson( '/api/v1/settings' )
		->assertOk()
		->assertJsonCount( 2, 'data' )
		->assertJsonFragment( [ 'key' => 'test-1' ] );
} );

test( 'user can store setting', function () {
	grantPermission( 'settings.manage' ); // Assumes 'settings.manage' grants create

	$data = [
		'key'   => 'new-setting',
		'value' => 'new-value',
		'type'  => 'string',
	];

	actingAs( $this->user )
		->postJson( '/api/v1/settings', $data )
		->assertCreated()
		->assertJsonFragment( [ 'key' => 'new-setting' ] );

	$this->assertDatabaseHas( 'settings', [ 'key' => 'new-setting', 'value' => 'new-value' ] );
} );

test( 'store setting fails validation', function () {
	grantPermission( 'settings.manage' );

	$data = [
		'key'   => 'INVALID KEY WITH SPACES',
		'value' => '', // Fails 'required'
		'type'  => 'string',
	];

	actingAs( $this->user )
		->postJson( '/api/v1/settings', $data )
		->assertStatus( 422 ) // Unprocessable Entity
		->assertJsonValidationErrors( [ 'key', 'value' ] );
} );

test( 'user can update setting', function () {
	grantPermission( 'settings.manage' ); // Assumes 'settings.manage' grants update
	$setting = Setting::create( [ 'key' => 'update-key', 'value' => 'old' ] );

	$data = [
		'key'   => 'update-key', // Use the key in the route for PUT
		'value' => 'new-value',
		'type'  => 'string',
	];

	actingAs( $this->user )
		->putJson( '/api/v1/settings/' . $setting->key, $data ) // Use key in URL
		->assertOk()
		->assertJsonFragment( [ 'value' => 'new-value' ] );

	$this->assertDatabaseHas( 'settings', [ 'key' => 'update-key', 'value' => 'new-value' ] );
} );

test( 'user can delete setting', function () {
	// Grant the specific 'settings.delete' permission
	addFilter( 'settings.delete', fn() => 'settings.delete' );
	grantPermission( 'settings.delete' );

	$setting = Setting::create( [ 'key' => 'delete-key', 'value' => 'old' ] );

	actingAs( $this->user )
		->deleteJson( '/api/v1/settings/' . $setting->key ) // Use key in URL
		->assertNoContent(); // 204 No Content

	$this->assertDatabaseMissing( 'settings', [ 'key' => 'delete-key' ] );
} );