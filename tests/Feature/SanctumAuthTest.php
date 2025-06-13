<?php

namespace Tests\Feature;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\Database\seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_create_an_api_token()
    {
        $token = $this->admin->createToken( 'test-token' )->plainTextToken;

        $this->assertNotEmpty( $token );
        $this->assertDatabaseHas( 'personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id'   => $this->admin->id,
            'name'           => 'test-token',
        ] );
    }

    #[Test]
    public function api_routes_can_be_accessed_with_sanctum_authentication()
    {
        // Act as admin with Sanctum
        Sanctum::actingAs( $this->admin, [ 'manage_users' ] );

        // Test accessing a protected route
        $response = $this->getJson( '/api/cms/users' );

        $response->assertStatus( 200 );
    }

    #[Test]
    public function api_routes_cannot_be_accessed_without_authentication()
    {
        $userData = [
            'username'              => 'newuser',
            'email'                 => 'newuser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'first_name'            => 'New',
            'last_name'             => 'User',
        ];

        // Test accessing a protected route without authentication
        $response = $this->postJson( '/api/cms/users', $userData );

        // The response should be unauthorized or forbidden
        $response->assertStatus( 403 );
    }

    #[Test]
    public function api_routes_cannot_be_accessed_with_invalid_abilities()
    {
        // Act as user with Sanctum but with invalid abilities
        Sanctum::actingAs( $this->user );

        $userData = [
            'username'              => 'newuser',
            'email'                 => 'newuser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'first_name'            => 'New',
            'last_name'             => 'User',
        ];

        // Test accessing a protected route without authentication
        $response = $this->postJson( '/api/cms/users', $userData );

        // The response should be forbidden
        $response->assertStatus( 403 );
    }

    #[Test]
    public function a_user_can_access_routes_they_are_authorized_for()
    {
        // Act as admin with Sanctum and proper abilities
        Sanctum::actingAs( $this->admin );

        // Test accessing a route the admin is authorized for
        $response = $this->getJson( '/api/cms/users' );

        $response->assertStatus( 200 );
    }

    #[Test]
    public function a_user_cannot_access_routes_they_are_not_authorized_for()
    {
        // Act as regular user with Sanctum but without admin abilities
        Sanctum::actingAs( $this->user );

        $userData = [
            'username'              => 'newuser',
            'email'                 => 'newuser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'first_name'            => 'New',
            'last_name'             => 'User',
        ];

        // Test accessing a protected route without authentication
        $response = $this->postJson( '/api/cms/users', $userData );

        // The response should be forbidden
        $response->assertStatus( 403 );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed( RoleSeeder::class );

        // Create an admin user
        $this->admin = User::factory()->create( [
                                                    'role_id' => 3
                                                ] );

        // Create a regular user for testing
        $this->user = User::factory()->create( [
                                                   'role_id' => 1,
                                               ] );
    }
}
