<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\MetadataClient;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\StreamsDownloadsToDisk;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * GitHub Update Source
 *
 * Fetches updates from GitHub releases.
 *
 * @since 1.0.0
 */
class GitHubUpdateSource implements UpdateSourceInterface
{
    use StreamsDownloadsToDisk;

    /**
     * GitHub access token for authentication.
     *
     * @since 1.0.0
     */
    protected ?string $accessToken = null;

    /**
     * GitHub repository owner.
     *
     * @since 1.0.0
     */
    protected string $owner;

    /**
     * GitHub repository name.
     *
     * @since 1.0.0
     */
    protected string $repo;

    /**
     * Create a new GitHubUpdateSource instance.
     *
     * @since 1.0.0
     *
     * @param  string  $url  GitHub repository URL
     * @param  string  $currentVersion  Current version
     */
    public function __construct(
        protected string $url,
        protected string $currentVersion,
    ) {
        $this->parseUrl( $url );
    }

    /**
     * Check if this source supports the given URL.
     *
     * @since 1.0.0
     *
     * @param  string  $url  URL to check
     *
     * @return bool True if URL is a GitHub repository
     */
    public function supports( string $url ): bool
    {
        $host = parse_url( $url, PHP_URL_HOST );

        return 'github.com' === $host || str_ends_with( (string) $host, '.github.com' );
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
        $releases = $this->fetchReleases();

        if ( empty( $releases ) ) {
            throw UpdateException::versionCheckFailed( 'No releases found on GitHub' );
        }

        // Get latest non-prerelease version
        $latest = collect( $releases )
            ->filter( fn ( $release ) => ! $release['prerelease'] )
            ->first();

        if ( ! $latest ) {
            throw UpdateException::versionCheckFailed( 'No stable releases found' );
        }

        // Find ZIP asset
        $zipAsset = collect( $latest['assets'] )
            ->first( fn ( $asset ) => Str::endsWith( $asset['name'], '.zip' ) );

        if ( ! $zipAsset ) {
            // Fallback to source code ZIP
            $zipAsset = [
                'browser_download_url' => $latest['zipball_url'],
            ];
        }

        return new UpdateInfo(
            currentVersion: $this->currentVersion,
            latestVersion: ltrim( $latest['tag_name'], 'v' ),
            downloadUrl: $zipAsset['browser_download_url'],
            changelog: $latest['body'] ?? null,
            releaseDate: $latest['published_at'] ?? null,
            sha256: $this->extractChecksum( $latest, $zipAsset['name'] ?? null ),
            metadata: [
                'source'      => 'github',
                'release_id'  => $latest['id'],
                'release_url' => $latest['html_url'],
                'tag_name'    => $latest['tag_name'],
            ],
        );
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
        // Get release info for the specified version
        if ( 'latest' === $version || empty( $version ) ) {
            $updateInfo  = $this->checkForUpdate();
            $downloadUrl = $updateInfo->downloadUrl;
        } else {
            $release     = $this->getReleaseByVersion( $version );
            $downloadUrl = $this->extractDownloadUrl( $release );
        }

        $headers = [];
        if ( $this->accessToken ) {
            $headers['Authorization'] = "token {$this->accessToken}";
        }

        return $this->streamDownloadToTempFile( $downloadUrl, $headers );
    }

    /**
     * Set authentication credentials.
     *
     * @since 1.0.0
     *
     * @param  array|string  $credentials  GitHub token or credentials array
     */
    public function setAuthentication( string|array $credentials ): void
    {
        $this->accessToken = is_string( $credentials ) ? $credentials : $credentials['token'] ?? null;
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
        return 'GitHub';
    }

    /**
     * Resolve the SHA-256 digest published for a specific release.
     *
     * @since 2.7.1
     *
     * @param  string  $version  Version to resolve.
     *
     * @return string|null Lowercase hex digest, or null when none is published.
     */
    public function checksumForVersion( string $version ): ?string
    {
        $release = $this->getReleaseByVersion( $version );

        $zipAsset = collect( $release['assets'] ?? [] )
            ->first( fn ( $asset ) => is_array( $asset ) && isset( $asset['name'] ) && Str::endsWith( $asset['name'], '.zip' ) );

        return $this->extractChecksum( $release, $zipAsset['name'] ?? null );
    }

    /**
     * Parse GitHub URL to extract owner and repository.
     *
     * @since 1.0.0
     *
     * @param  string  $url  GitHub URL
     *
     * @throws InvalidArgumentException If URL is invalid
     */
    protected function parseUrl( string $url ): void
    {
        // Extract owner and repo from URL
        // Supports: https://github.com/owner/repo
        if ( preg_match( '#github\.com/([^/]+)/([^/]+)#', $url, $matches ) ) {
            $this->owner = $matches[1];
            $repo        = $matches[2];
            $this->repo  = str_ends_with( $repo, '.git' ) ? substr( $repo, 0, -4 ) : $repo;
        } else {
            throw new InvalidArgumentException( 'Invalid GitHub URL' );
        }
    }

    /**
     * Fetch releases from GitHub API.
     *
     * @since 1.0.0
     *
     * @throws UpdateException
     *
     * @return array List of releases
     */
    protected function fetchReleases(): array
    {
        $apiUrl = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases";

        $headers = ['Accept' => 'application/vnd.github.v3+json'];
        if ( $this->accessToken ) {
            $headers['Authorization'] = "token {$this->accessToken}";
        }

        $response = MetadataClient::get( $apiUrl, $headers );

        if ( ! $this->responseIsSuccessful( $response['status'] ) ) {
            throw UpdateException::versionCheckFailed( "GitHub API error: {$response['status']}" );
        }

        $decoded = json_decode( $response['body'], true );

        return is_array( $decoded ) ? $decoded : [];
    }

    /**
     * Get a specific release by version/tag name.
     *
     * @since 1.0.0
     *
     * @param  string  $version  Version to fetch (without 'v' prefix)
     *
     * @throws UpdateException
     *
     * @return array Release data
     */
    protected function getReleaseByVersion( string $version ): array
    {
        // Try with 'v' prefix first (common convention)
        $tag = str_starts_with( $version, 'v' ) ? $version : "v{$version}";

        $apiUrl = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases/tags/{$tag}";

        $headers = ['Accept' => 'application/vnd.github.v3+json'];
        if ( $this->accessToken ) {
            $headers['Authorization'] = "token {$this->accessToken}";
        }

        $response = MetadataClient::get( $apiUrl, $headers );

        if ( ! $this->responseIsSuccessful( $response['status'] ) ) {
            // Try without 'v' prefix
            $tag    = ltrim( $version, 'v' );
            $apiUrl = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases/tags/{$tag}";

            $response = MetadataClient::get( $apiUrl, $headers );

            if ( ! $this->responseIsSuccessful( $response['status'] ) ) {
                throw UpdateException::downloadFailed( "Release not found for version: {$version}" );
            }
        }

        $decoded = json_decode( $response['body'], true );

        return is_array( $decoded ) ? $decoded : [];
    }

    /**
     * Check if a raw HTTP status code represents a 2xx success.
     *
     * @since 2.5.4
     */
    protected function responseIsSuccessful( int $status ): bool
    {
        return $status >= 200 && $status < 300;
    }

    /**
     * Extract download URL from release data.
     *
     * @since 1.0.0
     *
     * @param  array  $release  Release data from GitHub API
     *
     * @return string Download URL
     */
    protected function extractDownloadUrl( array $release ): string
    {
        // Find ZIP asset
        $zipAsset = collect( $release['assets'] ?? [] )
            ->first( fn ( $asset ) => Str::endsWith( $asset['name'], '.zip' ) );

        if ( $zipAsset ) {
            return $zipAsset['browser_download_url'];
        }

        // Fallback to source code ZIP
        return $release['zipball_url'] ?? throw UpdateException::downloadFailed( 'No download URL found in release' );
    }

    /**
     * Extract a SHA-256 checksum from a GitHub release.
     *
     * Discovery order mirrors the GitLab source:
     *   1. A release asset whose name ends with `.sha256` ( fetched and parsed ).
     *   2. A `SHA-256: <64-hex>` line embedded in the release body.
     *
     * Without this the GitHub source never populated `sha256`, so with the
     * shipped defaults ( `verify_checksum = true`,
     * `allow_unverified_updates = false` ) **every** GitHub-sourced update
     * threw `checksumRequired`. The only way to make GitHub updates work at
     * all was `CMS_UPDATES_ALLOW_UNVERIFIED=true` — which disarms the control
     * mitigating extraction-time vulnerabilities, and which an operator sets
     * once and never revisits.
     *
     * @since 2.7.1
     *
     * @param  array<string, mixed>  $release  Release payload from the GitHub API.
     * @param  string|null  $assetName  Name of the asset the digest must describe.
     *
     * @return string|null Lowercase 64-character hex digest, or null when none is published.
     */
    protected function extractChecksum( array $release, ?string $assetName = null ): ?string
    {
        $sidecar = $this->extractChecksumFromSidecar( $release, $assetName );

        if ( null !== $sidecar ) {
            return $sidecar;
        }

        return $this->extractChecksumFromBody( $release['body'] ?? null );
    }

    /**
     * Locate and fetch a `*.sha256` asset from a release.
     *
     * @since 2.7.1
     *
     * A release can carry several archives and several sidecars. Accepting the
     * first `.sha256` found would pair a digest with an archive it does not
     * describe — which fails closed as a checksum mismatch, but blocks a
     * legitimate update and looks like tampering. The sidecar must be named
     * for the asset actually being downloaded.
     *
     * @param  array<string, mixed>  $release  Release payload from the GitHub API.
     * @param  string|null  $assetName  Name of the asset the digest must describe.
     *
     * @return string|null Lowercase hex digest, or null when no usable sidecar exists.
     */
    protected function extractChecksumFromSidecar( array $release, ?string $assetName = null ): ?string
    {
        $assets = $release['assets'] ?? [];

        if ( ! is_array( $assets ) ) {
            return null;
        }

        // Only a sidecar named for the target asset qualifies. Without a
        // resolved asset name — the zipball fallback has none — there is
        // nothing to correlate against, so no sidecar is accepted and the
        // release-body marker is used instead.
        if ( null === $assetName || '' === $assetName ) {
            return null;
        }

        $expected = strtolower( $assetName ) . '.sha256';

        foreach ( $assets as $asset ) {
            if ( ! is_array( $asset ) ) {
                continue;
            }

            $name = isset( $asset['name'] ) && is_string( $asset['name'] ) ? $asset['name'] : '';
            $url  = isset( $asset['browser_download_url'] ) && is_string( $asset['browser_download_url'] )
                ? $asset['browser_download_url']
                : '';

            if ( '' === $url || strtolower( $name ) !== $expected ) {
                continue;
            }

            $hash = $this->fetchSidecarHash( $url );

            if ( null !== $hash ) {
                return $hash;
            }
        }

        return null;
    }

    /**
     * Download a sidecar file and extract the first SHA-256 digest it carries.
     *
     * Accepts the common sidecar formats: a bare digest, or a
     * `<digest>  <filename>` line.
     *
     * @since 2.7.1
     *
     * @param  string  $url  URL of the `.sha256` sidecar.
     *
     * @return string|null Lowercase hex digest, or null on failure.
     */
    protected function fetchSidecarHash( string $url ): ?string
    {
        $headers = ['Accept' => 'application/octet-stream'];

        if ( $this->accessToken ) {
            $headers['Authorization'] = "token {$this->accessToken}";
        }

        try {
            $response = MetadataClient::get( $url, $headers );
        } catch ( Throwable $e ) {
            Log::warning( 'Failed to fetch GitHub SHA-256 sidecar.', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ] );

            return null;
        }

        if ( ! $this->responseIsSuccessful( $response['status'] ) ) {
            Log::warning( 'GitHub SHA-256 sidecar request returned a non-success status.', [
                'url'    => $url,
                'status' => $response['status'],
            ] );

            return null;
        }

        if ( preg_match( '/\b([a-f0-9]{64})\b/i', $response['body'], $matches ) ) {
            return strtolower( $matches[1] );
        }

        Log::warning( 'GitHub SHA-256 sidecar did not contain a 64-character hex digest.', [
            'url' => $url,
        ] );

        return null;
    }

    /**
     * Extract a `SHA-256: <hex>` line from a release body.
     *
     * @since 2.7.1
     *
     * @param  string|null  $body  Release body ( Markdown allowed ).
     *
     * @return string|null Lowercase hex digest, or null when no marker is present.
     */
    protected function extractChecksumFromBody( ?string $body ): ?string
    {
        if ( ! is_string( $body ) || '' === $body ) {
            return null;
        }

        if ( preg_match( '/SHA-?256\s*[:=]\s*`?([a-f0-9]{64})`?/i', $body, $matches ) ) {
            return strtolower( $matches[1] );
        }

        return null;
    }
}
