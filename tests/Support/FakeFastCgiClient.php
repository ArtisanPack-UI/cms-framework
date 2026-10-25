<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\FastCgiClient;
use RuntimeException;

/**
 * FastCGI client double that runs the reset script with the CLI instead of
 * FPM, so a test proves the script is valid, and returns canned output.
 *
 * @since 2.16.0
 */
class FakeFastCgiClient extends FastCgiClient
{
    /**
     * Address the last request was sent to.
     *
     * @since 2.16.0
     */
    public string $address = '';

    /**
     * Params of the last request.
     *
     * @since 2.16.0
     *
     * @var array<string, string>
     */
    public array $params = [];

    /**
     * What the script printed when run with the CLI.
     *
     * @since 2.16.0
     */
    public string $scriptOutput = '';

    /**
     * Build the double.
     *
     * @since 2.16.0
     *
     * @param  string  $output  Stdout to return.
     */
    public function __construct( protected string $output )
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.16.0
     */
    public function run( string $address, array $params, float $timeout ): string
    {
        $this->address = $address;
        $this->params  = $params;

        if ( ! is_file( $params['SCRIPT_FILENAME'] ) ) {
            throw new RuntimeException( 'The reset script was not written before the request.' );
        }

        $this->scriptOutput = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $params['SCRIPT_FILENAME'] ) );

        return $this->output;
    }
}
