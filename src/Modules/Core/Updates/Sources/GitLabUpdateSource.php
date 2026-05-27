<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * GitLab Update Source
 *
 * Fetches updates from GitLab releases.
 *
 * @since 1.0.0
 */
class GitLabUpdateSource implements UpdateSourceInterface
{
    /**
     * GitLab access token.
     *
     * @since 1.0.0
     */
    protected ?string $accessToken = null;

    /**
     * GitLab project ID (URL-encoded path).
     *
     * @since 1.0.0
     */
    protected string $projectId;

    /**
     * Create a new GitLabUpdateSource instance.
     *
     * @since 1.0.0
     *
     * @param  string  $url  GitLab repository URL
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
     * @return bool True if URL is a GitLab repository
     */
    public function supports( string $url ): bool
    {
        $host = parse_url( $url, PHP_URL_HOST );

        return 'gitlab.com' === $host;
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
            throw UpdateException::versionCheckFailed( 'No releases found on GitLab' );
        }

        // Filter out prereleases and get latest stable
        $latest = collect( $releases )
            ->filter( fn ( $release ) => empty( $release['upcoming_release'] ) )
            ->first();

        if ( ! $latest ) {
            throw UpdateException::versionCheckFailed( 'No stable releases found' );
        }

        $downloadUrl = $this->resolveDownloadUrl( $latest );

        $sha256 = $this->extractChecksum( $latest, $downloadUrl );

        if ( null === $sha256 ) {
            Log::warning( 'GitLab release does not advertise a SHA-256 checksum; update integrity verification will be skipped.', [
                'project_id' => $this->projectId,
                'tag_name'   => $latest['tag_name'] ?? null,
            ] );
        }

        return new UpdateInfo(
            currentVersion: $this->currentVersion,
            latestVersion: ltrim( $latest['tag_name'], 'v' ),
            downloadUrl: $downloadUrl,
            changelog: $latest['description'] ?? null,
            releaseDate: $latest['created_at'] ?? null,
            sha256: $sha256,
            metadata: [
                'source'      => 'gitlab',
                'release_url' => "https://gitlab.com/{$this->projectId}/-/releases/{$latest['tag_name']}",
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

        $tempPath = storage_path( 'app/temp/update-' . bin2hex( random_bytes( 16 ) ) . '.zip' );

        if ( ! File::exists( dirname( $tempPath ) ) ) {
            File::makeDirectory( dirname( $tempPath ), 0755, true );
        }

        $headers = [];
        if ( $this->accessToken ) {
            $headers['PRIVATE-TOKEN'] = $this->accessToken;
        }

        $response = Http::withHeaders( $headers )
            ->timeout( config( 'cms.updates.download_timeout', 300 ) )
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
     * @param  array|string  $credentials  GitLab token or credentials array
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
        return 'GitLab';
    }

    /**
     * Parse GitLab URL to extract project ID.
     *
     * @since 1.0.0
     *
     * @param  string  $url  GitLab repository URL
     *
     * @throws InvalidArgumentException If URL is invalid
     */
    protected function parseUrl( string $url ): void
    {
        $host = parse_url( $url, PHP_URL_HOST );

        if ( 'gitlab.com' !== $host ) {
            throw new InvalidArgumentException( 'Invalid GitLab URL' );
        }

        $path = parse_url( $url, PHP_URL_PATH );

        if ( ! $path || '' === trim( $path, '/' ) ) {
            throw new InvalidArgumentException( 'Invalid GitLab URL' );
        }

        // URL-encode the project path for API
        $this->projectId = urlencode( trim( $path, '/' ) );
    }

    /**
     * Fetch releases from GitLab API.
     *
     * @since 1.0.0
     *
     * @throws UpdateException If API request fails
     *
     * @return array<array<string, mixed>> List of releases
     */
    protected function fetchReleases(): array
    {
        $apiUrl = "https://gitlab.com/api/v4/projects/{$this->projectId}/releases";

        $headers = [];
        if ( $this->accessToken ) {
            $headers['PRIVATE-TOKEN'] = $this->accessToken;
        }

        $response = Http::withHeaders( $headers )
            ->timeout( config( 'cms.updates.http_timeout', 15 ) )
            ->get( $apiUrl );

        if ( ! $response->successful() ) {
            throw UpdateException::versionCheckFailed( "GitLab API error: {$response->status()}" );
        }

        return $response->json();
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

        $apiUrl = "https://gitlab.com/api/v4/projects/{$this->projectId}/releases/{$tag}";

        $headers = [];
        if ( $this->accessToken ) {
            $headers['PRIVATE-TOKEN'] = $this->accessToken;
        }

        $response = Http::withHeaders( $headers )
            ->timeout( config( 'cms.updates.http_timeout', 15 ) )
            ->get( $apiUrl );

        if ( ! $response->successful() ) {
            // Try without 'v' prefix
            $tag    = ltrim( $version, 'v' );
            $apiUrl = "https://gitlab.com/api/v4/projects/{$this->projectId}/releases/{$tag}";

            $response = Http::withHeaders( $headers )
                ->timeout( config( 'cms.updates.http_timeout', 15 ) )
                ->get( $apiUrl );

            if ( ! $response->successful() ) {
                throw UpdateException::downloadFailed( "Release not found for version: {$version}" );
            }
        }

        return $response->json();
    }

    /**
     * Extract download URL from release data.
     *
     * @since 1.0.0
     *
     * @param  array  $release  Release data from GitLab API
     *
     * @throws UpdateException
     *
     * @return string Download URL
     */
    protected function extractDownloadUrl( array $release ): string
    {
        if ( ! isset( $release['tag_name'] ) ) {
            throw UpdateException::downloadFailed( 'No tag_name found in release' );
        }

        return $this->resolveDownloadUrl( $release );
    }

    /**
     * Resolve the download URL for a release based on the configured strategy.
     *
     * Two strategies are supported:
     *   - 'auto_archive' (default): returns the auto-generated GitLab source archive URL.
     *   - 'release_asset': returns the URL of the first asset link matching
     *     `cms.updates.gitlab_release_asset_pattern`; throws when no link matches.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $release  Release payload from the GitLab API.
     *
     * @throws UpdateException When `release_asset` is selected but no asset link matches the pattern.
     *
     * @return string Resolved download URL.
     */
    protected function resolveDownloadUrl( array $release ): string
    {
        $strategy = (string) config( 'cms.updates.gitlab_update_strategy', 'auto_archive' );

        if ( 'release_asset' === $strategy ) {
            $pattern  = (string) config( 'cms.updates.gitlab_release_asset_pattern', '*.zip' );
            $assetUrl = $this->findReleaseAssetUrl( $release, $pattern );

            if ( null === $assetUrl ) {
                throw UpdateException::downloadFailed(
                    "No release asset link matched pattern '{$pattern}' for tag '{$release['tag_name']}'. "
                    . 'Either upload a matching asset, adjust `cms.updates.gitlab_release_asset_pattern`, '
                    . "or set `cms.updates.gitlab_update_strategy` to 'auto_archive'.",
                );
            }

            return $assetUrl;
        }

        if ( 'auto_archive' === $strategy ) {
            return $this->buildAutoArchiveUrl( $release['tag_name'] );
        }

        throw UpdateException::downloadFailed(
            "Unsupported `cms.updates.gitlab_update_strategy` value '{$strategy}'. "
            . "Expected 'auto_archive' or 'release_asset'.",
        );
    }

    /**
     * Build the auto-generated GitLab source-archive URL for a tag.
     *
     * @since 2.0.0
     *
     * @param  string  $tagName  Release tag name (e.g. `v2.0.0`).
     *
     * @return string Auto-archive download URL.
     */
    protected function buildAutoArchiveUrl( string $tagName ): string
    {
        return "https://gitlab.com/api/v4/projects/{$this->projectId}/repository/archive.zip?sha={$tagName}";
    }

    /**
     * Locate a release-asset link whose name (or URL basename) matches a glob pattern.
     *
     * Matches case-insensitively using `fnmatch`. SHA-256 sidecar files
     * (`*.sha256`) are always excluded so the checksum sidecar is not chosen
     * as the download target when the pattern is broad.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $release  Release payload from the GitLab API.
     * @param  string  $pattern  Glob pattern compatible with `fnmatch`.
     *
     * @return string|null Matching asset URL, or null when no link matches.
     */
    protected function findReleaseAssetUrl( array $release, string $pattern ): ?string
    {
        $links = $release['assets']['links'] ?? [];

        if ( ! is_array( $links ) ) {
            return null;
        }

        $patternLower = strtolower( $pattern );

        foreach ( $links as $link ) {
            if ( ! is_array( $link ) ) {
                continue;
            }

            $url = isset( $link['url'] ) && is_string( $link['url'] ) ? $link['url'] : '';

            if ( '' === $url ) {
                continue;
            }

            $name = isset( $link['name'] ) && is_string( $link['name'] ) && '' !== $link['name']
                ? $link['name']
                : basename( parse_url( $url, PHP_URL_PATH ) ?: $url );

            $nameLower = strtolower( $name );

            // Never select a sidecar checksum as the download target.
            if ( str_ends_with( $nameLower, '.sha256' ) ) {
                continue;
            }

            if ( fnmatch( $patternLower, $nameLower ) ) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Extract a SHA-256 checksum from a GitLab release.
     *
     * Discovery order:
     *   1. An asset link whose name or URL ends with `.sha256` (fetched and parsed).
     *   2. A `SHA-256: <64-hex>` line embedded in the release description.
     *
     * The sidecar lookup is skipped when the download URL points at GitLab's
     * auto-generated source archive (`/repository/archive.zip`). Sidecars
     * accompany uploaded release assets — their digest does not match the
     * auto-archive, and attaching one would produce a deterministic checksum
     * mismatch on download.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $release      Release payload from the GitLab API.
     * @param  string                $downloadUrl  The resolved download URL the checksum will be paired with.
     *
     * @return string|null Lowercase 64-character hex digest, or null when no checksum is published.
     */
    protected function extractChecksum( array $release, string $downloadUrl ): ?string
    {
        // Sidecar checksums describe uploaded release assets; the GitLab
        // auto-archive endpoint has no published digest of its own, so fall
        // straight to the description-embedded checksum (which usually
        // references the same archive content).
        if ( str_contains( $downloadUrl, '/repository/archive.zip' ) ) {
            return $this->extractChecksumFromDescription( $release['description'] ?? null );
        }

        $sidecarHash = $this->extractChecksumFromSidecar( $release );

        if ( null !== $sidecarHash ) {
            return $sidecarHash;
        }

        return $this->extractChecksumFromDescription( $release['description'] ?? null );
    }

    /**
     * Locate and fetch a `*.sha256` sidecar link from a release's asset links.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $release  Release payload from the GitLab API.
     *
     * @return string|null Lowercase hex digest, or null if no usable sidecar is found.
     */
    protected function extractChecksumFromSidecar( array $release ): ?string
    {
        $links = $release['assets']['links'] ?? [];

        if ( ! is_array( $links ) ) {
            return null;
        }

        foreach ( $links as $link ) {
            if ( ! is_array( $link ) ) {
                continue;
            }

            $name = isset( $link['name'] ) && is_string( $link['name'] ) ? $link['name'] : '';
            $url  = isset( $link['url'] ) && is_string( $link['url'] ) ? $link['url'] : '';

            if ( '' === $url ) {
                continue;
            }

            if ( ! str_ends_with( strtolower( $name ), '.sha256' ) && ! str_ends_with( strtolower( $url ), '.sha256' ) ) {
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
     * Download a sidecar file and extract the first SHA-256 hex digest it contains.
     *
     * Accepts the common sidecar formats: a bare digest or a `<digest>  <filename>` line.
     *
     * @since 2.0.0
     *
     * @param  string  $url  URL of the `.sha256` sidecar.
     *
     * @return string|null Lowercase hex digest, or null on failure / unparseable contents.
     */
    protected function fetchSidecarHash( string $url ): ?string
    {
        $headers = [];
        if ( $this->accessToken ) {
            $headers['PRIVATE-TOKEN'] = $this->accessToken;
        }

        try {
            $response = Http::withHeaders( $headers )
                ->timeout( config( 'cms.updates.http_timeout', 15 ) )
                ->get( $url );
        } catch ( Throwable $e ) {
            Log::warning( 'Failed to fetch GitLab SHA-256 sidecar.', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ] );

            return null;
        }

        if ( ! $response->successful() ) {
            Log::warning( 'GitLab SHA-256 sidecar request returned a non-success status.', [
                'url'    => $url,
                'status' => $response->status(),
            ] );

            return null;
        }

        if ( preg_match( '/\b([a-f0-9]{64})\b/i', $response->body(), $matches ) ) {
            return strtolower( $matches[1] );
        }

        Log::warning( 'GitLab SHA-256 sidecar did not contain a 64-character hex digest.', [
            'url' => $url,
        ] );

        return null;
    }

    /**
     * Extract a `SHA-256: <hex>` line from a release description.
     *
     * @since 2.0.0
     *
     * @param  string|null  $description  Release description (Markdown allowed).
     *
     * @return string|null Lowercase hex digest, or null when no marker is present.
     */
    protected function extractChecksumFromDescription( ?string $description ): ?string
    {
        if ( ! is_string( $description ) || '' === $description ) {
            return null;
        }

        if ( preg_match( '/SHA-?256\s*[:=]\s*`?([a-f0-9]{64})`?/i', $description, $matches ) ) {
            return strtolower( $matches[1]);
        }

        return null;
    }
}
