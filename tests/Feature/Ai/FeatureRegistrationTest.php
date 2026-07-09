<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\CMSFramework\Ai\Agents\CategorySuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\ExcerptGenerationAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\TagSuggestionAgent;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;

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

it( 'registers the five features with the FeatureRegistry at boot', function (): void {
    /** @var FeatureRegistry $registry */
    $registry = $this->app->make( FeatureRegistry::class );

    foreach ( CMSFrameworkServiceProvider::AI_FEATURE_KEYS as $key ) {
        expect( $registry->get( $key ) )->not->toBeNull();
    }
} );
