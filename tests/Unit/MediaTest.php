<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_media_item()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_can_update_a_media_item()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
        ]);

        $media->update([
            'alt_text' => 'Updated alt text',
            'is_decorative' => false,
        ]);

        $this->assertEquals('Updated alt text', $media->alt_text);
        $this->assertFalse($media->is_decorative);
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'alt_text' => 'Updated alt text',
            'is_decorative' => false,
        ]);
    }

    /** @test */
    public function it_can_delete_a_media_item()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
        ]);
        $mediaId = $media->id;

        $media->delete();

        $this->assertDatabaseMissing('media', [
            'id' => $mediaId,
        ]);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $media->user);
        $this->assertEquals($user->id, $media->user->id);
    }

    /** @test */
    public function it_can_have_many_categories()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
        ]);
        $category1 = MediaCategory::factory()->create();
        $category2 = MediaCategory::factory()->create();

        $media->mediaCategories()->attach([$category1->id, $category2->id]);

        $this->assertCount(2, $media->mediaCategories);
        $this->assertTrue($media->mediaCategories->contains($category1));
        $this->assertTrue($media->mediaCategories->contains($category2));
    }

    /** @test */
    public function it_can_have_many_tags()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
        ]);
        $tag1 = MediaTag::factory()->create();
        $tag2 = MediaTag::factory()->create();

        $media->mediaTags()->attach([$tag1->id, $tag2->id]);

        $this->assertCount(2, $media->mediaTags);
        $this->assertTrue($media->mediaTags->contains($tag1));
        $this->assertTrue($media->mediaTags->contains($tag2));
    }

    /** @test */
    public function it_clears_alt_text_when_is_decorative_is_true()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
            'alt_text' => 'Some alt text',
            'is_decorative' => false,
        ]);

        $media->update([
            'is_decorative' => true,
        ]);

        $this->assertEquals('', $media->alt_text);
        $this->assertTrue($media->is_decorative);
    }

    /** @test */
    public function it_keeps_alt_text_when_is_decorative_is_false()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
            'alt_text' => 'Some alt text',
            'is_decorative' => false,
        ]);

        $media->update([
            'alt_text' => 'Updated alt text',
            'is_decorative' => false,
        ]);

        $this->assertEquals('Updated alt text', $media->alt_text);
        $this->assertFalse($media->is_decorative);
    }
}
