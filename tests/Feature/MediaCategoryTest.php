<?php

namespace Tests\Feature;

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for testing
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_media_category()
    {
        $category = MediaCategory::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);

        $this->assertInstanceOf(MediaCategory::class, $category);
        $this->assertEquals('Test Category', $category->name);
        $this->assertEquals('test-category', $category->slug);
        $this->assertDatabaseHas('media_categories', [
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);
    }

    /** @test */
    public function it_can_update_media_category()
    {
        $category = MediaCategory::factory()->create([
            'name' => 'Original Category',
            'slug' => 'original-category',
        ]);

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
    public function it_can_delete_media_category()
    {
        $category = MediaCategory::factory()->create();
        $categoryId = $category->id;

        $category->delete();

        $this->assertDatabaseMissing('media_categories', [
            'id' => $categoryId,
        ]);
    }

    /** @test */
    public function it_can_associate_media_items()
    {
        // Create a category and media items
        $category = MediaCategory::factory()->create();
        $media1 = Media::factory()->create(['user_id' => $this->user->id]);
        $media2 = Media::factory()->create(['user_id' => $this->user->id]);

        // Associate media with category
        $category->media()->attach([$media1->id, $media2->id]);

        // Refresh the model to get the relationships
        $category = $category->fresh(['media']);

        // Assert
        $this->assertCount(2, $category->media);
        $this->assertTrue($category->media->contains($media1));
        $this->assertTrue($category->media->contains($media2));
    }

    /** @test */
    public function it_can_sync_media_items()
    {
        // Create a category and media items
        $category = MediaCategory::factory()->create();
        $media1 = Media::factory()->create(['user_id' => $this->user->id]);
        $media2 = Media::factory()->create(['user_id' => $this->user->id]);
        $media3 = Media::factory()->create(['user_id' => $this->user->id]);

        // Initially associate media1 and media2
        $category->media()->attach([$media1->id, $media2->id]);

        // Now sync to only have media2 and media3
        $category->media()->sync([$media2->id, $media3->id]);

        // Refresh the model
        $category = $category->fresh(['media']);

        // Assert
        $this->assertCount(2, $category->media);
        $this->assertFalse($category->media->contains($media1));
        $this->assertTrue($category->media->contains($media2));
        $this->assertTrue($category->media->contains($media3));
    }

    /** @test */
    public function it_can_detach_all_media_items()
    {
        // Create a category and media items
        $category = MediaCategory::factory()->create();
        $media1 = Media::factory()->create(['user_id' => $this->user->id]);
        $media2 = Media::factory()->create(['user_id' => $this->user->id]);

        // Associate media with category
        $category->media()->attach([$media1->id, $media2->id]);

        // Detach all media
        $category->media()->detach();

        // Refresh the model
        $category = $category->fresh(['media']);

        // Assert
        $this->assertCount(0, $category->media);
    }

    /** @test */
    public function it_can_find_by_slug()
    {
        // Create categories with specific slugs
        MediaCategory::factory()->create(['slug' => 'first-category']);
        MediaCategory::factory()->create(['slug' => 'second-category']);

        // Find by slug
        $category = MediaCategory::where('slug', 'second-category')->first();

        // Assert
        $this->assertNotNull($category);
        $this->assertEquals('second-category', $category->slug);
    }
}
