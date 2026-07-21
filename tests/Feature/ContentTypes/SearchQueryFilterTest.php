<?php

declare( strict_types=1 );

/**
 * Feature coverage for the `ap.cmsFramework.search.query` filter introduced in
 * 2.5.0 (issue #196 / Wave 5).
 *
 * Drives {@see HasContentFilters::applySearchFilter()} through
 * {@see BlogManager::getArchiveQuery()} so the fire site is exercised the way a
 * real request would trigger it (not by calling the trait method directly).
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.5.0
 */

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Str;

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.search.query' );
} );

it( 'applies ap.cmsFramework.search.query when a search filter is present', function (): void {
    $received = [];
    addFilter(
        'ap.cmsFramework.search.query',
        function ( $query, string $term, array $context ) use ( & $received ) {
            $received[] = compact( 'term', 'context' );

            return $query;
        },
    );

    $manager = app( BlogManager::class );
    $manager->getArchiveQuery( [ 'search' => 'lorem' ] );

    expect( $received )->toHaveCount( 1 );
    expect( $received[ 0 ][ 'term' ] )->toBe( 'lorem' );
    expect( $received[ 0 ][ 'context' ][ 'model' ] )->toBe( Post::class );
    expect( $received[ 0 ][ 'context' ][ 'manager' ] )->toBe( BlogManager::class );
    expect( $received[ 0 ][ 'context' ][ 'filters' ] )->toBe( [ 'search' => 'lorem' ] );
} );

it( 'does not fire ap.cmsFramework.search.query when no search filter is provided', function (): void {
    $callCount = 0;
    addFilter( 'ap.cmsFramework.search.query', function ( $query ) use ( & $callCount ) {
        $callCount++;

        return $query;
    } );

    app( BlogManager::class )->getArchiveQuery( [] );

    expect( $callCount )->toBe( 0 );
} );

it( 'lets subscribers narrow the search query further', function (): void {
    $user = TestUser::factory()->create();

    $matching = Post::create( [
        'title'        => 'Findable Post',
        'slug'         => Str::slug( 'Findable Post' ) . '-' . Str::random( 5 ),
        'content'      => 'search-term-body',
        'author_id'    => $user->id,
        'status'       => ContentStatus::Published->value,
        'published_at' => now(),
    ] );

    Post::create( [
        'title'        => 'Other Post',
        'slug'         => Str::slug( 'Other Post' ) . '-' . Str::random( 5 ),
        'content'      => 'search-term-body',
        'author_id'    => $user->id,
        'status'       => ContentStatus::Published->value,
        'published_at' => now(),
    ] );

    // Narrow the LIKE-matched result set to the specific ID; verifies the
    // subscriber's mutation is honored by the caller.
    addFilter( 'ap.cmsFramework.search.query', function ( $query ) use ( $matching ) {
        return $query->where( 'id', $matching->id );
    } );

    $results = app( BlogManager::class )
        ->getArchiveQuery( [ 'search' => 'search-term-body' ] )
        ->get();

    expect( $results )->toHaveCount( 1 );
    expect( $results->first()->id )->toBe( $matching->id );
} );

it( 'fires ap.cmsFramework.search.query for the page manager with the correct model context', function (): void {
    $received = null;
    addFilter(
        'ap.cmsFramework.search.query',
        function ( $query, string $term, array $context ) use ( & $received ) {
            $received = $context;

            return $query;
        },
    );

    app( PageManager::class )->getPageQuery( [ 'search' => 'lorem' ] );

    expect( $received )->not->toBeNull();
    expect( $received[ 'model' ] )->toBe( Page::class );
    expect( $received[ 'manager' ] )->toBe( PageManager::class );
} );
