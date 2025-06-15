<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Http\Resources\PageResource;
use ArtisanPackUI\CMSFramework\Models\Page;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_transforms_page_model_to_array()
    {
        // Create a user and a page
        $user = User::factory()->create();
        $page = Page::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'content' => 'This is test content',
            'status' => 'published',
            'parent_id' => null,
            'order' => 1,
        ]);

        // Create the resource
        $resource = new PageResource($page);
        
        // Convert to array
        $array = $resource->toArray(request());

        // Assert the array has the expected structure and values
        $this->assertEquals($page->id, $array['id']);
        $this->assertEquals('Test Page', $array['title']);
        $this->assertEquals('test-page', $array['slug']);
        $this->assertEquals('This is test content', $array['content']);
        $this->assertEquals('published', $array['status']);
        $this->assertNull($array['parent_id']);
        $this->assertEquals(1, $array['order']);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    /** @test */
    public function it_includes_all_required_fields()
    {
        // Create a user and a page
        $user = User::factory()->create();
        $page = Page::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create the resource
        $resource = new PageResource($page);
        
        // Convert to array
        $array = $resource->toArray(request());

        // Assert the array has all required fields
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('parent_id', $array);
        $this->assertArrayHasKey('order', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    /** @test */
    public function it_handles_null_values_correctly()
    {
        // Create a user and a page with null values
        $user = User::factory()->create();
        $page = Page::factory()->create([
            'user_id' => $user->id,
            'content' => null,
            'parent_id' => null,
        ]);

        // Create the resource
        $resource = new PageResource($page);
        
        // Convert to array
        $array = $resource->toArray(request());

        // Assert null values are preserved
        $this->assertNull($array['content']);
        $this->assertNull($array['parent_id']);
    }
}