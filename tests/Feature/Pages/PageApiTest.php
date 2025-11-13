<?php

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);
});

test('can list pages', function () {
    $this->user->shouldReceive('can')
        ->with('pages.viewAny')
        ->andReturn(true);

    Page::create([
        'title' => 'About Us',
        'slug' => 'about-us',
        'author_id' => $this->user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/pages');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['title', 'slug', 'status', 'author_id', 'order'],
        ],
    ]);
});

test('can create page', function () {
    $this->user->shouldReceive('can')
        ->with('pages.create')
        ->andReturn(true);

    $data = [
        'title' => 'Contact',
        'slug' => 'contact',
        'content' => 'Contact page content',
        'author_id' => $this->user->id,
        'status' => 'published',
        'order' => 1,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/pages', $data);

    $response->assertCreated();
    $response->assertJsonFragment(['slug' => 'contact']);

    expect(Page::where('slug', 'contact')->exists())->toBeTrue();
});

test('can set parent page', function () {
    $this->user->shouldReceive('can')
        ->with('pages.create')
        ->andReturn(true);

    $parent = Page::create([
        'title' => 'Services',
        'slug' => 'services',
        'author_id' => $this->user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $data = [
        'title' => 'Web Development',
        'slug' => 'web-development',
        'author_id' => $this->user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/v1/pages', $data);

    $response->assertCreated();

    $child = Page::where('slug', 'web-development')->first();
    expect($child->parent_id)->toBe($parent->id);
    expect($child->parent->title)->toBe('Services');
});

test('can reorder pages', function () {
    $this->user->shouldReceive('can')
        ->with('pages.edit')
        ->andReturn(true);

    $page1 = Page::create([
        'title' => 'Page 1',
        'slug' => 'page-1',
        'author_id' => $this->user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $page2 = Page::create([
        'title' => 'Page 2',
        'slug' => 'page-2',
        'author_id' => $this->user->id,
        'status' => 'published',
        'order' => 2,
    ]);

    // Reorder pages
    $response = $this->actingAs($this->user)->postJson('/api/v1/pages/reorder', [
        'pages' => [
            ['id' => $page2->id, 'order' => 1],
            ['id' => $page1->id, 'order' => 2],
        ],
    ]);

    $response->assertSuccessful();

    $page1->refresh();
    $page2->refresh();

    expect($page1->order)->toBe(2);
    expect($page2->order)->toBe(1);
});

test('can update page', function () {
    $this->user->shouldReceive('can')
        ->with('pages.edit')
        ->andReturn(true);

    $page = Page::create([
        'title' => 'Original Title',
        'slug' => 'original-title',
        'author_id' => $this->user->id,
        'status' => 'draft',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->putJson('/api/v1/pages/'.$page->id, [
        'title' => 'Updated Title',
        'status' => 'published',
    ]);

    $response->assertSuccessful();

    $page->refresh();
    expect($page->title)->toBe('Updated Title');
    expect($page->status)->toBe('published');
});

test('can delete page', function () {
    $this->user->shouldReceive('can')
        ->with('pages.delete')
        ->andReturn(true);

    $page = Page::create([
        'title' => 'Page to Delete',
        'slug' => 'page-to-delete',
        'author_id' => $this->user->id,
        'status' => 'draft',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->deleteJson('/api/v1/pages/'.$page->id);

    $response->assertNoContent();
    expect(Page::find($page->id))->toBeNull();
});

test('can move page in hierarchy', function () {
    $this->user->shouldReceive('can')
        ->with('pages.edit')
        ->andReturn(true);

    $parent1 = Page::create([
        'title' => 'Parent 1',
        'slug' => 'parent-1',
        'author_id' => $this->user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $parent2 = Page::create([
        'title' => 'Parent 2',
        'slug' => 'parent-2',
        'author_id' => $this->user->id,
        'status' => 'published',
        'order' => 2,
    ]);

    $child = Page::create([
        'title' => 'Child',
        'slug' => 'child',
        'author_id' => $this->user->id,
        'parent_id' => $parent1->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->putJson('/api/v1/pages/'.$child->id, [
        'parent_id' => $parent2->id,
    ]);

    $response->assertSuccessful();

    $child->refresh();
    expect($child->parent_id)->toBe($parent2->id);
});

test('unauthorized user cannot create page', function () {
    $this->user->shouldReceive('can')
        ->with('pages.create')
        ->andReturn(false);

    $data = [
        'title' => 'Unauthorized Page',
        'slug' => 'unauthorized-page',
        'author_id' => $this->user->id,
        'status' => 'draft',
        'order' => 1,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/pages', $data);

    $response->assertForbidden();
});

test('unauthorized user cannot update page', function () {
    $this->user->shouldReceive('can')
        ->with('pages.edit')
        ->andReturn(false);

    $page = Page::create([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'author_id' => $this->user->id,
        'status' => 'draft',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->putJson('/api/v1/pages/'.$page->id, ['title' => 'Updated']);

    $response->assertForbidden();
});

test('unauthorized user cannot delete page', function () {
    $this->user->shouldReceive('can')
        ->with('pages.delete')
        ->andReturn(false);

    $page = Page::create([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'author_id' => $this->user->id,
        'status' => 'draft',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->deleteJson('/api/v1/pages/'.$page->id);

    $response->assertForbidden();
});

test('can filter pages by template', function () {
    $this->user->shouldReceive('can')
        ->with('pages.viewAny')
        ->andReturn(true);

    Page::create([
        'title' => 'Landing Page',
        'slug' => 'landing',
        'author_id' => $this->user->id,
        'template' => 'landing',
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Contact Page',
        'slug' => 'contact',
        'author_id' => $this->user->id,
        'template' => 'contact',
        'status' => 'published',
        'order' => 2,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/pages?template=landing');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['slug' => 'landing']);
});

test('can get top level pages only', function () {
    $this->user->shouldReceive('can')
        ->with('pages.viewAny')
        ->andReturn(true);

    $parent = Page::create([
        'title' => 'Parent',
        'slug' => 'parent',
        'author_id' => $this->user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Child',
        'slug' => 'child',
        'author_id' => $this->user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/pages?top_level=true');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['slug' => 'parent']);
});
