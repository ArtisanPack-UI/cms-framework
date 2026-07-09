<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\CMSFramework\Ai\Agents\ExcerptGenerationAgent;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns the excerpt and computes char_count from the returned string', function (): void {
    $this->prompter->queue( [
        'excerpt'    => 'A short excerpt.',
        'char_count' => 999, // model lying — validator must recompute
    ] );

    $result = ExcerptGenerationAgent::for( [ 'content' => 'A long post about many things.' ] )->run();

    expect( $result['excerpt'] )->toBe( 'A short excerpt.' );
    expect( $result['char_count'] )->toBe( mb_strlen( 'A short excerpt.' ) );
} );

it( 'truncates excerpts longer than max_chars', function (): void {
    $long = str_repeat( 'x', 300 );
    $this->prompter->queue( [ 'excerpt' => $long, 'char_count' => 300 ] );

    $result = ExcerptGenerationAgent::for( [
        'content'   => 'body',
        'max_chars' => 150,
    ] )->run();

    expect( mb_strlen( $result['excerpt'] ) )->toBe( 150 );
    expect( $result['char_count'] )->toBe( 150 );
} );

it( 'forwards max_chars into the prompter message', function (): void {
    $this->prompter->queue( [ 'excerpt' => 'ok', 'char_count' => 2 ] );

    ExcerptGenerationAgent::for( [
        'content'   => 'body',
        'max_chars' => 250,
    ] )->run();

    $parts = collect( $this->prompter->calls[0]['message'] )->pluck( 'text' );
    expect( $parts->contains( fn ( string $t ): bool => str_contains( $t, 'max_chars: 250' ) ) )->toBeTrue();
} );

it( 'raises FeatureError on invalid input', function (): void {
    expect( fn () => ExcerptGenerationAgent::for( 'not-an-array' )->run() )
        ->toThrow( FeatureError::class );

    expect( fn () => ExcerptGenerationAgent::for( [ 'content' => '' ] )->run() )
        ->toThrow( FeatureError::class );
} );
