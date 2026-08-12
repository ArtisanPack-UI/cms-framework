<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent;
use ArtisanPackUI\CMSFramework\Livewire\Ai\AiTools;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Closure;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use RuntimeException;

/**
 * Covers the shared `AiController::runAgent()` / `AiTools::run()`
 * wrappers — the exception-to-envelope and exception-to-event mapping
 * that every AI endpoint funnels through.
 *
 * The construction-failure cases are the point of this file. Both
 * wrappers build their agent through the container, and
 * `docs/AI-Features.md` invites hosts to bind a subclass over an agent,
 * so a binding whose constructor cannot resolve is a reachable path. If
 * the agent were built at the call site instead of inside the wrapper's
 * try block, that throw would escape the handler: the HTTP surface would
 * lose its JSON envelope and the Livewire surface would never dispatch a
 * status event, leaving a front-end waiting on a signal that never comes.
 */

/**
 * Bind an agent class to a replacement that throws while being built,
 * mimicking a host override with an unresolvable constructor dependency.
 */
function bindUnconstructableAgent( string $agentClass ): void
{
    app()->bind( $agentClass, function (): never {
        throw new RuntimeException( 'container blew up building the agent' );
    } );
}

/**
 * Bind an agent class to a stub whose run() throws the given exception.
 */
function bindThrowingAgent( string $agentClass, callable $thrower ): void
{
    app()->bind( $agentClass, function () use ( $agentClass, $thrower ) {
        return new class( $agentClass, Closure::fromCallable( $thrower ) ) extends PostTitleSuggestionAgent {
            public function __construct( string $agentClass, private Closure $thrower )
            {
                $this->featureKey = ( new $agentClass() )->featureKey;
            }

            public function run(): array
            {
                ( $this->thrower )();
            }
        };
    } );
}

describe( 'AiController::runAgent()', function (): void {
    beforeEach( function (): void {
        $this->user = TestUser::create( [
            'name'     => 'AI Tester',
            'email'    => 'ai-tester@example.com',
            'password' => bcrypt( 'password' ),
        ] );
    } );

    it( 'still answers with the JSON envelope when the agent cannot be built', function (): void {
        bindUnconstructableAgent( PostTitleSuggestionAgent::class );

        $response = $this->actingAs( $this->user, 'sanctum' )
            ->postJson( '/api/v1/cms/ai/post-title', [ 'content' => str_repeat( 'word ', 40 ) ] );

        $response->assertStatus( 500 )
            ->assertJson( [
                'feature' => 'cms.post_title',
                'error'   => 'internal_error',
                'message' => 'Unexpected error running AI feature.',
            ] );
    } );

    it( 'does not leak the underlying exception message', function (): void {
        bindUnconstructableAgent( PostTitleSuggestionAgent::class );

        $response = $this->actingAs( $this->user, 'sanctum' )
            ->postJson( '/api/v1/cms/ai/post-title', [ 'content' => str_repeat( 'word ', 40 ) ] );

        expect( $response->json( 'message' ) )->not->toContain( 'container blew up' );
    } );

    it( 'maps each agent exception to its status code and carries the feature key', function ( callable $thrower, int $status, string $error ): void {
        bindThrowingAgent( PostTitleSuggestionAgent::class, $thrower );

        $response = $this->actingAs( $this->user, 'sanctum' )
            ->postJson( '/api/v1/cms/ai/post-title', [ 'content' => str_repeat( 'word ', 40 ) ] );

        $response->assertStatus( $status )
            ->assertJson( [
                'feature' => 'cms.post_title',
                'error'   => $error,
            ] );
    } )->with( [
        'disabled'            => [ fn (): never => throw FeatureDisabledException::forFeature( 'cms.post_title' ), 403, 'feature_disabled' ],
        'missing credentials' => [ fn (): never => throw MissingCredentialsException::forFeature( 'cms.post_title' ), 503, 'missing_credentials' ],
        'invalid input'       => [ fn (): never => throw new FeatureError( 'bad input' ), 422, 'invalid_input' ],
        'unexpected'          => [ fn (): never => throw new RuntimeException( 'boom' ), 500, 'internal_error' ],
    ] );
} );

describe( 'AiTools::run()', function (): void {
    beforeEach( function (): void {
        // `livewire/livewire` is require-dev, not a hard dependency — the
        // framework guards its component registration with class_exists()
        // and boots fine without it, so the shared TestCase deliberately
        // leaves Livewire unregistered. Boot it just for this group.
        app()->register( LivewireServiceProvider::class );
    } );

    it( 'still dispatches an error event when the agent cannot be built', function (): void {
        bindUnconstructableAgent( PostTitleSuggestionAgent::class );

        Livewire::test( AiTools::class )
            ->call( 'suggestPostTitles', str_repeat( 'word ', 40 ) )
            ->assertDispatched( 'ap-cms-ai:cms.post_title:error' );
    } );

    it( 'maps each agent exception to its status event', function ( callable $thrower, string $event ): void {
        bindThrowingAgent( PostTitleSuggestionAgent::class, $thrower );

        Livewire::test( AiTools::class )
            ->call( 'suggestPostTitles', str_repeat( 'word ', 40 ) )
            ->assertDispatched( $event );
    } )->with( [
        'disabled'            => [ fn (): never => throw FeatureDisabledException::forFeature( 'cms.post_title' ), 'ap-cms-ai:cms.post_title:disabled' ],
        'missing credentials' => [ fn (): never => throw MissingCredentialsException::forFeature( 'cms.post_title' ), 'ap-cms-ai:cms.post_title:missing-credentials' ],
        'invalid input'       => [ fn (): never => throw new FeatureError( 'bad input' ), 'ap-cms-ai:cms.post_title:invalid-input' ],
        'unexpected'          => [ fn (): never => throw new RuntimeException( 'boom' ), 'ap-cms-ai:cms.post_title:error' ],
    ] );
} );
