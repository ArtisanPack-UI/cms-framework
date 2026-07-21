<?php

declare( strict_types=1 );

/**
 * Feature coverage for the Page model lifecycle hooks introduced in 2.5.0
 * (issue #196 / Wave 5).
 *
 * Mirrors {@see ArtisanPackUI\CMSFramework\Tests\Feature\Blog\PostLifecycleHooksTest}
 * — same fire semantics under the `ap.cmsFramework.page.*` namespace.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.5.0
 */

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Str;

/**
 * Build a Page with sensible defaults so each test can override only the
 * attribute it cares about (status / published_at).
 *
 * @param  array<string, mixed>  $attributes
 */
function createLifecyclePage( array $attributes = [] ): Page
{
    $title = fake()->sentence( 4, true );
    $user  = TestUser::factory()->create();

    return Page::create( array_merge( [
        'title'        => $title,
        'slug'         => Str::slug( $title ) . '-' . Str::random( 5 ),
        'content'      => fake()->paragraph(),
        'excerpt'      => fake()->sentence(),
        'author_id'    => $user->id,
        'status'       => ContentStatus::Draft->value,
        'published_at' => null,
        'order'        => 0,
        'template'     => 'default',
    ], $attributes ) );
}

afterEach( function (): void {
    removeAllActions( 'ap.cmsFramework.page.saving' );
    removeAllActions( 'ap.cmsFramework.page.saved' );
    removeAllActions( 'ap.cmsFramework.page.published' );
    removeAllActions( 'ap.cmsFramework.page.trashed' );
    removeAllActions( 'ap.cmsFramework.page.restored' );
} );

it( 'fires ap.cmsFramework.page.saving before the record is written', function (): void {
    $seen = [];

    addAction( 'ap.cmsFramework.page.saving', function ( Page $page ) use ( & $seen ): void {
        $seen[] = $page->exists;
    } );

    createLifecyclePage();

    expect( $seen )->toHaveCount( 1 );
    expect( $seen[ 0 ] )->toBeFalse();
} );

it( 'fires ap.cmsFramework.page.saved after the record is written', function (): void {
    $received = null;

    addAction( 'ap.cmsFramework.page.saved', function ( Page $page ) use ( & $received ): void {
        $received = $page;
    } );

    $page = createLifecyclePage();

    expect( $received )->not->toBeNull();
    expect( $received->id )->toBe( $page->id );
    expect( $received->exists )->toBeTrue();
} );

it( 'fires ap.cmsFramework.page.published when a draft transitions to published', function (): void {
    $callCount = 0;
    addAction( 'ap.cmsFramework.page.published', function () use ( & $callCount ): void {
        $callCount++;
    } );

    $page = createLifecyclePage();
    expect( $callCount )->toBe( 0 );

    $page->status       = ContentStatus::Published;
    $page->published_at = now();
    $page->save();

    expect( $callCount )->toBe( 1 );
} );

it( 'does not fire ap.cmsFramework.page.published on subsequent saves of an already-published page', function (): void {
    $page = createLifecyclePage( [
        'status'       => ContentStatus::Published->value,
        'published_at' => now(),
    ] );

    $callCount = 0;
    addAction( 'ap.cmsFramework.page.published', function () use ( & $callCount ): void {
        $callCount++;
    } );

    $page->title = 'Post-publish title tweak';
    $page->save();

    expect( $callCount )->toBe( 0 );
} );

it( 'fires ap.cmsFramework.page.trashed on soft delete', function (): void {
    $page = createLifecyclePage();

    $received = null;
    addAction( 'ap.cmsFramework.page.trashed', function ( Page $page ) use ( & $received ): void {
        $received = $page;
    } );

    $page->delete();

    expect( $received )->not->toBeNull();
    expect( $received->id )->toBe( $page->id );
    expect( $received->trashed() )->toBeTrue();
} );

it( 'does not fire ap.cmsFramework.page.trashed on force delete', function (): void {
    $page = createLifecyclePage();

    $callCount = 0;
    addAction( 'ap.cmsFramework.page.trashed', function () use ( & $callCount ): void {
        $callCount++;
    } );

    $page->forceDelete();

    expect( $callCount )->toBe( 0 );
} );

it( 'fires ap.cmsFramework.page.restored on restore', function (): void {
    $page = createLifecyclePage();
    $page->delete();

    $received = null;
    addAction( 'ap.cmsFramework.page.restored', function ( Page $page ) use ( & $received ): void {
        $received = $page;
    } );

    $page->restore();

    expect( $received )->not->toBeNull();
    expect( $received->id )->toBe( $page->id );
    expect( $received->trashed() )->toBeFalse();
} );
