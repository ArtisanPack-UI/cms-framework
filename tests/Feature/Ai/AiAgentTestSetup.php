<?php

/**
 * Shared bootstrap for cms-framework AI agent tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Contracts\CredentialResolver;
use ArtisanPackUI\Ai\Credentials\ChainedCredentialResolver;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Tests\Support\FakeAgentPrompter;

/**
 * Registers a fake prompter, stub credentials, and enables the five
 * feature toggles the CMS AI surface cares about.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
final class AiAgentTestSetup
{
    /**
     * @since 2.3.0
     *
     * @param  \Illuminate\Foundation\Application  $app  Application instance.
     *
     * @return FakeAgentPrompter The bound fake prompter.
     */
    public static function bootstrap( $app ): FakeAgentPrompter
    {
        /** @var ChainedCredentialResolver $resolver */
        $resolver = $app->make( CredentialResolver::class );
        $resolver->setOverride(
            new Credentials( provider: 'anthropic', apiKey: 'sk-test', defaultModel: 'claude-haiku-4-5' ),
        );
        $resolver->useStore( fn () => null );

        $prompter = new FakeAgentPrompter();
        $app->instance( AgentPrompter::class, $prompter );

        foreach ( CMSFrameworkServiceProvider::AI_FEATURE_KEYS as $key ) {
            $app['config']->set( "artisanpack.ai.features.{$key}.enabled", true );
        }

        return $prompter;
    }
}
