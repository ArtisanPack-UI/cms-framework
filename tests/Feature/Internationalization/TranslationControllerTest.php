<?php

declare(strict_types=1);

namespace Tests\Feature\Internationalization;

use ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language;
use ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Translation;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TranslationControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Language $language;
    protected string $baseUrl = '/admin/api/internationalization/translations';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->language = Language::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    /** @test */
    public function it_can_list_translations(): void
    {
        Translation::factory()->count(3)->create(['language_id' => $this->language->id]);

        $response = $this->getJson($this->baseUrl);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'translations',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total'
                    ],
                    'statistics' => [
                        'total',
                        'pending',
                        'approved',
                        'outdated',
                        'fuzzy'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_translations_by_language(): void
    {
        $language1 = Language::factory()->create();
        $language2 = Language::factory()->create();
        
        Translation::factory()->create(['language_id' => $language1->id]);
        Translation::factory()->create(['language_id' => $language2->id]);

        $response = $this->getJson($this->baseUrl . "?language_id={$language1->id}");

        $response->assertOk();
        $translations = $response->json('data.translations');
        
        $this->assertCount(1, $translations);
        $this->assertEquals($language1->id, $translations[0]['language_id']);
    }

    /** @test */
    public function it_can_filter_translations_by_group(): void
    {
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'group' => 'common'
        ]);
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'group' => 'auth'
        ]);

        $response = $this->getJson($this->baseUrl . '?group=common');

        $response->assertOk();
        $translations = $response->json('data.translations');
        
        $this->assertCount(1, $translations);
        $this->assertEquals('common', $translations[0]['group']);
    }

    /** @test */
    public function it_can_filter_translations_by_status(): void
    {
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'pending'
        ]);
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'approved'
        ]);

        $response = $this->getJson($this->baseUrl . '?status=approved');

        $response->assertOk();
        $translations = $response->json('data.translations');
        
        $this->assertCount(1, $translations);
        $this->assertEquals('approved', $translations[0]['status']);
    }

    /** @test */
    public function it_can_search_translations(): void
    {
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'hello_world',
            'value' => 'Hello World'
        ]);
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'goodbye',
            'value' => 'Goodbye'
        ]);

        $response = $this->getJson($this->baseUrl . '?search=hello');

        $response->assertOk();
        $translations = $response->json('data.translations');
        
        $this->assertCount(1, $translations);
        $this->assertEquals('hello_world', $translations[0]['key']);
    }

    /** @test */
    public function it_can_create_a_translation(): void
    {
        $translationData = [
            'language_id' => $this->language->id,
            'key' => 'welcome',
            'value' => 'Welcome to our site',
            'group' => 'common',
            'context' => 'Homepage greeting',
            'metadata' => ['section' => 'hero']
        ];

        $response = $this->postJson($this->baseUrl, $translationData);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'translation',
                    'message'
                ]
            ]);

        $this->assertDatabaseHas('translations', [
            'language_id' => $this->language->id,
            'key' => 'welcome',
            'value' => 'Welcome to our site',
            'group' => 'common',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_translation(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['language_id', 'key', 'value', 'group']);
    }

    /** @test */
    public function it_prevents_duplicate_translations(): void
    {
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'existing_key',
            'group' => 'common'
        ]);

        $response = $this->postJson($this->baseUrl, [
            'language_id' => $this->language->id,
            'key' => 'existing_key',
            'value' => 'New value',
            'group' => 'common'
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Translation already exists for this key and language.');
    }

    /** @test */
    public function it_can_show_a_translation(): void
    {
        $translation = Translation::factory()->create(['language_id' => $this->language->id]);

        $response = $this->getJson("{$this->baseUrl}/{$translation->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'translation',
                    'history'
                ]
            ]);
    }

    /** @test */
    public function it_can_update_a_translation(): void
    {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'value' => 'Old value',
            'status' => 'approved'
        ]);

        $updateData = [
            'value' => 'New value',
            'context' => 'Updated context'
        ];

        $response = $this->putJson("{$this->baseUrl}/{$translation->id}", $updateData);

        $response->assertOk()
            ->assertJsonPath('data.translation.value', 'New value');

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'value' => 'New value',
            'status' => 'pending' // Should be marked as pending when value changes
        ]);
    }

    /** @test */
    public function it_can_delete_a_translation(): void
    {
        $translation = Translation::factory()->create(['language_id' => $this->language->id]);

        $response = $this->deleteJson("{$this->baseUrl}/{$translation->id}");

        $response->assertOk();
        $this->assertSoftDeleted('translations', ['id' => $translation->id]);
    }

    /** @test */
    public function it_can_approve_a_translation(): void
    {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'pending'
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$translation->id}/approve");

        $response->assertOk();
        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'status' => 'approved',
            'reviewer_id' => $this->user->id
        ]);
    }

    /** @test */
    public function it_cannot_approve_already_approved_translation(): void
    {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$translation->id}/approve");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Translation is already approved.');
    }

    /** @test */
    public function it_can_reject_a_translation(): void
    {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'pending'
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$translation->id}/reject", [
            'comment' => 'Incorrect translation'
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'status' => 'rejected',
            'reviewer_id' => $this->user->id
        ]);
    }

    /** @test */
    public function it_can_mark_translation_as_fuzzy(): void
    {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$translation->id}/mark-fuzzy");

        $response->assertOk();
        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'status' => 'fuzzy'
        ]);
    }

    /** @test */
    public function it_can_mark_translation_as_outdated(): void
    {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'approved'
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$translation->id}/mark-outdated");

        $response->assertOk();
        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'status' => 'outdated'
        ]);
    }

    /** @test */
    public function it_can_perform_bulk_approve_action(): void
    {
        $translation1 = Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'pending'
        ]);
        $translation2 = Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'pending'
        ]);

        $response = $this->postJson("{$this->baseUrl}/bulk-action", [
            'action' => 'approve',
            'translation_ids' => [$translation1->id, $translation2->id]
        ]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 2);

        $this->assertDatabaseHas('translations', [
            'id' => $translation1->id,
            'status' => 'approved'
        ]);
        $this->assertDatabaseHas('translations', [
            'id' => $translation2->id,
            'status' => 'approved'
        ]);
    }

    /** @test */
    public function it_can_perform_bulk_delete_action(): void
    {
        $translation1 = Translation::factory()->create(['language_id' => $this->language->id]);
        $translation2 = Translation::factory()->create(['language_id' => $this->language->id]);

        $response = $this->postJson("{$this->baseUrl}/bulk-action", [
            'action' => 'delete',
            'translation_ids' => [$translation1->id, $translation2->id]
        ]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 2);

        $this->assertSoftDeleted('translations', ['id' => $translation1->id]);
        $this->assertSoftDeleted('translations', ['id' => $translation2->id]);
    }

    /** @test */
    public function it_validates_bulk_action_parameters(): void
    {
        $response = $this->postJson("{$this->baseUrl}/bulk-action", [
            'action' => 'invalid_action',
            'translation_ids' => []
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['action', 'translation_ids']);
    }

    /** @test */
    public function it_can_get_missing_translations(): void
    {
        $referenceLanguage = Language::factory()->create(['is_default' => true]);
        $targetLanguage = Language::factory()->create();

        // Create reference translations
        Translation::factory()->create([
            'language_id' => $referenceLanguage->id,
            'key' => 'existing_key',
            'group' => 'common'
        ]);
        Translation::factory()->create([
            'language_id' => $referenceLanguage->id,
            'key' => 'missing_key',
            'group' => 'common'
        ]);

        // Create only one translation in target language
        Translation::factory()->create([
            'language_id' => $targetLanguage->id,
            'key' => 'existing_key',
            'group' => 'common'
        ]);

        $response = $this->getJson("{$this->baseUrl}/missing?language_id={$targetLanguage->id}&group=common");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'language',
                    'reference_language',
                    'missing_translations',
                    'count',
                    'message'
                ]
            ]);

        $missingTranslations = $response->json('data.missing_translations');
        $this->assertCount(1, $missingTranslations);
        $this->assertEquals('missing_key', $missingTranslations[0]['key']);
    }

    /** @test */
    public function it_can_get_translation_statistics(): void
    {
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'pending'
        ]);
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'approved'
        ]);
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'status' => 'rejected'
        ]);

        $response = $this->getJson("{$this->baseUrl}/statistics");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total',
                    'by_status' => [
                        'pending',
                        'approved',
                        'rejected',
                        'fuzzy',
                        'outdated'
                    ],
                    'by_language',
                    'by_group'
                ]
            ]);

        $stats = $response->json('data');
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['by_status']['pending']);
        $this->assertEquals(1, $stats['by_status']['approved']);
        $this->assertEquals(1, $stats['by_status']['rejected']);
    }

    /** @test */
    public function it_can_filter_statistics_by_language(): void
    {
        $otherLanguage = Language::factory()->create();

        Translation::factory()->create(['language_id' => $this->language->id]);
        Translation::factory()->create(['language_id' => $otherLanguage->id]);

        $response = $this->getJson("{$this->baseUrl}/statistics?language_id={$this->language->id}");

        $response->assertOk();
        $stats = $response->json('data');
        $this->assertEquals(1, $stats['total']);
    }

    /** @test */
    public function it_can_extract_translation_keys(): void
    {
        // Mock the extraction process since we can't test actual file extraction in unit tests
        $response = $this->postJson("{$this->baseUrl}/extract-keys", [
            'paths' => ['app/', 'resources/views/'],
            'language_id' => $this->language->id,
            'group' => 'extracted',
            'create_missing' => true,
            'mark_unused' => false
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'extracted_keys',
                    'created_translations',
                    'existing_translations',
                    'marked_unused',
                    'statistics',
                    'message'
                ]
            ]);
    }

    /** @test */
    public function it_validates_extract_keys_parameters(): void
    {
        $response = $this->postJson("{$this->baseUrl}/extract-keys", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['paths', 'language_id', 'group']);
    }

    /** @test */
    public function it_can_paginate_translations(): void
    {
        Translation::factory()->count(25)->create(['language_id' => $this->language->id]);

        $response = $this->getJson($this->baseUrl . '?per_page=10');

        $response->assertOk();
        
        $pagination = $response->json('data.pagination');
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['last_page']);
        
        $translations = $response->json('data.translations');
        $this->assertCount(10, $translations);
    }

    /** @test */
    public function it_can_sort_translations(): void
    {
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'z_key'
        ]);
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'a_key'
        ]);

        $response = $this->getJson($this->baseUrl . '?sort_by=key&sort_direction=asc');

        $response->assertOk();
        
        $translations = $response->json('data.translations');
        $this->assertEquals('a_key', $translations[0]['key']);
        $this->assertEquals('z_key', $translations[1]['key']);
    }
}