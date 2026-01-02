<?php

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
        'supports'    => ['title', 'content'],
    ];

    $manager->register( $args );

    $registeredTypes = $manager->getRegisteredContentTypes();

    expect( $registeredTypes )->toHaveKey( 'products' );
    expect( $registeredTypes['products']['name'] )->toBe( 'Products' );
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
        'supports'      => ['title', 'content', 'excerpt', 'featured_image'],
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
    expect( $contentType->supports )->toBe( ['title', 'content', 'excerpt', 'featured_image'] );
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
    ]);

    $registeredTypes = $manager->getRegisteredContentTypes();

    expect( $registeredTypes)->toHaveKey( 'posts');
    expect( $registeredTypes)->toHaveKey( 'custom');
});
