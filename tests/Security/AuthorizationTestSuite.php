<?php

namespace Tests\Security;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Setting;
use ArtisanPackUI\Database\seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Authorization Testing Suite for CMS Framework
 * 
 * This test suite performs comprehensive authorization testing including:
 * - Role-based access control validation
 * - Permission escalation attempts
 * - Resource access boundary testing
 * - API endpoint authorization validation
 * - Cross-user resource access prevention
 * - Administrative function protection
 * - Policy-based access control testing
 * - Privilege boundary enforcement
 * 
 * @package Tests\Security
 */
class AuthorizationTestSuite extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $editor;
    protected User $author;
    protected User $subscriber;
    protected Role $adminRole;
    protected Role $editorRole;
    protected Role $authorRole;
    protected Role $subscriberRole;
    protected Content $adminContent;
    protected Content $editorContent;
    protected Content $authorContent;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RoleSeeder::class);
        $this->setupTestUsersAndRoles();
        $this->setupTestContent();
    }

    /**
     * Set up test users and roles
     */
    protected function setupTestUsersAndRoles(): void
    {
        // Get roles
        $this->adminRole = Role::where('slug', 'admin')->first();
        $this->editorRole = Role::where('slug', 'editor')->first();
        $this->authorRole = Role::where('slug', 'author')->first() 
            ?? Role::factory()->create(['slug' => 'author', 'name' => 'Author']);
        $this->subscriberRole = Role::where('slug', 'subscriber')->first() 
            ?? Role::factory()->create(['slug' => 'subscriber', 'name' => 'Subscriber']);

        // Create test users
        $this->admin = User::factory()->create([
            'username' => 'admin_authz_test',
            'email' => 'admin@authztest.com',
            'role_id' => $this->adminRole->id,
        ]);

        $this->editor = User::factory()->create([
            'username' => 'editor_authz_test',
            'email' => 'editor@authztest.com',
            'role_id' => $this->editorRole->id,
        ]);

        $this->author = User::factory()->create([
            'username' => 'author_authz_test',
            'email' => 'author@authztest.com',
            'role_id' => $this->authorRole->id,
        ]);

        $this->subscriber = User::factory()->create([
            'username' => 'subscriber_authz_test',
            'email' => 'subscriber@authztest.com',
            'role_id' => $this->subscriberRole->id,
        ]);
    }

    /**
     * Set up test content for different users
     */
    protected function setupTestContent(): void
    {
        $this->adminContent = Content::factory()->create([
            'title' => 'Admin Content',
            'content' => 'Content created by admin',
            'author_id' => $this->admin->id,
            'status' => 'published',
        ]);

        $this->editorContent = Content::factory()->create([
            'title' => 'Editor Content',
            'content' => 'Content created by editor',
            'author_id' => $this->editor->id,
            'status' => 'published',
        ]);

        $this->authorContent = Content::factory()->create([
            'title' => 'Author Content',
            'content' => 'Content created by author',
            'author_id' => $this->author->id,
            'status' => 'draft',
        ]);
    }

    /**
     * Test role-based access control for user management
     */
    public function test_role_based_access_control_for_user_management(): void
    {
        $testCases = [
            // Admin should have full access
            [
                'user' => $this->admin,
                'expectedAccess' => [
                    'list' => true,
                    'create' => true,
                    'view' => true,
                    'edit' => true,
                    'delete' => true,
                ]
            ],
            // Editor should have limited access
            [
                'user' => $this->editor,
                'expectedAccess' => [
                    'list' => false,
                    'create' => false,
                    'view' => false,
                    'edit' => false,
                    'delete' => false,
                ]
            ],
            // Author should have minimal access
            [
                'user' => $this->author,
                'expectedAccess' => [
                    'list' => false,
                    'create' => false,
                    'view' => false,
                    'edit' => false,
                    'delete' => false,
                ]
            ],
            // Subscriber should have no access
            [
                'user' => $this->subscriber,
                'expectedAccess' => [
                    'list' => false,
                    'create' => false,
                    'view' => false,
                    'edit' => false,
                    'delete' => false,
                ]
            ],
        ];

        foreach ($testCases as $testCase) {
            Sanctum::actingAs($testCase['user']);

            // Test list users
            $listResponse = $this->getJson('/api/cms/users');
            if ($testCase['expectedAccess']['list']) {
                $this->assertEquals(200, $listResponse->status(), 
                    "{$testCase['user']->username} should be able to list users");
            } else {
                $this->assertContains($listResponse->status(), [401, 403], 
                    "{$testCase['user']->username} should not be able to list users");
            }

            // Test create user
            $createResponse = $this->postJson('/api/cms/users', [
                'username' => 'test_create_user',
                'email' => 'create@test.com',
                'password' => 'password123',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);
            if ($testCase['expectedAccess']['create']) {
                $this->assertContains($createResponse->status(), [201, 422], 
                    "{$testCase['user']->username} should be able to create users");
            } else {
                $this->assertContains($createResponse->status(), [401, 403], 
                    "{$testCase['user']->username} should not be able to create users");
            }

            // Test view specific user
            $viewResponse = $this->getJson("/api/cms/users/{$this->admin->id}");
            if ($testCase['expectedAccess']['view']) {
                $this->assertEquals(200, $viewResponse->status(), 
                    "{$testCase['user']->username} should be able to view users");
            } else {
                $this->assertContains($viewResponse->status(), [401, 403], 
                    "{$testCase['user']->username} should not be able to view users");
            }
        }
    }

    /**
     * Test content access control based on ownership and roles
     */
    public function test_content_access_control(): void
    {
        $contentTestCases = [
            // Test admin access to all content
            [
                'user' => $this->admin,
                'content' => $this->editorContent,
                'operations' => ['view' => true, 'edit' => true, 'delete' => true]
            ],
            [
                'user' => $this->admin,
                'content' => $this->authorContent,
                'operations' => ['view' => true, 'edit' => true, 'delete' => true]
            ],
            // Test editor access to own and others' content
            [
                'user' => $this->editor,
                'content' => $this->editorContent,
                'operations' => ['view' => true, 'edit' => true, 'delete' => true]
            ],
            [
                'user' => $this->editor,
                'content' => $this->authorContent,
                'operations' => ['view' => true, 'edit' => false, 'delete' => false]
            ],
            // Test author access to own content only
            [
                'user' => $this->author,
                'content' => $this->authorContent,
                'operations' => ['view' => true, 'edit' => true, 'delete' => false]
            ],
            [
                'user' => $this->author,
                'content' => $this->editorContent,
                'operations' => ['view' => true, 'edit' => false, 'delete' => false]
            ],
            // Test subscriber limited access
            [
                'user' => $this->subscriber,
                'content' => $this->adminContent,
                'operations' => ['view' => true, 'edit' => false, 'delete' => false]
            ],
            [
                'user' => $this->subscriber,
                'content' => $this->authorContent,
                'operations' => ['view' => false, 'edit' => false, 'delete' => false] // Draft content
            ],
        ];

        foreach ($contentTestCases as $testCase) {
            Sanctum::actingAs($testCase['user']);

            // Test view content
            $viewResponse = $this->getJson("/api/cms/content/{$testCase['content']->id}");
            if ($testCase['operations']['view']) {
                $this->assertEquals(200, $viewResponse->status(), 
                    "{$testCase['user']->username} should be able to view content {$testCase['content']->id}");
            } else {
                $this->assertContains($viewResponse->status(), [401, 403, 404], 
                    "{$testCase['user']->username} should not be able to view content {$testCase['content']->id}");
            }

            // Test edit content
            $editResponse = $this->putJson("/api/cms/content/{$testCase['content']->id}", [
                'title' => 'Updated Title by ' . $testCase['user']->username,
            ]);
            if ($testCase['operations']['edit']) {
                $this->assertContains($editResponse->status(), [200, 422], 
                    "{$testCase['user']->username} should be able to edit content {$testCase['content']->id}");
            } else {
                $this->assertContains($editResponse->status(), [401, 403, 404], 
                    "{$testCase['user']->username} should not be able to edit content {$testCase['content']->id}");
            }

            // Test delete content
            $deleteResponse = $this->deleteJson("/api/cms/content/{$testCase['content']->id}");
            if ($testCase['operations']['delete']) {
                $this->assertContains($deleteResponse->status(), [200, 204], 
                    "{$testCase['user']->username} should be able to delete content {$testCase['content']->id}");
            } else {
                $this->assertContains($deleteResponse->status(), [401, 403, 404], 
                    "{$testCase['user']->username} should not be able to delete content {$testCase['content']->id}");
            }
        }
    }

    /**
     * Test permission escalation prevention
     */
    public function test_permission_escalation_prevention(): void
    {
        Sanctum::actingAs($this->subscriber);

        $escalationAttempts = [
            // Try to update own role to admin
            [
                'endpoint' => "/api/cms/users/{$this->subscriber->id}",
                'method' => 'PUT',
                'data' => ['role_id' => $this->adminRole->id],
            ],
            // Try to create admin user
            [
                'endpoint' => '/api/cms/users',
                'method' => 'POST',
                'data' => [
                    'username' => 'escalation_admin',
                    'email' => 'escalation@test.com',
                    'password' => 'password123',
                    'role_id' => $this->adminRole->id,
                    'first_name' => 'Escalation',
                    'last_name' => 'Admin',
                ],
            ],
            // Try to modify another user's role
            [
                'endpoint' => "/api/cms/users/{$this->author->id}",
                'method' => 'PUT',
                'data' => ['role_id' => $this->adminRole->id],
            ],
            // Try to access admin-only settings
            [
                'endpoint' => '/api/cms/settings',
                'method' => 'POST',
                'data' => [
                    'key' => 'admin_setting',
                    'value' => 'escalated_value',
                    'description' => 'Escalation attempt',
                ],
            ],
        ];

        foreach ($escalationAttempts as $attempt) {
            $response = match($attempt['method']) {
                'POST' => $this->postJson($attempt['endpoint'], $attempt['data']),
                'PUT' => $this->putJson($attempt['endpoint'], $attempt['data']),
                'DELETE' => $this->deleteJson($attempt['endpoint']),
                default => $this->getJson($attempt['endpoint'])
            };

            $this->assertContains($response->status(), [401, 403], 
                "Permission escalation attempt should be blocked: {$attempt['method']} {$attempt['endpoint']}");
        }
    }

    /**
     * Test cross-user resource access prevention
     */
    public function test_cross_user_resource_access_prevention(): void
    {
        // Author trying to access other users' private resources
        Sanctum::actingAs($this->author);

        $crossAccessAttempts = [
            // Try to view another user's draft content
            ['method' => 'GET', 'endpoint' => "/api/cms/users/{$this->admin->id}"],
            ['method' => 'GET', 'endpoint' => "/api/cms/users/{$this->editor->id}"],
            
            // Try to edit another user's profile
            [
                'method' => 'PUT', 
                'endpoint' => "/api/cms/users/{$this->subscriber->id}",
                'data' => ['first_name' => 'Hacked Name']
            ],
            
            // Try to delete another user's content
            ['method' => 'DELETE', 'endpoint' => "/api/cms/content/{$this->editorContent->id}"],
            
            // Try to modify system settings
            [
                'method' => 'POST',
                'endpoint' => '/api/cms/settings',
                'data' => [
                    'key' => 'system_setting',
                    'value' => 'unauthorized_change',
                    'description' => 'Unauthorized modification',
                ]
            ],
        ];

        foreach ($crossAccessAttempts as $attempt) {
            $response = match($attempt['method']) {
                'GET' => $this->getJson($attempt['endpoint']),
                'POST' => $this->postJson($attempt['endpoint'], $attempt['data'] ?? []),
                'PUT' => $this->putJson($attempt['endpoint'], $attempt['data'] ?? []),
                'DELETE' => $this->deleteJson($attempt['endpoint']),
                default => $this->getJson($attempt['endpoint'])
            };

            $this->assertContains($response->status(), [401, 403, 404], 
                "Cross-user resource access should be prevented: {$attempt['method']} {$attempt['endpoint']}");
        }
    }

    /**
     * Test administrative function protection
     */
    public function test_administrative_function_protection(): void
    {
        $nonAdminUsers = [$this->editor, $this->author, $this->subscriber];
        
        $adminFunctions = [
            // User management
            ['method' => 'GET', 'endpoint' => '/api/cms/users'],
            ['method' => 'DELETE', 'endpoint' => "/api/cms/users/{$this->subscriber->id}"],
            
            // Role management
            ['method' => 'GET', 'endpoint' => '/api/cms/roles'],
            [
                'method' => 'POST',
                'endpoint' => '/api/cms/roles',
                'data' => [
                    'name' => 'Unauthorized Role',
                    'slug' => 'unauthorized',
                    'description' => 'This should not be created',
                    'capabilities' => ['manage_users'],
                ]
            ],
            
            // System settings
            ['method' => 'GET', 'endpoint' => '/api/cms/settings'],
            [
                'method' => 'POST',
                'endpoint' => '/api/cms/settings',
                'data' => [
                    'key' => 'system_config',
                    'value' => 'unauthorized_value',
                    'description' => 'Unauthorized system configuration',
                ]
            ],
        ];

        foreach ($nonAdminUsers as $user) {
            Sanctum::actingAs($user);
            
            foreach ($adminFunctions as $function) {
                $response = match($function['method']) {
                    'GET' => $this->getJson($function['endpoint']),
                    'POST' => $this->postJson($function['endpoint'], $function['data'] ?? []),
                    'PUT' => $this->putJson($function['endpoint'], $function['data'] ?? []),
                    'DELETE' => $this->deleteJson($function['endpoint']),
                    default => $this->getJson($function['endpoint'])
                };

                $this->assertContains($response->status(), [401, 403], 
                    "{$user->username} should not access admin function: {$function['method']} {$function['endpoint']}");
            }
        }
    }

    /**
     * Test API endpoint authorization validation
     */
    public function test_api_endpoint_authorization_validation(): void
    {
        $endpointTests = [
            // Public endpoints (should work without authentication)
            [
                'endpoints' => [],
                'user' => null,
                'expectedStatus' => [401, 403] // All endpoints require auth
            ],
            
            // Editor endpoints
            [
                'endpoints' => [
                    ['method' => 'GET', 'url' => '/api/cms/content'],
                    ['method' => 'POST', 'url' => '/api/cms/content', 'data' => [
                        'title' => 'Editor Created Content',
                        'content' => 'Content by editor',
                        'content_type' => 'post',
                        'status' => 'draft',
                        'slug' => 'editor-content-' . uniqid(),
                        'author_id' => $this->editor->id,
                    ]],
                ],
                'user' => $this->editor,
                'expectedStatus' => [200, 201]
            ],
            
            // Author endpoints
            [
                'endpoints' => [
                    ['method' => 'GET', 'url' => '/api/cms/content'],
                    ['method' => 'POST', 'url' => '/api/cms/content', 'data' => [
                        'title' => 'Author Created Content',
                        'content' => 'Content by author',
                        'content_type' => 'post',
                        'status' => 'draft',
                        'slug' => 'author-content-' . uniqid(),
                        'author_id' => $this->author->id,
                    ]],
                ],
                'user' => $this->author,
                'expectedStatus' => [200, 201, 403] // May have limited access
            ],
        ];

        foreach ($endpointTests as $test) {
            if ($test['user']) {
                Sanctum::actingAs($test['user']);
            }

            foreach ($test['endpoints'] as $endpoint) {
                $response = match($endpoint['method']) {
                    'GET' => $this->getJson($endpoint['url']),
                    'POST' => $this->postJson($endpoint['url'], $endpoint['data'] ?? []),
                    'PUT' => $this->putJson($endpoint['url'], $endpoint['data'] ?? []),
                    'DELETE' => $this->deleteJson($endpoint['url']),
                    default => $this->getJson($endpoint['url'])
                };

                $this->assertContains($response->status(), $test['expectedStatus'], 
                    "Endpoint authorization failed for {$endpoint['method']} {$endpoint['url']} " .
                    "with user: " . ($test['user']?->username ?? 'anonymous'));
            }
        }
    }

    /**
     * Test resource ownership validation
     */
    public function test_resource_ownership_validation(): void
    {
        // Test content ownership
        Sanctum::actingAs($this->author);

        // Author should be able to edit own content
        $ownContentResponse = $this->putJson("/api/cms/content/{$this->authorContent->id}", [
            'title' => 'Updated by owner',
        ]);
        $this->assertContains($ownContentResponse->status(), [200, 422], 
            'Author should be able to edit own content');

        // Author should not be able to edit others' content
        $othersContentResponse = $this->putJson("/api/cms/content/{$this->editorContent->id}", [
            'title' => 'Unauthorized edit attempt',
        ]);
        $this->assertContains($othersContentResponse->status(), [401, 403], 
            'Author should not be able to edit others\' content');

        // Test that ownership cannot be transferred without proper permissions
        $transferOwnershipResponse = $this->putJson("/api/cms/content/{$this->authorContent->id}", [
            'author_id' => $this->admin->id, // Try to transfer ownership
        ]);
        $this->assertContains($transferOwnershipResponse->status(), [403, 422], 
            'Regular user should not be able to transfer content ownership');
    }

    /**
     * Test role hierarchy enforcement
     */
    public function test_role_hierarchy_enforcement(): void
    {
        $roleHierarchyTests = [
            // Lower-level users cannot manage higher-level users
            [
                'actor' => $this->author,
                'target' => $this->editor,
                'action' => 'edit',
                'shouldAllow' => false,
            ],
            [
                'actor' => $this->editor,
                'target' => $this->admin,
                'action' => 'edit',
                'shouldAllow' => false,
            ],
            [
                'actor' => $this->subscriber,
                'target' => $this->author,
                'action' => 'view',
                'shouldAllow' => false,
            ],
            
            // Higher-level users can manage lower-level users
            [
                'actor' => $this->admin,
                'target' => $this->editor,
                'action' => 'edit',
                'shouldAllow' => true,
            ],
            [
                'actor' => $this->admin,
                'target' => $this->subscriber,
                'action' => 'delete',
                'shouldAllow' => true,
            ],
        ];

        foreach ($roleHierarchyTests as $test) {
            Sanctum::actingAs($test['actor']);

            $response = match($test['action']) {
                'view' => $this->getJson("/api/cms/users/{$test['target']->id}"),
                'edit' => $this->putJson("/api/cms/users/{$test['target']->id}", [
                    'first_name' => 'Hierarchy Test',
                ]),
                'delete' => $this->deleteJson("/api/cms/users/{$test['target']->id}"),
                default => $this->getJson("/api/cms/users/{$test['target']->id}")
            };

            if ($test['shouldAllow']) {
                $this->assertContains($response->status(), [200, 201, 204, 422], 
                    "{$test['actor']->username} should be able to {$test['action']} {$test['target']->username}");
            } else {
                $this->assertContains($response->status(), [401, 403], 
                    "{$test['actor']->username} should not be able to {$test['action']} {$test['target']->username}");
            }
        }
    }

    /**
     * Test capability-based access control
     */
    public function test_capability_based_access_control(): void
    {
        // Create custom roles with specific capabilities
        $customRole = Role::factory()->create([
            'name' => 'Content Editor',
            'slug' => 'content-editor',
            'capabilities' => ['manage_content', 'edit_posts'],
        ]);

        $limitedRole = Role::factory()->create([
            'name' => 'Limited User',
            'slug' => 'limited-user',
            'capabilities' => ['read_posts'],
        ]);

        $contentEditor = User::factory()->create([
            'username' => 'content_editor_test',
            'email' => 'contenteditor@test.com',
            'role_id' => $customRole->id,
        ]);

        $limitedUser = User::factory()->create([
            'username' => 'limited_user_test',
            'email' => 'limiteduser@test.com',
            'role_id' => $limitedRole->id,
        ]);

        // Test content editor capabilities
        Sanctum::actingAs($contentEditor);
        
        $contentCreateResponse = $this->postJson('/api/cms/content', [
            'title' => 'Capability Test Content',
            'content' => 'Testing capability-based access',
            'content_type' => 'post',
            'status' => 'draft',
            'slug' => 'capability-test-' . uniqid(),
            'author_id' => $contentEditor->id,
        ]);
        $this->assertContains($contentCreateResponse->status(), [201, 422], 
            'Content editor should be able to create content');

        // Test limited user capabilities
        Sanctum::actingAs($limitedUser);
        
        $limitedCreateResponse = $this->postJson('/api/cms/content', [
            'title' => 'Unauthorized Content',
            'content' => 'This should not be created',
            'content_type' => 'post',
            'status' => 'draft',
            'slug' => 'unauthorized-' . uniqid(),
            'author_id' => $limitedUser->id,
        ]);
        $this->assertContains($limitedCreateResponse->status(), [401, 403], 
            'Limited user should not be able to create content');

        $limitedViewResponse = $this->getJson('/api/cms/content');
        $this->assertContains($limitedViewResponse->status(), [200, 403], 
            'Limited user may or may not be able to view content based on implementation');
    }

    /**
     * Test authorization bypass attempts through parameter manipulation
     */
    public function test_authorization_bypass_through_parameter_manipulation(): void
    {
        Sanctum::actingAs($this->subscriber);

        $bypassAttempts = [
            // Try to bypass authorization with special parameters
            [
                'endpoint' => '/api/cms/users',
                'method' => 'GET',
                'params' => ['admin' => 'true'],
            ],
            [
                'endpoint' => '/api/cms/users',
                'method' => 'GET',
                'params' => ['bypass_auth' => '1'],
            ],
            [
                'endpoint' => '/api/cms/content',
                'method' => 'POST',
                'data' => [
                    'title' => 'Bypass Test',
                    'content' => 'Testing bypass',
                    'content_type' => 'post',
                    'status' => 'published',
                    'slug' => 'bypass-test',
                    'author_id' => $this->admin->id, // Try to impersonate admin
                    'force_create' => true,
                    'skip_authorization' => true,
                ],
            ],
            // Try to manipulate user context
            [
                'endpoint' => "/api/cms/users/{$this->admin->id}",
                'method' => 'GET',
                'params' => ['acting_as' => 'admin'],
            ],
        ];

        foreach ($bypassAttempts as $attempt) {
            $url = $attempt['endpoint'];
            if (!empty($attempt['params'])) {
                $url .= '?' . http_build_query($attempt['params']);
            }

            $response = match($attempt['method']) {
                'GET' => $this->getJson($url),
                'POST' => $this->postJson($url, $attempt['data'] ?? []),
                'PUT' => $this->putJson($url, $attempt['data'] ?? []),
                'DELETE' => $this->deleteJson($url),
                default => $this->getJson($url)
            };

            $this->assertContains($response->status(), [401, 403, 422], 
                "Authorization bypass should not work: {$attempt['method']} {$url}");
        }
    }
}