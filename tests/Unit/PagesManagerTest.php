<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Features\Pages\PagesManager;
use ArtisanPackUI\CMSFramework\Models\Page;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PagesManagerTest extends TestCase
{
    use RefreshDatabase;

    protected PagesManager $pagesManager;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pagesManager = new PagesManager();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_get_all_pages()
    {
        // Create some test pages
        Page::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Get all pages using the manager
        $pages = $this->pagesManager->all();

        // Assert we have the expected number of pages
        $this->assertCount(3, $pages);
        $this->assertInstanceOf(Page::class, $pages->first());
    }

    /** @test */
    public function it_can_get_a_page_by_id()
    {
        // Create a test page
        $page = Page::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Page',
        ]);

        // Get the page using the manager
        $retrievedPage = $this->pagesManager->get($page->id);

        // Assert we got the expected page
        $this->assertInstanceOf(Page::class, $retrievedPage);
        $this->assertEquals($page->id, $retrievedPage->id);
        $this->assertEquals('Test Page', $retrievedPage->title);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_page()
    {
        // Try to get a page that doesn't exist
        $retrievedPage = $this->pagesManager->get(999);

        // Assert we got null
        $this->assertNull($retrievedPage);
    }

    /** @test */
    public function it_can_create_a_page()
    {
        // Create a page using the manager
        $pageData = [
            'user_id' => $this->user->id,
            'title' => 'New Page',
            'slug' => 'new-page',
            'content' => 'This is a new page.',
            'status' => 'published',
        ];

        $page = $this->pagesManager->create($pageData);

        // Assert the page was created correctly
        $this->assertInstanceOf(Page::class, $page);
        $this->assertEquals('New Page', $page->title);
        $this->assertEquals('new-page', $page->slug);
        $this->assertEquals('This is a new page.', $page->content);
        $this->assertEquals('published', $page->status);
        $this->assertEquals($this->user->id, $page->user_id);

        // Assert the page exists in the database
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title' => 'New Page',
            'slug' => 'new-page',
        ]);
    }

    /** @test */
    public function it_can_update_a_page()
    {
        // Create a test page
        $page = Page::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'content' => 'Original content',
        ]);

        // Update the page using the manager
        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ];

        $updatedPage = $this->pagesManager->update($page->id, $updateData);

        // Assert the page was updated correctly
        $this->assertInstanceOf(Page::class, $updatedPage);
        $this->assertEquals($page->id, $updatedPage->id);
        $this->assertEquals('Updated Title', $updatedPage->title);
        $this->assertEquals('original-slug', $updatedPage->slug); // Slug should not change
        $this->assertEquals('Updated content', $updatedPage->content);

        // Assert the page was updated in the database
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ]);
    }

    /** @test */
    public function it_returns_null_when_updating_nonexistent_page()
    {
        // Try to update a page that doesn't exist
        $updateData = [
            'title' => 'Updated Title',
        ];

        $updatedPage = $this->pagesManager->update(999, $updateData);

        // Assert we got null
        $this->assertNull($updatedPage);
    }

    /** @test */
    public function it_can_delete_a_page()
    {
        // Create a test page
        $page = Page::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Delete the page using the manager
        $result = $this->pagesManager->delete($page->id);

        // Assert the deletion was successful
        $this->assertTrue($result);

        // Assert the page was deleted from the database
        $this->assertDatabaseMissing('pages', [
            'id' => $page->id,
        ]);
    }

    /** @test */
    public function it_returns_false_when_deleting_nonexistent_page()
    {
        // Try to delete a page that doesn't exist
        $result = $this->pagesManager->delete(999);

        // Assert the deletion failed
        $this->assertFalse($result);
    }

    /** @test */
    public function it_refreshes_cache_when_creating_a_page()
    {
        // Mock the Cache facade
        Cache::shouldReceive('forget')
            ->once()
            ->with('cms.website.pages.resolved');

        // Create a page using the manager
        $pageData = [
            'user_id' => $this->user->id,
            'title' => 'New Page',
            'slug' => 'new-page',
            'content' => 'This is a new page.',
            'status' => 'published',
        ];

        $this->pagesManager->create($pageData);
    }

    /** @test */
    public function it_refreshes_cache_when_updating_a_page()
    {
        // Create a test page
        $page = Page::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Mock the Cache facade
        Cache::shouldReceive('forget')
            ->once()
            ->with('cms.website.pages.resolved');

        // Update the page using the manager
        $updateData = [
            'title' => 'Updated Title',
        ];

        $this->pagesManager->update($page->id, $updateData);
    }

    /** @test */
    public function it_refreshes_cache_when_deleting_a_page()
    {
        // Create a test page
        $page = Page::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Mock the Cache facade
        Cache::shouldReceive('forget')
            ->once()
            ->with('cms.website.pages.resolved');

        // Delete the page using the manager
        $this->pagesManager->delete($page->id);
    }

    /** @test */
    public function it_uses_cache_when_getting_all_pages()
    {
        // Create some test pages
        Page::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Mock the Cache facade
        Cache::shouldReceive('remember')
            ->once()
            ->with('cms.website.pages.resolved', 60 * 24, \Mockery::type('Closure'))
            ->andReturn(Page::all());

        // Get all pages using the manager
        $this->pagesManager->all();
    }
}