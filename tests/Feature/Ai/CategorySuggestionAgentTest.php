<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\CMSFramework\Ai\Agents\CategorySuggestionAgent;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );

    $this->tree = [
        [
            'name'     => 'News',
            'children' => [
                [ 'name' => 'Product Updates' ],
                [ 'name' => 'Company' ],
            ],
        ],
        [ 'name' => 'Tutorials' ],
    ];
} );

it( 'accepts a valid slash-delimited path', function (): void {
    $this->prompter->queue( [
        'selected'   => 'News/Product Updates',
        'confidence' => 0.9,
        'rationale'  => 'body announces a new feature',
    ] );

    $result = CategorySuggestionAgent::for( [
        'content'       => 'We just shipped X.',
        'category_tree' => $this->tree,
    ] )->run();

    expect( $result['selected'] )->toBe( 'News/Product Updates' );
    expect( $result['confidence'] )->toBe( 0.9 );
} );

it( 'drops hallucinated paths and zeros confidence when no fit is chosen', function (): void {
    $this->prompter->queue( [
        'selected'   => 'News/Not A Real Child',
        'confidence' => 0.8,
        'rationale'  => 'guessed',
    ] );

    $result = CategorySuggestionAgent::for( [
        'content'       => 'body',
        'category_tree' => $this->tree,
    ] )->run();

    expect( $result['selected'] )->toBe( '' );
    expect( $result['confidence'] )->toBe( 0.0 );
} );

it( 'accepts a root-level path', function (): void {
    $this->prompter->queue( [
        'selected'   => 'Tutorials',
        'confidence' => 0.7,
        'rationale'  => 'looks like a tutorial',
    ] );

    $result = CategorySuggestionAgent::for( [
        'content'       => 'body',
        'category_tree' => $this->tree,
    ] )->run();

    expect( $result['selected'] )->toBe( 'Tutorials' );
} );

it( 'clamps confidence to [0, 1]', function (): void {
    $this->prompter->queue( [
        'selected'   => 'Tutorials',
        'confidence' => 2.5,
        'rationale'  => 'r',
    ] );

    $result = CategorySuggestionAgent::for( [
        'content'       => 'body',
        'category_tree' => $this->tree,
    ] )->run();

    expect( $result['confidence'] )->toBe( 1.0 );
} );

it( 'raises FeatureError on invalid input', function (): void {
    expect( fn () => CategorySuggestionAgent::for( 'nope' )->run() )->toThrow( FeatureError::class );

    expect( fn () => CategorySuggestionAgent::for( [ 'content' => 'ok' ] )->run() )
        ->toThrow( FeatureError::class );

    expect( fn () => CategorySuggestionAgent::for( [ 'category_tree' => [] ] )->run() )
        ->toThrow( FeatureError::class );
} );
