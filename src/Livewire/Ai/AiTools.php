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

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\CMSFramework\Ai\Agents\CategorySuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\ExcerptGenerationAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\TagSuggestionAgent;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

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
            'cms.post_title',
            fn () => PostTitleSuggestionAgent::for( [
                'content' => $content,
                'tone'    => $tone,
                'count'   => $count,
            ] )->run(),
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
            'cms.excerpt',
            fn () => ExcerptGenerationAgent::for( [
                'content'   => $content,
                'max_chars' => $maxChars,
            ] )->run(),
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
            'cms.suggest_tags',
            fn () => TagSuggestionAgent::for( [
                'content'        => $content,
                'available_tags' => $availableTags,
                'allow_new'      => $allowNew,
                'max_selected'   => $maxSelected,
            ] )->run(),
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
            'cms.suggest_category',
            fn () => CategorySuggestionAgent::for( [
                'content'       => $content,
                'category_tree' => $categoryTree,
            ] )->run(),
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
            'cms.suggest_slug',
            fn () => SlugSuggestionAgent::for( [
                'title'     => $title,
                'excerpt'   => $excerpt,
                'max_chars' => $maxChars,
            ] )->run(),
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
        /** @var FeatureRegistry $registry */
        $registry = app( FeatureRegistry::class );

        $state = [];
        foreach ( CMSFrameworkServiceProvider::AI_FEATURE_KEYS as $key ) {
            $state[ $key ] = null !== $registry->get( $key ) && $registry->isToggleOn( $key );
        }
        return $state;
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
     * Shared run-and-emit path. Kept private so callers can only reach
     * agents through the five public entry points, each of which
     * pre-shapes its input.
     *
     * @since 2.3.0
     *
     * @param  string   $featureKey  Feature key (for status events).
     * @param  callable $callback    Callable that runs the agent and returns its output.
     *
     * @return void
     */
    private function run( string $featureKey, callable $callback ): void
    {
        try {
            $output = $callback();

            $this->dispatch(
                sprintf( 'ap-cms-ai:%s:success', $featureKey ),
                feature: $featureKey,
                output: $output,
            );
        } catch ( FeatureDisabledException $e ) {
            $this->dispatch(
                sprintf( 'ap-cms-ai:%s:disabled', $featureKey ),
                feature: $featureKey,
                message: $e->getMessage(),
            );
        } catch ( MissingCredentialsException $e ) {
            $this->dispatch(
                sprintf( 'ap-cms-ai:%s:missing-credentials', $featureKey ),
                feature: $featureKey,
                message: $e->getMessage(),
            );
        } catch ( FeatureError $e ) {
            // Validation-style failure (missing required field, hallucinated
            // tag, etc.). Emit under a distinct event so front-ends can
            // route it to a form-level warning instead of a generic
            // error toast — mirrors the HTTP surface's 422 status.
            $this->dispatch(
                sprintf( 'ap-cms-ai:%s:invalid-input', $featureKey ),
                feature: $featureKey,
                message: $e->getMessage(),
            );
        } catch ( Throwable $e ) {
            Log::error( 'cms-framework AI trigger failed', [
                'feature' => $featureKey,
                'error'   => $e->getMessage(),
            ] );

            $this->dispatch(
                sprintf( 'ap-cms-ai:%s:error', $featureKey ),
                feature: $featureKey,
                message: 'Unexpected error running AI feature.',
            );
        }
    }
}
