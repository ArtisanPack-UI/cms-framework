<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Custom JSON Update Source
 *
 * Fetches updates from custom JSON endpoints.
 *
 * @since 1.0.0
 */
class CustomJsonUpdateSource implements UpdateSourceInterface
{
    /**
     * Query parameters for authentication or other purposes.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    protected array $queryParams = [];

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

        $downloadUrl = $data['download_url'];

        $tempPath = storage_path( 'app/temp/update-' . time() . '.zip' );

        if ( ! File::exists( dirname( $tempPath ) ) ) {
            File::makeDirectory( dirname( $tempPath ), 0755, true );
        }

        $response = Http::timeout( config( 'cms.updates.download_timeout', 300 ) )
            ->get( $downloadUrl );

        if ( ! $response->successful() ) {
            throw UpdateException::downloadFailed( $downloadUrl );
        }

        File::put( $tempPath, $response->body() );

        return $tempPath;
    }

    /**
     * Set authentication credentials.
     *
     * @since 1.0.0
     *
     * @param  array|string  $credentials  Credentials (token, query params, etc.)
     */
    public function setAuthentication( string|array $credentials ): void
    {
        // For custom JSON, credentials can be query params or headers
        if ( is_array( $credentials ) ) {
            $this->queryParams = $credentials;
        } else {
            // Assume it's a token to be passed as query param
            $this->queryParams['token'] = $credentials;
        }
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

        $response = Http::timeout( config( 'cms.updates.http_timeout', 15 ) )
            ->retry( config( 'cms.updates.http_retries', 3 ), 100, throw: false )
            ->get( $url );

        if ( ! $response->successful() ) {
            throw UpdateException::versionCheckFailed( "HTTP {$response->status()}: {$response->body()}" );
        }

        $data = $response->json();

        if ( ! is_array( $data ) ) {
            throw UpdateException::invalidJsonResponse( $url );
        }

        return $data;
    }
}
