<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\ContentType;
use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_content_item()
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
        ]);

        $this->assertInstanceOf(Content::class, $content);
        $this->assertDatabaseHas('content', [
            'id' => $content->id,
            'title' => $content->title,
            'slug' => $content->slug,
            'author_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_can_update_a_content_item()
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
        ]);

        $content->update([
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'content' => 'Updated content text',
        ]);

        $this->assertEquals('Updated Title', $content->title);
        $this->assertEquals('updated-slug', $content->slug);
        $this->assertEquals('Updated content text', $content->content);
        $this->assertDatabaseHas('content', [
            'id' => $content->id,
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'content' => 'Updated content text',
        ]);
    }

    /** @test */
    public function it_can_delete_a_content_item()
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
        ]);
        $contentId = $content->id;

        $content->delete();

        $this->assertDatabaseMissing('content', [
            'id' => $contentId,
        ]);
    }

    /** @test */
    public function it_belongs_to_an_author()
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $content->author);
        $this->assertEquals($user->id, $content->author->id);
    }

    /** @test */
    public function it_can_have_a_parent_content()
    {
        $user = User::factory()->create();
        $parentContent = Content::factory()->create([
            'author_id' => $user->id,
        ]);

        $childContent = Content::factory()->create([
            'author_id' => $user->id,
            'parent_id' => $parentContent->id,
        ]);

        $this->assertInstanceOf(Content::class, $childContent->parent);
        $this->assertEquals($parentContent->id, $childContent->parent->id);
    }

    /** @test */
    public function it_can_have_child_content()
    {
        $user = User::factory()->create();
        $parentContent = Content::factory()->create([
            'author_id' => $user->id,
        ]);

        $childContent = Content::factory()->create([
            'author_id' => $user->id,
            'parent_id' => $parentContent->id,
        ]);

        $this->assertCount(1, $parentContent->children);
        $this->assertTrue($parentContent->children->contains($childContent));
    }

    /** @test */
    public function it_can_have_terms()
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
        ]);

        $term1 = Term::factory()->create();
        $term2 = Term::factory()->create();

        $content->terms()->attach([$term1->id, $term2->id]);

        $this->assertCount(2, $content->terms);
        $this->assertTrue($content->terms->contains($term1));
        $this->assertTrue($content->terms->contains($term2));
    }

    /** @test */
    public function it_can_get_and_set_meta_values()
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
            'meta' => [
                'test_key' => 'test_value',
                'nested' => [
                    'key' => 'nested_value'
                ]
            ],
        ]);

        // Test getting meta values
        $this->assertEquals('test_value', $content->getMeta('test_key'));
        $this->assertEquals('nested_value', $content->getMeta('nested.key'));
        $this->assertNull($content->getMeta('non_existent_key'));
        $this->assertEquals('default', $content->getMeta('non_existent_key', 'default'));

        // Test setting meta values
        $content->setMeta('new_key', 'new_value');
        $this->assertEquals('new_value', $content->getMeta('new_key'));

        $content->setMeta('nested.new_key', 'new_nested_value');
        $this->assertEquals('new_nested_value', $content->getMeta('nested.new_key'));
    }

    /** @test */
    public function it_can_filter_by_type()
    {
        $user = User::factory()->create();

        // Create content items of different types
        $post1 = Content::factory()->create([
            'author_id' => $user->id,
            'type' => 'post',
        ]);

        $post2 = Content::factory()->create([
            'author_id' => $user->id,
            'type' => 'post',
        ]);

        $page = Content::factory()->create([
            'author_id' => $user->id,
            'type' => 'page',
        ]);

        // Test the ofType scope
        $posts = Content::ofType('post')->get();
        $this->assertCount(2, $posts);
        $this->assertTrue($posts->contains($post1));
        $this->assertTrue($posts->contains($post2));
        $this->assertFalse($posts->contains($page));
    }

    /** @test */
    public function it_can_filter_published_content()
    {
        $user = User::factory()->create();

        // Create published content
        $publishedContent = Content::factory()->create([
            'author_id' => $user->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        // Create draft content
        $draftContent = Content::factory()->create([
            'author_id' => $user->id,
            'status' => 'draft',
        ]);

        // Create scheduled content
        $scheduledContent = Content::factory()->create([
            'author_id' => $user->id,
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);

        // Test the published scope
        $publishedItems = Content::published()->get();
        $this->assertCount(1, $publishedItems);
        $this->assertTrue($publishedItems->contains($publishedContent));
        $this->assertFalse($publishedItems->contains($draftContent));
        $this->assertFalse($publishedItems->contains($scheduledContent));
    }
}
