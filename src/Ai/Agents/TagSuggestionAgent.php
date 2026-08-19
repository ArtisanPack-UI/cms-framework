<?php

/**
 * Tag suggestion agent.
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
 * Suggest tags for a post from an existing taxonomy. By default the
 * agent MUST pick from `available_tags` and never invents new tags. Set
 * `allow_new: true` to unlock a `suggested_new` array of candidate tag
 * names — hosts remain responsible for creating them.
 *
 * ## Input
 *
 * ```
 * [
 *   'content'        => string,          // required
 *   'available_tags' => string[],        // required, existing tag names
 *   'allow_new'      => bool|null,       // optional, default false
 *   'max_selected'   => int|null,        // optional, 1-10 (default 5)
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   selected:      [ { tag: string, confidence: float } ],
 *   suggested_new: string[]              // only when allow_new is true
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
class TagSuggestionAgent extends ArtisanPackAgent
{
    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'cms.suggest_tags';

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
You select tags for a piece of content from a fixed taxonomy.

Requirements:
- Every entry in `selected.tag` MUST come from the supplied `available_tags` list, matched by exact string. Case must match.
- `confidence` is 0.0 (weak signal) to 1.0 (strong signal). Prefer omitting a tag over guessing — an empty `selected` array is a valid answer.
- Return at most `max_selected` entries, ordered by confidence descending.
- Base every selection on evidence in the supplied content. Do NOT project topics that are not present.
- If `allow_new` is true, you MAY additionally return `suggested_new` — a short list of tag names that would meaningfully cover a topic present in the content but missing from `available_tags`. Do not repeat names that are already in `available_tags` (case-insensitive). Each new name must be a short, lowercase, kebab-case slug (2–4 words at most). Omit `suggested_new` entirely (or return an empty array) when nothing is missing.
- If `allow_new` is false or absent, do NOT include `suggested_new` at all — the taxonomy is closed.

Return a JSON object with keys: `selected` (array of {tag, confidence}) and optionally `suggested_new` (array of strings).
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
            'required'             => [ 'selected' ],
            'properties'           => [
                'selected'      => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => [ 'tag', 'confidence' ],
                        'properties'           => [
                            'tag'        => [ 'type' => 'string' ],
                            'confidence' => [
                                'type'    => 'number',
                                'minimum' => 0,
                                'maximum' => 1,
                            ],
                        ],
                    ],
                ],
                'suggested_new' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function execute( Credentials $credentials, string $model, string $instructions ): array
    {
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- input() is passed to normalizeInput(), which validates content and available_tags shape.
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
            'output'        => $this->validateOutput( $result['output'], $normalized ),
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
     * @return array{ content: string, available_tags: array<int, string>, allow_new: bool, max_selected: int }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with `content` and `available_tags` keys.',
            );
        }

        $content = isset( $input['content'] ) && is_string( $input['content'] ) ? trim( $input['content'] ) : '';

        if ( '' === $content ) {
            throw FeatureError::forFeature( $this->featureKey, '`content` must be a non-empty string.' );
        }

        if ( ! isset( $input['available_tags'] ) || ! is_array( $input['available_tags'] ) ) {
            throw FeatureError::forFeature( $this->featureKey, '`available_tags` must be an array of strings.' );
        }

        $tags = [];
        foreach ( $input['available_tags'] as $tag ) {
            if ( is_string( $tag ) && '' !== trim( $tag ) ) {
                $tags[] = trim( $tag );
            }
        }

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- filter_var(..., FILTER_VALIDATE_BOOLEAN) is validation coercing allow_new to a strict bool.
        $allowNew = isset( $input['allow_new'] ) && filter_var( $input['allow_new'], FILTER_VALIDATE_BOOLEAN );

        $maxSelected = 5;
        if ( isset( $input['max_selected'] ) ) {
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- filter_var(..., FILTER_VALIDATE_INT, range 1-10) is validation constraining max_selected.
            $parsed = filter_var(
                $input['max_selected'],
                FILTER_VALIDATE_INT,
                [ 'options' => [ 'min_range' => 1, 'max_range' => 10 ] ],
            );

            if ( false !== $parsed ) {
                $maxSelected = $parsed;
            }
        }

        return [
            'content'        => $content,
            'available_tags' => $tags,
            'allow_new'      => $allowNew,
            'max_selected'   => $maxSelected,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 2.3.0
     *
     * @param  array{ content: string, available_tags: array<int, string>, allow_new: bool, max_selected: int }  $input  Normalized input.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $input ): array
    {
        return [
            [ 'type' => 'text', 'text' => sprintf( 'allow_new: %s', $input['allow_new'] ? 'true' : 'false' ) ],
            [ 'type' => 'text', 'text' => sprintf( 'max_selected: %d', $input['max_selected'] ) ],
            [ 'type' => 'text', 'text' => 'available_tags: ' . implode( ', ', $input['available_tags'] ) ],
            [ 'type' => 'text', 'text' => "Content:\n" . $input['content'] ],
        ];
    }

    /**
     * Enforce output invariants — drop hallucinated tags, clamp size,
     * and only include `suggested_new` when the caller allowed it.
     *
     * @since 2.3.0
     *
     * @param  array<string, mixed>                                                                              $output      Decoded model output.
     * @param  array{ content: string, available_tags: array<int, string>, allow_new: bool, max_selected: int }  $normalized  Normalized input.
     *
     * @return array{ selected: array<int, array{ tag: string, confidence: float }>, suggested_new?: array<int, string> }
     */
    protected function validateOutput( array $output, array $normalized ): array
    {
        $allowed = array_flip( $normalized['available_tags'] );

        $selected    = [];
        $rawSelected = isset( $output['selected'] ) && is_array( $output['selected'] ) ? $output['selected'] : [];

        foreach ( $rawSelected as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            // Trim BEFORE the allow-list lookup — `normalizeInput` trimmed
            // every `available_tags` entry, so a model return of `"laravel "`
            // (with incidental whitespace) would silently fail the isset
            // check and drop an otherwise-valid selection.
            $tag = isset( $entry['tag'] ) ? trim( (string) $entry['tag'] ) : '';

            if ( '' === $tag || ! isset( $allowed[ $tag ] ) ) {
                continue;
            }

            $confidence = isset( $entry['confidence'] ) ? (float) $entry['confidence'] : 0.0;
            $confidence = max( 0.0, min( 1.0, $confidence ) );

            $selected[] = [
                'tag'        => $tag,
                'confidence' => $confidence,
            ];

            if ( count( $selected ) === $normalized['max_selected'] ) {
                break;
            }
        }

        $result = [ 'selected' => $selected ];

        if ( $normalized['allow_new'] ) {
            $existingLower = array_flip( array_map( 'strtolower', $normalized['available_tags'] ) );
            $seen          = [];
            $new           = [];

            $rawNew = isset( $output['suggested_new'] ) && is_array( $output['suggested_new'] )
                ? $output['suggested_new']
                : [];

            foreach ( $rawNew as $candidate ) {
                if ( ! is_string( $candidate ) ) {
                    continue;
                }

                $trimmed = trim( $candidate );
                if ( '' === $trimmed ) {
                    continue;
                }

                $lower = strtolower( $trimmed );
                if ( isset( $existingLower[ $lower ] ) || isset( $seen[ $lower ] ) ) {
                    continue;
                }

                $seen[ $lower ] = true;
                $new[]          = $trimmed;
            }

            $result['suggested_new'] = $new;
        }

        return $result;
    }
}
