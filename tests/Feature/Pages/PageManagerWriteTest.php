<?php

declare( strict_types=1 );

/**
 * Feature coverage for the PageManager write API introduced in 2.7.0 (#250).
 *
 * Mirrors {@see Tests\Feature\Blog\BlogManagerWriteTest} against Page, plus
 * coverage for the hierarchical parent_id / order / template attributes that
 * only Page carries.
 *
 * @since 2.7.0
 */

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageCategory;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageTag;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
    $this->manager = new PageManager;
    $this->user    = TestUser::factory()->create();
} );

afterEach( function (): void {
    removeAllFilters( 'ap.contentTypes.registeredCustomFields' );
} );

test( 'autoDraft creates a draft page with a unique slug and stamps the author', function (): void {
    $page = $this->manager->autoDraft( $this->user->id );

    expect( $page->exists )->toBeTrue();
    expect( $page->title )->toBe( 'Untitled page' );
    expect( $page->status )->toBe( ContentStatus::Draft );
    expect( $page->author_id )->toBe( $this->user->id );
    expect( $page->slug )->toBe( 'untitled-page' );
    expect( $page->order )->toBe( 0 );
} );

test( 'autoDraft appends a counter when the base slug is already taken', function (): void {
    $this->manager->autoDraft( $this->user->id );
    $second = $this->manager->autoDraft( $this->user->id );

    expect( $second->slug )->toBe( 'untitled-page-2' );
} );

test( 'uniqueSlug avoids collisions with soft-deleted rows too', function (): void {
    $page = $this->manager->autoDraft( $this->user->id );
    $page->delete();

    expect( $this->manager->uniqueSlug( 'untitled-page' ) )->toBe( 'untitled-page-2' );
} );

test( 'uniqueSlug falls back to `page` when the source is not slugifiable', function (): void {
    expect( $this->manager->uniqueSlug( '!!!' ) )->toBe( 'page' );
} );

test( 'create writes the fillable subset and derives a slug when one is missing', function (): void {
    $page = $this->manager->create( [
        'title'   => 'About us',
        'status'  => 'draft',
        'excerpt' => 'The about page',
    ], null, $this->user->id );

    expect( $page->exists )->toBeTrue();
    expect( $page->slug )->toBe( 'about-us' );
    expect( $page->status )->toBe( ContentStatus::Draft );
    expect( $page->author_id )->toBe( $this->user->id );
} );

test( 'create stamps published_at when the initial status is Published', function (): void {
    $page = $this->manager->create( [
        'title'  => 'Launch page',
        'status' => ContentStatus::Published,
    ], null, $this->user->id );

    expect( $page->status )->toBe( ContentStatus::Published );
    expect( $page->published_at )->not->toBeNull();
} );

test( 'create writes hierarchical attributes (parent_id, order, template)', function (): void {
    $parent = $this->manager->create( [
        'title'  => 'Parent',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $child = $this->manager->create( [
        'title'     => 'Child',
        'status'    => ContentStatus::Draft,
        'parent_id' => $parent->id,
        'order'     => 5,
        'template'  => 'sidebar',
    ], null, $this->user->id );

    expect( $child->parent_id )->toBe( $parent->id );
    expect( $child->order )->toBe( 5 );
    expect( $child->template )->toBe( 'sidebar' );
} );

test( 'create applies custom-field values into the metadata column before insert', function (): void {
    addFilter( 'ap.contentTypes.registeredCustomFields', function ( array $fields ): array {
        $fields['landing_cta'] = [
            'key'           => 'landing_cta',
            'name'          => 'Landing CTA',
            'type'          => 'text',
            'content_types' => ['pages'],
            'required'      => false,
            'default_value' => null,
            'storage'       => 'metadata',
        ];

        return $fields;
    } );

    $page = $this->manager->create( [
        'title'    => 'Landing',
        'status'   => ContentStatus::Draft,
        'metadata' => [],
    ], [
        'landing_cta' => 'Sign up now',
    ], $this->user->id );

    expect( $page->fresh()->metadata )->toBe( ['landing_cta' => 'Sign up now'] );
} );

test( 'update stamps published_at exactly once on the first draft -> published transition', function (): void {
    $page = $this->manager->create( [
        'title'  => 'Was Draft',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $published  = $this->manager->update( $page, ['status' => ContentStatus::Published] );
    $firstStamp = $published->published_at;

    expect( $firstStamp )->not->toBeNull();

    $this->manager->update( $page, ['status' => ContentStatus::Draft] );
    $republished = $this->manager->update( $page, ['status' => ContentStatus::Published] );

    expect( $republished->published_at->equalTo( $firstStamp ) )->toBeTrue();
} );

test( 'update can move a page into a new hierarchy position', function (): void {
    $parent = $this->manager->create( [
        'title'  => 'Section A',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $page = $this->manager->create( [
        'title'  => 'Loose page',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $moved = $this->manager->update( $page, [
        'parent_id' => $parent->id,
        'order'     => 3,
    ] );

    expect( $moved->parent_id )->toBe( $parent->id );
    expect( $moved->order )->toBe( 3 );
} );

test( 'update leaves the existing slug intact when the caller passes an empty slug', function (): void {
    $page = $this->manager->create( [
        'title' => 'Keep me',
        'slug'  => 'keep-me',
    ], null, $this->user->id );

    $updated = $this->manager->update( $page, [
        'title' => 'Renamed',
        'slug'  => '',
    ] );

    expect( $updated->slug )->toBe( 'keep-me' );
} );

test( 'update rejects reparenting a page to itself', function (): void {
    $page = $this->manager->create( [
        'title' => 'Self',
    ], null, $this->user->id );

    expect( fn () => $this->manager->update( $page, ['parent_id' => $page->id] ) )
        ->toThrow( InvalidArgumentException::class, 'itself' );
} );

test( 'update rejects reparenting a page to one of its own descendants', function (): void {
    $parent = $this->manager->create( ['title' => 'Parent'], null, $this->user->id );
    $child  = $this->manager->create( [
        'title'     => 'Child',
        'parent_id' => $parent->id,
    ], null, $this->user->id );

    expect( fn () => $this->manager->update( $parent, ['parent_id' => $child->id] ) )
        ->toThrow( InvalidArgumentException::class, 'descendant' );
} );

test( 'delete soft-deletes the page', function (): void {
    $page = $this->manager->create( [
        'title'  => 'Doomed',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $this->manager->delete( $page );

    expect( Page::find( $page->id ) )->toBeNull();
    expect( Page::withTrashed()->find( $page->id ) )->not->toBeNull();
} );

test( 'duplicate returns a fresh draft with (Copy) title and unique slug', function (): void {
    $page = $this->manager->create( [
        'title'    => 'Original',
        'status'   => ContentStatus::Published,
        'template' => 'sidebar',
    ], null, $this->user->id );

    $copy = $this->manager->duplicate( $page );

    expect( $copy->id )->not->toBe( $page->id );
    expect( $copy->title )->toBe( 'Original (Copy)' );
    expect( $copy->status )->toBe( ContentStatus::Draft );
    expect( $copy->published_at )->toBeNull();
    expect( $copy->slug )->toBe( 'original-copy' );
    // Copies preserve template but reset status to Draft.
    expect( $copy->template )->toBe( 'sidebar' );
} );

test( 'duplicate mirrors the source page category and tag associations', function (): void {
    $category = PageCategory::create( ['name' => 'Docs', 'slug' => 'docs'] );
    $tag      = PageTag::create( ['name' => 'V2', 'slug' => 'v2'] );

    $page = $this->manager->create( [
        'title'  => 'Original',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $page->categories()->sync( [ $category->id ] );
    $page->tags()->sync( [ $tag->id ] );

    $copy = $this->manager->duplicate( $page );

    expect( $copy->categories->pluck( 'id' )->all() )->toBe( [ $category->id ] );
    expect( $copy->tags->pluck( 'id' )->all() )->toBe( [ $tag->id ] );
} );
