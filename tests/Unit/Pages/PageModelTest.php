<?php

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageCategory;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageTag;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
});

test('page can be created with required attributes', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title' => 'About Us',
        'slug' => 'about-us',
        'content' => 'About us content',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    expect($page)->toBeInstanceOf(Page::class);
    expect($page->title)->toBe('About Us');
    expect($page->slug)->toBe('about-us');
    expect($page->order)->toBe(1);
});

test('page has parent child relationship', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $parentPage = Page::create([
        'title' => 'Parent Page',
        'slug' => 'parent',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $childPage = Page::create([
        'title' => 'Child Page',
        'slug' => 'child',
        'author_id' => $user->id,
        'parent_id' => $parentPage->id,
        'status' => 'published',
        'order' => 1,
    ]);

    expect($childPage->parent)->toBeInstanceOf(Page::class);
    expect($childPage->parent->id)->toBe($parentPage->id);
    expect($parentPage->children)->toHaveCount(1);
    expect($parentPage->children->first()->id)->toBe($childPage->id);
});

test('page children are ordered by order column', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $parent = Page::create([
        'title' => 'Parent',
        'slug' => 'parent',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Child 2',
        'slug' => 'child-2',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 2,
    ]);

    Page::create([
        'title' => 'Child 1',
        'slug' => 'child-1',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $children = $parent->children;

    expect($children)->toHaveCount(2);
    expect($children->first()->title)->toBe('Child 1');
    expect($children->last()->title)->toBe('Child 2');
});

test('page ancestors returns all parent pages', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $grandparent = Page::create([
        'title' => 'Grandparent',
        'slug' => 'grandparent',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $parent = Page::create([
        'title' => 'Parent',
        'slug' => 'parent',
        'author_id' => $user->id,
        'parent_id' => $grandparent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $child = Page::create([
        'title' => 'Child',
        'slug' => 'child',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $ancestors = $child->ancestors();

    expect($ancestors)->toHaveCount(2);
    expect($ancestors->first()->title)->toBe('Grandparent');
    expect($ancestors->last()->title)->toBe('Parent');
});

test('page descendants returns all child pages recursively', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $parent = Page::create([
        'title' => 'Parent',
        'slug' => 'parent',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $child = Page::create([
        'title' => 'Child',
        'slug' => 'child',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $grandchild = Page::create([
        'title' => 'Grandchild',
        'slug' => 'grandchild',
        'author_id' => $user->id,
        'parent_id' => $child->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $descendants = $parent->descendants();

    expect($descendants)->toHaveCount(2);
    expect($descendants->pluck('title')->toArray())->toContain('Child');
    expect($descendants->pluck('title')->toArray())->toContain('Grandchild');
});

test('page siblings returns pages with same parent', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $parent = Page::create([
        'title' => 'Parent',
        'slug' => 'parent',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $child1 = Page::create([
        'title' => 'Child 1',
        'slug' => 'child-1',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Child 2',
        'slug' => 'child-2',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 2,
    ]);

    $siblings = $child1->siblings;

    expect($siblings)->toHaveCount(1);
    expect($siblings->first()->title)->toBe('Child 2');
});

test('breadcrumb attribute generates correct trail', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $grandparent = Page::create([
        'title' => 'Company',
        'slug' => 'company',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $parent = Page::create([
        'title' => 'About',
        'slug' => 'about',
        'author_id' => $user->id,
        'parent_id' => $grandparent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $child = Page::create([
        'title' => 'Team',
        'slug' => 'team',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $breadcrumb = $child->breadcrumb;

    expect($breadcrumb)->toHaveCount(3);
    expect($breadcrumb[0]['title'])->toBe('Company');
    expect($breadcrumb[1]['title'])->toBe('About');
    expect($breadcrumb[2]['title'])->toBe('Team');
});

test('depth attribute returns correct hierarchy level', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $level0 = Page::create([
        'title' => 'Level 0',
        'slug' => 'level-0',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $level1 = Page::create([
        'title' => 'Level 1',
        'slug' => 'level-1',
        'author_id' => $user->id,
        'parent_id' => $level0->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $level2 = Page::create([
        'title' => 'Level 2',
        'slug' => 'level-2',
        'author_id' => $user->id,
        'parent_id' => $level1->id,
        'status' => 'published',
        'order' => 1,
    ]);

    expect($level0->depth)->toBe(0);
    expect($level1->depth)->toBe(1);
    expect($level2->depth)->toBe(2);
});

test('permalink attribute generates hierarchical url', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $parent = Page::create([
        'title' => 'Services',
        'slug' => 'services',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $child = Page::create([
        'title' => 'Web Development',
        'slug' => 'web-development',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    expect($parent->permalink)->toContain('/services');
    expect($child->permalink)->toContain('/services/web-development');
});

test('published scope returns only published pages', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    Page::create([
        'title' => 'Published Page',
        'slug' => 'published',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Draft Page',
        'slug' => 'draft',
        'author_id' => $user->id,
        'status' => 'draft',
        'order' => 2,
    ]);

    $publishedPages = Page::published()->get();

    expect($publishedPages)->toHaveCount(1);
    expect($publishedPages->first()->title)->toBe('Published Page');
});

test('draft scope returns only draft pages', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    Page::create([
        'title' => 'Published Page',
        'slug' => 'published',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Draft Page',
        'slug' => 'draft',
        'author_id' => $user->id,
        'status' => 'draft',
        'order' => 2,
    ]);

    $draftPages = Page::draft()->get();

    expect($draftPages)->toHaveCount(1);
    expect($draftPages->first()->title)->toBe('Draft Page');
});

test('by author scope filters pages by author', function () {
    $author1 = TestUser::create([
        'name' => 'Author 1',
        'email' => 'author1@example.com',
        'password' => 'password',
    ]);

    $author2 = TestUser::create([
        'name' => 'Author 2',
        'email' => 'author2@example.com',
        'password' => 'password',
    ]);

    Page::create([
        'title' => 'Page by Author 1',
        'slug' => 'page-1',
        'author_id' => $author1->id,
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Page by Author 2',
        'slug' => 'page-2',
        'author_id' => $author2->id,
        'status' => 'published',
        'order' => 2,
    ]);

    $author1Pages = Page::byAuthor($author1->id)->get();

    expect($author1Pages)->toHaveCount(1);
    expect($author1Pages->first()->title)->toBe('Page by Author 1');
});

test('top level scope returns pages without parents', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $parent = Page::create([
        'title' => 'Top Level Page',
        'slug' => 'top-level',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Child Page',
        'slug' => 'child',
        'author_id' => $user->id,
        'parent_id' => $parent->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $topLevelPages = Page::topLevel()->get();

    expect($topLevelPages)->toHaveCount(1);
    expect($topLevelPages->first()->title)->toBe('Top Level Page');
});

test('by template scope filters pages by template', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    Page::create([
        'title' => 'Landing Page',
        'slug' => 'landing',
        'author_id' => $user->id,
        'template' => 'landing',
        'status' => 'published',
        'order' => 1,
    ]);

    Page::create([
        'title' => 'Contact Page',
        'slug' => 'contact',
        'author_id' => $user->id,
        'template' => 'contact',
        'status' => 'published',
        'order' => 2,
    ]);

    $landingPages = Page::byTemplate('landing')->get();

    expect($landingPages)->toHaveCount(1);
    expect($landingPages->first()->title)->toBe('Landing Page');
});

test('is published method returns true for published pages', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title' => 'Published Page',
        'slug' => 'published',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    expect($page->isPublished())->toBeTrue();
});

test('is published method returns false for draft pages', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title' => 'Draft Page',
        'slug' => 'draft',
        'author_id' => $user->id,
        'status' => 'draft',
        'order' => 1,
    ]);

    expect($page->isPublished())->toBeFalse();
});

test('page has categories relationship', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $category = PageCategory::create([
        'name' => 'Documentation',
        'slug' => 'documentation',
    ]);

    $page->categories()->attach($category->id);

    expect($page->categories)->toHaveCount(1);
    expect($page->categories->first()->name)->toBe('Documentation');
});

test('page has tags relationship', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $tag = PageTag::create([
        'name' => 'Important',
        'slug' => 'important',
    ]);

    $page->tags()->attach($tag->id);

    expect($page->tags)->toHaveCount(1);
    expect($page->tags->first()->name)->toBe('Important');
});

test('page uses soft deletes', function () {
    $user = TestUser::create([
        'name' => 'Test Author',
        'email' => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'author_id' => $user->id,
        'status' => 'published',
        'order' => 1,
    ]);

    $pageId = $page->id;

    $page->delete();

    expect(Page::find($pageId))->toBeNull();
    expect(Page::withTrashed()->find($pageId))->not->toBeNull();
});
