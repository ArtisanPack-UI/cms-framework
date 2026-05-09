<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

test('page block_content round-trips an array through the cast', function (): void {
    $user = TestUser::create([
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ]);

    $blocks = [
        [
            'blockName' => 'core/heading',
            'attrs'     => ['level' => 1],
            'innerHTML' => '<h1>Hello</h1>',
        ],
        [
            'blockName' => 'core/paragraph',
            'attrs'     => [],
            'innerHTML' => '<p>World</p>',
        ],
    ];

    $page = Page::create([
        'title'     => 'Block Page',
        'slug'      => 'block-page',
        'author_id' => $user->id,
        'status'    => 'draft',
        'order'     => 0,
    ]);

    $page->setBlockContent($blocks);
    $page->save();

    $fresh = Page::find($page->id);

    expect($fresh->block_content)->toBe($blocks);
});

test('page block_content is excluded from mass assignment', function (): void {
    $user = TestUser::create([
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title'         => 'Mass Assign Page',
        'slug'          => 'mass-assign-page',
        'author_id'     => $user->id,
        'status'        => 'draft',
        'order'         => 0,
        'block_content' => [
            ['blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>nope</p>'],
        ],
    ]);

    expect($page->block_content)->toBeNull();
    expect($page->fresh()->block_content)->toBeNull();
});

test('page exposes block_content via HasBlockContent helpers', function (): void {
    $user = TestUser::create([
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title'     => 'Helpers Page',
        'slug'      => 'helpers-page',
        'author_id' => $user->id,
        'status'    => 'draft',
        'order'     => 0,
    ]);

    expect($page->getBlockContent())->toBe([]);
    expect($page->getBlockContentColumn())->toBe('block_content');

    $blocks = [
        ['blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>Hi</p>'],
    ];

    $page->setBlockContent($blocks);
    $page->save();

    $fresh = Page::find($page->id);

    expect($fresh->getBlockContent())->toBe($blocks);
});

test('page block_content is nullable and defaults to null', function (): void {
    $user = TestUser::create([
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ]);

    $page = Page::create([
        'title'     => 'No Blocks Page',
        'slug'      => 'no-blocks-page',
        'author_id' => $user->id,
        'status'    => 'draft',
        'order'     => 0,
    ]);

    expect($page->block_content)->toBeNull();
});
