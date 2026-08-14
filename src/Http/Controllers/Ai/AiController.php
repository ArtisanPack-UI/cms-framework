<?php

/**
 * JSON API controller for the cms-framework AI trigger surface.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Http\Controllers\Ai;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\CMSFramework\Ai\Agents\CategorySuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\ExcerptGenerationAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\TagSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Support\AgentMeta;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Http\Requests\Ai\ExcerptRequest;
use ArtisanPackUI\CMSFramework\Http\Requests\Ai\PostTitleRequest;
use ArtisanPackUI\CMSFramework\Http\Requests\Ai\SuggestCategoryRequest;
use ArtisanPackUI\CMSFramework\Http\Requests\Ai\SuggestSlugRequest;
use ArtisanPackUI\CMSFramework\Http\Requests\Ai\SuggestTagsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * REST surface used by non-Livewire front-ends. Each endpoint runs one
 * agent against a validated body and returns the shaped output.
 *
 * Kept intentionally framework-agnostic — React and Vue apps consume
 * these endpoints directly via `fetch`, so adding a CMS AI feature does
 * not require any changes to the `@artisanpack-ui/react` or
 * `@artisanpack-ui/vue` packages. Feature-toggle enforcement lives
 * inside the agents themselves; this controller only wraps errors in a
 * consistent JSON envelope.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
class AiController
{
    /**
     * Return the enabled state of the five cms.* features so a
     * front-end can decide which affordances to render.
     *
     * @since 2.3.0
     *
     * @return JsonResponse
     */
    public function features(): JsonResponse
    {
        return new JsonResponse( [ 'features' => CMSFrameworkServiceProvider::aiFeatureStateMap() ] );
    }

    /**
     * POST /post-title.
     *
     * @since 2.3.0
     *
     * @param  PostTitleRequest  $request  Validated request.
     *
     * @return JsonResponse
     */
    public function postTitle( PostTitleRequest $request ): JsonResponse
    {
        return $this->runAgent(
            PostTitleSuggestionAgent::class,
            $request->validated(),
        );
    }

    /**
     * POST /excerpt.
     *
     * @since 2.3.0
     *
     * @param  ExcerptRequest  $request  Validated request.
     *
     * @return JsonResponse
     */
    public function excerpt( ExcerptRequest $request ): JsonResponse
    {
        return $this->runAgent(
            ExcerptGenerationAgent::class,
            $request->validated(),
        );
    }

    /**
     * POST /suggest-tags.
     *
     * @since 2.3.0
     *
     * @param  SuggestTagsRequest  $request  Validated request.
     *
     * @return JsonResponse
     */
    public function suggestTags( SuggestTagsRequest $request ): JsonResponse
    {
        return $this->runAgent(
            TagSuggestionAgent::class,
            $request->validated(),
        );
    }

    /**
     * POST /suggest-category.
     *
     * @since 2.3.0
     *
     * @param  SuggestCategoryRequest  $request  Validated request.
     *
     * @return JsonResponse
     */
    public function suggestCategory( SuggestCategoryRequest $request ): JsonResponse
    {
        return $this->runAgent(
            CategorySuggestionAgent::class,
            $request->validated(),
        );
    }

    /**
     * POST /suggest-slug.
     *
     * @since 2.3.0
     *
     * @param  SuggestSlugRequest  $request  Validated request.
     *
     * @return JsonResponse
     */
    public function suggestSlug( SuggestSlugRequest $request ): JsonResponse
    {
        return $this->runAgent(
            SlugSuggestionAgent::class,
            $request->validated(),
        );
    }

    /**
     * Shared wrapper — normalizes the four agent-exception categories
     * into consistent status codes + JSON envelopes.
     *
     * The feature key is read off the agent class rather than passed in,
     * so `AI_FEATURE_KEYS` and each agent's `$featureKey` stay the only
     * places a key is spelled. Renaming one is a single-property edit.
     *
     * Takes the class and input rather than a built agent so that
     * `for()` runs *inside* the try: it resolves through the container,
     * and `docs/AI-Features.md` invites hosts to bind a subclass over an
     * agent, so construction is a real throw site. Building it at the
     * call site would let a failed binding escape this handler and lose
     * the JSON error envelope entirely.
     *
     * @since 2.3.0
     *
     * @param  class-string<ArtisanPackAgent>  $agentClass  Agent to run; supplies its own feature key.
     * @param  mixed                           $input       Validated input for the agent.
     *
     * @return JsonResponse
     */
    private function runAgent( string $agentClass, mixed $input ): JsonResponse
    {
        $featureKey = AgentMeta::featureKey( $agentClass );

        if ( Gate::denies( CMSFrameworkServiceProvider::AI_USE_ABILITY ) ) {
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'forbidden',
                'message' => 'You are not authorized to use AI features.',
            ], 403 );
        }

        try {
            $output = $agentClass::for( $input )->run();
            return new JsonResponse( [
                'feature' => $featureKey,
                'output'  => $output,
            ] );
        } catch ( FeatureDisabledException $e ) {
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'feature_disabled',
                'message' => $e->getMessage(),
            ], 403 );
        } catch ( MissingCredentialsException $e ) {
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'missing_credentials',
                'message' => $e->getMessage(),
            ], 503 );
        } catch ( FeatureError $e ) {
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'invalid_input',
                'message' => $e->getMessage(),
            ], 422 );
        } catch ( Throwable $e ) {
            Log::error( 'cms-framework AI API call failed', [
                'feature' => $featureKey,
                'error'   => $e->getMessage(),
            ] );
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'internal_error',
                'message' => 'Unexpected error running AI feature.',
            ], 500 );
        }
    }
}
