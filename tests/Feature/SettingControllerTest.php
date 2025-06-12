<?php

namespace Tests\Feature;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin role with all capabilities
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'capabilities' => ['viewAny_settings', 'create_settings', 'view_settings', 'update_settings', 'delete_settings']
        ]);

        // Create an admin user
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id
        ]);

        // Create a regular user for testing
        $this->user = User::factory()->create();

        // Create some test settings
        $this->settings = Setting::factory()->count(3)->create();
    }

    #[Test]
    public function it_can_list_all_settings_with_sanctum_auth()
    {
        // Act as admin with Sanctum
        Sanctum::actingAs($this->admin, ['cms:read']);

        // Test accessing the settings index endpoint
        $response = $this->getJson('/api/cms/settings');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_can_show_a_specific_setting_with_sanctum_auth()
    {
        // Act as admin with Sanctum
        Sanctum::actingAs($this->admin, ['cms:read']);

        // Get the first setting
        $setting = $this->settings->first();

        // Test accessing the setting show endpoint
        $response = $this->getJson("/api/cms/settings/{$setting->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $setting->id);
    }

    #[Test]
    public function it_can_create_a_new_setting_with_sanctum_auth()
    {
        // Act as admin with Sanctum
        Sanctum::actingAs($this->admin, ['cms:read']);

        // Data for the new setting
        $settingData = [
            'key' => 'new-setting',
            'value' => 'new-value',
            'type' => 'string',
        ];

        // Test creating a new setting
        $response = $this->postJson('/api/cms/settings', $settingData);

        $response->assertStatus(201);
        $response->assertJsonPath('data.value', 'new-value');

        // Verify the setting was created in the database
        $this->assertDatabaseHas('settings', [
            'key' => 'new-setting',
        ]);
    }

    #[Test]
    public function it_can_update_a_setting_with_sanctum_auth()
    {
        // Act as admin with Sanctum
        Sanctum::actingAs($this->admin, ['cms:read']);

        // Get the first setting
        $setting = $this->settings->first();

        // Data for updating the setting
        $updateData = [
            'key' => $setting->key,
            'value' => 'updated-value',
            'type' => 'string',
        ];

        // Test updating the setting
        $response = $this->putJson("/api/cms/settings/{$setting->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $setting->id);
        $response->assertJsonPath('data.value', 'updated-value');

        // Verify the setting was updated in the database
        $this->assertDatabaseHas('settings', [
            'id' => $setting->id,
            'value' => 'updated-value',
        ]);
    }

    #[Test]
    public function it_can_delete_a_setting_with_sanctum_auth()
    {
        // Act as admin with Sanctum
        Sanctum::actingAs($this->admin, ['cms:read']);

        // Get the first setting
        $setting = $this->settings->first();

        // Test deleting the setting
        $response = $this->deleteJson("/api/cms/settings/{$setting->id}");

        $response->assertStatus(200);

        // Verify the setting was deleted from the database
        $this->assertDatabaseMissing('settings', [
            'id' => $setting->id,
        ]);
    }

    #[Test]
    public function it_cannot_access_settings_without_authentication()
    {
        // Test accessing the settings index endpoint without authentication
        $response = $this->getJson('/api/cms/settings');

        // The response should be unauthorized or forbidden
        $response->assertStatus(403);
    }

    #[Test]
    public function it_cannot_access_settings_without_proper_abilities()
    {
        // Act as regular user with Sanctum but without proper abilities
        Sanctum::actingAs($this->user, []);

        // Test accessing the settings index endpoint
        $response = $this->getJson('/api/cms/settings');

        // The response should be forbidden
        $response->assertStatus(403);
    }
}
