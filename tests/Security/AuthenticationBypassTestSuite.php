<?php

namespace Tests\Security;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\Database\seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Authentication Bypass Testing Suite for CMS Framework
 * 
 * This test suite performs comprehensive authentication bypass testing including:
 * - Token manipulation and forgery tests
 * - Session fixation attack scenarios
 * - Brute force protection testing
 * - Multi-factor authentication bypass attempts
 * - Password reset vulnerability testing
 * - JWT token tampering tests
 * - Session hijacking prevention
 * - Cookie manipulation attacks
 * 
 * @package Tests\Security
 */
class AuthenticationBypassTestSuite extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected User $disabledUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RoleSeeder::class);
        $this->setupTestUsers();
    }

    /**
     * Set up test users
     */
    protected function setupTestUsers(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $userRole = Role::where('slug', 'user')->first();
        
        $this->admin = User::factory()->create([
            'username' => 'admin_auth_test',
            'email' => 'admin@authtest.com',
            'password' => Hash::make('secure_admin_password_123'),
            'role_id' => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        $this->regularUser = User::factory()->create([
            'username' => 'user_auth_test',
            'email' => 'user@authtest.com',
            'password' => Hash::make('user_password_456'),
            'role_id' => $userRole->id,
            'email_verified_at' => now(),
        ]);

        $this->disabledUser = User::factory()->create([
            'username' => 'disabled_auth_test',
            'email' => 'disabled@authtest.com',
            'password' => Hash::make('disabled_password_789'),
            'role_id' => $userRole->id,
            'email_verified_at' => null, // Not verified
        ]);
    }

    /**
     * Test brute force protection on login attempts
     */
    public function test_brute_force_protection_on_login(): void
    {
        $maxAttempts = 10;
        $responses = [];
        
        // Perform multiple failed login attempts
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->postJson('/api/cms/auth/login', [
                'email' => 'admin@authtest.com',
                'password' => 'wrong_password_' . $i,
                'device_name' => 'Brute Force Test ' . $i,
            ]);

            $responses[] = $response->status();
            
            // Stop if rate limiting kicks in
            if ($response->status() === 429) {
                break;
            }
        }

        // Should have rate limiting protection
        $rateLimitedResponses = array_filter($responses, fn($status) => $status === 429);
        $this->assertGreaterThan(0, count($rateLimitedResponses), 
            'Brute force protection should rate limit repeated failed login attempts');

        // Wait a moment and test if legitimate login still works
        sleep(1);
        $legitimateResponse = $this->postJson('/api/cms/auth/login', [
            'email' => 'admin@authtest.com',
            'password' => 'secure_admin_password_123',
            'device_name' => 'Legitimate Login',
        ]);

        // Legitimate login should work (or be temporarily blocked with clear message)
        $this->assertContains($legitimateResponse->status(), [200, 429], 
            'Legitimate login should work or be temporarily blocked with clear indication');
    }

    /**
     * Test token manipulation and forgery attempts
     */
    public function test_token_manipulation_and_forgery(): void
    {
        // Create a valid token
        $validToken = $this->admin->createToken('test-token')->plainTextToken;
        $tokenParts = explode('|', $validToken);
        
        if (count($tokenParts) === 2) {
            $tokenId = $tokenParts[0];
            $tokenHash = $tokenParts[1];

            $manipulationAttempts = [
                // Token structure manipulation
                $tokenId . '|' . str_reverse($tokenHash),
                $tokenId . '|' . strtoupper($tokenHash),
                $tokenId . '|' . substr($tokenHash, 1),
                ($tokenId + 1) . '|' . $tokenHash,
                $tokenId . '|' . $tokenHash . 'extra',
                
                // Encoding attempts
                $tokenId . '|' . base64_encode($tokenHash),
                $tokenId . '|' . md5($tokenHash),
                $tokenId . '|' . sha1($tokenHash),
                
                // Special character injection
                $tokenId . '|' . $tokenHash . '; DROP TABLE users;',
                $tokenId . '|' . $tokenHash . '<script>alert(1)</script>',
                
                // Null byte injection
                $tokenId . '|' . $tokenHash . "\x00admin",
                
                // Unicode manipulation
                $tokenId . '|' . $tokenHash . "\u0000",
                
                // Length manipulation
                $tokenId . '|' . str_repeat('a', strlen($tokenHash)),
            ];

            foreach ($manipulationAttempts as $manipulatedToken) {
                $response = $this->withHeaders([
                    'Authorization' => 'Bearer ' . $manipulatedToken,
                    'Accept' => 'application/json',
                ])->getJson('/api/cms/auth/user');

                $this->assertEquals(401, $response->status(), 
                    "Manipulated token should be rejected: " . substr($manipulatedToken, 0, 50) . '...');
            }
        }
    }

    /**
     * Test session fixation attack prevention
     */
    public function test_session_fixation_prevention(): void
    {
        // Create multiple tokens for the same user
        $token1 = $this->admin->createToken('session-1')->plainTextToken;
        $token2 = $this->admin->createToken('session-2')->plainTextToken;
        
        // Both tokens should work initially
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->getJson('/api/cms/auth/user');
        
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->getJson('/api/cms/auth/user');

        $this->assertEquals(200, $response1->status(), 'First token should work');
        $this->assertEquals(200, $response2->status(), 'Second token should work');

        // Simulate session fixation by trying to reuse a token after logout
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/cms/auth/logout');

        $this->assertEquals(200, $logoutResponse->status(), 'Logout should succeed');

        // Try to reuse the logged-out token
        $reuseResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->getJson('/api/cms/auth/user');

        $this->assertEquals(401, $reuseResponse->status(), 
            'Logged-out token should not be reusable (session fixation prevention)');

        // The other token should still work
        $otherTokenResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->getJson('/api/cms/auth/user');

        $this->assertEquals(200, $otherTokenResponse->status(), 
            'Other valid token should still work');
    }

    /**
     * Test password-based attacks and weak password handling
     */
    public function test_password_based_attacks(): void
    {
        $commonPasswords = [
            'password',
            '123456',
            'admin',
            'password123',
            'qwerty',
            'letmein',
            'welcome',
            'monkey',
            '1234567890',
            'admin123',
        ];

        foreach ($commonPasswords as $password) {
            // Test login with common passwords
            $response = $this->postJson('/api/cms/auth/login', [
                'email' => 'admin@authtest.com',
                'password' => $password,
                'device_name' => 'Common Password Test',
            ]);

            // Should not succeed with common passwords (assuming admin doesn't use them)
            $this->assertContains($response->status(), [401, 422, 429], 
                "Common password '{$password}' should not succeed for admin login");
        }

        // Test dictionary attack patterns
        $dictionaryPatterns = [
            'password' . date('Y'), // password2025
            'admin' . date('m'), // admin08  
            $this->admin->username . '123', // admin_auth_test123
            $this->admin->email . '!', // admin@authtest.com!
        ];

        foreach ($dictionaryPatterns as $pattern) {
            $response = $this->postJson('/api/cms/auth/login', [
                'email' => 'admin@authtest.com',
                'password' => $pattern,
                'device_name' => 'Dictionary Attack Test',
            ]);

            $this->assertContains($response->status(), [401, 422, 429], 
                "Dictionary pattern '{$pattern}' should not succeed");
        }
    }

    /**
     * Test authentication bypass through HTTP headers
     */
    public function test_authentication_bypass_through_headers(): void
    {
        $bypassHeaders = [
            // Admin headers that might be trusted
            ['X-Forwarded-User' => 'admin'],
            ['X-Remote-User' => 'admin@authtest.com'],
            ['X-Authenticated-User' => 'admin_auth_test'],
            
            // Internal headers
            ['X-Internal-Auth' => 'true'],
            ['X-Auth-Bypass' => '1'],
            ['X-Admin-Override' => 'admin'],
            
            // Proxy headers
            ['X-Forwarded-For' => '127.0.0.1'],
            ['X-Real-IP' => '127.0.0.1'],
            ['X-Original-URL' => '/admin'],
            
            // Custom headers
            ['Authorization' => 'Basic YWRtaW46YWRtaW4='], // admin:admin in base64
            ['X-API-Key' => 'secret-admin-key'],
            ['X-Auth-Token' => 'bypass-token-123'],
        ];

        foreach ($bypassHeaders as $headers) {
            $response = $this->withHeaders($headers)->getJson('/api/cms/users');

            // Headers should not bypass authentication
            $this->assertContains($response->status(), [401, 403], 
                'Headers should not bypass authentication: ' . json_encode($headers));
        }
    }

    /**
     * Test JWT token tampering (if JWT is used)
     */
    public function test_jwt_token_tampering(): void
    {
        // Create a valid token first
        $validToken = $this->admin->createToken('jwt-test')->plainTextToken;
        
        // Simulate JWT-like token structure manipulation
        $jwtLikePayloads = [
            // Header manipulation
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJub25lIn0.eyJzdWIiOiIxIiwibmFtZSI6ImFkbWluIiwiaWF0IjoxNjkzMDU0ODAwfQ.',
            
            // Payload manipulation (admin claim)
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIiwibmFtZSI6ImFkbWluIiwiYWRtaW4iOnRydWUsImlhdCI6MTY5MzA1NDgwMH0.signature',
            
            // Algorithm confusion
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJub25lIn0.eyJzdWIiOiIxIiwibmFtZSI6ImFkbWluIn0.',
            
            // Null signature
            $validToken . '.null',
            
            // Empty signature
            $validToken . '.',
            
            // Modified signature
            $validToken . 'modified',
        ];

        foreach ($jwtLikePayloads as $tamperedToken) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $tamperedToken,
                'Accept' => 'application/json',
            ])->getJson('/api/cms/users');

            $this->assertContains($response->status(), [401, 403], 
                'Tampered JWT-like token should be rejected');
        }
    }

    /**
     * Test account enumeration prevention
     */
    public function test_account_enumeration_prevention(): void
    {
        $testEmails = [
            'admin@authtest.com', // Valid email
            'user@authtest.com', // Valid email
            'nonexistent@authtest.com', // Non-existent email
            'fake@example.com', // Non-existent email
            '', // Empty email
            'invalid-email', // Invalid format
        ];

        $responseTimes = [];
        $responseMessages = [];

        foreach ($testEmails as $email) {
            $startTime = microtime(true);
            
            $response = $this->postJson('/api/cms/auth/login', [
                'email' => $email,
                'password' => 'wrong_password',
                'device_name' => 'Enumeration Test',
            ]);
            
            $endTime = microtime(true);
            $responseTimes[$email] = $endTime - $startTime;
            $responseMessages[$email] = $response->json('message') ?? '';
        }

        // Response times should be similar (within reasonable variance)
        $times = array_values($responseTimes);
        $avgTime = array_sum($times) / count($times);
        
        foreach ($times as $time) {
            $variance = abs($time - $avgTime) / $avgTime;
            $this->assertLessThan(0.5, $variance, 
                'Response times should not vary significantly (prevents user enumeration)');
        }

        // Error messages should be similar for existing and non-existing users
        $uniqueMessages = array_unique(array_values($responseMessages));
        $this->assertLessThanOrEqual(2, count($uniqueMessages), 
            'Error messages should not reveal whether user exists (prevents enumeration)');
    }

    /**
     * Test multi-factor authentication bypass attempts
     */
    public function test_mfa_bypass_attempts(): void
    {
        // Note: This assumes MFA is implemented. Adjust based on actual implementation
        
        // Create a user that might have MFA enabled
        $mfaUser = User::factory()->create([
            'username' => 'mfa_test_user',
            'email' => 'mfa@authtest.com',
            'password' => Hash::make('mfa_password_123'),
            'role_id' => Role::where('slug', 'admin')->first()->id,
            // Simulate MFA settings
            'settings' => json_encode(['mfa_enabled' => true, 'mfa_secret' => 'fake_secret']),
        ]);

        // Test login without MFA token
        $response = $this->postJson('/api/cms/auth/login', [
            'email' => 'mfa@authtest.com',
            'password' => 'mfa_password_123',
            'device_name' => 'MFA Bypass Test',
        ]);

        // Should require MFA if enabled (implementation dependent)
        $this->assertContains($response->status(), [200, 401, 422], 
            'MFA-enabled account should handle authentication appropriately');

        // Test MFA bypass attempts
        $mfaBypassAttempts = [
            ['mfa_token' => '000000'],
            ['mfa_token' => '123456'],
            ['mfa_token' => ''],
            ['mfa_token' => 'bypass'],
            ['mfa_disabled' => 'true'],
            ['skip_mfa' => '1'],
        ];

        foreach ($mfaBypassAttempts as $attempt) {
            $response = $this->postJson('/api/cms/auth/login', array_merge([
                'email' => 'mfa@authtest.com',
                'password' => 'mfa_password_123',
                'device_name' => 'MFA Bypass Test',
            ], $attempt));

            // MFA should not be bypassable
            $this->assertContains($response->status(), [401, 422], 
                'MFA bypass attempt should be rejected: ' . json_encode($attempt));
        }
    }

    /**
     * Test password reset vulnerability
     */
    public function test_password_reset_vulnerabilities(): void
    {
        // Test password reset token manipulation
        $resetTokenAttempts = [
            'fake_reset_token_123',
            '',
            '0',
            'admin',
            $this->admin->id,
            base64_encode('admin'),
            md5('admin'),
            str_repeat('a', 100),
        ];

        foreach ($resetTokenAttempts as $token) {
            // Note: Adjust endpoint based on actual password reset implementation
            $response = $this->postJson('/api/cms/auth/password/reset', [
                'token' => $token,
                'email' => 'admin@authtest.com',
                'password' => 'new_password_123',
                'password_confirmation' => 'new_password_123',
            ]);

            // Invalid tokens should be rejected
            $this->assertContains($response->status(), [404, 422, 401], 
                "Invalid password reset token should be rejected: {$token}");
        }

        // Test password reset without proper validation
        $response = $this->postJson('/api/cms/auth/password/reset', [
            'email' => 'admin@authtest.com',
            'password' => 'new_password_123',
            'password_confirmation' => 'new_password_123',
            // Missing token
        ]);

        $this->assertContains($response->status(), [404, 422], 
            'Password reset without token should be rejected');
    }

    /**
     * Test concurrent session management
     */
    public function test_concurrent_session_management(): void
    {
        // Create multiple sessions for the same user
        $sessions = [];
        for ($i = 1; $i <= 5; $i++) {
            $token = $this->admin->createToken("session-{$i}");
            $sessions[] = $token->plainTextToken;
        }

        // All sessions should work initially
        foreach ($sessions as $index => $token) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/cms/auth/user');

            $this->assertEquals(200, $response->status(), 
                "Session {$index} should work initially");
        }

        // Test session limit enforcement (if implemented)
        // Create additional sessions that might exceed limits
        for ($i = 6; $i <= 10; $i++) {
            $token = $this->admin->createToken("extra-session-{$i}");
            $extraSession = $token->plainTextToken;

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $extraSession,
            ])->getJson('/api/cms/auth/user');

            // Should either work or enforce session limits
            $this->assertContains($response->status(), [200, 401], 
                'Extra sessions should be handled according to session policy');
        }

        // Test concurrent session invalidation
        $logoutAllResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $sessions[0],
        ])->postJson('/api/cms/auth/logout-all');

        $this->assertEquals(200, $logoutAllResponse->status(), 
            'Logout all should succeed');

        // All sessions should be invalidated
        foreach ($sessions as $index => $token) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/cms/auth/user');

            $this->assertEquals(401, $response->status(), 
                "Session {$index} should be invalidated after logout-all");
        }
    }

    /**
     * Test cookie-based authentication vulnerabilities
     */
    public function test_cookie_based_authentication_vulnerabilities(): void
    {
        $maliciousCookies = [
            // Session hijacking attempts
            'laravel_session=hijacked_session_123',
            'remember_token=fake_remember_token',
            'auth_token=admin_bypass_token',
            
            // XSS payload in cookies
            'user_pref=<script>alert(1)</script>',
            'theme=javascript:alert(1)',
            
            // SQL injection in cookies
            'sort_by=name; DROP TABLE users; --',
            
            // Path traversal
            'file_path=../../../etc/passwd',
            
            // Very long cookies (buffer overflow attempt)
            'long_cookie=' . str_repeat('A', 10000),
        ];

        foreach ($maliciousCookies as $cookie) {
            $response = $this->withHeaders([
                'Cookie' => $cookie,
            ])->getJson('/api/cms/users');

            // Malicious cookies should not provide authentication
            $this->assertContains($response->status(), [401, 403], 
                "Malicious cookie should not provide authentication: {$cookie}");
        }
    }

    /**
     * Test timing attack prevention in authentication
     */
    public function test_timing_attack_prevention(): void
    {
        $credentials = [
            ['email' => 'admin@authtest.com', 'password' => 'wrong_password'], // Valid user, wrong password
            ['email' => 'nonexistent@test.com', 'password' => 'any_password'], // Non-existent user
            ['email' => '', 'password' => ''], // Empty credentials
            ['email' => 'admin@authtest.com', 'password' => ''], // Valid user, empty password
        ];

        $timings = [];

        foreach ($credentials as $index => $cred) {
            $startTime = microtime(true);
            
            $response = $this->postJson('/api/cms/auth/login', array_merge($cred, [
                'device_name' => "Timing Test {$index}",
            ]));
            
            $endTime = microtime(true);
            $timings[$index] = $endTime - $startTime;
        }

        // Calculate timing variance
        $avgTime = array_sum($timings) / count($timings);
        $maxVariance = 0;

        foreach ($timings as $time) {
            $variance = abs($time - $avgTime) / $avgTime;
            $maxVariance = max($maxVariance, $variance);
        }

        // Timing variance should be reasonable (not revealing information)
        $this->assertLessThan(0.8, $maxVariance, 
            'Authentication timing should not vary significantly (prevents timing attacks)');
    }
}