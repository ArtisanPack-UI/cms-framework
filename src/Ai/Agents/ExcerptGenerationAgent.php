<?php

/**
 * Excerpt generation agent.
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
 * Generate a natural-sounding excerpt (default `<= 200 chars`) from a
 * post's full content. Callers may raise the length cap up to 400 chars
 * for longer meta descriptions.
 *
 * ## Input
 *
 * ```
 * [
 *   'content'    => string,       // required, full post body
 *   'max_chars'  => int|null,     // optional, 80-400 (default 200)
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   excerpt: string,   // <= max_chars
 *   char_count: int    // actual excerpt length
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
class ExcerptGenerationAgent extends ArtisanPackAgent
{
    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'cms.excerpt';

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
You summarize a post's body into a single-paragraph excerpt suitable for a listing page or a meta description.

Requirements:
- Return one paragraph, at most `max_chars` characters (see the accompanying instruction). Prefer to end on a sentence boundary.
- Base the excerpt strictly on the supplied content. Do NOT invent facts.
- Do NOT start with "This post" / "In this article" / "The author" — write the excerpt as if it were the standalone description of the content.
- Preserve neutral, factual tone; do not editorialize or add calls to action unless the source content itself does.
- If the content is too short to summarize (e.g. a one-sentence post), return the content verbatim (or truncated at max_chars) rather than padding it out.

Return a JSON object with keys `excerpt` (string) and `char_count` (integer, the length of `excerpt`).
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
            'required'             => [ 'excerpt', 'char_count' ],
            'properties'           => [
                'excerpt'    => [ 'type' => 'string' ],
                'char_count' => [
                    'type'    => 'integer',
                    'minimum' => 0,
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
            'output'        => $this->validateOutput( $result['output'], $normalized['max_chars'] ),
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
     * @return array{ content: string, max_chars: int }
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

        $maxChars = 200;
        if ( isset( $input['max_chars'] ) ) {
            $parsed = filter_var(
                $input['max_chars'],
                FILTER_VALIDATE_INT,
                [ 'options' => [ 'min_range' => 80, 'max_range' => 400 ] ],
            );

            if ( false !== $parsed ) {
                $maxChars = $parsed;
            }
        }

        return [
            'content'   => $content,
            'max_chars' => $maxChars,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 2.3.0
     *
     * @param  array{ content: string, max_chars: int }  $input  Normalized input.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $input ): array
    {
        return [
            [ 'type' => 'text', 'text' => sprintf( 'max_chars: %d', $input['max_chars'] ) ],
            [ 'type' => 'text', 'text' => "Content:\n" . $input['content'] ],
        ];
    }

    /**
     * Enforce output invariants — clamp excerpt length and recompute
     * `char_count` from the returned excerpt so callers can trust it.
     *
     * @since 2.3.0
     *
     * @param  array<string, mixed>  $output    Decoded model output.
     * @param  int                   $maxChars  Requested cap.
     *
     * @return array{ excerpt: string, char_count: int }
     */
    protected function validateOutput( array $output, int $maxChars ): array
    {
        $excerpt = isset( $output['excerpt'] ) ? trim( (string) $output['excerpt'] ) : '';

        if ( mb_strlen( $excerpt ) > $maxChars ) {
            $excerpt = mb_substr( $excerpt, 0, $maxChars );
        }

        return [
            'excerpt'    => $excerpt,
            'char_count' => mb_strlen( $excerpt ),
        ];
    }
}
