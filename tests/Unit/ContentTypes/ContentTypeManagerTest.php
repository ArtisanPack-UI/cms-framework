<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use ArtisanPackUI\Hooks\Facades\Filter;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'register content type adds content type to filter hook', function (): void {
    $manager = new ContentTypeManager;

    $args = [
        'name'        => 'Products',
        'slug'        => 'products',
        'table_name'  => 'products',
        'model_class' => 'App\\Models\\Product',
        'supports'    => ['title', 'editor'],
    ];

    $manager->register( $args );

    $registeredTypes = $manager->getRegisteredContentTypes();

    expect( $registeredTypes )->toHaveKey( 'products' );
    expect( $registeredTypes['products']['name'] )->toBe( 'Products' );
    expect( $registeredTypes['products']['supports'] )->toBe( ['title', 'editor'] );
} );

test( 'get registered content types returns database content types', function (): void {
    $manager = new ContentTypeManager;

    ContentType::create( [
        'name'          => 'Blog Posts',
        'slug'          => 'posts',
        'table_name'    => 'posts',
        'model_class'   => 'App\\Models\\Post',
        'hierarchical'  => false,
        'has_archive'   => true,
        'public'        => true,
        'show_in_admin' => true,
    ] );

    $registeredTypes = $manager->getRegisteredContentTypes();

    expect( $registeredTypes )->toHaveKey( 'posts' );
    expect( $registeredTypes['posts']['name'] )->toBe( 'Blog Posts' );
} );

test( 'get content type returns correct content type by slug', function (): void {
    $manager = new ContentTypeManager;

    ContentType::create( [
        'name'          => 'Pages',
        'slug'          => 'pages',
        'table_name'    => 'pages',
        'model_class'   => 'App\\Models\\Page',
        'hierarchical'  => true,
        'has_archive'   => false,
        'public'        => true,
        'show_in_admin' => true,
    ] );

    $contentType = $manager->getContentType( 'pages' );

    expect( $contentType )->toBeInstanceOf( ContentType::class );
    expect( $contentType->slug )->toBe( 'pages' );
    expect( $contentType->name )->toBe( 'Pages' );
    expect( $contentType->hierarchical )->toBeTrue();
} );

test( 'get content type returns null for non existent content type', function (): void {
    $manager = new ContentTypeManager;

    $contentType = $manager->getContentType( 'non-existent' );

    expect( $contentType )->toBeNull();
} );

test( 'create content type creates new content type in database', function (): void {
    $manager = new ContentTypeManager;

    $data = [
        'name'          => 'Events',
        'slug'          => 'events',
        'table_name'    => 'events',
        'model_class'   => 'App\\Models\\Event',
        'hierarchical'  => false,
        'has_archive'   => true,
        'archive_slug'  => 'events',
        'supports'      => ['title', 'editor', 'excerpt', 'featured_image'],
        'public'        => true,
        'show_in_admin' => true,
        'icon'          => 'fas-calendar',
        'menu_position' => 30,
    ];

    $contentType = $manager->createContentType( $data );

    expect( $contentType )->toBeInstanceOf( ContentType::class );
    expect( $contentType->slug )->toBe( 'events' );
    expect( $contentType->name )->toBe( 'Events' );
    expect( $contentType->has_archive )->toBeTrue();
    expect( $contentType->archive_slug )->toBe( 'events' );
    expect( $contentType->supports )->toBe( ['title', 'editor', 'excerpt', 'featured_image'] );
    expect( $contentType->icon )->toBe( 'fas-calendar' );
    expect( $contentType->menu_position )->toBe( 30 );
    expect( $contentType->exists )->toBeTrue();
} );

test( 'update content type updates existing content type', function (): void {
    $manager = new ContentTypeManager;

    ContentType::create( [
        'name'          => 'Portfolios',
        'slug'          => 'portfolios',
        'table_name'    => 'portfolios',
        'model_class'   => 'App\\Models\\Portfolio',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    $updatedContentType = $manager->updateContentType( 'portfolios', [
        'name' => 'Portfolio Items',
        'icon' => 'fas-briefcase',
    ] );

    expect( $updatedContentType->name )->toBe( 'Portfolio Items' );
    expect( $updatedContentType->icon )->toBe( 'fas-briefcase' );
    expect( $updatedContentType->slug )->toBe( 'portfolios' ); // Should not change
} );

test( 'update content type throws exception for non existent content type', function (): void {
    $manager = new ContentTypeManager;

    expect( fn () => $manager->updateContentType( 'non-existent', ['name' => 'Updated'] ) )
        ->toThrow( Exception::class, 'Content type non-existent not found.' );
} );

test( 'delete content type deletes existing content type', function (): void {
    $manager = new ContentTypeManager;

    ContentType::create( [
        'name'          => 'Testimonials',
        'slug'          => 'testimonials',
        'table_name'    => 'testimonials',
        'model_class'   => 'App\\Models\\Testimonial',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    $result = $manager->deleteContentType( 'testimonials' );

    expect( $result )->toBeTrue();
    expect( ContentType::where( 'slug', 'testimonials' )->exists() )->toBeFalse();
} );

test( 'delete content type returns false for non existent content type', function (): void {
    $manager = new ContentTypeManager;

    $result = $manager->deleteContentType( 'non-existent' );

    expect( $result )->toBeFalse();
} );

test( 'content type exists returns true for existing content type', function (): void {
    $manager = new ContentTypeManager;

    ContentType::create( [
        'name'          => 'FAQs',
        'slug'          => 'faqs',
        'table_name'    => 'faqs',
        'model_class'   => 'App\\Models\\FAQ',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    $exists = $manager->contentTypeExists( 'faqs' );

    expect( $exists )->toBeTrue();
} );

test( 'content type exists returns false for non existent content type', function (): void {
    $manager = new ContentTypeManager;

    $exists = $manager->contentTypeExists( 'non-existent' );

    expect( $exists )->toBeFalse();
} );

test( 'content type manager works with app container', function (): void {
    $manager = app( ContentTypeManager::class );

    $data = [
        'name'          => 'Services',
        'slug'          => 'services',
        'table_name'    => 'services',
        'model_class'   => 'App\\Models\\Service',
        'public'        => true,
        'show_in_admin' => true,
    ];

    $contentType = $manager->createContentType( $data );

    expect( $contentType )->toBeInstanceOf( ContentType::class );
    expect( $contentType->slug )->toBe( 'services' );
} );

test( 'registered content types merges database and filtered content types', function (): void {
    $manager = new ContentTypeManager;

    // Create in database
    ContentType::create( [
        'name'          => 'Posts',
        'slug'          => 'posts',
        'table_name'    => 'posts',
        'model_class'   => 'App\\Models\\Post',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    // Register via filter
    $manager->register( [
        'name'        => 'Custom Type',
        'slug'        => 'custom',
        'table_name'  => 'custom',
        'model_class' => 'App\\Models\\Custom',
    ] );

    $registeredTypes = $manager->getRegisteredContentTypes();

    expect( $registeredTypes )->toHaveKey( 'posts' );
    expect( $registeredTypes )->toHaveKey( 'custom' );
} );

test( 'get content type returns filter-registered content type as unpersisted model', function (): void {
    $manager = new ContentTypeManager;

    $manager->register( [
        'name'        => 'Filter Only',
        'slug'        => 'filter-only',
        'table_name'  => 'filter_only',
        'model_class' => 'App\\Models\\FilterOnly',
        'supports'    => ['title'],
    ] );

    $contentType = $manager->getContentType( 'filter-only' );

    expect( $contentType )->not->toBeNull();
    expect( $contentType )->toBeInstanceOf( ContentType::class );
    expect( $contentType->slug )->toBe( 'filter-only' );
    expect( $contentType->name )->toBe( 'Filter Only' );
    expect( $contentType->exists )->toBeFalse();
    expect( $contentType->supportsFeature( 'title' ) )->toBeTrue();
} );

test( 'get content type prefers database entry over filter entry for the same slug', function (): void {
    $manager = new ContentTypeManager;

    ContentType::create( [
        'name'          => 'DB Version',
        'slug'          => 'shared',
        'table_name'    => 'shared',
        'model_class'   => 'App\\Models\\Shared',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    $manager->register( [
        'name'        => 'Filter Version',
        'slug'        => 'shared',
        'table_name'  => 'shared',
        'model_class' => 'App\\Models\\SharedFilter',
    ] );

    $contentType = $manager->getContentType( 'shared' );

    expect( $contentType->name )->toBe( 'DB Version' );
    expect( $contentType->exists )->toBeTrue();
} );

test( 'updateContentType refuses filter-only slugs (no phantom INSERT)', function (): void {
    $manager = new ContentTypeManager;

    $manager->register( [
        'name'        => 'Filter Only',
        'slug'        => 'filter-only-update',
        'table_name'  => 'filter_only_update',
        'model_class' => 'App\\Models\\FilterOnly',
    ] );

    expect( fn () => $manager->updateContentType( 'filter-only-update', ['name' => 'Renamed'] ) )
        ->toThrow( Exception::class, 'not found' );

    expect( ContentType::where( 'slug', 'filter-only-update' )->exists() )->toBeFalse();
} );

test( 'deleteContentType returns false for filter-only slugs and never no-op-deletes', function (): void {
    $manager = new ContentTypeManager;

    $manager->register( [
        'name'        => 'Filter Only',
        'slug'        => 'filter-only-delete',
        'table_name'  => 'filter_only_delete',
        'model_class' => 'App\\Models\\FilterOnly',
    ] );

    expect( $manager->deleteContentType( 'filter-only-delete' ) )->toBeFalse();
} );

test( 'filter-hydrated content type strips persistence-critical keys', function (): void {
    $manager = new ContentTypeManager;

    $manager->register( [
        'id'          => 999,
        'created_at'  => '2020-01-01 00:00:00',
        'name'        => 'Sneaky',
        'slug'        => 'sneaky',
        'table_name'  => 'sneaky',
        'model_class' => 'App\\Models\\Sneaky',
    ] );

    $contentType = $manager->getContentType( 'sneaky' );

    expect( $contentType->id )->toBeNull();
    expect( $contentType->created_at )->toBeNull();
    expect( $contentType->slug )->toBe( 'sneaky' );
} );

test( 'getPersistedContentType never returns filter-hydrated entries', function (): void {
    $manager = new ContentTypeManager;

    $manager->register( [
        'name'        => 'Filter Only',
        'slug'        => 'persist-check',
        'table_name'  => 'persist_check',
        'model_class' => 'App\\Models\\PersistCheck',
    ] );

    expect( $manager->getPersistedContentType( 'persist-check' ) )->toBeNull();
} );

test( 'content type exists returns true for filter-registered content types', function (): void {
    $manager = new ContentTypeManager;

    $manager->register( [
        'name'        => 'Filter Only',
        'slug'        => 'filter-existence',
        'table_name'  => 'filter_existence',
        'model_class' => 'App\\Models\\FilterExistence',
    ] );

    expect( $manager->contentTypeExists( 'filter-existence' ) )->toBeTrue();
    expect( $manager->contentTypeExists( 'nonexistent' ) )->toBeFalse();
} );
