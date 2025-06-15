<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\Page;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_page()
    {
        $user = User::factory()->create();
        $page = Page::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'content' => 'This is a test page content.',
            'status' => 'published',
        ]);

        $this->assertInstanceOf(Page::class, $page);
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'content' => 'This is a test page content.',
            'status' => 'published',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_can_update_a_page()
    {
        $user = User::factory()->create();
        $page = Page::factory()->create([
            'user_id' => $user->id,
            'title' => 'Original Title',
            'slug' => 'original-slug',
        ]);

        $page->update([
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'content' => 'Updated content',
        ]);

        $this->assertEquals('Updated Title', $page->title);
        $this->assertEquals('updated-slug', $page->slug);
        $this->assertEquals('Updated content', $page->content);
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'content' => 'Updated content',
        ]);
    }

    /** @test */
    public function it_can_delete_a_page()
    {
        $user = User::factory()->create();
        $page = Page::factory()->create([
            'user_id' => $user->id,
        ]);
        $pageId = $page->id;

        $page->delete();

        $this->assertDatabaseMissing('pages', [
            'id' => $pageId,
        ]);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $page = Page::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $page->user);
        $this->assertEquals($user->id, $page->user->id);
    }

    /** @test */
    public function it_can_have_a_parent_page()
    {
        $user = User::factory()->create();
        $parentPage = Page::factory()->create([
            'user_id' => $user->id,
        ]);
        $childPage = Page::factory()->create([
            'user_id' => $user->id,
            'parent_id' => $parentPage->id,
        ]);

        $this->assertInstanceOf(Page::class, $childPage->parent);
        $this->assertEquals($parentPage->id, $childPage->parent->id);
    }

    /** @test */
    public function it_can_have_child_pages()
    {
        $user = User::factory()->create();
        $parentPage = Page::factory()->create([
            'user_id' => $user->id,
        ]);
        $childPage1 = Page::factory()->create([
            'user_id' => $user->id,
            'parent_id' => $parentPage->id,
        ]);
        $childPage2 = Page::factory()->create([
            'user_id' => $user->id,
            'parent_id' => $parentPage->id,
        ]);

        $this->assertCount(2, $parentPage->children);
        $this->assertTrue($parentPage->children->contains($childPage1));
        $this->assertTrue($parentPage->children->contains($childPage2));
    }
}