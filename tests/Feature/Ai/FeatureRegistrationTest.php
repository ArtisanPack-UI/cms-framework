<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\CMSFramework\Ai\Agents\CategorySuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\ExcerptGenerationAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\TagSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Support\AgentMeta;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use RuntimeException;

it( 'declares five cms.* features from the service provider', function (): void {
    $provider = new CMSFrameworkServiceProvider( $this->app );
    $features = $provider->aiFeatures();

    expect( $features )->toHaveKeys( [
        'cms.post_title',
        'cms.excerpt',
        'cms.suggest_tags',
        'cms.suggest_category',
        'cms.suggest_slug',
    ] );

    expect( $features['cms.post_title']['agent'] )->toBe( PostTitleSuggestionAgent::class );
    expect( $features['cms.excerpt']['agent'] )->toBe( ExcerptGenerationAgent::class );
    expect( $features['cms.suggest_tags']['agent'] )->toBe( TagSuggestionAgent::class );
    expect( $features['cms.suggest_category']['agent'] )->toBe( CategorySuggestionAgent::class );
    expect( $features['cms.suggest_slug']['agent'] )->toBe( SlugSuggestionAgent::class );

    foreach ( $features as $definition ) {
        expect( $definition['package'] )->toBe( 'artisanpack-ui/cms-framework' );
    }
} );

it( 'reads agent metadata off the declared class, not a container override', function (): void {
    // A host binding a subclass must not silently re-key the registry:
    // AI_FEATURE_KEYS would still list the original, leaving the two
    // disagreeing. AgentMeta reflects the declared class for this reason.
    app()->bind( PostTitleSuggestionAgent::class, fn (): PostTitleSuggestionAgent => new class extends PostTitleSuggestionAgent {
        public string $featureKey = 'myapp.hijacked';

        public string $package = 'myapp/ai';
    } );

    $features = ( new CMSFrameworkServiceProvider( $this->app ) )->aiFeatures();

    expect( $features )->toHaveKey( 'cms.post_title' )
        ->and( $features )->not->toHaveKey( 'myapp.hijacked' )
        ->and( $features['cms.post_title']['package'] )->toBe( 'artisanpack-ui/cms-framework' );
} );

it( 'resolves agent metadata without constructing the agent', function (): void {
    // The trigger surfaces call AgentMeta to build their error envelopes,
    // so it must not be able to throw on the very path that reports
    // failures — including when a host override cannot be constructed.
    app()->bind( PostTitleSuggestionAgent::class, function (): never {
        throw new RuntimeException( 'must never be constructed' );
    } );

    expect( AgentMeta::featureKey( PostTitleSuggestionAgent::class ) )->toBe( 'cms.post_title' )
        ->and( AgentMeta::package( PostTitleSuggestionAgent::class ) )->toBe( 'artisanpack-ui/cms-framework' );
} );

it( 'keeps AI_FEATURE_KEYS in step with the agents', function (): void {
    // AI_FEATURE_KEYS has to stay a hand-written const — it is public API
    // and PHP consts cannot resolve agents. This is the guard that stops
    // it drifting from the agent properties it mirrors: rename an agent's
    // $featureKey without updating the const and this fails.
    $provider = new CMSFrameworkServiceProvider( $this->app );

    expect( array_keys( $provider->aiFeatures() ) )
        ->toBe( CMSFrameworkServiceProvider::AI_FEATURE_KEYS );
} );

it( 'covers every declared agent in AI_AGENTS exactly once', function (): void {
    expect( CMSFrameworkServiceProvider::AI_AGENTS )
        ->toHaveCount( count( CMSFrameworkServiceProvider::AI_FEATURE_KEYS ) )
        ->and( array_unique( CMSFrameworkServiceProvider::AI_AGENTS ) )
        ->toBe( CMSFrameworkServiceProvider::AI_AGENTS );
} );

it( 'registers the five features with the FeatureRegistry at boot', function (): void {
    /** @var FeatureRegistry $registry */
    $registry = $this->app->make( FeatureRegistry::class );

    foreach ( CMSFrameworkServiceProvider::AI_FEATURE_KEYS as $key ) {
        expect( $registry->get( $key ) )->not->toBeNull();
    }
} );
