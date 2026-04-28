<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'post block_content round-trips an array through the cast', function (): void {
    $user = TestUser::create( [
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $blocks = [
        [
            'blockName' => 'core/heading',
            'attrs'     => [ 'level' => 2 ],
            'innerHTML' => '<h2>Hello</h2>',
        ],
        [
            'blockName' => 'core/paragraph',
            'attrs'     => [],
            'innerHTML' => '<p>World</p>',
        ],
    ];

    $post = Post::create( [
        'title'         => 'Block Post',
        'slug'          => 'block-post',
        'block_content' => $blocks,
        'author_id'     => $user->id,
        'status'        => 'draft',
    ] );

    $fresh = Post::find( $post->id );

    expect( $fresh->block_content )->toBe( $blocks );
} );

test( 'post exposes block_content via HasBlockContent helpers', function (): void {
    $user = TestUser::create( [
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'     => 'Helpers Post',
        'slug'      => 'helpers-post',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    expect( $post->getBlockContent() )->toBe( [] );
    expect( $post->getBlockContentColumn() )->toBe( 'block_content' );

    $blocks = [
        [ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>Hi</p>' ],
    ];

    $post->setBlockContent( $blocks );
    $post->save();

    $fresh = Post::find( $post->id );

    expect( $fresh->getBlockContent() )->toBe( $blocks );
} );

test( 'post block_content is nullable and defaults to null', function (): void {
    $user = TestUser::create( [
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $post = Post::create( [
        'title'     => 'No Blocks',
        'slug'      => 'no-blocks',
        'author_id' => $user->id,
        'status'    => 'draft',
    ] );

    expect( $post->block_content )->toBeNull();
} );
