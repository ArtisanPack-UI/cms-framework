<?php

use ArtisanPackUI\CMSFramework\Features\Pages\PagesManager;
use ArtisanPackUI\CMSFramework\Models\Page;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Set up test data before each test
beforeEach(function () {
    // Create a user
    $this->user = User::factory()->create();
    
    // Create the PagesManager
    $this->pagesManager = new PagesManager();
});

it('can manage the full lifecycle of a page', function () {
    // 1. Create a page
    $pageData = [
        'user_id' => $this->user->id,
        'title' => 'Feature Test Page',
        'slug' => 'feature-test-page',
        'content' => 'This is a page created in a feature test.',
        'status' => 'draft',
    ];
    
    $page = $this->pagesManager->create($pageData);
    
    // Assert the page was created correctly
    expect($page)->toBeInstanceOf(Page::class);
    expect($page->title)->toBe('Feature Test Page');
    expect($page->slug)->toBe('feature-test-page');
    expect($page->status)->toBe('draft');
    
    // 2. Retrieve the page
    $retrievedPage = $this->pagesManager->get($page->id);
    
    // Assert the page was retrieved correctly
    expect($retrievedPage)->toBeInstanceOf(Page::class);
    expect($retrievedPage->id)->toBe($page->id);
    expect($retrievedPage->title)->toBe('Feature Test Page');
    
    // 3. Update the page
    $updateData = [
        'title' => 'Updated Feature Test Page',
        'status' => 'published',
        'published_at' => now(),
    ];
    
    $updatedPage = $this->pagesManager->update($page->id, $updateData);
    
    // Assert the page was updated correctly
    expect($updatedPage)->toBeInstanceOf(Page::class);
    expect($updatedPage->id)->toBe($page->id);
    expect($updatedPage->title)->toBe('Updated Feature Test Page');
    expect($updatedPage->status)->toBe('published');
    expect($updatedPage->published_at)->not->toBeNull();
    
    // 4. Delete the page
    $deleted = $this->pagesManager->delete($page->id);
    
    // Assert the page was deleted
    expect($deleted)->toBeTrue();
    $this->assertDatabaseMissing('pages', ['id' => $page->id]);
});

it('can create a page with a parent-child relationship', function () {
    // Create a parent page
    $parentPage = Page::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Parent Page',
        'slug' => 'parent-page',
    ]);
    
    // Create a child page
    $childPageData = [
        'user_id' => $this->user->id,
        'title' => 'Child Page',
        'slug' => 'child-page',
        'content' => 'This is a child page.',
        'status' => 'published',
        'parent_id' => $parentPage->id,
    ];
    
    $childPage = $this->pagesManager->create($childPageData);
    
    // Assert the child page was created with the correct parent
    expect($childPage->parent_id)->toBe($parentPage->id);
    
    // Retrieve the parent page and check its children
    $parentWithChildren = Page::with('children')->find($parentPage->id);
    expect($parentWithChildren->children)->toHaveCount(1);
    expect($parentWithChildren->children->first()->id)->toBe($childPage->id);
});

it('can retrieve all pages', function () {
    // Create multiple pages
    Page::factory()->count(5)->create([
        'user_id' => $this->user->id,
    ]);
    
    // Retrieve all pages
    $pages = $this->pagesManager->all();
    
    // Assert we got all pages
    expect($pages)->toHaveCount(5);
});

it('can handle pages with different statuses', function () {
    // Create pages with different statuses
    $draftPage = Page::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'draft',
        'published_at' => null,
    ]);
    
    $pendingPage = Page::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'pending',
        'published_at' => null,
    ]);
    
    $publishedPage = Page::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'published',
        'published_at' => now(),
    ]);
    
    // Retrieve and check each page
    $retrievedDraft = $this->pagesManager->get($draftPage->id);
    $retrievedPending = $this->pagesManager->get($pendingPage->id);
    $retrievedPublished = $this->pagesManager->get($publishedPage->id);
    
    expect($retrievedDraft->status)->toBe('draft');
    expect($retrievedDraft->published_at)->toBeNull();
    
    expect($retrievedPending->status)->toBe('pending');
    expect($retrievedPending->published_at)->toBeNull();
    
    expect($retrievedPublished->status)->toBe('published');
    expect($retrievedPublished->published_at)->not->toBeNull();
});

it('can handle page ordering', function () {
    // Create pages with different orders
    $page1 = Page::factory()->create([
        'user_id' => $this->user->id,
        'order' => 1,
    ]);
    
    $page2 = Page::factory()->create([
        'user_id' => $this->user->id,
        'order' => 2,
    ]);
    
    $page3 = Page::factory()->create([
        'user_id' => $this->user->id,
        'order' => 3,
    ]);
    
    // Update the order of page3
    $updatedPage = $this->pagesManager->update($page3->id, ['order' => 0]);
    
    // Assert the order was updated
    expect($updatedPage->order)->toBe(0);
    
    // Retrieve all pages ordered by 'order'
    $orderedPages = Page::orderBy('order')->get();
    
    // Assert the pages are in the correct order
    expect($orderedPages->first()->id)->toBe($page3->id);
    expect($orderedPages->last()->id)->toBe($page2->id);
});