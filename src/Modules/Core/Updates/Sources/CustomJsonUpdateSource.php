<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\MetadataClient;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\StreamsDownloadsToDisk;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;

/**
 * Custom JSON Update Source
 *
 * Fetches updates from custom JSON endpoints.
 *
 * @since 1.0.0
 */
class CustomJsonUpdateSource implements UpdateSourceInterface
{
    use StreamsDownloadsToDisk;

    /**
     * Query parameters for authentication or other purposes.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    protected array $queryParams = [];

    /**
     * Request headers carrying the feed credential.
     *
     * Sent with the feed request, and with the archive download only while it
     * stays on the feed's own origin.
     *
     * @since 2.12.0
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Create a new CustomJsonUpdateSource instance.
     *
     * @since 1.0.0
     *
     * @param  string  $url  JSON endpoint URL
     * @param  string  $currentVersion  Current version
     */
    public function __construct(
        protected string $url,
        protected string $currentVersion,
    ) {
    }

    /**
     * Check if this source supports the given URL.
     *
     * This is the fallback source - supports any URL.
     *
     * @since 1.0.0
     *
     * @param  string  $url  URL to check
     *
     * @return bool Always returns true (fallback source)
     */
    public function supports( string $url ): bool
    {
        return true;
    }

    /**
     * Check for available updates.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     *
     * @return UpdateInfo Update information
     */
    public function checkForUpdate(): UpdateInfo
    {
        $data = $this->fetchJson();

        // Validate required fields
        if ( ! isset( $data['version'] ) ) {
            throw UpdateException::missingRequiredField( 'version' );
        }

        if ( ! isset( $data['download_url'] ) ) {
            throw UpdateException::missingRequiredField( 'download_url' );
        }

        return UpdateInfo::fromArray( $data, $this->currentVersion );
    }

    /**
     * Download the specified version.
     *
     * @since 1.0.0
     *
     * @param  string  $version  Version to download
     *
     * @throws UpdateException
     *
     * @return string Path to downloaded ZIP file
     */
    public function downloadUpdate( string $version ): string
    {
        // Fetch update info - for custom JSON, check if version parameter is supported
        // If version is 'latest' or null, use checkForUpdate()
        if ( 'latest' === $version || empty( $version ) ) {
            $data = $this->fetchJson();
        } else {
            // Try to fetch specific version by passing version as query param
            $originalParams               = $this->queryParams;
            $this->queryParams['version'] = $version;
            $data                         = $this->fetchJson();
            $this->queryParams            = $originalParams;
        }

        if ( ! isset( $data['download_url'] ) ) {
            throw UpdateException::missingRequiredField( 'download_url' );
        }

        // The credential headers are scoped to the feed's own origin: the feed
        // response chooses `download_url`, so sending them unconditionally
        // would hand the credential to any host the response names.
        $feedOrigin = $this->originOf( $this->url );

        return $this->streamDownloadToTempFile(
            $data['download_url'],
            null === $feedOrigin ? [] : $this->headers,
            $feedOrigin,
        );
    }

    /**
     * Set authentication credentials.
     *
     * Three shapes are accepted:
     *
     * - A string is sent as the `token` query parameter.
     * - A flat array is sent as query parameters.
     * - An array with a `headers` key sends those as request headers instead,
     *   keeping the credential out of URLs and access logs, e.g.
     *   `['headers' => ['Authorization' => 'Bearer …']]`. An optional `query`
     *   key carries any query parameters to send alongside them.
     *
     * Header mode is opt-in: a string still means `?token=`, so existing feeds
     * are unaffected.
     *
     * @since 1.0.0
     * @since 2.12.0 Accepts the `['headers' => [...], 'query' => [...]]` shape.
     *
     * @param  array|string  $credentials  Token string, query parameters, or a `headers` / `query` array.
     */
    public function setAuthentication( string|array $credentials ): void
    {
        // Each call replaces the header credential, so switching back to a
        // query-string mode cannot leave an earlier header in force.
        if ( is_string( $credentials ) ) {
            $this->headers              = [];
            $this->queryParams['token'] = $credentials;

            return;
        }

        if ( ! is_array( $credentials['headers'] ?? null ) ) {
            $this->headers     = [];
            $this->queryParams = $credentials;

            return;
        }

        $this->headers     = $this->normalizeHeaders( $credentials['headers'] );
        $this->queryParams = is_array( $credentials['query'] ?? null ) ? $credentials['query'] : [];
    }

    /**
     * Get the source name.
     *
     * @since 1.0.0
     *
     * @return string Source name
     */
    public function getName(): string
    {
        return 'Custom JSON';
    }

    /**
     * Fetch and parse JSON from the update URL.
     *
     * @since 1.0.0
     *
     * @throws UpdateException If request fails or JSON is invalid
     *
     * @return array<string, mixed> Update data
     */
    protected function fetchJson(): array
    {
        $url = $this->url;

        // Add query parameters if set
        if ( ! empty( $this->queryParams ) ) {
            $url .= ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $this->queryParams );
        }

        $credentialed = [] !== $this->headers;

        if ( $credentialed ) {
            $this->assertSecureFeedUrl();
        }

        // Redirects are not followed while credential headers are set. The
        // HTTP client strips `Authorization` on a cross-origin redirect but
        // forwards custom header names, so following one would hand a header
        // like `X-License-Key` to whatever host — or plaintext scheme — the
        // redirect names.
        $retries  = max( 0, (int) config( 'cms.updates.http_retries', 3 ) - 1 );
        $response = MetadataClient::get( $url, $this->headers, retries: $retries, followRedirects: ! $credentialed );

        if ( $credentialed && $response['status'] >= 300 && $response['status'] < 400 ) {
            throw UpdateException::versionCheckFailed(
                "HTTP {$response['status']}: the update feed redirected, and redirects are not followed while credential headers are configured. Point the feed URL at its final location.",
            );
        }

        if ( $response['status'] < 200 || $response['status'] >= 300 ) {
            throw UpdateException::versionCheckFailed( "HTTP {$response['status']}: {$response['body']}" );
        }

        $data = json_decode( $response['body'], true );

        if ( ! is_array( $data ) ) {
            // The bare feed URL, not `$url`: that one carries the query-string
            // credentials, and this message reaches logs and command output.
            // Redacted too, since a host may write credentials into the
            // configured URL itself.
            throw UpdateException::invalidJsonResponse(
                MetadataClient::redactUrlCredentials( $this->url ),
            );
        }

        return $data;
    }

    /**
     * Refuse to send credential headers to a plaintext feed.
     *
     * The plugin and theme managers already reject non-https sources, but this
     * class is also reachable directly and through `UpdateCheckerFactory`,
     * which accept any URL. `cms.updates.allow_insecure_transport` is the same
     * opt-out the archive download honours.
     *
     * @since 2.12.0
     *
     * @throws UpdateException When the feed URL is not https and the opt-out is off.
     */
    protected function assertSecureFeedUrl(): void
    {
        $scheme = strtolower( (string) parse_url( $this->url, PHP_URL_SCHEME ) );

        if ( 'https' === $scheme || config( 'cms.updates.allow_insecure_transport', false ) ) {
            return;
        }

        throw UpdateException::versionCheckFailed(
            'Refusing to send credential headers to an update feed over an insecure transport. Use an https feed URL.',
        );
    }

    /**
     * Reduce a caller-supplied header map to usable name/value string pairs.
     *
     * Entries without a name or a value are dropped, so an unset `env()` in a
     * host's config sends no header rather than an empty one.
     *
     * @since 2.12.0
     *
     * @param  array<array-key, mixed>  $headers  Raw header map.
     *
     * @return array<string, string> Header names mapped to values.
     */
    protected function normalizeHeaders( array $headers ): array
    {
        $normalized = [];

        foreach ( $headers as $name => $value ) {
            if ( ! is_string( $name ) || '' === trim( $name ) || ! is_scalar( $value ) ) {
                continue;
            }

            $value = trim( (string) $value );

            if ( '' !== $value ) {
                $normalized[ trim( $name ) ] = $value;
            }
        }

        return $normalized;
    }
}
