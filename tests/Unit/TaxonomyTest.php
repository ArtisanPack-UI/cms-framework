<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use ArtisanPackUI\CMSFramework\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_taxonomy()
    {
        $taxonomy = Taxonomy::factory()->create();

        $this->assertInstanceOf(Taxonomy::class, $taxonomy);
        $this->assertDatabaseHas('taxonomies', [
            'id' => $taxonomy->id,
            'handle' => $taxonomy->handle,
            'label' => $taxonomy->label,
            'label_plural' => $taxonomy->label_plural,
        ]);
    }

    /** @test */
    public function it_can_update_a_taxonomy()
    {
        $taxonomy = Taxonomy::factory()->create();

        $taxonomy->update([
            'label' => 'Updated Label',
            'label_plural' => 'Updated Labels',
            'hierarchical' => true,
        ]);

        $this->assertEquals('Updated Label', $taxonomy->label);
        $this->assertEquals('Updated Labels', $taxonomy->label_plural);
        $this->assertTrue($taxonomy->hierarchical);
        $this->assertDatabaseHas('taxonomies', [
            'id' => $taxonomy->id,
            'label' => 'Updated Label',
            'label_plural' => 'Updated Labels',
            'hierarchical' => true,
        ]);
    }

    /** @test */
    public function it_can_delete_a_taxonomy()
    {
        $taxonomy = Taxonomy::factory()->create();
        $taxonomyId = $taxonomy->id;

        $taxonomy->delete();

        $this->assertDatabaseMissing('taxonomies', [
            'id' => $taxonomyId,
        ]);
    }

    /** @test */
    public function it_casts_content_types_as_array()
    {
        $contentTypes = ['post', 'page', 'custom'];

        $taxonomy = Taxonomy::factory()->create([
            'content_types' => $contentTypes,
        ]);

        $this->assertIsArray($taxonomy->content_types);
        $this->assertEquals($contentTypes, $taxonomy->content_types);
    }

    /** @test */
    public function it_casts_hierarchical_as_boolean()
    {
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);

        $this->assertIsBool($taxonomy->hierarchical);
        $this->assertTrue($taxonomy->hierarchical);

        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => false,
        ]);

        $this->assertIsBool($taxonomy->hierarchical);
        $this->assertFalse($taxonomy->hierarchical);
    }

    /** @test */
    public function it_can_have_terms()
    {
        $taxonomy = Taxonomy::factory()->create();

        $term1 = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $term2 = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $this->assertCount(2, $taxonomy->terms);
        $this->assertTrue($taxonomy->terms->contains($term1));
        $this->assertTrue($taxonomy->terms->contains($term2));
    }
}
