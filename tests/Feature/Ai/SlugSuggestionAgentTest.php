<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns the model slug and any alternates', function (): void {
    $this->prompter->queue( [
        'slug'       => 'ship-faster-cms',
        'alternates' => [ 'faster-cms', 'cms-shipping' ],
    ] );

    $result = SlugSuggestionAgent::for( [ 'title' => 'How to Ship Faster with the CMS' ] )->run();

    expect( $result['slug'] )->toBe( 'ship-faster-cms' );
    expect( $result['alternates'] )->toBe( [ 'faster-cms', 'cms-shipping' ] );
} );

it( 'sanitizes non-kebab output from the model', function (): void {
    $this->prompter->queue( [
        'slug'       => 'How To_Ship__Faster!!',
        'alternates' => [],
    ] );

    $result = SlugSuggestionAgent::for( [ 'title' => 'Anything' ] )->run();

    expect( $result['slug'] )->toBe( 'how-to-ship-faster' );
} );

it( 'clamps the slug to max_chars on a hyphen boundary when possible', function (): void {
    $this->prompter->queue( [
        'slug'       => 'a-b-c-d-e-f-g-h-i-j-k-l-m-n-o-p-q-r-s-t-u-v-w-x-y-z',
        'alternates' => [],
    ] );

    $result = SlugSuggestionAgent::for( [
        'title'     => 'title',
        'max_chars' => 20,
    ] )->run();

    expect( strlen( $result['slug'] ) )->toBeLessThanOrEqual( 20 );
    expect( str_ends_with( $result['slug'], '-' ) )->toBeFalse();
} );

it( 'drops alternates that duplicate the primary slug or each other', function (): void {
    $this->prompter->queue( [
        'slug'       => 'ship-faster',
        'alternates' => [ 'ship-faster', 'faster-shipping', 'faster-shipping' ],
    ] );

    $result = SlugSuggestionAgent::for( [ 'title' => 't' ] )->run();

    expect( $result['alternates'] )->toBe( [ 'faster-shipping' ] );
} );

it( 'raises FeatureError on invalid input', function (): void {
    expect( fn () => SlugSuggestionAgent::for( 'nope' )->run() )->toThrow( FeatureError::class );

    expect( fn () => SlugSuggestionAgent::for( [ 'title' => '' ] )->run() )
        ->toThrow( FeatureError::class );
} );
