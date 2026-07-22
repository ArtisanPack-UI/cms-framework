<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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
        $tempPath = storage_path( 'app/temp/update-' . bin2hex( random_bytes( 16 ) ) . '.zip' );

        if ( ! File::exists( dirname( $tempPath ) ) ) {
            File::makeDirectory( dirname( $tempPath ), 0755, true );
        }

        try {
            $response = Http::withHeaders( $headers )
                ->timeout( config( 'cms.updates.download_timeout', 300 ) )
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
}
