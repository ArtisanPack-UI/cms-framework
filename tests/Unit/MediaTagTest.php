<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\CMSFramework\Models\MediaTag;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaTagTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_media_tag()
    {
        $tag = MediaTag::factory()->create();

        $this->assertInstanceOf(MediaTag::class, $tag);
        $this->assertDatabaseHas('media_tags', [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ]);
    }

    /** @test */
    public function it_can_update_a_media_tag()
    {
        $tag = MediaTag::factory()->create();

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
    public function it_can_delete_a_media_tag()
    {
        $tag = MediaTag::factory()->create();
        $tagId = $tag->id;

        $tag->delete();

        $this->assertDatabaseMissing('media_tags', [
            'id' => $tagId,
        ]);
    }

    /** @test */
    public function it_can_have_many_media_items()
    {
        $user = User::factory()->create();
        $tag = MediaTag::factory()->create();
        $media1 = Media::factory()->create([
            'user_id' => $user->id,
        ]);
        $media2 = Media::factory()->create([
            'user_id' => $user->id,
        ]);

        $tag->media()->attach([$media1->id, $media2->id]);

        $this->assertCount(2, $tag->media);
        $this->assertTrue($tag->media->contains($media1));
        $this->assertTrue($tag->media->contains($media2));
    }

    /** @test */
    public function it_can_find_tag_by_slug()
    {
        $tag = MediaTag::factory()->create([
            'slug' => 'test-tag',
        ]);

        $foundTag = MediaTag::where('slug', 'test-tag')->first();

        $this->assertInstanceOf(MediaTag::class, $foundTag);
        $this->assertEquals($tag->id, $foundTag->id);
    }
}
