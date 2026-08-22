<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Pages\Database\Factories\PageFactory;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

test( 'the page factory populates author_id from the configured user model', function (): void {
    $page = PageFactory::new()->create();

    expect( $page )->toBeInstanceOf( Page::class )
        ->and( $page->author_id )->not->toBeNull()
        ->and( TestUser::whereKey( $page->author_id )->exists() )->toBeTrue();
} );

test( 'the page factory resolves the configured model rather than App\\Models\\User', function (): void {
    $page = PageFactory::new()->create();

    expect( $page->author )->toBeInstanceOf( TestUser::class );
} );

test( 'the page factory leaves author_id null when the user model cannot be resolved', function (): void {
    config( ['auth.providers.users.model' => 'Definitely\\Missing\\User'] );

    $attributes = PageFactory::new()->definition();

    expect( $attributes['author_id'] )->toBeNull();
} );
