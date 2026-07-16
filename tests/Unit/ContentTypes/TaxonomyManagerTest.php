<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\TaxonomyManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Taxonomy;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'get taxonomy returns filter-registered taxonomy as unpersisted model', function (): void {
    $manager = new TaxonomyManager;

    $manager->registerTaxonomy( [
        'name'              => 'Filter Category',
        'slug'              => 'filter-category',
        'content_type_slug' => 'posts',
        'hierarchical'      => true,
        'show_in_admin'     => true,
    ] );

    $taxonomy = $manager->getTaxonomy( 'filter-category' );

    expect( $taxonomy )->not->toBeNull();
    expect( $taxonomy )->toBeInstanceOf( Taxonomy::class );
    expect( $taxonomy->slug )->toBe( 'filter-category' );
    expect( $taxonomy->name )->toBe( 'Filter Category' );
    expect( $taxonomy->content_type_slug )->toBe( 'posts' );
    expect( $taxonomy->exists )->toBeFalse();
} );

test( 'get taxonomy prefers database entry over filter entry', function (): void {
    $manager = new TaxonomyManager;

    Taxonomy::create( [
        'name'              => 'DB Tag',
        'slug'              => 'shared-tag',
        'content_type_slug' => 'posts',
        'hierarchical'      => false,
        'show_in_admin'     => true,
    ] );

    $manager->registerTaxonomy( [
        'name'              => 'Filter Tag',
        'slug'              => 'shared-tag',
        'content_type_slug' => 'posts',
    ] );

    $taxonomy = $manager->getTaxonomy( 'shared-tag' );

    expect( $taxonomy->name )->toBe( 'DB Tag' );
    expect( $taxonomy->exists )->toBeTrue();
} );

test( 'taxonomy exists returns true for filter-registered taxonomies', function (): void {
    $manager = new TaxonomyManager;

    $manager->registerTaxonomy( [
        'name'              => 'Filter Series',
        'slug'              => 'filter-series',
        'content_type_slug' => 'posts',
    ] );

    expect( $manager->taxonomyExists( 'filter-series' ) )->toBeTrue();
    expect( $manager->taxonomyExists( 'nonexistent-taxonomy' ) )->toBeFalse();
} );

test( 'get taxonomies for content type merges DB and filter entries', function (): void {
    $manager = new TaxonomyManager;

    Taxonomy::create( [
        'name'              => 'DB Category',
        'slug'              => 'db-category',
        'content_type_slug' => 'posts',
        'hierarchical'      => true,
        'show_in_admin'     => true,
    ] );

    $manager->registerTaxonomy( [
        'name'              => 'Filter Category',
        'slug'              => 'filter-category',
        'content_type_slug' => 'posts',
    ] );

    $manager->registerTaxonomy( [
        'name'              => 'Wrong Content Type',
        'slug'              => 'other',
        'content_type_slug' => 'pages',
    ] );

    $taxonomies = $manager->getTaxonomiesForContentType( 'posts' );

    $slugs = $taxonomies->pluck( 'slug' )->all();
    expect( $slugs )->toContain( 'db-category' );
    expect( $slugs )->toContain( 'filter-category' );
    expect( $slugs )->not->toContain( 'other' );
} );

test( 'get taxonomies for content type prefers DB entry when slugs collide', function (): void {
    $manager = new TaxonomyManager;

    Taxonomy::create( [
        'name'              => 'DB Version',
        'slug'              => 'collision',
        'content_type_slug' => 'posts',
        'hierarchical'      => true,
        'show_in_admin'     => true,
    ] );

    $manager->registerTaxonomy( [
        'name'              => 'Filter Version',
        'slug'              => 'collision',
        'content_type_slug' => 'posts',
    ] );

    $taxonomies = $manager->getTaxonomiesForContentType( 'posts' );

    $collision = $taxonomies->firstWhere( 'slug', 'collision' );
    expect( $collision )->not->toBeNull();
    expect( $collision->name )->toBe( 'DB Version' );
    expect( $collision->exists )->toBeTrue();
} );

test( 'updateTaxonomy refuses filter-only slugs (no phantom INSERT)', function (): void {
    $manager = new TaxonomyManager;

    $manager->registerTaxonomy( [
        'name'              => 'Filter Only',
        'slug'              => 'filter-only-tag',
        'content_type_slug' => 'posts',
    ] );

    expect( fn () => $manager->updateTaxonomy( 'filter-only-tag', ['name' => 'Renamed'] ) )
        ->toThrow( Exception::class, 'not found' );

    expect( Taxonomy::where( 'slug', 'filter-only-tag' )->exists() )->toBeFalse();
} );

test( 'deleteTaxonomy returns false for filter-only slugs', function (): void {
    $manager = new TaxonomyManager;

    $manager->registerTaxonomy( [
        'name'              => 'Filter Only',
        'slug'              => 'filter-only-delete',
        'content_type_slug' => 'posts',
    ] );

    expect( $manager->deleteTaxonomy( 'filter-only-delete' ) )->toBeFalse();
} );

test( 'filter-hydrated taxonomy strips persistence-critical keys', function (): void {
    $manager = new TaxonomyManager;

    $manager->registerTaxonomy( [
        'id'                => 999,
        'created_at'        => '2020-01-01 00:00:00',
        'name'              => 'Sneaky',
        'slug'              => 'sneaky-tag',
        'content_type_slug' => 'posts',
    ] );

    $taxonomy = $manager->getTaxonomy( 'sneaky-tag' );

    expect( $taxonomy->id )->toBeNull();
    expect( $taxonomy->created_at )->toBeNull();
} );

test( 'getPersistedTaxonomy never returns filter-hydrated entries', function (): void {
    $manager = new TaxonomyManager;

    $manager->registerTaxonomy( [
        'name'              => 'Filter Only',
        'slug'              => 'persist-tag',
        'content_type_slug' => 'posts',
    ] );

    expect( $manager->getPersistedTaxonomy( 'persist-tag' ) )->toBeNull();
} );
