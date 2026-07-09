<?php

/**
 * Category suggestion agent.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Ai\Agents;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use JsonException;

/**
 * Suggest a single category for a post from a hierarchical taxonomy.
 * Unlike tags, category assignment is usually single-select and picks
 * the most specific matching node — the returned string is the full
 * slash-delimited path (e.g. `News/Product Updates`) so parent
 * disambiguation is unambiguous.
 *
 * ## Input
 *
 * ```
 * [
 *   'content'       => string,   // required
 *   'category_tree' => array,    // required, nested list of {name, children?}
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   selected:   string,      // slash-delimited path into category_tree, or "" if none fit
 *   confidence: float,       // 0.0 - 1.0
 *   rationale:  string       // single sentence
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
class CategorySuggestionAgent extends ArtisanPackAgent
{
    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'cms.suggest_category';

    /**
     * {@inheritDoc}
     */
    public string $package = 'artisanpack-ui/cms-framework';

    /**
     * {@inheritDoc}
     */
    public string $defaultModel = 'claude-haiku-4-5';

    /**
     * {@inheritDoc}
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You pick a single category for a piece of content from a hierarchical taxonomy.

Requirements:
- `selected` MUST be a slash-delimited path whose segments each match a `name` from `category_tree`, walked from a root down through any children. Case must match exactly.
- Prefer the most specific matching leaf. Only pick an ancestor when no descendant fits.
- If no category fits with reasonable confidence, return `selected` as an empty string, `confidence` as 0, and explain why in `rationale`. Never guess.
- `confidence` is 0.0 (weak signal) to 1.0 (strong signal).
- `rationale` is a single sentence tying the choice back to specific evidence in the content.

Return a JSON object with keys: `selected` (string), `confidence` (float 0..1), `rationale` (string).
PROMPT;
    }

    /**
     * {@inheritDoc}
     */
    public function outputSchema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => [ 'selected', 'confidence', 'rationale' ],
            'properties'           => [
                'selected'   => [ 'type' => 'string' ],
                'confidence' => [
                    'type'    => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
                'rationale'  => [ 'type' => 'string' ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function execute( Credentials $credentials, string $model, string $instructions ): array
    {
        $normalized = $this->normalizeInput( $this->input() );

        $prompter = app( AgentPrompter::class );

        $result = $prompter->prompt(
            credentials: $credentials,
            model: $model,
            instructions: $instructions,
            message: $this->buildMessage( $normalized ),
            outputSchema: $this->outputSchema(),
        );

        return [
            'output'        => $this->validateOutput( $result['output'], $normalized['valid_paths'] ),
            'input_tokens'  => (int) ( $result['input_tokens'] ?? 0 ),
            'output_tokens' => (int) ( $result['output_tokens'] ?? 0 ),
        ];
    }

    /**
     * Validate and shape-check the raw agent input.
     *
     * @since 2.3.0
     *
     * @param  mixed  $input  Raw agent input.
     *
     * @return array{ content: string, category_tree: array<int, mixed>, valid_paths: array<string, true> }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with `content` and `category_tree` keys.',
            );
        }

        $content = isset( $input['content'] ) && is_string( $input['content'] ) ? trim( $input['content'] ) : '';

        if ( '' === $content ) {
            throw FeatureError::forFeature( $this->featureKey, '`content` must be a non-empty string.' );
        }

        if ( ! isset( $input['category_tree'] ) || ! is_array( $input['category_tree'] ) ) {
            throw FeatureError::forFeature( $this->featureKey, '`category_tree` must be an array.' );
        }

        $tree        = array_values( $input['category_tree'] );
        $validPaths  = [];
        $this->collectPaths( $tree, '', $validPaths );

        return [
            'content'       => $content,
            'category_tree' => $tree,
            'valid_paths'   => $validPaths,
        ];
    }

    /**
     * Walk the category tree, building a flat lookup of every valid
     * slash-delimited path. Entries without a `name` are silently
     * skipped so a malformed leaf can't reject the whole tree.
     *
     * @since 2.3.0
     *
     * @param  array<int, mixed>    $nodes    Sibling nodes at this depth.
     * @param  string               $prefix   Accumulated path prefix.
     * @param  array<string, true>  $paths    Accumulator (by reference).
     *
     * @return void
     */
    protected function collectPaths( array $nodes, string $prefix, array &$paths ): void
    {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            $name = isset( $node['name'] ) && is_string( $node['name'] ) ? trim( $node['name'] ) : '';
            if ( '' === $name ) {
                continue;
            }

            // Reject names containing the path separator. If we accepted them
            // we would produce paths indistinguishable from real parent/child
            // paths — e.g. `{name: 'News/Updates'}` and `{name: 'News',
            // children: [{name: 'Updates'}]}` both serialize to `News/Updates`
            // and the validator would be unable to disambiguate a hallucinated
            // model pick from a real one.
            if ( str_contains( $name, '/' ) ) {
                continue;
            }

            $path           = '' === $prefix ? $name : $prefix . '/' . $name;
            $paths[ $path ] = true;

            if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
                $this->collectPaths( $node['children'], $path, $paths );
            }
        }
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 2.3.0
     *
     * @param  array{ content: string, category_tree: array<int, mixed>, valid_paths: array<string, true> }  $input  Normalized input.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $input ): array
    {
        try {
            $encoded = json_encode(
                $input['category_tree'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch ( JsonException $exception ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                sprintf( 'category_tree could not be serialized: %s', $exception->getMessage() ),
                $exception,
            );
        }

        return [
            [ 'type' => 'text', 'text' => 'category_tree: ' . $encoded ],
            [ 'type' => 'text', 'text' => "Content:\n" . $input['content'] ],
        ];
    }

    /**
     * Enforce output invariants — drop hallucinated paths, clamp
     * confidence, and coerce an empty selection to zero confidence so
     * downstream callers can trust the pair.
     *
     * @since 2.3.0
     *
     * @param  array<string, mixed>  $output       Decoded model output.
     * @param  array<string, true>   $validPaths   Set of legal slash-paths.
     *
     * @return array{ selected: string, confidence: float, rationale: string }
     */
    protected function validateOutput( array $output, array $validPaths ): array
    {
        $selected = isset( $output['selected'] ) ? trim( (string) $output['selected'] ) : '';

        if ( '' !== $selected && ! isset( $validPaths[ $selected ] ) ) {
            $selected = '';
        }

        $confidence = isset( $output['confidence'] ) ? (float) $output['confidence'] : 0.0;
        $confidence = max( 0.0, min( 1.0, $confidence ) );

        if ( '' === $selected ) {
            $confidence = 0.0;
        }

        $rationale = isset( $output['rationale'] ) ? trim( (string) $output['rationale'] ) : '';

        return [
            'selected'   => $selected,
            'confidence' => $confidence,
            'rationale'  => $rationale,
        ];
    }
}
