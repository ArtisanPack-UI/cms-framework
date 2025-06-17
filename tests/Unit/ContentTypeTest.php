<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\ContentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentTypeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_content_type()
    {
        $contentType = ContentType::factory()->create();

        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertDatabaseHas('content_types', [
            'id' => $contentType->id,
            'handle' => $contentType->handle,
            'label' => $contentType->label,
            'label_plural' => $contentType->label_plural,
            'slug' => $contentType->slug,
        ]);
    }

    /** @test */
    public function it_can_update_a_content_type()
    {
        $contentType = ContentType::factory()->create();

        $contentType->update([
            'label' => 'Updated Label',
            'label_plural' => 'Updated Labels',
            'slug' => 'updated-slug',
        ]);

        $this->assertEquals('Updated Label', $contentType->label);
        $this->assertEquals('Updated Labels', $contentType->label_plural);
        $this->assertEquals('updated-slug', $contentType->slug);
        $this->assertDatabaseHas('content_types', [
            'id' => $contentType->id,
            'label' => 'Updated Label',
            'label_plural' => 'Updated Labels',
            'slug' => 'updated-slug',
        ]);
    }

    /** @test */
    public function it_can_delete_a_content_type()
    {
        $contentType = ContentType::factory()->create();
        $contentTypeId = $contentType->id;

        $contentType->delete();

        $this->assertDatabaseMissing('content_types', [
            'id' => $contentTypeId,
        ]);
    }

    /** @test */
    public function it_casts_definition_as_array()
    {
        $definition = [
            'public' => true,
            'hierarchical' => false,
            'supports' => ['title', 'content', 'author'],
            'fields' => [
                [
                    'name' => 'test_field',
                    'type' => 'text',
                    'label' => 'Test Field',
                ]
            ]
        ];

        $contentType = ContentType::factory()->create([
            'definition' => $definition,
        ]);

        $this->assertIsArray($contentType->definition);
        $this->assertEquals($definition, $contentType->definition);
    }
}
