<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaCategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_media_category()
    {
        $category = MediaCategory::factory()->create();
        
        $this->assertInstanceOf(MediaCategory::class, $category);
        $this->assertDatabaseHas('media_categories', [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ]);
    }

    /** @test */
    public function it_can_update_a_media_category()
    {
        $category = MediaCategory::factory()->create();
        
        $category->update([
            'name' => 'Updated Category',
            'slug' => 'updated-category',
        ]);
        
        $this->assertEquals('Updated Category', $category->name);
        $this->assertEquals('updated-category', $category->slug);
        $this->assertDatabaseHas('media_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'slug' => 'updated-category',
        ]);
    }

    /** @test */
    public function it_can_delete_a_media_category()
    {
        $category = MediaCategory::factory()->create();
        $categoryId = $category->id;
        
        $category->delete();
        
        $this->assertDatabaseMissing('media_categories', [
            'id' => $categoryId,
        ]);
    }

    /** @test */
    public function it_can_have_many_media_items()
    {
        $category = MediaCategory::factory()->create();
        $media1 = Media::factory()->create();
        $media2 = Media::factory()->create();
        
        $category->media()->attach([$media1->id, $media2->id]);
        
        $this->assertCount(2, $category->media);
        $this->assertTrue($category->media->contains($media1));
        $this->assertTrue($category->media->contains($media2));
    }

    /** @test */
    public function it_can_find_category_by_slug()
    {
        $category = MediaCategory::factory()->create([
            'slug' => 'test-category',
        ]);
        
        $foundCategory = MediaCategory::where('slug', 'test-category')->first();
        
        $this->assertInstanceOf(MediaCategory::class, $foundCategory);
        $this->assertEquals($category->id, $foundCategory->id);
    }
}