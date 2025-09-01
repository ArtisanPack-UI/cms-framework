<?php

namespace Tests\Security;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\Database\seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SQL Injection Testing Suite for CMS Framework
 * 
 * This test suite performs comprehensive SQL injection testing including:
 * - Parameter injection tests for all endpoints
 * - Database query injection scenarios
 * - ORM injection vulnerability tests
 * - Union-based SQL injection attempts
 * - Time-based blind SQL injection tests
 * - Boolean-based blind SQL injection tests
 * - Error-based SQL injection detection
 * 
 * @package Tests\Security
 */
class SqlInjectionTestSuite extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $testUser;
    protected Content $testContent;
    protected array $sqlInjectionPayloads;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RoleSeeder::class);
        $this->setupTestData();
        $this->setupSqlInjectionPayloads();
    }

    /**
     * Set up test data
     */
    protected function setupTestData(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        
        $this->admin = User::factory()->create([
            'username' => 'admin_sql_test',
            'email' => 'admin@sqltest.com',
            'role_id' => $adminRole->id,
        ]);

        $this->testUser = User::factory()->create([
            'username' => 'test_user_sql',
            'email' => 'testuser@sqltest.com',
            'role_id' => $adminRole->id,
        ]);

        $this->testContent = Content::factory()->create([
            'title' => 'Test Content for SQL Injection',
            'content' => 'Test content body',
            'author_id' => $this->admin->id,
            'status' => 'published',
        ]);
    }

    /**
     * Set up SQL injection payloads for testing
     */
    protected function setupSqlInjectionPayloads(): void
    {
        $this->sqlInjectionPayloads = [
            // Classic SQL injection attempts
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "' OR 1=1 --",
            "' OR 'a'='a",
            "admin'--",
            "admin'#",
            "admin'/*",
            "' OR 1=1#",
            "' OR 1=1--",
            "') OR '1'='1--",
            "') OR ('1'='1--",

            // Union-based injection
            "' UNION SELECT 1,2,3,4,5--",
            "' UNION SELECT NULL,username,password,NULL,NULL FROM users--",
            "' UNION ALL SELECT 1,2,3,4,5--",
            "1' UNION SELECT 1,2,3,4,5--",

            // Time-based blind injection
            "'; WAITFOR DELAY '00:00:05'--",
            "'; SELECT SLEEP(5)--",
            "'; pg_sleep(5)--",
            "' AND (SELECT * FROM (SELECT(SLEEP(5)))a)--",

            // Boolean-based blind injection
            "' AND 1=1--",
            "' AND 1=2--",
            "' AND SUBSTRING(@@version,1,1)='5'--",
            "' AND (SELECT COUNT(*) FROM users)>0--",

            // Error-based injection
            "' AND EXTRACTVALUE(1, CONCAT(0x7e, (SELECT @@version), 0x7e))--",
            "' AND (SELECT * FROM information_schema.tables)--",
            "' AND UPDATEXML(1,CONCAT(0x7e,(SELECT @@version),0x7e),1)--",

            // NoSQL injection attempts (for completeness)
            "{\"\$ne\": null}",
            "{\"\$gt\": \"\"}",
            "'; return true; //",

            // Second-order injection
            "test'; INSERT INTO users (username, email) VALUES ('hacker', 'hack@test.com'); --",

            // Encoded payloads
            "%27%20OR%20%271%27%3D%271", // ' OR '1'='1 (URL encoded)
            "&#x27; OR &#x27;1&#x27;=&#x27;1", // HTML encoded
            
            // Advanced payloads
            "' AND (SELECT CASE WHEN (1=1) THEN 1/0 ELSE 1 END)--",
            "' AND (SELECT CASE WHEN (SUBSTRING(@@version,1,1)='5') THEN 1/0 ELSE 1 END)--",
        ];
    }

    /**
     * Test SQL injection in user authentication endpoints
     */
    public function test_sql_injection_in_authentication(): void
    {
        foreach ($this->sqlInjectionPayloads as $payload) {
            $response = $this->postJson('/api/cms/auth/login', [
                'email' => $payload,
                'password' => 'password123',
                'device_name' => 'SQL Injection Test'
            ]);

            // Should return 401/422, not 500 or allow authentication
            $this->assertContains($response->status(), [401, 422], 
                "Authentication should safely handle SQL injection payload: {$payload}");

            // Additional test with payload in password field
            $response = $this->postJson('/api/cms/auth/login', [
                'email' => 'admin@sqltest.com',
                'password' => $payload,
                'device_name' => 'SQL Injection Test'
            ]);

            $this->assertContains($response->status(), [401, 422], 
                "Password field should safely handle SQL injection payload: {$payload}");
        }
    }

    /**
     * Test SQL injection in user management endpoints
     */
    public function test_sql_injection_in_user_management(): void
    {
        Sanctum::actingAs($this->admin);

        foreach ($this->sqlInjectionPayloads as $payload) {
            // Test user creation
            $response = $this->postJson('/api/cms/users', [
                'username' => $payload,
                'email' => 'sqltest@test.com',
                'password' => 'password123',
                'first_name' => 'SQL',
                'last_name' => 'Test',
            ]);

            $this->assertContains($response->status(), [201, 422], 
                "User creation should safely handle SQL injection in username: {$payload}");

            // Test user creation with payload in email
            $response = $this->postJson('/api/cms/users', [
                'username' => 'sqltest_user',
                'email' => $payload,
                'password' => 'password123',
                'first_name' => 'SQL',
                'last_name' => 'Test',
            ]);

            $this->assertContains($response->status(), [201, 422], 
                "User creation should safely handle SQL injection in email: {$payload}");

            // Test user update
            $response = $this->putJson("/api/cms/users/{$this->testUser->id}", [
                'first_name' => $payload,
                'last_name' => 'Updated',
            ]);

            $this->assertContains($response->status(), [200, 422], 
                "User update should safely handle SQL injection: {$payload}");
        }
    }

    /**
     * Test SQL injection in content management endpoints
     */
    public function test_sql_injection_in_content_management(): void
    {
        Sanctum::actingAs($this->admin);

        foreach ($this->sqlInjectionPayloads as $payload) {
            // Test content creation
            $response = $this->postJson('/api/cms/content', [
                'title' => $payload,
                'content' => 'Test content body',
                'content_type' => 'post',
                'status' => 'published',
                'slug' => 'test-sql-' . uniqid(),
                'author_id' => $this->admin->id,
            ]);

            $this->assertContains($response->status(), [201, 422], 
                "Content creation should safely handle SQL injection in title: {$payload}");

            // Test content creation with payload in content body
            $response = $this->postJson('/api/cms/content', [
                'title' => 'SQL Test Content',
                'content' => $payload,
                'content_type' => 'post',
                'status' => 'published',
                'slug' => 'test-sql-body-' . uniqid(),
                'author_id' => $this->admin->id,
            ]);

            $this->assertContains($response->status(), [201, 422], 
                "Content creation should safely handle SQL injection in content: {$payload}");

            // Test content update
            $response = $this->putJson("/api/cms/content/{$this->testContent->id}", [
                'title' => $payload,
                'content' => 'Updated content',
            ]);

            $this->assertContains($response->status(), [200, 422], 
                "Content update should safely handle SQL injection: {$payload}");
        }
    }

    /**
     * Test SQL injection in search and filter parameters
     */
    public function test_sql_injection_in_search_parameters(): void
    {
        Sanctum::actingAs($this->admin);

        foreach ($this->sqlInjectionPayloads as $payload) {
            // Test user search with malicious query
            $response = $this->getJson("/api/cms/users?search=" . urlencode($payload));

            $this->assertContains($response->status(), [200, 422], 
                "User search should safely handle SQL injection: {$payload}");

            // Test content search with malicious query
            $response = $this->getJson("/api/cms/content?search=" . urlencode($payload));

            $this->assertContains($response->status(), [200, 422], 
                "Content search should safely handle SQL injection: {$payload}");

            // Test filtering with malicious parameters
            $response = $this->getJson("/api/cms/content?status=" . urlencode($payload));

            $this->assertContains($response->status(), [200, 422], 
                "Content filtering should safely handle SQL injection: {$payload}");
        }
    }

    /**
     * Test SQL injection in URL parameters and route model binding
     */
    public function test_sql_injection_in_url_parameters(): void
    {
        Sanctum::actingAs($this->admin);

        $maliciousIds = [
            "1'; DROP TABLE users; --",
            "1 OR 1=1",
            "1 UNION SELECT 1,2,3",
            "1; WAITFOR DELAY '00:00:05'",
            "1' AND 1=1--",
        ];

        foreach ($maliciousIds as $maliciousId) {
            // Test user show with malicious ID
            $response = $this->getJson("/api/cms/users/" . urlencode($maliciousId));

            $this->assertContains($response->status(), [404, 422], 
                "User show should safely handle malicious ID: {$maliciousId}");

            // Test content show with malicious ID
            $response = $this->getJson("/api/cms/content/" . urlencode($maliciousId));

            $this->assertContains($response->status(), [404, 422], 
                "Content show should safely handle malicious ID: {$maliciousId}");

            // Test user update with malicious ID
            $response = $this->putJson("/api/cms/users/" . urlencode($maliciousId), [
                'first_name' => 'Updated',
            ]);

            $this->assertContains($response->status(), [404, 422], 
                "User update should safely handle malicious ID: {$maliciousId}");

            // Test user delete with malicious ID
            $response = $this->deleteJson("/api/cms/users/" . urlencode($maliciousId));

            $this->assertContains($response->status(), [404, 422], 
                "User delete should safely handle malicious ID: {$maliciousId}");
        }
    }

    /**
     * Test SQL injection in JSON payloads and nested data
     */
    public function test_sql_injection_in_json_payloads(): void
    {
        Sanctum::actingAs($this->admin);

        $nestedPayloads = [
            [
                'user' => [
                    'name' => "'; DROP TABLE users; --",
                    'email' => 'test@example.com'
                ]
            ],
            [
                'settings' => [
                    'theme' => "' OR '1'='1",
                    'color' => 'blue'
                ]
            ],
            [
                'metadata' => [
                    'title' => "' UNION SELECT password FROM users--",
                    'description' => 'Normal description'
                ]
            ],
        ];

        foreach ($nestedPayloads as $payload) {
            // Test user creation with nested malicious data
            $response = $this->postJson('/api/cms/users', array_merge([
                'username' => 'nested_test_' . uniqid(),
                'email' => 'nested@test.com',
                'password' => 'password123',
                'first_name' => 'Nested',
                'last_name' => 'Test',
            ], $payload));

            $this->assertContains($response->status(), [201, 422], 
                "Nested JSON payload should be handled safely");

            // Test content creation with nested malicious data
            $response = $this->postJson('/api/cms/content', array_merge([
                'title' => 'Nested Test Content',
                'content' => 'Test content',
                'content_type' => 'post',
                'status' => 'published',
                'slug' => 'nested-test-' . uniqid(),
                'author_id' => $this->admin->id,
            ], $payload));

            $this->assertContains($response->status(), [201, 422], 
                "Content creation with nested payload should be handled safely");
        }
    }

    /**
     * Test second-order SQL injection vulnerabilities
     */
    public function test_second_order_sql_injection(): void
    {
        Sanctum::actingAs($this->admin);

        // Create a user with potentially malicious data that gets stored
        $maliciousUsername = "test'; DROP TABLE users; --";
        
        $response = $this->postJson('/api/cms/users', [
            'username' => $maliciousUsername,
            'email' => 'secondorder@test.com',
            'password' => 'password123',
            'first_name' => 'Second',
            'last_name' => 'Order',
        ]);

        if ($response->status() === 201) {
            $createdUser = User::where('email', 'secondorder@test.com')->first();
            
            // Now try to use this stored data in another operation
            // This tests if the stored malicious data gets executed in a second query
            $searchResponse = $this->getJson('/api/cms/users?search=' . urlencode($createdUser->username));
            
            $this->assertContains($searchResponse->status(), [200, 422], 
                'Second-order SQL injection should not execute stored malicious data');

            // Test updating the user (which might trigger second-order injection)
            $updateResponse = $this->putJson("/api/cms/users/{$createdUser->id}", [
                'first_name' => 'Updated Second',
                'last_name' => 'Order',
            ]);

            $this->assertContains($updateResponse->status(), [200, 422], 
                'User update should not trigger second-order SQL injection');
        }
    }

    /**
     * Test blind SQL injection detection through timing
     */
    public function test_blind_sql_injection_timing(): void
    {
        Sanctum::actingAs($this->admin);

        $timingPayloads = [
            "'; WAITFOR DELAY '00:00:01'--",
            "'; SELECT SLEEP(1)--", 
            "'; pg_sleep(1)--",
            "' AND (SELECT * FROM (SELECT(SLEEP(1)))a)--",
        ];

        foreach ($timingPayloads as $payload) {
            $startTime = microtime(true);
            
            $response = $this->postJson('/api/cms/users', [
                'username' => $payload,
                'email' => 'timing@test.com',
                'password' => 'password123',
                'first_name' => 'Timing',
                'last_name' => 'Test',
            ]);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // If the payload causes a delay, it might indicate SQL injection vulnerability
            $this->assertLessThan(2, $executionTime, 
                "Request should not be delayed by timing-based SQL injection payload: {$payload}");
            
            $this->assertContains($response->status(), [201, 422], 
                "Timing-based SQL injection should be prevented: {$payload}");
        }
    }

    /**
     * Test SQL injection in ORDER BY and LIMIT clauses
     */
    public function test_sql_injection_in_query_clauses(): void
    {
        Sanctum::actingAs($this->admin);

        $orderByPayloads = [
            "1; DROP TABLE users--",
            "(SELECT CASE WHEN (1=1) THEN username ELSE email END)",
            "IF(1=1, username, email)",
            "username; DROP TABLE users--",
            "CASE WHEN 1=1 THEN username ELSE email END",
        ];

        foreach ($orderByPayloads as $payload) {
            // Test ordering with malicious payload
            $response = $this->getJson("/api/cms/users?sort=" . urlencode($payload));

            $this->assertContains($response->status(), [200, 422], 
                "ORDER BY injection should be prevented: {$payload}");

            $response = $this->getJson("/api/cms/content?sort=" . urlencode($payload));

            $this->assertContains($response->status(), [200, 422], 
                "Content ORDER BY injection should be prevented: {$payload}");
        }

        // Test LIMIT clause injection
        $limitPayloads = [
            "1; DROP TABLE users--",
            "1 UNION SELECT * FROM users--",
            "1,1; DROP TABLE users--",
        ];

        foreach ($limitPayloads as $payload) {
            $response = $this->getJson("/api/cms/users?limit=" . urlencode($payload));

            $this->assertContains($response->status(), [200, 422], 
                "LIMIT injection should be prevented: {$payload}");
        }
    }

    /**
     * Test database error handling to prevent information disclosure
     */
    public function test_database_error_information_disclosure(): void
    {
        Sanctum::actingAs($this->admin);

        $errorInducingPayloads = [
            "'; SELECT * FROM nonexistent_table; --",
            "' AND EXTRACTVALUE(1, CONCAT(0x7e, database(), 0x7e))--",
            "' AND (SELECT COUNT(*) FROM information_schema.tables)--",
            "'; SELECT version(); --",
        ];

        foreach ($errorInducingPayloads as $payload) {
            $response = $this->postJson('/api/cms/users', [
                'username' => $payload,
                'email' => 'error@test.com',
                'password' => 'password123',
                'first_name' => 'Error',
                'last_name' => 'Test',
            ]);

            // Should not return 500 errors that might leak information
            $this->assertNotEquals(500, $response->status(), 
                "Database errors should not leak information: {$payload}");

            // Check that response doesn't contain database-specific error messages
            $responseContent = $response->getContent();
            $sensitivePatterns = [
                '/mysql/i',
                '/postgresql/i',
                '/sqlite/i',
                '/sql.*error/i',
                '/database/i',
                '/table.*doesn.*exist/i',
                '/column.*not.*found/i',
            ];

            foreach ($sensitivePatterns as $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $responseContent, 
                    "Response should not contain sensitive database information");
            }
        }
    }

    /**
     * Verify that Laravel's built-in protections are working
     */
    public function test_laravel_sql_injection_protections(): void
    {
        // Test that Eloquent ORM prevents SQL injection
        $maliciousEmail = "'; DROP TABLE users; --";
        
        // This should be safe due to parameter binding
        $user = User::where('email', $maliciousEmail)->first();
        $this->assertNull($user, 'Eloquent should safely handle malicious parameters');

        // Test raw queries with bindings (safe)
        $users = DB::select('SELECT * FROM users WHERE email = ?', [$maliciousEmail]);
        $this->assertIsArray($users, 'Raw queries with bindings should be safe');

        // Verify that the users table still exists after these operations
        $userCount = User::count();
        $this->assertIsInt($userCount, 'Users table should still exist after SQL injection tests');
    }
}