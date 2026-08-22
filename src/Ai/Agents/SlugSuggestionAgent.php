<?php

/**
 * Slug suggestion agent.
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
use Illuminate\Support\Str;

/**
 * Suggest a URL slug for a post from its title (and optional excerpt).
 * Unlike a straight kebab-case of the title, this agent optimizes for
 * SEO: it drops stop words, keeps the keyword-forward tokens, and stays
 * short (default 60 char cap).
 *
 * Uniqueness against existing slugs is intentionally NOT part of the
 * agent's contract — the caller enforces uniqueness (Eloquent lookup,
 * database constraint, ...) with the returned slug.
 *
 * ## Input
 *
 * ```
 * [
 *   'title'     => string,       // required
 *   'excerpt'   => string|null,  // optional, helps disambiguate short titles
 *   'max_chars' => int|null,     // optional, 20-100 (default 60)
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   slug:       string,      // sanitized kebab-case slug, <= max_chars
 *   alternates: string[]     // 0-2 backup slugs for uniqueness collisions
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
class SlugSuggestionAgent extends ArtisanPackAgent
{
    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'cms.suggest_slug';

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
You generate a URL slug from a post title.

Requirements:
- The slug MUST be lowercase kebab-case (ASCII letters, digits, and hyphens only). No leading, trailing, or consecutive hyphens.
- Drop stop words (the, a, an, and, or, but, for, on, of, to, with, in, at, by, from, is, are, was, were, be, been, being) unless removing them would obscure meaning.
- Prefer the noun/keyword-carrying tokens of the title over filler.
- Do NOT translate. Transliterate non-ASCII characters into their closest ASCII equivalent.
- Stay within `max_chars` characters. Cut a whole trailing word rather than truncating mid-word.
- Return up to 2 `alternates` — each also compliant with the rules above — as fallback options a caller can try on a uniqueness collision. Zero alternates is fine when only one obvious slug fits.

Return a JSON object with keys `slug` (string) and `alternates` (array of strings).
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
            'required'             => [ 'slug', 'alternates' ],
            'properties'           => [
                'slug'       => [ 'type' => 'string' ],
                'alternates' => [
                    'type'     => 'array',
                    'maxItems' => 2,
                    'items'    => [ 'type' => 'string' ],
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function execute( Credentials $credentials, string $model, string $instructions ): array
    {
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- input() flows into normalizeInput(), which validates the array and trims title/excerpt.
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
     * @return array{ title: string, excerpt: string|null, max_chars: int }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with a `title` key.',
            );
        }

        $title = isset( $input['title'] ) && is_string( $input['title'] ) ? trim( $input['title'] ) : '';

        if ( '' === $title ) {
            throw FeatureError::forFeature( $this->featureKey, '`title` must be a non-empty string.' );
        }

        $excerpt = null;
        if ( isset( $input['excerpt'] ) && is_string( $input['excerpt'] ) && '' !== trim( $input['excerpt'] ) ) {
            $excerpt = trim( $input['excerpt'] );
        }

        $maxChars = 60;
        if ( isset( $input['max_chars'] ) ) {
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- filter_var(..., FILTER_VALIDATE_INT, range 20-100) is validation constraining max_chars.
            $parsed = filter_var(
                $input['max_chars'],
                FILTER_VALIDATE_INT,
                [ 'options' => [ 'min_range' => 20, 'max_range' => 100 ] ],
            );

            if ( false !== $parsed ) {
                $maxChars = $parsed;
            }
        }

        return [
            'title'     => $title,
            'excerpt'   => $excerpt,
            'max_chars' => $maxChars,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 2.3.0
     *
     * @param  array{ title: string, excerpt: string|null, max_chars: int }  $input  Normalized input.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $input ): array
    {
        $parts = [
            [ 'type' => 'text', 'text' => sprintf( 'max_chars: %d', $input['max_chars'] ) ],
            [ 'type' => 'text', 'text' => 'Title: ' . $input['title'] ],
        ];

        if ( null !== $input['excerpt'] ) {
            $parts[] = [ 'type' => 'text', 'text' => "Excerpt:\n" . $input['excerpt'] ];
        }

        return $parts;
    }

    /**
     * Enforce output invariants — sanitize each slug so a hallucinated
     * uppercase-with-underscores string can't leak through.
     *
     * @since 2.3.0
     *
     * @param  array<string, mixed>  $output    Decoded model output.
     * @param  int                   $maxChars  Requested cap.
     *
     * @return array{ slug: string, alternates: array<int, string> }
     */
    protected function validateOutput( array $output, int $maxChars ): array
    {
        $slug = $this->sanitizeSlug( isset( $output['slug'] ) ? (string) $output['slug'] : '', $maxChars );

        $alternates = [];
        $rawAlts    = isset( $output['alternates'] ) && is_array( $output['alternates'] ) ? $output['alternates'] : [];

        foreach ( $rawAlts as $candidate ) {
            if ( ! is_string( $candidate ) ) {
                continue;
            }

            $clean = $this->sanitizeSlug( $candidate, $maxChars );
            if ( '' === $clean || $clean === $slug || in_array( $clean, $alternates, true ) ) {
                continue;
            }

            $alternates[] = $clean;

            if ( 2 === count( $alternates ) ) {
                break;
            }
        }

        return [
            'slug'       => $slug,
            'alternates' => $alternates,
        ];
    }

    /**
     * Coerce a raw string into a compliant lowercase kebab-case slug and
     * clamp it to `max_chars`, cutting on a hyphen boundary when
     * possible so we don't truncate mid-word.
     *
     * Delegates the ASCII-folding + kebab-case pipeline to `Str::slug()`.
     * The previous byte-oriented `preg_replace( '/[^a-z0-9-]+/', '-', … )`
     * collapsed every non-ASCII byte to a hyphen, so a model return like
     * `cafe-culture` (with an acute-e) became `caf-culture`. `Str::slug`
     * transliterates instead, matching the agent's documented
     * "transliterate non-ASCII" contract.
     *
     * @since 2.3.0
     *
     * @param  string  $raw       Raw candidate slug.
     * @param  int     $maxChars  Length cap.
     *
     * @return string
     */
    protected function sanitizeSlug( string $raw, int $maxChars ): string
    {
        $slug = Str::slug( $raw, '-' );

        if ( strlen( $slug ) <= $maxChars ) {
            return $slug;
        }

        $truncated  = substr( $slug, 0, $maxChars );
        $lastHyphen = strrpos( $truncated, '-' );

        // Prefer cutting on a hyphen so we don't leave a partial word
        // hanging at the end of the slug — but only when doing so keeps
        // a meaningful portion of the string (guard against a single
        // leading word blowing past the cap).
        if ( false !== $lastHyphen && $lastHyphen > 0 ) {
            $truncated = substr( $truncated, 0, $lastHyphen );
        }

        return trim( $truncated, '-' );
    }
}
