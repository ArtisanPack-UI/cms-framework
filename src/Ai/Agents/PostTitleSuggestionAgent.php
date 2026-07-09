<?php

/**
 * Post title suggestion agent.
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

/**
 * Content-authoring helper. Generate 3–5 title variants from the draft
 * body of a post. Consumable by any package that authors long-form
 * content (visual-editor, custom CMS surfaces, etc.).
 *
 * ## Input
 *
 * ```
 * [
 *   'content' => string,        // required, draft body
 *   'tone'    => string|null,   // optional, e.g. "playful", "authoritative"
 *   'count'   => int|null,      // optional, 3–5 (default 5)
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   titles: [ { title: string, rationale: string } ]
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
class PostTitleSuggestionAgent extends ArtisanPackAgent
{
    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'cms.post_title';

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
You generate title variants for a piece of long-form content.

Requirements:
- Return between 3 and 5 titles, ordered strongest-first.
- Every title must be <= 80 characters and free of trailing punctuation (except a question mark when the title is a question).
- Base every title strictly on the supplied content. Do NOT invent facts, statistics, names, or quotes.
- Vary the shape across the set — one direct/descriptive, one benefit-led, one curiosity/question if appropriate. Avoid clickbait.
- If a `tone` is supplied, bias all variants toward it without abandoning accuracy.
- `rationale` is a single sentence explaining why the title fits.

Return a JSON object with key `titles` (array of {title, rationale}).
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
            'required'             => [ 'titles' ],
            'properties'           => [
                'titles' => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'maxItems' => 5,
                    'items'    => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => [ 'title', 'rationale' ],
                        'properties'           => [
                            'title'     => [
                                'type'      => 'string',
                                'maxLength' => 80,
                            ],
                            'rationale' => [ 'type' => 'string' ],
                        ],
                    ],
                ],
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
            'output'        => $this->validateOutput( $result['output'], $normalized['count'] ),
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
     * @return array{ content: string, tone: string|null, count: int }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with a `content` key.',
            );
        }

        $content = isset( $input['content'] ) && is_string( $input['content'] ) ? trim( $input['content'] ) : '';

        if ( '' === $content ) {
            throw FeatureError::forFeature( $this->featureKey, '`content` must be a non-empty string.' );
        }

        $tone = null;
        if ( isset( $input['tone'] ) && is_string( $input['tone'] ) && '' !== trim( $input['tone'] ) ) {
            $tone = trim( $input['tone'] );
        }

        $count = 5;
        if ( isset( $input['count'] ) ) {
            $parsed = filter_var(
                $input['count'],
                FILTER_VALIDATE_INT,
                [ 'options' => [ 'min_range' => 3, 'max_range' => 5 ] ],
            );

            if ( false !== $parsed ) {
                $count = $parsed;
            }
        }

        return [
            'content' => $content,
            'tone'    => $tone,
            'count'   => $count,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 2.3.0
     *
     * @param  array{ content: string, tone: string|null, count: int }  $input  Normalized input.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $input ): array
    {
        $parts = [
            [ 'type' => 'text', 'text' => sprintf( 'Requested count: %d', $input['count'] ) ],
        ];

        if ( null !== $input['tone'] ) {
            $parts[] = [ 'type' => 'text', 'text' => sprintf( 'Tone: %s', $input['tone'] ) ];
        }

        $parts[] = [ 'type' => 'text', 'text' => "Content:\n" . $input['content'] ];

        return $parts;
    }

    /**
     * Enforce output invariants and clamp to the requested count.
     *
     * @since 2.3.0
     *
     * @param  array<string, mixed>  $output  Decoded model output.
     * @param  int                   $count   Requested title count.
     *
     * @return array{ titles: array<int, array{ title: string, rationale: string }> }
     */
    protected function validateOutput( array $output, int $count ): array
    {
        $raw = isset( $output['titles'] ) && is_array( $output['titles'] ) ? $output['titles'] : [];

        $clean = [];
        foreach ( $raw as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $title     = isset( $entry['title'] ) ? trim( (string) $entry['title'] ) : '';
            $rationale = isset( $entry['rationale'] ) ? trim( (string) $entry['rationale'] ) : '';

            if ( '' === $title || '' === $rationale ) {
                continue;
            }

            if ( mb_strlen( $title ) > 80 ) {
                $title = mb_substr( $title, 0, 80 );
            }

            $clean[] = [
                'title'     => $title,
                'rationale' => $rationale,
            ];

            if ( count( $clean ) === $count ) {
                break;
            }
        }

        return [ 'titles' => $clean ];
    }
}
