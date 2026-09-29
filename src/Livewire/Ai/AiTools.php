<?php

/**
 * Livewire trigger surface for the cms-framework AI features.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Livewire\Ai;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Concerns\HandlesAiFeatureResponses;
use ArtisanPackUI\CMSFramework\Ai\Agents\CategorySuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\ExcerptGenerationAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\TagSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Support\AgentMeta;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Thin Livewire wrapper that runs any of the five cms-framework AI
 * agents and dispatches the shaped result via a browser event. Admin
 * Blade surfaces (and any host embedding the CMS) listen for
 * `ap-cms-ai:{featureKey}:{status}` and fold the payload into the UI.
 *
 * React and Vue front-ends do NOT need this component — they call the
 * REST endpoints registered under `/api/v1/cms/ai/*` directly (see
 * `AiController`). This keeps `@artisanpack-ui/react` and
 * `@artisanpack-ui/vue` framework-agnostic: adding a new AI trigger
 * only touches the backend package.
 *
 * The component intentionally holds no persistent state — a Livewire
 * class was picked so CSRF, auth, and rate limiting come "for free"
 * via the standard Livewire endpoint, and feature-toggle checks live
 * in one place.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
class AiTools extends Component
{
    use HandlesAiFeatureResponses;

    /**
     * Generate 3–5 title variants from a draft body.
     *
     * @since 2.3.0
     *
     * @param  string       $content  Draft body.
     * @param  string|null  $tone     Optional tone hint.
     * @param  int|null     $count    Optional variant count (3-5).
     *
     * @return void
     */
    #[On( 'ap-cms-ai:suggest-post-titles' )]
    public function suggestPostTitles( string $content, ?string $tone = null, ?int $count = null ): void
    {
        $this->run(
            PostTitleSuggestionAgent::class,
            [
                'content' => $content,
                'tone'    => $tone,
                'count'   => $count,
            ],
        );
    }

    /**
     * Generate an excerpt from full post content.
     *
     * @since 2.3.0
     *
     * @param  string    $content   Full post body.
     * @param  int|null  $maxChars  Optional length cap (80-400).
     *
     * @return void
     */
    #[On( 'ap-cms-ai:generate-excerpt' )]
    public function generateExcerpt( string $content, ?int $maxChars = null ): void
    {
        $this->run(
            ExcerptGenerationAgent::class,
            [
                'content'   => $content,
                'max_chars' => $maxChars,
            ],
        );
    }

    /**
     * Suggest tags from an existing taxonomy.
     *
     * @since 2.3.0
     *
     * @param  string             $content        Content to tag.
     * @param  array<int, string> $availableTags  Existing tag names.
     * @param  bool               $allowNew       Whether to include suggested_new.
     * @param  int|null           $maxSelected    Optional cap on selected tags (1-10).
     *
     * @return void
     */
    #[On( 'ap-cms-ai:suggest-tags' )]
    public function suggestTags(
        string $content,
        array $availableTags,
        bool $allowNew = false,
        ?int $maxSelected = null,
    ): void {
        $this->run(
            TagSuggestionAgent::class,
            [
                'content'        => $content,
                'available_tags' => $availableTags,
                'allow_new'      => $allowNew,
                'max_selected'   => $maxSelected,
            ],
        );
    }

    /**
     * Suggest a single category from a hierarchical taxonomy.
     *
     * @since 2.3.0
     *
     * @param  string             $content       Content to categorize.
     * @param  array<int, mixed>  $categoryTree  Nested list of {name, children?} nodes.
     *
     * @return void
     */
    #[On( 'ap-cms-ai:suggest-category' )]
    public function suggestCategory( string $content, array $categoryTree ): void
    {
        $this->run(
            CategorySuggestionAgent::class,
            [
                'content'       => $content,
                'category_tree' => $categoryTree,
            ],
        );
    }

    /**
     * Suggest an SEO-friendly slug from a title.
     *
     * @since 2.3.0
     *
     * @param  string       $title     Post title.
     * @param  string|null  $excerpt   Optional excerpt for disambiguation.
     * @param  int|null     $maxChars  Optional length cap (20-100).
     *
     * @return void
     */
    #[On( 'ap-cms-ai:suggest-slug' )]
    public function suggestSlug( string $title, ?string $excerpt = null, ?int $maxChars = null ): void
    {
        $this->run(
            SlugSuggestionAgent::class,
            [
                'title'     => $title,
                'excerpt'   => $excerpt,
                'max_chars' => $maxChars,
            ],
        );
    }

    /**
     * Return the currently-enabled feature toggle map so the front-end
     * knows which affordances to render.
     *
     * @since 2.3.0
     *
     * @return array<string, bool>
     */
    public function enabledFeatures(): array
    {
        return $this->aiFeatureStateMap( CMSFrameworkServiceProvider::AI_FEATURE_KEYS );
    }

    /**
     * Blade shell — hosts can override the view or inline the component
     * without body. The default view is intentionally empty; this is a
     * transport component, not a UI.
     *
     * @since 2.3.0
     *
     * @return string
     */
    public function render(): string
    {
        return '<div class="ap-cms-ai-tools" data-testid="ap-cms-ai-tools"></div>';
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
        return 'cms-framework AI trigger failed';
    }

    /**
     * Shared run-and-emit path. Kept private so callers can only reach
     * agents through the five public entry points, each of which
     * pre-shapes its input.
     *
     * The feature key is read off the agent class rather than passed in,
     * so `AI_FEATURE_KEYS` and each agent's `$featureKey` stay the only
     * places a key is spelled. Renaming one is a single-property edit.
     *
     * The exception ladder is delegated to
     * {@see HandlesAiFeatureResponses::handleAiFeature()}; this method
     * folds the outcome into a browser event whose name is driven by
     * {@see AiFeatureOutcome::$statusSlug}. The gate check stays here —
     * authorization belongs to this surface, not to the shared ladder.
     *
     * @since 2.3.0
     *
     * @param  class-string<ArtisanPackAgent>  $agentClass  Agent to run; supplies its own feature key.
     * @param  mixed                           $input       Pre-shaped input for the agent.
     *
     * @return void
     */
    private function run( string $agentClass, mixed $input ): void
    {
        $featureKey = AgentMeta::featureKey( $agentClass );

        if ( Gate::denies( CMSFrameworkServiceProvider::AI_USE_ABILITY ) ) {
            $this->dispatch(
                sprintf( 'ap-cms-ai:%s:forbidden', $featureKey ),
                feature: $featureKey,
                message: 'You are not authorized to use AI features.',
            );

            return;
        }

        $outcome = $this->handleAiFeature(
            $featureKey,
            fn () => $agentClass::for( $input )->run(),
        );

        if ( $outcome->succeeded ) {
            $this->dispatch(
                sprintf( 'ap-cms-ai:%s:success', $outcome->feature ),
                feature: $outcome->feature,
                output: $outcome->output,
            );

            return;
        }

        $this->dispatch(
            sprintf( 'ap-cms-ai:%s:%s', $outcome->feature, $outcome->statusSlug ),
            feature: $outcome->feature,
            message: $outcome->message,
        );
    }
}
