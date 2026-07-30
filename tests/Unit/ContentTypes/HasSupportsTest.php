<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\SupportsFeature;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasSupports;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;

test( 'post model uses HasSupports trait', function (): void {
    expect( in_array( HasSupports::class, class_uses_recursive( Post::class ) ) )->toBeTrue();
} );

test( 'page model uses HasSupports trait', function (): void {
    expect( in_array( HasSupports::class, class_uses_recursive( Page::class ) ) )->toBeTrue();
} );

test( 'content type model uses HasSupports trait', function (): void {
    expect( in_array( HasSupports::class, class_uses_recursive( ContentType::class ) ) )->toBeTrue();
} );

test( 'post default supports declares editor + blog panels', function (): void {
    $supports = ( new Post )->supports();

    expect( $supports )
        ->toContain( 'title', 'editor', 'excerpt', 'featured_image' )
        ->toContain( 'categories', 'tags', 'custom_fields', 'seo', 'author', 'revisions' )
        ->not->toContain( 'page_attributes' )
        ->not->toContain( 'templates' );
} );

test( 'page default supports declares editor + page panels', function (): void {
    $supports = ( new Page )->supports();

    expect( $supports )
        ->toContain( 'title', 'editor', 'excerpt', 'featured_image' )
        ->toContain( 'custom_fields', 'seo', 'author', 'page_attributes', 'templates', 'revisions' )
        ->not->toContain( 'categories' )
        ->not->toContain( 'tags' );
} );

test( 'supportsFeature returns true for enum and string forms', function (): void {
    $post = new Post;

    expect( $post->supportsFeature( SupportsFeature::Editor ) )->toBeTrue();
    expect( $post->supportsFeature( 'editor' ) )->toBeTrue();
    expect( $post->supportsFeature( 'templates' ) )->toBeFalse();
} );

test( 'supportsFeature treats title as always on', function (): void {
    $ct = new ContentType( ['supports' => []] );

    expect( $ct->supportsFeature( 'title' ) )->toBeTrue();
    expect( $ct->supportsFeature( 'editor' ) )->toBeFalse();
} );

test( 'content type reads supports from the persisted column', function (): void {
    $ct = new ContentType( ['supports' => ['editor', 'seo', 'unknown-flag']] );

    expect( $ct->supports() )->toBe( ['editor', 'seo'] );
} );

test( 'content type with null supports falls back to title + editor', function (): void {
    $ct = new ContentType( ['supports' => null] );

    expect( $ct->supports() )->toBe( ['title', 'editor'] );
} );
