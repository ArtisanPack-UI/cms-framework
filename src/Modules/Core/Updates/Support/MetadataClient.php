<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Metadata Client
 *
 * Performs the updater's small metadata GETs (release feeds, single-release
 * lookups, SHA-256 sidecars, custom JSON endpoints) through a raw Guzzle
 * client that bypasses Laravel's HTTP factory event dispatch.
 *
 * Laravel dispatches `Illuminate\Http\Client\Events\RequestSending` and
 * `ResponseReceived` around every `Http::` request. Any userland listener on
 * those events — Herd Pro's `HttpClientWatcher`, Telescope's HTTP client
 * watcher, Debugbar, custom monitoring hooks — can block or corrupt the
 * request lifecycle. When Herd's dump-server socket is stopped or wedged,
 * the listener blocks for the full `max_execution_time` and the feed check
 * dies at `phar://.../dump.phar/src/Herd.php:56` instead of the ~200ms it
 * would take against the real endpoint (see #231). #224 shielded the
 * download response body against the same class of interaction; this class
 * extends the invariant to the feed / version / sidecar / JSON metadata
 * requests, which have no operational value in an observer's timeline.
 *
 * Raw Guzzle does not dispatch Laravel's HTTP client events, so no watcher
 * can inspect or block these requests.
 *
 * ### Testing
 *
 * Because production traffic bypasses the `Http::` facade, `Http::fake()` no
 * longer intercepts these calls. For test convenience, {@see useHttpFacadeBridge}
 * routes them back through `Http::` so existing `Http::fake()` and
 * `Http::assertSent()` calls continue to work. Regression tests that need to
 * exercise the raw path (to prove no `RequestSending` listener fires) inject
 * a mock Guzzle client via {@see setClient}.
 *
 * @since 2.5.4
 */
final class MetadataClient
{
    /**
     * Optional override that routes calls through a custom responder instead of
     * the raw Guzzle client. Used by {@see useHttpFacadeBridge} to keep
     * `Http::fake()` in play for existing tests.
     *
     * @var (Closure(string, array<string, string>, int): array{status: int, body: string})|null
     *
     * @since 2.5.4
     */
    private static ?Closure $responder = null;

    /**
     * Optional Guzzle client override injected by tests that need to exercise
     * the raw-Guzzle path without hitting the network.
     *
     * @since 2.5.4
     */
    private static ?ClientInterface $client = null;

    /**
     * Perform a GET against the given URL and return the status + body.
     *
     * The call bypasses Laravel's HTTP client factory (and therefore its
     * `RequestSending` / `ResponseReceived` event dispatch) unless a test
     * bridge or client override is installed.
     *
     * @since 2.5.4
     *
     * @param  string                 $url             Absolute URL to fetch.
     * @param  array<string, string>  $headers         Request headers.
     * @param  int|null               $timeoutSeconds  Per-request timeout. Falls back to
     *                                                 `cms.updates.http_timeout` (15s).
     * @param  int                    $retries         Additional retry attempts on transport
     *                                                 error or 5xx response. Zero means one
     *                                                 attempt total.
     *
     * @throws UpdateException When every attempt fails at the transport layer.
     *
     * @return array{status: int, body: string} Response status and raw body.
     */
    public static function get(
        string $url,
        array $headers = [],
        ?int $timeoutSeconds = null,
        int $retries = 0,
    ): array {
        $timeout = $timeoutSeconds ?? (int) config( 'cms.updates.http_timeout', 15 );

        if ( null !== self::$responder ) {
            return ( self::$responder )( $url, $headers, $timeout );
        }

        $attempts  = max( 1, $retries + 1 );
        $lastError = null;
        $last      = null;

        for ( $attempt = 0; $attempt < $attempts; $attempt++ ) {
            try {
                $response = self::client()->request( 'GET', $url, [
                    'headers'         => $headers,
                    'timeout'         => $timeout,
                    'connect_timeout' => $timeout,
                    'http_errors'     => false,
                ] );

                $last = [
                    'status' => $response->getStatusCode(),
                    'body'   => (string) $response->getBody(),
                ];

                // Retry on 5xx (matches Laravel's `Http::retry()` default), return everything else.
                if ( $last['status'] < 500 ) {
                    return $last;
                }
            } catch ( Throwable $e ) {
                $lastError = $e;
            }
        }

        if ( null !== $last ) {
            return $last;
        }

        throw UpdateException::versionCheckFailed(
            'HTTP request failed: ' . ( $lastError?->getMessage() ?? 'unknown transport error' ),
        );
    }

    /**
     * Route future calls through Laravel's `Http::` facade so `Http::fake()`
     * continues to intercept them in tests.
     *
     * Production callers must never invoke this — it re-enables the exact
     * event dispatch this class exists to bypass.
     *
     * @since 2.5.4
     */
    public static function useHttpFacadeBridge(): void
    {
        self::$responder = static function ( string $url, array $headers, int $timeout ): array {
            $response = Http::withHeaders( $headers )
                ->timeout( $timeout )
                ->get( $url );

            return [
                'status' => $response->status(),
                'body'   => $response->body(),
            ];
        };
    }

    /**
     * Inject a Guzzle client (typically backed by `MockHandler`) so tests can
     * exercise the raw-Guzzle path without hitting the network.
     *
     * @since 2.5.4
     */
    public static function setClient( ?ClientInterface $client ): void
    {
        self::$client = $client;
    }

    /**
     * Clear any bridge or injected client. Tests should call this in tearDown.
     *
     * @since 2.5.4
     */
    public static function reset(): void
    {
        self::$responder = null;
        self::$client    = null;
    }

    /**
     * Resolve the Guzzle client to use for the next request.
     *
     * @since 2.5.4
     */
    private static function client(): ClientInterface
    {
        return self::$client ?? new Client();
    }
}
