<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\CMSFramework\Ai\Agents\TagSuggestionAgent;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns only tags that appear in available_tags', function (): void {
    $this->prompter->queue( [
        'selected' => [
            [ 'tag' => 'laravel', 'confidence' => 0.9 ],
            [ 'tag' => 'not-a-real-tag', 'confidence' => 0.7 ], // must be dropped
            [ 'tag' => 'cms', 'confidence' => 0.6 ],
        ],
    ] );

    $result = TagSuggestionAgent::for( [
        'content'        => 'post body',
        'available_tags' => [ 'laravel', 'cms', 'php' ],
    ] )->run();

    expect( $result['selected'] )->toHaveCount( 2 );
    expect( collect( $result['selected'] )->pluck( 'tag' )->all() )->toBe( [ 'laravel', 'cms' ] );
} );

it( 'trims incidental whitespace from model-returned tags before allow-list lookup', function (): void {
    $this->prompter->queue( [
        'selected' => [
            [ 'tag' => 'laravel ', 'confidence' => 0.9 ],
            [ 'tag' => "\tcms\n", 'confidence' => 0.8 ],
        ],
    ] );

    $result = TagSuggestionAgent::for( [
        'content'        => 'body',
        'available_tags' => [ 'laravel', 'cms' ],
    ] )->run();

    expect( collect( $result['selected'] )->pluck( 'tag' )->all() )->toBe( [ 'laravel', 'cms' ] );
} );

it( 'omits suggested_new when allow_new is false', function (): void {
    $this->prompter->queue( [
        'selected'      => [],
        'suggested_new' => [ 'brand-new-tag' ],
    ] );

    $result = TagSuggestionAgent::for( [
        'content'        => 'body',
        'available_tags' => [ 'a' ],
    ] )->run();

    expect( $result )->not->toHaveKey( 'suggested_new' );
} );

it( 'returns suggested_new when allow_new is true, dropping duplicates', function (): void {
    $this->prompter->queue( [
        'selected'      => [],
        'suggested_new' => [
            'brand-new-tag',
            'Laravel', // already exists case-insensitively
            'brand-new-tag', // duplicate within response
            'another-new',
        ],
    ] );

    $result = TagSuggestionAgent::for( [
        'content'        => 'body',
        'available_tags' => [ 'laravel' ],
        'allow_new'      => true,
    ] )->run();

    expect( $result['suggested_new'] )->toBe( [ 'brand-new-tag', 'another-new' ] );
} );

it( 'clamps confidences to [0, 1]', function (): void {
    $this->prompter->queue( [
        'selected' => [
            [ 'tag' => 'a', 'confidence' => 1.7 ],
            [ 'tag' => 'b', 'confidence' => -0.3 ],
        ],
    ] );

    $result = TagSuggestionAgent::for( [
        'content'        => 'body',
        'available_tags' => [ 'a', 'b' ],
    ] )->run();

    expect( $result['selected'][0]['confidence'] )->toBe( 1.0 );
    expect( $result['selected'][1]['confidence'] )->toBe( 0.0 );
} );

it( 'honors max_selected', function (): void {
    $this->prompter->queue( [
        'selected' => [
            [ 'tag' => 'a', 'confidence' => 1.0 ],
            [ 'tag' => 'b', 'confidence' => 0.9 ],
            [ 'tag' => 'c', 'confidence' => 0.8 ],
        ],
    ] );

    $result = TagSuggestionAgent::for( [
        'content'        => 'body',
        'available_tags' => [ 'a', 'b', 'c' ],
        'max_selected'   => 2,
    ] )->run();

    expect( $result['selected'] )->toHaveCount( 2 );
} );

it( 'raises FeatureError on invalid input', function (): void {
    expect( fn () => TagSuggestionAgent::for( 'nope' )->run() )->toThrow( FeatureError::class );

    expect( fn () => TagSuggestionAgent::for( [ 'available_tags' => [] ] )->run() )
        ->toThrow( FeatureError::class );

    expect( fn () => TagSuggestionAgent::for( [ 'content' => 'ok' ] )->run() )
        ->toThrow( FeatureError::class );
} );
