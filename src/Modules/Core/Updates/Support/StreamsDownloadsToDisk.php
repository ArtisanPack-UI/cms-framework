<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Stream update downloads straight to disk, closing the response body before
 * downstream HTTP listeners can materialize the release archive in memory.
 *
 * `Http::sink()` already writes the response body to a file rather than a PHP
 * string, but the resulting `Illuminate\Http\Client\Response` still exposes a
 * PSR-7 stream over that file. Third-party listeners on `ResponseReceived`
 * (Telescope, Sentry, custom monitoring) that call `$response->body()` will
 * then copy the entire archive back into a string via
 * `GuzzleHttp\Psr7\Utils::copyToString`, blowing the 128M `memory_limit` on
 * any release near ~half of it.
 *
 * This trait wraps `Http::sink()` with a response middleware that closes the
 * sink body as soon as Guzzle hands the response back and swaps in a fresh
 * in-memory empty stream — before the `Response` wrapper is constructed and
 * before `ResponseReceived` fires — so any later `body()` call returns an
 * empty string rather than reloading the archive or throwing on a detached
 * stream (see #224 for the Herd Pro / Telescope / Debugbar interaction).
 *
 * @since 2.5.2
 */
trait StreamsDownloadsToDisk
{
    /**
     * Download the given URL to a fresh temp file, streaming the body straight
     * to disk and closing the underlying sink stream before anyone can pull it
     * back into memory.
     *
     * @since 2.5.2
     *
     * @param  string  $downloadUrl  Absolute URL of the release archive.
     * @param  array<string, string>  $headers  Request headers to send.
     *
     * @throws UpdateException When the response is not a 2xx.
     * @throws Throwable On transport errors (partial file is removed first).
     *
     * @return string Absolute path to the downloaded file.
     */
    protected function streamDownloadToTempFile( string $downloadUrl, array $headers = [] ): string
    {
        $this->assertSecureDownloadUrl( $downloadUrl );

        $tempPath = storage_path( 'app/temp/update-' . bin2hex( random_bytes( 16 ) ) . '.zip' );

        if ( ! File::exists( dirname( $tempPath ) ) ) {
            File::makeDirectory( dirname( $tempPath ), 0755, true );
        }

        try {
            $response = Http::withHeaders( $headers )
                ->timeout( config( 'cms.updates.download_timeout', 300 ) )
                // Constrain redirects to the same scheme policy as the initial
                // URL. Validating only the URL we were handed is not enough:
                // Guzzle follows redirects by default, so an `https` release
                // URL that 302s to `http` would downgrade the transport
                // silently — and the archive it returns is executed as PHP.
                ->withOptions( [
                    'allow_redirects' => [
                        'max'       => 5,
                        'strict'    => true,
                        'protocols' => config( 'cms.updates.allow_insecure_transport', false )
                            ? ['http', 'https']
                            : ['https'],
                    ],
                ] )
                ->withResponseMiddleware( function ( ResponseInterface $response ): ResponseInterface {
                    $response->getBody()->close();

                    return $response->withBody( Utils::streamFor( '' ) );
                } )
                ->sink( $tempPath )
                ->get( $downloadUrl );

            if ( ! $response->successful() ) {
                throw UpdateException::downloadFailed( $downloadUrl );
            }

            return $tempPath;
        } catch ( Throwable $e ) {
            File::delete( $tempPath );

            throw $e;
        }
    }

    /**
     * Refuse to fetch a release archive over an insecure transport.
     *
     * `download_url` arrives from the update source's own metadata — for the
     * custom-JSON source, straight out of a remote document — and was passed
     * to the downloader with no scheme or host validation at all. TLS is one
     * of only three things standing between "update source compromised" and
     * "host owned", the others being the checksum and the exclusion list, and
     * this pipeline is by design an RCE channel: it overwrites PHP files and
     * then runs `composer install`, which executes `post-install-cmd` scripts
     * from the just-overwritten `composer.json`.
     *
     * `cms.updates.allow_insecure_transport` exists for air-gapped mirrors on
     * a trusted network. It defaults to false.
     *
     * @since 2.7.1
     *
     * @param  string  $downloadUrl  URL about to be fetched.
     *
     * @throws UpdateException When the URL is not https and the opt-out is off.
     */
    protected function assertSecureDownloadUrl( string $downloadUrl ): void
    {
        $scheme = strtolower( (string) parse_url( $downloadUrl, PHP_URL_SCHEME ) );

        if ( 'https' === $scheme ) {
            return;
        }

        if ( config( 'cms.updates.allow_insecure_transport', false ) ) {
            Log::warning( 'cms-framework: downloading an update over an insecure transport because cms.updates.allow_insecure_transport is enabled.', [
                'scheme' => $scheme,
            ] );

            return;
        }

        throw UpdateException::insecureDownloadUrl( $downloadUrl );
    }
}
