<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TorMorten\Eventy\Facades\Eventy;
use Tests\TestCase;

class RoleEventyHooksTest extends TestCase
{
    use RefreshDatabase;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->role = Role::factory()->create([
            'name' => 'Test Role',
            'slug' => 'test-role',
            'capabilities' => ['read_posts']
        ]);
    }

    /** @test */
    public function it_fires_capability_adding_filter_hook(): void
    {
        $hookFired = false;
        $originalCapability = null;
        $roleInstance = null;

        Eventy::addFilter('ap.cms.roles.capability_adding', function ($capability, $role) use (&$hookFired, &$originalCapability, &$roleInstance) {
            $hookFired = true;
            $originalCapability = $capability;
            $roleInstance = $role;
            
            return 'modified_' . $capability;
        });

        $this->role->addCapability('new_capability');

        $this->assertTrue($hookFired);
        $this->assertEquals('new_capability', $originalCapability);
        $this->assertInstanceOf(Role::class, $roleInstance);
        $this->assertEquals($this->role->id, $roleInstance->id);
        
        // Verify the capability was modified
        $this->role->refresh();
        $this->assertTrue($this->role->hasCapability('modified_new_capability'));
        $this->assertFalse($this->role->hasCapability('new_capability'));
    }

    /** @test */
    public function it_fires_capability_added_action_hook(): void
    {
        $hookFired = false;
        $addedCapability = null;
        $roleInstance = null;

        Eventy::addAction('ap.cms.roles.capability_added', function ($capability, $role) use (&$hookFired, &$addedCapability, &$roleInstance) {
            $hookFired = true;
            $addedCapability = $capability;
            $roleInstance = $role;
        });

        $this->role->addCapability('new_capability');

        $this->assertTrue($hookFired);
        $this->assertEquals('new_capability', $addedCapability);
        $this->assertInstanceOf(Role::class, $roleInstance);
        $this->assertEquals($this->role->id, $roleInstance->id);
    }

    /** @test */
    public function it_does_not_fire_capability_added_hook_when_capability_already_exists(): void
    {
        $hookFired = false;

        Eventy::addAction('ap.cms.roles.capability_added', function () use (&$hookFired) {
            $hookFired = true;
        });

        // Try to add an existing capability
        $result = $this->role->addCapability('read_posts');

        $this->assertFalse($result);
        $this->assertFalse($hookFired);
    }

    /** @test */
    public function it_fires_has_capability_filter_hook(): void
    {
        $hookFired = false;
        $originalResult = null;
        $checkedCapability = null;
        $roleInstance = null;

        Eventy::addFilter('ap.cms.roles.has_capability', function ($hasCapability, $capability, $role) use (&$hookFired, &$originalResult, &$checkedCapability, &$roleInstance) {
            $hookFired = true;
            $originalResult = $hasCapability;
            $checkedCapability = $capability;
            $roleInstance = $role;
            
            // Override the result - return true even if role doesn't have capability
            return true;
        });

        $result = $this->role->hasCapability('non_existent_capability');

        $this->assertTrue($hookFired);
        $this->assertFalse($originalResult);
        $this->assertEquals('non_existent_capability', $checkedCapability);
        $this->assertInstanceOf(Role::class, $roleInstance);
        $this->assertEquals($this->role->id, $roleInstance->id);
        $this->assertTrue($result); // Should be true due to filter override
    }

    /** @test */
    public function it_fires_capability_removing_filter_hook(): void
    {
        $hookFired = false;
        $originalCapability = null;
        $roleInstance = null;

        Eventy::addFilter('ap.cms.roles.capability_removing', function ($capability, $role) use (&$hookFired, &$originalCapability, &$roleInstance) {
            $hookFired = true;
            $originalCapability = $capability;
            $roleInstance = $role;
            
            return $capability; // Allow removal
        });

        $this->role->removeCapability('read_posts');

        $this->assertTrue($hookFired);
        $this->assertEquals('read_posts', $originalCapability);
        $this->assertInstanceOf(Role::class, $roleInstance);
        $this->assertEquals($this->role->id, $roleInstance->id);
    }

    /** @test */
    public function it_can_prevent_capability_removal_via_filter_hook(): void
    {
        Eventy::addFilter('ap.cms.roles.capability_removing', function ($capability, $role) {
            // Prevent removal by returning false
            return false;
        });

        $result = $this->role->removeCapability('read_posts');

        $this->assertFalse($result);
        $this->role->refresh();
        $this->assertTrue($this->role->hasCapability('read_posts'));
    }

    /** @test */
    public function it_fires_capability_removed_action_hook(): void
    {
        $hookFired = false;
        $removedCapability = null;
        $roleInstance = null;

        Eventy::addAction('ap.cms.roles.capability_removed', function ($capability, $role) use (&$hookFired, &$removedCapability, &$roleInstance) {
            $hookFired = true;
            $removedCapability = $capability;
            $roleInstance = $role;
        });

        $this->role->removeCapability('read_posts');

        $this->assertTrue($hookFired);
        $this->assertEquals('read_posts', $removedCapability);
        $this->assertInstanceOf(Role::class, $roleInstance);
        $this->assertEquals($this->role->id, $roleInstance->id);
    }

    /** @test */
    public function it_does_not_fire_capability_removed_hook_when_capability_does_not_exist(): void
    {
        $hookFired = false;

        Eventy::addAction('ap.cms.roles.capability_removed', function () use (&$hookFired) {
            $hookFired = true;
        });

        // Try to remove a non-existent capability
        $result = $this->role->removeCapability('non_existent_capability');

        $this->assertFalse($result);
        $this->assertFalse($hookFired);
    }

    /** @test */
    public function it_can_modify_capability_before_removal_via_filter_hook(): void
    {
        // Add another capability to test modification
        $this->role->addCapability('edit_posts');

        Eventy::addFilter('ap.cms.roles.capability_removing', function ($capability, $role) {
            // Modify the capability being removed
            if ($capability === 'read_posts') {
                return 'edit_posts';
            }
            return $capability;
        });

        $result = $this->role->removeCapability('read_posts');

        $this->assertTrue($result);
        $this->role->refresh();
        
        // The original capability should still exist
        $this->assertTrue($this->role->hasCapability('read_posts'));
        // The modified capability should be removed
        $this->assertFalse($this->role->hasCapability('edit_posts'));
    }

    /** @test */
    public function it_maintains_hook_parameter_order_consistency(): void
    {
        // Test that all hooks maintain consistent parameter ordering
        $parameterOrders = [];

        // Test capability_adding filter (capability, role)
        Eventy::addFilter('ap.cms.roles.capability_adding', function ($capability, $role) use (&$parameterOrders) {
            $parameterOrders['capability_adding'] = [
                'capability' => is_string($capability),
                'role' => $role instanceof Role
            ];
            return $capability;
        });

        // Test capability_added action (capability, role)
        Eventy::addAction('ap.cms.roles.capability_added', function ($capability, $role) use (&$parameterOrders) {
            $parameterOrders['capability_added'] = [
                'capability' => is_string($capability),
                'role' => $role instanceof Role
            ];
        });

        // Test has_capability filter (hasCapability, capability, role)
        Eventy::addFilter('ap.cms.roles.has_capability', function ($hasCapability, $capability, $role) use (&$parameterOrders) {
            $parameterOrders['has_capability'] = [
                'hasCapability' => is_bool($hasCapability),
                'capability' => is_string($capability),
                'role' => $role instanceof Role
            ];
            return $hasCapability;
        });

        // Test capability_removing filter (capability, role)
        Eventy::addFilter('ap.cms.roles.capability_removing', function ($capability, $role) use (&$parameterOrders) {
            $parameterOrders['capability_removing'] = [
                'capability' => is_string($capability),
                'role' => $role instanceof Role
            ];
            return $capability;
        });

        // Test capability_removed action (capability, role)
        Eventy::addAction('ap.cms.roles.capability_removed', function ($capability, $role) use (&$parameterOrders) {
            $parameterOrders['capability_removed'] = [
                'capability' => is_string($capability),
                'role' => $role instanceof Role
            ];
        });

        // Execute operations to trigger hooks
        $this->role->addCapability('test_capability');
        $this->role->hasCapability('read_posts');
        $this->role->removeCapability('test_capability');

        // Verify all hooks were called with correct parameter types
        $this->assertCount(5, $parameterOrders);
        
        foreach ($parameterOrders as $hookName => $params) {
            foreach ($params as $paramName => $isCorrectType) {
                $this->assertTrue($isCorrectType, "Hook {$hookName} parameter {$paramName} has incorrect type");
            }
        }
    }

    protected function tearDown(): void
    {
        // Clear all Eventy hooks to prevent interference between tests
        Eventy::getFilters()->clear();
        Eventy::getActions()->clear();
        
        parent::tearDown();
    }
}