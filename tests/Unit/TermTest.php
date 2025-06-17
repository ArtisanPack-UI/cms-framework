<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_term()
    {
        $taxonomy = Taxonomy::factory()->create();
        $term = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $this->assertInstanceOf(Term::class, $term);
        $this->assertDatabaseHas('terms', [
            'id' => $term->id,
            'name' => $term->name,
            'slug' => $term->slug,
            'taxonomy_id' => $taxonomy->id,
        ]);
    }

    /** @test */
    public function it_can_update_a_term()
    {
        $taxonomy = Taxonomy::factory()->create();
        $term = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $term->update([
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
        ]);

        $this->assertEquals('Updated Name', $term->name);
        $this->assertEquals('updated-slug', $term->slug);
        $this->assertDatabaseHas('terms', [
            'id' => $term->id,
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
        ]);
    }

    /** @test */
    public function it_can_delete_a_term()
    {
        $taxonomy = Taxonomy::factory()->create();
        $term = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);
        $termId = $term->id;

        $term->delete();

        $this->assertDatabaseMissing('terms', [
            'id' => $termId,
        ]);
    }

    /** @test */
    public function it_belongs_to_a_taxonomy()
    {
        $taxonomy = Taxonomy::factory()->create();
        $term = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $this->assertInstanceOf(Taxonomy::class, $term->taxonomy);
        $this->assertEquals($taxonomy->id, $term->taxonomy->id);
    }

    /** @test */
    public function it_can_have_a_parent_term()
    {
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);

        $parentTerm = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $childTerm = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
            'parent_id' => $parentTerm->id,
        ]);

        $this->assertInstanceOf(Term::class, $childTerm->parent);
        $this->assertEquals($parentTerm->id, $childTerm->parent->id);
    }

    /** @test */
    public function it_can_have_child_terms()
    {
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);

        $parentTerm = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $childTerm = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
            'parent_id' => $parentTerm->id,
        ]);

        $this->assertCount(1, $parentTerm->children);
        $this->assertTrue($parentTerm->children->contains($childTerm));
    }

    /** @test */
    public function it_can_have_content()
    {
        $taxonomy = Taxonomy::factory()->create();
        $term = Term::factory()->create([
            'taxonomy_id' => $taxonomy->id,
        ]);

        $user = User::factory()->create();
        $content1 = Content::factory()->create([
            'author_id' => $user->id,
        ]);

        $content2 = Content::factory()->create([
            'author_id' => $user->id,
        ]);

        $term->content()->attach([$content1->id, $content2->id]);

        $this->assertCount(2, $term->content);
        $this->assertTrue($term->content->contains($content1));
        $this->assertTrue($term->content->contains($content2));
    }
}
