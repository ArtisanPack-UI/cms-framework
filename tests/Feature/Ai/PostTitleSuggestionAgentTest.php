<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns shaped titles when the prompter responds', function (): void {
    $this->prompter->queue( [
        'titles' => [
            [ 'title' => 'How to Ship Faster', 'rationale' => 'benefit-led' ],
            [ 'title' => 'What Makes a Great CMS?', 'rationale' => 'curiosity question' ],
            [ 'title' => 'Ship Faster with the CMS Framework', 'rationale' => 'direct/descriptive' ],
        ],
    ] );

    $result = PostTitleSuggestionAgent::for( [
        'content' => 'A long draft about shipping faster with the CMS framework.',
    ] )->run();

    expect( $result['titles'] )->toHaveCount( 3 );
    expect( $result['titles'][0]['title'] )->toBe( 'How to Ship Faster' );
} );

it( 'drops titles missing required fields', function (): void {
    $this->prompter->queue( [
        'titles' => [
            [ 'title' => 'Ok', 'rationale' => 'direct' ],
            [ 'title'     => '', 'rationale' => 'empty title' ],
            [ 'rationale' => 'no title at all' ],
        ],
    ] );

    $result = PostTitleSuggestionAgent::for( [ 'content' => 'body' ] )->run();

    expect( $result['titles'] )->toHaveCount( 1 );
    expect( $result['titles'][0]['title'] )->toBe( 'Ok' );
} );

it( 'clamps titles longer than 80 chars', function (): void {
    $long = str_repeat( 'a', 120 );
    $this->prompter->queue( [
        'titles' => [ [ 'title' => $long, 'rationale' => 'too long' ] ],
    ] );

    $result = PostTitleSuggestionAgent::for( [ 'content' => 'body' ] )->run();

    expect( mb_strlen( $result['titles'][0]['title'] ) )->toBe( 80 );
} );

it( 'honors the requested count', function (): void {
    $queued = [ 'titles' => [] ];
    for ( $i = 0; $i < 5; $i++ ) {
        $queued['titles'][] = [ 'title' => "Title {$i}", 'rationale' => 'ok' ];
    }
    $this->prompter->queue( $queued );

    $result = PostTitleSuggestionAgent::for( [
        'content' => 'body',
        'count'   => 3,
    ] )->run();

    expect( $result['titles'] )->toHaveCount( 3 );
} );

it( 'forwards tone into the prompter message when set', function (): void {
    $this->prompter->queue( [ 'titles' => [ [ 'title' => 't', 'rationale' => 'r' ] ] ] );

    PostTitleSuggestionAgent::for( [
        'content' => 'body',
        'tone'    => 'playful',
    ] )->run();

    $parts = collect( $this->prompter->calls[0]['message'] )->pluck( 'text' );
    expect( $parts->contains( fn ( string $t ): bool => str_contains( $t, 'Tone: playful' ) ) )->toBeTrue();
} );

it( 'raises FeatureError on invalid input', function (): void {
    expect( fn () => PostTitleSuggestionAgent::for( 'not-an-array' )->run() )
        ->toThrow( FeatureError::class );

    expect( fn () => PostTitleSuggestionAgent::for( [ 'content' => '' ] )->run() )
        ->toThrow( FeatureError::class );
} );
