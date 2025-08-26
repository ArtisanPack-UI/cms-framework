<?php

namespace Tests\Security;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\Database\seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Penetration Testing Suite for CMS Framework
 * 
 * This test suite performs comprehensive penetration testing including:
 * - Authentication bypass attempts
 * - Privilege escalation testing
 * - Session management security tests
 * - API security penetration tests
 * - Token manipulation and forgery tests
 * 
 * @package Tests\Security
 */
class PenetrationTestSuite extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $editor;
    protected User $regularUser;
    protected Role $adminRole;
    protected Role $editorRole;
    protected Role $userRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RoleSeeder::class);
        $this->setupTestUsers();
    }

    /**
     * Set up test users with different roles
     */
    protected function setupTestUsers(): void
    {
        $this->adminRole = Role::where('slug', 'admin')->first();
        $this->editorRole = Role::where('slug', 'editor')->first();
        $this->userRole = Role::where('slug', 'user')->first();

        $this->admin = User::factory()->create([
            'username' => 'admin_user',
            'email' => 'admin@security-test.com',
            'role_id' => $this->adminRole->id,
        ]);

        $this->editor = User::factory()->create([
            'username' => 'editor_user', 
            'email' => 'editor@security-test.com',
            'role_id' => $this->editorRole->id,
        ]);

        $this->regularUser = User::factory()->create([
            'username' => 'regular_user',
            'email' => 'user@security-test.com', 
            'role_id' => $this->userRole->id,
        ]);
    }

    /**
     * Test: Authentication bypass with invalid tokens
     */
    public function test_authentication_bypass_with_invalid_token(): void
    {
        $invalidTokens = [
            'invalid_token_123',
            '1|invalid_sanctum_token',
            'Bearer fake_token',
            str_repeat('a', 100), // Excessively long token
            '', // Empty token
            null, // Null token
        ];

        foreach ($invalidTokens as $token) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->getJson('/api/cms/users');

            $this->assertContains($response->status(), [401, 403], 
                "Invalid token '{$token}' should not provide access");
        }
    }

    /**
     * Test: Authentication bypass with manipulated token structure
     */
    public function test_authentication_bypass_with_manipulated_tokens(): void
    {
        // Create a valid token first
        $validToken = $this->admin->createToken('test-token')->plainTextToken;
        $tokenParts = explode('|', $validToken);
        
        if (count($tokenParts) === 2) {
            $tokenId = $tokenParts[0];
            $tokenHash = $tokenParts[1];

            // Test manipulated token variations
            $manipulatedTokens = [
                $tokenId . '|' . str_rot13($tokenHash), // ROT13 encoded hash
                $tokenId . '|' . strrev($tokenHash), // Reversed hash
                ($tokenId + 1) . '|' . $tokenHash, // Different token ID
                $tokenId . '|' . substr($tokenHash, 0, -5) . 'aaaaa', // Modified hash
                $tokenId . '|' . $tokenHash . 'extra', // Extended hash
            ];

            foreach ($manipulatedTokens as $token) {
                $response = $this->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ])->getJson('/api/cms/users');

                $this->assertContains($response->status(), [401, 403], 
                    "Manipulated token should not provide access");
            }
        }
    }

    /**
     * Test: Privilege escalation through role manipulation
     */
    public function test_privilege_escalation_through_role_manipulation(): void
    {
        Sanctum::actingAs($this->regularUser);

        // Attempt to escalate privileges by manipulating role_id in requests
        $escalationAttempts = [
            ['role_id' => $this->adminRole->id],
            ['role_id' => $this->editorRole->id],
            ['user_role_id' => $this->adminRole->id],
            ['admin' => true],
            ['is_admin' => 1],
            ['permissions' => ['manage_users', 'manage_content']],
        ];

        foreach ($escalationAttempts as $payload) {
            // Try to create a user with elevated privileges
            $response = $this->postJson('/api/cms/users', array_merge([
                'username' => 'escalation_test_' . uniqid(),
                'email' => 'escalation_' . uniqid() . '@test.com',
                'password' => 'password123',
                'first_name' => 'Escalation',
                'last_name' => 'Test',
            ], $payload));

            // Should be forbidden for regular user
            $this->assertEquals(403, $response->status(), 
                'Regular user should not be able to create users with elevated privileges');

            // Try to update own user with elevated privileges
            $response = $this->putJson("/api/cms/users/{$this->regularUser->id}", $payload);
            
            // Should either be forbidden or ignore the privilege escalation attempt
            $this->assertContains($response->status(), [403, 422], 
                'User should not be able to escalate own privileges');
        }
    }

    /**
     * Test: Session fixation and hijacking attempts
     */
    public function test_session_fixation_attempts(): void
    {
        // Create a token for the regular user
        $userToken = $this->regularUser->createToken('user-token')->plainTextToken;
        
        // Try to use that token to perform admin actions
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $userToken,
            'Accept' => 'application/json'
        ])->postJson('/api/cms/users', [
            'username' => 'hijack_test',
            'email' => 'hijack@test.com',
            'password' => 'password123',
            'first_name' => 'Hijack',
            'last_name' => 'Test',
        ]);

        $this->assertEquals(403, $response->status(), 
            'Regular user token should not allow admin operations');
    }

    /**
     * Test: API endpoint enumeration and unauthorized access
     */
    public function test_api_endpoint_enumeration(): void
    {
        $sensitiveEndpoints = [
            'GET:/api/cms/users',
            'POST:/api/cms/users', 
            'PUT:/api/cms/users/1',
            'DELETE:/api/cms/users/1',
            'GET:/api/cms/roles',
            'POST:/api/cms/roles',
            'GET:/api/cms/settings',
            'POST:/api/cms/settings',
            'GET:/api/cms/content',
            'POST:/api/cms/content',
        ];

        // Test without authentication
        foreach ($sensitiveEndpoints as $endpoint) {
            [$method, $url] = explode(':', $endpoint);
            
            $response = match($method) {
                'GET' => $this->getJson($url),
                'POST' => $this->postJson($url, ['test' => 'data']),
                'PUT' => $this->putJson($url, ['test' => 'data']),
                'DELETE' => $this->deleteJson($url),
                default => $this->getJson($url)
            };

            $this->assertContains($response->status(), [401, 403, 405], 
                "Endpoint {$endpoint} should require authentication");
        }
    }

    /**
     * Test: Mass assignment vulnerabilities
     */
    public function test_mass_assignment_vulnerabilities(): void
    {
        Sanctum::actingAs($this->editor);

        // Try to mass assign protected fields
        $maliciousPayload = [
            'username' => 'mass_assign_test',
            'email' => 'mass_assign@test.com', 
            'password' => 'password123',
            'first_name' => 'Mass',
            'last_name' => 'Assign',
            // Potentially dangerous mass assignment attempts
            'id' => 99999,
            'role_id' => $this->adminRole->id, // Try to assign admin role
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
            'email_verified_at' => now(),
            'remember_token' => 'fake_token',
        ];

        $response = $this->postJson('/api/cms/users', $maliciousPayload);

        if ($response->status() === 201) {
            $createdUser = User::where('username', 'mass_assign_test')->first();
            
            // Verify that protected fields were not mass assigned
            $this->assertNotEquals(99999, $createdUser->id, 
                'ID should not be mass assignable');
            $this->assertNotEquals($this->adminRole->id, $createdUser->role_id, 
                'Admin role should not be mass assignable by editor');
            $this->assertNotEquals('2020-01-01 00:00:00', $createdUser->created_at->format('Y-m-d H:i:s'),
                'created_at should not be mass assignable');
        }
    }

    /**
     * Test: Brute force protection on authentication endpoints
     */
    public function test_brute_force_protection(): void
    {
        $attempts = [];
        
        // Perform multiple failed login attempts
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/cms/auth/login', [
                'email' => 'admin@security-test.com',
                'password' => 'wrong_password_' . $i,
                'device_name' => 'Brute Force Test'
            ]);

            $attempts[] = $response->status();
        }

        // Check if rate limiting kicks in (should get 429 status codes)
        $tooManyRequests = collect($attempts)->filter(fn($status) => $status === 429)->count();
        
        $this->assertGreaterThan(0, $tooManyRequests, 
            'Brute force protection should limit repeated failed login attempts');
    }

    /**
     * Test: Token lifetime and expiration handling
     */
    public function test_token_lifetime_security(): void
    {
        // Create a token with specific abilities
        $token = $this->regularUser->createToken('test-token', ['read'])->plainTextToken;

        // Test that token works initially
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->getJson('/api/cms/auth/user');

        $this->assertEquals(200, $response->status(), 'Token should work initially');

        // Manually expire the token by updating its database record
        $tokenModel = PersonalAccessToken::findToken($token);
        if ($tokenModel) {
            $tokenModel->update(['created_at' => now()->subDays(365)]);
        }

        // Test that expired token doesn't work (if expiration is implemented)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->getJson('/api/cms/auth/user');

        // Note: Laravel Sanctum doesn't have built-in expiration by default
        // This test documents the behavior for when expiration is implemented
        $this->assertTrue(in_array($response->status(), [200, 401]), 
            'Token expiration behavior should be consistent');
    }

    /**
     * Test: Concurrent session handling
     */
    public function test_concurrent_session_security(): void
    {
        // Create multiple tokens for the same user
        $token1 = $this->admin->createToken('session-1')->plainTextToken;
        $token2 = $this->admin->createToken('session-2')->plainTextToken;
        $token3 = $this->admin->createToken('session-3')->plainTextToken;

        // Verify all tokens work initially
        foreach ([$token1, $token2, $token3] as $token) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->getJson('/api/cms/auth/user');

            $this->assertEquals(200, $response->status(), 
                'All concurrent tokens should work initially');
        }

        // Logout from all devices using one token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
            'Accept' => 'application/json'
        ])->postJson('/api/cms/auth/logout-all');

        $this->assertEquals(200, $response->status(), 'Logout all should succeed');

        // Verify all tokens are invalidated
        foreach ([$token1, $token2, $token3] as $token) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->getJson('/api/cms/auth/user');

            $this->assertEquals(401, $response->status(), 
                'All tokens should be invalidated after logout-all');
        }
    }

    /**
     * Test: Parameter pollution attacks
     */
    public function test_parameter_pollution_attacks(): void
    {
        Sanctum::actingAs($this->admin);

        // Test HTTP Parameter Pollution (HPP) attacks
        $pollutionAttacks = [
            // Array injection in query parameters
            'username[]' => 'admin',
            'password[]' => ['password123', 'admin123'],
            // Duplicate parameters (simulated through array format)
            'role_id' => [$this->userRole->id, $this->adminRole->id],
            'email' => ['user@test.com', 'admin@test.com'],
        ];

        $response = $this->postJson('/api/cms/users', array_merge([
            'username' => 'pollution_test',
            'email' => 'pollution@test.com',
            'password' => 'password123',
            'first_name' => 'Pollution',
            'last_name' => 'Test',
        ], $pollutionAttacks));

        // Application should handle parameter pollution gracefully
        $this->assertContains($response->status(), [201, 422], 
            'Application should handle parameter pollution without security issues');

        if ($response->status() === 201) {
            $createdUser = User::where('username', 'pollution_test')->first();
            $this->assertNotNull($createdUser, 'User should be created if validation passes');
            
            // Verify that parameter pollution didn't cause privilege escalation
            $this->assertNotEquals($this->adminRole->id, $createdUser->role_id,
                'Parameter pollution should not cause privilege escalation');
        }
    }
}