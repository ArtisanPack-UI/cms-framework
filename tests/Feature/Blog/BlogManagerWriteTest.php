<?php

declare( strict_types=1 );

/**
 * Feature coverage for the BlogManager write API introduced in 2.7.0 (#250).
 *
 * Proves the manager owns the persistence orchestration for `Post` — unique
 * slug allocation, custom-field application timing, published_at transitions,
 * soft-delete, duplicate — so downstream apps stop reinventing these rules
 * per-controller.
 *
 * @since 2.7.0
 */

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
    $this->manager = new BlogManager;
    $this->user    = TestUser::factory()->create();
} );

afterEach( function (): void {
    removeAllFilters( 'ap.contentTypes.registeredCustomFields' );
} );

test( 'autoDraft creates a draft post with a unique slug and stamps the author', function (): void {
    $post = $this->manager->autoDraft( $this->user->id );

    expect( $post->exists )->toBeTrue();
    expect( $post->title )->toBe( 'Untitled post' );
    expect( $post->status )->toBe( ContentStatus::Draft );
    expect( $post->author_id )->toBe( $this->user->id );
    expect( $post->slug )->toBe( 'untitled-post' );
} );

test( 'autoDraft appends a counter when the base slug is already taken', function (): void {
    $this->manager->autoDraft( $this->user->id );
    $second = $this->manager->autoDraft( $this->user->id );
    $third  = $this->manager->autoDraft( $this->user->id );

    expect( $second->slug )->toBe( 'untitled-post-2' );
    expect( $third->slug )->toBe( 'untitled-post-3' );
} );

test( 'uniqueSlug avoids collisions with soft-deleted rows too', function (): void {
    $post = $this->manager->autoDraft( $this->user->id );
    $post->delete();

    expect( $this->manager->uniqueSlug( 'untitled-post' ) )->toBe( 'untitled-post-2' );
} );

test( 'uniqueSlug falls back to `post` when the source is not slugifiable', function (): void {
    expect( $this->manager->uniqueSlug( '!!!' ) )->toBe( 'post' );
} );

test( 'create writes the fillable subset and derives a slug when one is missing', function (): void {
    $post = $this->manager->create( [
        'title'   => 'Hello World',
        'status'  => 'draft',
        'excerpt' => 'A short summary',
    ], null, $this->user->id );

    expect( $post->exists )->toBeTrue();
    expect( $post->title )->toBe( 'Hello World' );
    expect( $post->slug )->toBe( 'hello-world' );
    expect( $post->status )->toBe( ContentStatus::Draft );
    expect( $post->author_id )->toBe( $this->user->id );
    expect( $post->excerpt )->toBe( 'A short summary' );
    expect( $post->published_at )->toBeNull();
} );

test( 'create stamps published_at when the initial status is Published', function (): void {
    $post = $this->manager->create( [
        'title'  => 'Live from day one',
        'status' => ContentStatus::Published,
    ], null, $this->user->id );

    expect( $post->status )->toBe( ContentStatus::Published );
    expect( $post->published_at )->not->toBeNull();
} );

test( 'create applies custom-field values into the metadata column before insert', function (): void {
    // Filter-register a metadata-storage custom field so the
    // HasCustomFields trait's magic setter routes `reading_time` into
    // the JSON column instead of falling through to Eloquent's column
    // path. DB-created fields (via CustomField::create) are always
    // column-mode; only filter-registered fields use metadata storage.
    addFilter( 'ap.contentTypes.registeredCustomFields', function ( array $fields ): array {
        $fields['reading_time'] = [
            'key'           => 'reading_time',
            'name'          => 'Reading Time',
            'type'          => 'text',
            'content_types' => ['posts'],
            'required'      => false,
            'default_value' => null,
            'storage'       => 'metadata',
        ];

        return $fields;
    } );

    $post = $this->manager->create( [
        'title'    => 'With metadata',
        'status'   => ContentStatus::Draft,
        'metadata' => [],
    ], [
        'reading_time' => '4 minutes',
    ], $this->user->id );

    expect( $post->fresh()->metadata )->toBe( ['reading_time' => '4 minutes'] );
} );

test( 'create drops a custom-field value whose key shadows a real posts column', function (): void {
    // #253 — a plugin filter-registers a metadata field keyed to `author_id`,
    // so a request payload carrying `custom_fields[author_id]` would reach
    // `parent::__set()` and hijack the author. The key must be dropped.
    addFilter( 'ap.contentTypes.registeredCustomFields', function ( array $fields ): array {
        $fields['author_id'] = [
            'key'           => 'author_id',
            'name'          => 'Author Id',
            'type'          => 'text',
            'content_types' => ['posts'],
            'required'      => false,
            'default_value' => null,
            'storage'       => 'metadata',
        ];

        return $fields;
    } );

    $post = $this->manager->create( [
        'title'    => 'Hijack attempt',
        'status'   => ContentStatus::Draft,
        'metadata' => [],
    ], [
        'author_id' => 999_999,
    ], $this->user->id );

    expect( $post->fresh()->author_id )->toBe( $this->user->id );
    expect( $post->fresh()->metadata )->toBe( [] );
} );

test( 'update drops a custom-field value whose key shadows a real posts column', function (): void {
    addFilter( 'ap.contentTypes.registeredCustomFields', function ( array $fields ): array {
        $fields['author_id'] = [
            'key'           => 'author_id',
            'name'          => 'Author Id',
            'type'          => 'text',
            'content_types' => ['posts'],
            'required'      => false,
            'default_value' => null,
            'storage'       => 'metadata',
        ];

        return $fields;
    } );

    $post = $this->manager->create( [
        'title'  => 'Owned',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $this->manager->update( $post, ['title' => 'Still owned'], ['author_id' => 999_999] );

    expect( $post->fresh()->author_id )->toBe( $this->user->id );
    expect( $post->fresh()->title )->toBe( 'Still owned' );
} );

test( 'create drops an unregistered custom-field key that names a real posts column', function (): void {
    // No registration at all: the payload keys are request input, so a bare
    // column name must be dropped on the same terms as a registered one.
    $post = $this->manager->create( [
        'title'  => 'No registration needed',
        'status' => ContentStatus::Draft,
    ], [
        'author_id' => 999_999,
    ], $this->user->id );

    expect( $post->fresh()->author_id )->toBe( $this->user->id );
} );

test( 'create rejects attributes outside the fillable + write allowlist', function (): void {
    $post = $this->manager->create( [
        'title'      => 'Guarded',
        'status'     => ContentStatus::Draft,
        'id'         => 999,
        'created_at' => '2024-01-01 00:00:00',
    ], null, $this->user->id );

    expect( $post->id )->not->toBe( 999 );
} );

test( 'update fills the record and preserves untouched attributes', function (): void {
    $post = $this->manager->create( [
        'title'   => 'First',
        'excerpt' => 'Original excerpt',
        'status'  => ContentStatus::Draft,
    ], null, $this->user->id );

    $updated = $this->manager->update( $post, [
        'title'  => 'Second',
        'status' => ContentStatus::Draft,
    ] );

    expect( $updated->title )->toBe( 'Second' );
    expect( $updated->excerpt )->toBe( 'Original excerpt' );
} );

test( 'update stamps published_at exactly once on the first draft -> published transition', function (): void {
    $post = $this->manager->create( [
        'title'  => 'Was Draft',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    expect( $post->published_at )->toBeNull();

    $published  = $this->manager->update( $post, ['status' => ContentStatus::Published] );
    $firstStamp = $published->published_at;

    expect( $firstStamp )->not->toBeNull();

    // Round-trip through Draft and back — the original stamp must not
    // be overwritten on the second publish.
    $this->manager->update( $post, ['status' => ContentStatus::Draft] );
    $republished = $this->manager->update( $post, ['status' => ContentStatus::Published] );

    expect( $republished->published_at->equalTo( $firstStamp ) )->toBeTrue();
} );

test( 'delete soft-deletes the post', function (): void {
    $post = $this->manager->create( [
        'title'  => 'Doomed',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $this->manager->delete( $post );

    expect( Post::find( $post->id ) )->toBeNull();
    expect( Post::withTrashed()->find( $post->id ) )->not->toBeNull();
} );

test( 'duplicate returns a fresh draft with (Copy) title, unique slug, and mirrored taxonomies', function (): void {
    $category = PostCategory::create( ['name' => 'Cat', 'slug' => 'cat'] );
    $tag      = PostTag::create( ['name' => 'Tag', 'slug' => 'tag'] );

    $post = $this->manager->create( [
        'title'  => 'Original',
        'status' => ContentStatus::Published,
    ], null, $this->user->id );

    $this->manager->syncCategories( $post, [ $category->id ] );
    $this->manager->syncTags( $post, [ $tag->id ] );

    $copy = $this->manager->duplicate( $post );

    expect( $copy->id )->not->toBe( $post->id );
    expect( $copy->title )->toBe( 'Original (Copy)' );
    expect( $copy->status )->toBe( ContentStatus::Draft );
    expect( $copy->published_at )->toBeNull();
    expect( $copy->slug )->toBe( 'original-copy' );
    expect( $copy->categories->pluck( 'id' )->all() )->toBe( [ $category->id ] );
    expect( $copy->tags->pluck( 'id' )->all() )->toBe( [ $tag->id ] );
} );

test( 'update leaves the existing slug intact when the caller passes an empty slug', function (): void {
    $post = $this->manager->create( [
        'title' => 'Keep the slug',
        'slug'  => 'keep-the-slug',
    ], null, $this->user->id );

    $updated = $this->manager->update( $post, [
        'title' => 'New title',
        'slug'  => '',
    ] );

    expect( $updated->slug )->toBe( 'keep-the-slug' );
    expect( $updated->title )->toBe( 'New title' );
} );

test( 'syncCategories replaces the category set atomically', function (): void {
    $a = PostCategory::create( ['name' => 'A', 'slug' => 'a'] );
    $b = PostCategory::create( ['name' => 'B', 'slug' => 'b'] );
    $c = PostCategory::create( ['name' => 'C', 'slug' => 'c'] );

    $post = $this->manager->create( [
        'title'  => 'Categorized',
        'status' => ContentStatus::Draft,
    ], null, $this->user->id );

    $this->manager->syncCategories( $post, [ $a->id, $b->id ] );
    expect( $post->categories()->pluck( 'id' )->all() )->toEqualCanonicalizing( [ $a->id, $b->id ] );

    $this->manager->syncCategories( $post, [ $c->id ] );
    expect( $post->categories()->pluck( 'id' )->all() )->toBe( [ $c->id ] );
} );
