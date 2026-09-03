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
use ArtisanPackUI\Ai\Concerns\HandlesAiFeatureResponses;
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
    use HandlesAiFeatureResponses;

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
        return new JsonResponse( [ 'features' => $this->aiFeatureStateMap( CMSFrameworkServiceProvider::AI_FEATURE_KEYS ) ] );
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
     * Tag the shared handler's log line with this surface.
     *
     * @since 2.11.0
     *
     * @return string
     */
    protected function aiFeatureLogMessage(): string
    {
        return 'cms-framework AI API call failed';
    }

    /**
     * Shared wrapper — delegates the exception ladder to the shared
     * {@see HandlesAiFeatureResponses::handleAiFeature()} trait and folds
     * the outcome into the JSON envelope.
     *
     * The feature key is read off the agent class rather than passed in,
     * so `AI_FEATURE_KEYS` and each agent's `$featureKey` stay the only
     * places a key is spelled. Renaming one is a single-property edit.
     *
     * The 403 `forbidden` gate check stays here — it is the one failure
     * that belongs to this surface (authorization), not to the shared
     * agent-exception ladder.
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

        $outcome = $this->handleAiFeature(
            $featureKey,
            fn () => $agentClass::for( $input )->run(),
        );

        if ( $outcome->succeeded ) {
            return new JsonResponse( [
                'feature' => $outcome->feature,
                'output'  => $outcome->output,
            ] );
        }

        return new JsonResponse( [
            'feature' => $outcome->feature,
            'error'   => $outcome->errorCode,
            'message' => $outcome->message,
        ], $outcome->status );
    }
}
