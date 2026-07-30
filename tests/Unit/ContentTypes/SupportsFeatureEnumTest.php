<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\SupportsFeature;

test( 'supports feature enum lists the canonical flag set', function (): void {
    $values = SupportsFeature::values();

    expect( $values )->toContain( 'title', 'editor', 'excerpt', 'featured_image' );
    expect( $values )->toContain( 'categories', 'tags', 'custom_fields', 'seo' );
    expect( $values )->toContain( 'author', 'page_attributes', 'revisions', 'templates' );
    expect( $values )->toHaveCount( 12 );
} );

test( 'supports feature enum can be created from string values', function (): void {
    expect( SupportsFeature::from( 'editor' ) )->toBe( SupportsFeature::Editor );
    expect( SupportsFeature::from( 'page_attributes' ) )->toBe( SupportsFeature::PageAttributes );
} );

test( 'supports feature enum tryFrom returns null for invalid value', function (): void {
    expect( SupportsFeature::tryFrom( 'content' ) )->toBeNull();
    expect( SupportsFeature::tryFrom( 'nonsense' ) )->toBeNull();
} );

test( 'supports feature filter drops unknown flags', function (): void {
    $filtered = SupportsFeature::filter( ['editor', 'content', 'seo', 'nonsense-flag', 42, null] );

    expect( $filtered )->toBe( ['editor', 'seo'] );
} );

test( 'supports feature filter preserves order and does not deduplicate', function (): void {
    $filtered = SupportsFeature::filter( ['seo', 'editor', 'seo'] );

    expect( $filtered )->toBe( ['seo', 'editor', 'seo'] );
} );
