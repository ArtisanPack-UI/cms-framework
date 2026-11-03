<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support\Composer;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;

/**
 * {@see WebOpcacheResetter} that records each reset instead of reaching for a
 * PHP-FPM socket, the loopback route or the operator's reload command.
 */
class RecordingWebOpcacheResetter extends WebOpcacheResetter
{
    /**
     * How many times reset() ran.
     */
    public int $resets = 0;

    /**
     * What reset() returns: the mechanism that worked, or null when none did.
     */
    public ?string $method = 'fastcgi';

    /**
     * What failures() returns.
     *
     * @var array<string, string>
     */
    public array $failureReasons = [];

    public function reset(): ?string
    {
        $this->resets++;

        return $this->method;
    }

    /**
     * @return array<string, string>
     */
    public function failures(): array
    {
        return $this->failureReasons;
    }
}
