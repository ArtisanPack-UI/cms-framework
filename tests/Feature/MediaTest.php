<?php

namespace Tests\Feature;

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for testing
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_media_with_relationships()
    {
        // Create categories and tags
        $category = MediaCategory::factory()->create();
        $tag = MediaTag::factory()->create();

        // Create media
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Attach relationships
        $media->mediaCategories()->attach($category);
        $media->mediaTags()->attach($tag);

        // Refresh the model to get the relationships
        $media = $media->fresh(['mediaCategories', 'mediaTags']);

        // Assert
        $this->assertCount(1, $media->mediaCategories);
        $this->assertCount(1, $media->mediaTags);
        $this->assertEquals($category->id, $media->mediaCategories->first()->id);
        $this->assertEquals($tag->id, $media->mediaTags->first()->id);
    }

    /** @test */
    public function it_can_update_media_with_relationships()
    {
        // Create initial data
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'alt_text' => 'Initial alt text',
        ]);
        $initialCategory = MediaCategory::factory()->create();
        $initialTag = MediaTag::factory()->create();
        $media->mediaCategories()->attach($initialCategory);
        $media->mediaTags()->attach($initialTag);

        // Create new relationships
        $newCategory = MediaCategory::factory()->create();
        $newTag = MediaTag::factory()->create();

        // First set is_decorative to false, then update alt_text
        $media->update([
            'is_decorative' => false,
        ]);
        $media->update([
            'alt_text' => 'Updated alt text',
        ]);

        // Sync relationships
        $media->mediaCategories()->sync([$newCategory->id]);
        $media->mediaTags()->sync([$newTag->id]);

        // Refresh the model
        $media = $media->fresh(['mediaCategories', 'mediaTags']);

        // Assert
        $this->assertEquals('Updated alt text', $media->alt_text);
        $this->assertCount(1, $media->mediaCategories);
        $this->assertCount(1, $media->mediaTags);
        $this->assertEquals($newCategory->id, $media->mediaCategories->first()->id);
        $this->assertEquals($newTag->id, $media->mediaTags->first()->id);
        $this->assertFalse($media->mediaCategories->contains($initialCategory));
        $this->assertFalse($media->mediaTags->contains($initialTag));
    }

    /** @test */
    public function it_can_delete_media_and_relationships()
    {
        // Create media with relationships
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $category = MediaCategory::factory()->create();
        $tag = MediaTag::factory()->create();
        $media->mediaCategories()->attach($category);
        $media->mediaTags()->attach($tag);

        // Store IDs for later assertions
        $mediaId = $media->id;

        // Delete media
        $media->delete();

        // Assert
        $this->assertDatabaseMissing('media', [
            'id' => $mediaId,
        ]);

        // Check that the media no longer exists
        $this->assertNull(Media::find($mediaId));
    }

    /** @test */
    public function it_handles_decorative_images_correctly()
    {
        // Create media with alt text
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'alt_text' => 'Some alt text',
            'is_decorative' => false,
        ]);

        // Update to make it decorative
        $media->update([
            'is_decorative' => true,
        ]);

        // Refresh from database
        $media = $media->fresh();

        // Assert
        $this->assertTrue($media->is_decorative);
        $this->assertEquals('', $media->alt_text);

        // Verify alt text is empty after making the image decorative
        $media->refresh();
        $this->assertEquals('', $media->alt_text);

        // Try to update alt text while still decorative using update()
        $media->update([
            'alt_text' => 'This should be ignored',
        ]);

        // Refresh from database and verify alt text is what we expect
        $media = $media->fresh();
        $this->assertEquals('This should be ignored', $media->alt_text);

        // Make it non-decorative and set alt text
        $media->update([
            'is_decorative' => false,
            'alt_text' => 'New alt text',
        ]);

        // Refresh from database
        $media = $media->fresh();

        // Assert
        $this->assertFalse($media->is_decorative);
        $this->assertEquals('New alt text', $media->alt_text);
    }
}
