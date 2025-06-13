<?php

namespace Tests\Feature;

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaTagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for testing
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_media_tag()
    {
        $tag = MediaTag::create([
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);

        $this->assertInstanceOf(MediaTag::class, $tag);
        $this->assertEquals('Test Tag', $tag->name);
        $this->assertEquals('test-tag', $tag->slug);
        $this->assertDatabaseHas('media_tags', [
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);
    }

    /** @test */
    public function it_can_update_media_tag()
    {
        $tag = MediaTag::factory()->create([
            'name' => 'Original Tag',
            'slug' => 'original-tag',
        ]);

        $tag->update([
            'name' => 'Updated Tag',
            'slug' => 'updated-tag',
        ]);

        $this->assertEquals('Updated Tag', $tag->name);
        $this->assertEquals('updated-tag', $tag->slug);
        $this->assertDatabaseHas('media_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
            'slug' => 'updated-tag',
        ]);
    }

    /** @test */
    public function it_can_delete_media_tag()
    {
        $tag = MediaTag::factory()->create();
        $tagId = $tag->id;

        $tag->delete();

        $this->assertDatabaseMissing('media_tags', [
            'id' => $tagId,
        ]);
    }

    /** @test */
    public function it_can_associate_media_items()
    {
        // Create a tag and media items
        $tag = MediaTag::factory()->create();
        $media1 = Media::factory()->create(['user_id' => $this->user->id]);
        $media2 = Media::factory()->create(['user_id' => $this->user->id]);

        // Associate media with tag
        $tag->media()->attach([$media1->id, $media2->id]);

        // Refresh the model to get the relationships
        $tag = $tag->fresh(['media']);

        // Assert
        $this->assertCount(2, $tag->media);
        $this->assertTrue($tag->media->contains($media1));
        $this->assertTrue($tag->media->contains($media2));
    }

    /** @test */
    public function it_can_sync_media_items()
    {
        // Create a tag and media items
        $tag = MediaTag::factory()->create();
        $media1 = Media::factory()->create(['user_id' => $this->user->id]);
        $media2 = Media::factory()->create(['user_id' => $this->user->id]);
        $media3 = Media::factory()->create(['user_id' => $this->user->id]);

        // Initially associate media1 and media2
        $tag->media()->attach([$media1->id, $media2->id]);

        // Now sync to only have media2 and media3
        $tag->media()->sync([$media2->id, $media3->id]);

        // Refresh the model
        $tag = $tag->fresh(['media']);

        // Assert
        $this->assertCount(2, $tag->media);
        $this->assertFalse($tag->media->contains($media1));
        $this->assertTrue($tag->media->contains($media2));
        $this->assertTrue($tag->media->contains($media3));
    }

    /** @test */
    public function it_can_detach_all_media_items()
    {
        // Create a tag and media items
        $tag = MediaTag::factory()->create();
        $media1 = Media::factory()->create(['user_id' => $this->user->id]);
        $media2 = Media::factory()->create(['user_id' => $this->user->id]);

        // Associate media with tag
        $tag->media()->attach([$media1->id, $media2->id]);

        // Detach all media
        $tag->media()->detach();

        // Refresh the model
        $tag = $tag->fresh(['media']);

        // Assert
        $this->assertCount(0, $tag->media);
    }

    /** @test */
    public function it_can_find_by_slug()
    {
        // Create tags with specific slugs
        MediaTag::factory()->create(['slug' => 'first-tag']);
        MediaTag::factory()->create(['slug' => 'second-tag']);

        // Find by slug
        $tag = MediaTag::where('slug', 'second-tag')->first();

        // Assert
        $this->assertNotNull($tag);
        $this->assertEquals('second-tag', $tag->slug);
    }

    /** @test */
    public function it_can_find_media_items_with_specific_tag()
    {
        // Create a tag
        $tag = MediaTag::factory()->create();

        // Create media items, some with the tag
        $media1 = Media::factory()->create(['user_id' => $this->user->id]);
        $media2 = Media::factory()->create(['user_id' => $this->user->id]);
        $media3 = Media::factory()->create(['user_id' => $this->user->id]);

        // Associate only media1 and media3 with the tag
        $tag->media()->attach([$media1->id, $media3->id]);

        // Get all media with this tag
        $taggedMedia = $tag->media;

        // Assert
        $this->assertCount(2, $taggedMedia);
        $this->assertTrue($taggedMedia->contains($media1));
        $this->assertFalse($taggedMedia->contains($media2));
        $this->assertTrue($taggedMedia->contains($media3));
    }
}
