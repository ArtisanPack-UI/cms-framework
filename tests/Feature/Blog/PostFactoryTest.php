<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Database\Factories\PostFactory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

test( 'the post factory populates author_id from the configured user model', function (): void {
    $post = PostFactory::new()->create();

    expect( $post )->toBeInstanceOf( Post::class )
        ->and( $post->author_id )->not->toBeNull()
        ->and( TestUser::whereKey( $post->author_id )->exists() )->toBeTrue();
} );

test( 'the post factory resolves the configured model rather than App\\Models\\User', function (): void {
    $post = PostFactory::new()->create();

    expect( $post->author )->toBeInstanceOf( TestUser::class );
} );

test( 'the post factory leaves author_id null when the user model cannot be resolved', function (): void {
    config( ['auth.providers.users.model' => 'Definitely\\Missing\\User'] );

    $attributes = PostFactory::new()->definition();

    expect( $attributes['author_id'] )->toBeNull();
} );
