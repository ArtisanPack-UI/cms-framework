<?php

declare(strict_types=1);

namespace Tests\Feature\Internationalization;

use ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LanguageControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected string $baseUrl = '/admin/api/internationalization/languages';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    /** @test */
    public function it_can_list_languages(): void
    {
        Language::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'languages',
                    'meta' => [
                        'total',
                        'active_count',
                        'rtl_count'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_languages_by_active_status(): void
    {
        Language::factory()->create(['is_active' => true]);
        Language::factory()->create(['is_active' => false]);

        $response = $this->getJson($this->baseUrl . '?active=1');

        $response->assertOk();
        $languages = $response->json('data.languages');
        
        $this->assertCount(1, $languages);
        $this->assertTrue($languages[0]['is_active']);
    }

    /** @test */
    public function it_can_filter_languages_by_rtl_status(): void
    {
        Language::factory()->create(['is_rtl' => true]);
        Language::factory()->create(['is_rtl' => false]);

        $response = $this->getJson($this->baseUrl . '?rtl=1');

        $response->assertOk();
        $languages = $response->json('data.languages');
        
        $this->assertCount(1, $languages);
        $this->assertTrue($languages[0]['is_rtl']);
    }

    /** @test */
    public function it_can_create_a_language(): void
    {
        $languageData = [
            'name' => 'Spanish',
            'code' => 'es',
            'locale' => 'es_ES',
            'native_name' => 'Español',
            'is_active' => true,
            'is_rtl' => false,
            'sort_order' => 10,
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'datetime_format' => 'd/m/Y H:i',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'metadata' => ['region' => 'Europe']
        ];

        $response = $this->postJson($this->baseUrl, $languageData);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'language',
                    'message'
                ]
            ]);

        $this->assertDatabaseHas('languages', [
            'name' => 'Spanish',
            'code' => 'es',
            'locale' => 'es_ES'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_language(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'locale', 'native_name']);
    }

    /** @test */
    public function it_validates_unique_code_when_creating_language(): void
    {
        Language::factory()->create(['code' => 'en']);

        $response = $this->postJson($this->baseUrl, [
            'name' => 'English',
            'code' => 'en',
            'locale' => 'en_US',
            'native_name' => 'English'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function it_can_show_a_language(): void
    {
        $language = Language::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$language->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'language',
                    'statistics' => [
                        'total_translations',
                        'completed_translations',
                        'pending_translations',
                        'completion_percentage',
                        'completion_status'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_update_a_language(): void
    {
        $language = Language::factory()->create(['name' => 'Old Name']);

        $updateData = ['name' => 'New Name'];

        $response = $this->putJson("{$this->baseUrl}/{$language->id}", $updateData);

        $response->assertOk()
            ->assertJsonPath('data.language.name', 'New Name');

        $this->assertDatabaseHas('languages', [
            'id' => $language->id,
            'name' => 'New Name'
        ]);
    }

    /** @test */
    public function it_can_delete_a_language(): void
    {
        $language = Language::factory()->create([
            'is_default' => false,
            'is_fallback' => false
        ]);

        $response = $this->deleteJson("{$this->baseUrl}/{$language->id}");

        $response->assertOk();
        $this->assertSoftDeleted('languages', ['id' => $language->id]);
    }

    /** @test */
    public function it_cannot_delete_default_language(): void
    {
        $language = Language::factory()->create(['is_default' => true]);

        $response = $this->deleteJson("{$this->baseUrl}/{$language->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete the default language.');

        $this->assertDatabaseHas('languages', ['id' => $language->id]);
    }

    /** @test */
    public function it_cannot_delete_fallback_language(): void
    {
        $language = Language::factory()->create(['is_fallback' => true]);

        $response = $this->deleteJson("{$this->baseUrl}/{$language->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete the fallback language.');

        $this->assertDatabaseHas('languages', ['id' => $language->id]);
    }

    /** @test */
    public function it_can_set_default_language(): void
    {
        $oldDefault = Language::factory()->create(['is_default' => true]);
        $newDefault = Language::factory()->create(['is_default' => false]);

        $response = $this->postJson("{$this->baseUrl}/{$newDefault->id}/set-default");

        $response->assertOk();

        $this->assertDatabaseHas('languages', [
            'id' => $oldDefault->id,
            'is_default' => false
        ]);

        $this->assertDatabaseHas('languages', [
            'id' => $newDefault->id,
            'is_default' => true,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_set_fallback_language(): void
    {
        $oldFallback = Language::factory()->create(['is_fallback' => true]);
        $newFallback = Language::factory()->create(['is_fallback' => false]);

        $response = $this->postJson("{$this->baseUrl}/{$newFallback->id}/set-fallback");

        $response->assertOk();

        $this->assertDatabaseHas('languages', [
            'id' => $oldFallback->id,
            'is_fallback' => false
        ]);

        $this->assertDatabaseHas('languages', [
            'id' => $newFallback->id,
            'is_fallback' => true,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_toggle_active_status(): void
    {
        $language = Language::factory()->create([
            'is_active' => true,
            'is_default' => false
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$language->id}/toggle-active");

        $response->assertOk();
        $this->assertDatabaseHas('languages', [
            'id' => $language->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function it_cannot_deactivate_default_language(): void
    {
        $language = Language::factory()->create([
            'is_active' => true,
            'is_default' => true
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$language->id}/toggle-active");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot deactivate the default language.');

        $this->assertDatabaseHas('languages', [
            'id' => $language->id,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_export_language_pack(): void
    {
        Storage::fake('local');
        
        $language = Language::factory()->create(['code' => 'es']);

        $response = $this->getJson("{$this->baseUrl}/{$language->id}/export-pack?format=json");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'filename',
                    'download_url',
                    'statistics',
                    'message'
                ]
            ]);

        Storage::assertExists('language_packs/' . $response->json('data.filename'));
    }

    /** @test */
    public function it_can_export_language_pack_in_php_format(): void
    {
        Storage::fake('local');
        
        $language = Language::factory()->create(['code' => 'es']);

        $response = $this->getJson("{$this->baseUrl}/{$language->id}/export-pack?format=php");

        $response->assertOk();
        
        $filename = $response->json('data.filename');
        $this->assertStringEndsWith('.php', $filename);
        Storage::assertExists('language_packs/' . $filename);
    }

    /** @test */
    public function it_can_import_language_pack(): void
    {
        $language = Language::factory()->create();
        
        $packData = [
            'language' => $language->toApiArray(),
            'translations' => [
                'common' => [
                    'hello' => 'Hola',
                    'goodbye' => 'Adiós'
                ],
                'auth' => [
                    'login' => 'Iniciar sesión'
                ]
            ],
            'metadata' => [
                'exported_at' => now()->toISOString(),
                'version' => '1.0.0'
            ]
        ];

        $file = UploadedFile::fake()->createWithContent(
            'language_pack.json',
            json_encode($packData)
        );

        $response = $this->postJson("{$this->baseUrl}/{$language->id}/import-pack", [
            'file' => $file,
            'overwrite_existing' => true
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'statistics' => [
                        'imported',
                        'updated',
                        'skipped',
                        'total_processed'
                    ],
                    'message'
                ]
            ]);

        $this->assertDatabaseHas('translations', [
            'language_id' => $language->id,
            'group' => 'common',
            'key' => 'hello',
            'value' => 'Hola'
        ]);
    }

    /** @test */
    public function it_validates_file_format_when_importing_language_pack(): void
    {
        $language = Language::factory()->create();
        
        $file = UploadedFile::fake()->create('invalid.txt', 100);

        $response = $this->postJson("{$this->baseUrl}/{$language->id}/import-pack", [
            'file' => $file
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function it_handles_invalid_language_pack_format(): void
    {
        $language = Language::factory()->create();
        
        $invalidPackData = ['invalid' => 'format'];

        $file = UploadedFile::fake()->createWithContent(
            'invalid_pack.json',
            json_encode($invalidPackData)
        );

        $response = $this->postJson("{$this->baseUrl}/{$language->id}/import-pack", [
            'file' => $file
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid language pack format.');
    }
}