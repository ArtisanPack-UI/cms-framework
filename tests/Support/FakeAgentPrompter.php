<?php

/**
 * Test fake for the AgentPrompter contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;

/**
 * Records every prompter call and returns queued responses in FIFO
 * order. Mirrors the fakes shipped by the ai package's own suite and
 * by visual-editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */
final class FakeAgentPrompter implements AgentPrompter
{
    /**
     * Recorded calls, in invocation order.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $calls = [];

    /**
     * Queued responses (FIFO).
     *
     * @var array<int, array<string, mixed>>
     */
    private array $queue = [];

    /**
     * Push a canned response onto the queue.
     *
     * @since 2.3.0
     *
     * @param  array<string, mixed>  $output        Structured output body.
     * @param  int                   $inputTokens   Reported input tokens.
     * @param  int                   $outputTokens  Reported output tokens.
     *
     * @return void
     */
    public function queue( array $output, int $inputTokens = 100, int $outputTokens = 50 ): void
    {
        $this->queue[] = [
            'output'        => $output,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function prompt(
        Credentials $credentials,
        string $model,
        string $instructions,
        string|array $message,
        array $outputSchema,
    ): array {
        $this->calls[] = [
            'credentials'   => $credentials,
            'model'         => $model,
            'instructions'  => $instructions,
            'message'       => $message,
            'output_schema' => $outputSchema,
        ];

        if ( [] === $this->queue ) {
            return [
                'output'        => [],
                'input_tokens'  => 0,
                'output_tokens' => 0,
            ];
        }

        return array_shift( $this->queue );
    }
}
