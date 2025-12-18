<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * GitHub Update Source
 *
 * Fetches updates from GitHub releases.
 *
 * @since 2.0.0
 */
class GitHubUpdateSource implements UpdateSourceInterface
{
    /**
     * GitHub access token for authentication.
     *
     * @since 2.0.0
     */
    protected ?string $accessToken = null;

    /**
     * GitHub repository owner.
     *
     * @since 2.0.0
     */
    protected string $owner;

    /**
     * GitHub repository name.
     *
     * @since 2.0.0
     */
    protected string $repo;

    /**
     * Create a new GitHubUpdateSource instance.
     *
     * @since 2.0.0
     *
     * @param  string  $url  GitHub repository URL
     * @param  string  $currentVersion  Current version
     */
    public function __construct(
        protected string $url,
        protected string $currentVersion,
    ) {
        $this->parseUrl($url);
    }

    /**
     * Check if this source supports the given URL.
     *
     * @since 2.0.0
     *
     * @param  string  $url  URL to check
     *
     * @return bool True if URL is a GitHub repository
     */
    public function supports(string $url): bool
    {
        return Str::contains($url, 'github.com');
    }

    /**
     * Check for available updates.
     *
     * @since 2.0.0
     *
     * @throws UpdateException
     *
     * @return UpdateInfo Update information
     */
    public function checkForUpdate(): UpdateInfo
    {
        $releases = $this->fetchReleases();

        if (empty($releases)) {
            throw UpdateException::versionCheckFailed('No releases found on GitHub');
        }

        // Get latest non-prerelease version
        $latest = collect($releases)
            ->filter(fn ($release) => ! $release['prerelease'])
            ->first();

        if (! $latest) {
            throw UpdateException::versionCheckFailed('No stable releases found');
        }

        // Find ZIP asset
        $zipAsset = collect($latest['assets'])
            ->first(fn ($asset) => Str::endsWith($asset['name'], '.zip'));

        if (! $zipAsset) {
            // Fallback to source code ZIP
            $zipAsset = [
                'browser_download_url' => $latest['zipball_url'],
            ];
        }

        return new UpdateInfo(
            currentVersion: $this->currentVersion,
            latestVersion: ltrim($latest['tag_name'], 'v'),
            hasUpdate: version_compare(ltrim($latest['tag_name'], 'v'), $this->currentVersion, '>'),
            downloadUrl: $zipAsset['browser_download_url'],
            changelog: $latest['body'] ?? null,
            releaseDate: $latest['published_at'] ?? null,
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
     * @since 2.0.0
     *
     * @param  string  $version  Version to download
     *
     * @throws UpdateException
     *
     * @return string Path to downloaded ZIP file
     */
    public function downloadUpdate(string $version): string
    {
        // Get release info for the specified version
        if ('latest' === $version || empty($version)) {
            $updateInfo  = $this->checkForUpdate();
            $downloadUrl = $updateInfo->downloadUrl;
        } else {
            $release     = $this->getReleaseByVersion($version);
            $downloadUrl = $this->extractDownloadUrl($release);
        }

        $tempPath = storage_path('app/temp/update-'.time().'.zip');

        // Ensure temp directory exists
        if (! File::exists(dirname($tempPath))) {
            File::makeDirectory(dirname($tempPath), 0755, true);
        }

        $headers = [];
        if ($this->accessToken) {
            $headers['Authorization'] = "token {$this->accessToken}";
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('cms.updates.download_timeout', 300))
            ->get($downloadUrl);

        if (! $response->successful()) {
            throw UpdateException::downloadFailed($downloadUrl);
        }

        File::put($tempPath, $response->body());

        return $tempPath;
    }

    /**
     * Set authentication credentials.
     *
     * @since 2.0.0
     *
     * @param  array|string  $credentials  GitHub token or credentials array
     */
    public function setAuthentication(string|array $credentials): void
    {
        $this->accessToken = is_string($credentials) ? $credentials : $credentials['token'] ?? null;
    }

    /**
     * Get the source name.
     *
     * @since 2.0.0
     *
     * @return string Source name
     */
    public function getName(): string
    {
        return 'GitHub';
    }

    /**
     * Parse GitHub URL to extract owner and repository.
     *
     * @since 2.0.0
     *
     * @param  string  $url  GitHub URL
     *
     * @throws InvalidArgumentException If URL is invalid
     */
    protected function parseUrl(string $url): void
    {
        // Extract owner and repo from URL
        // Supports: https://github.com/owner/repo
        if (preg_match('#github\.com/([^/]+)/([^/]+)#', $url, $matches)) {
            $this->owner = $matches[1];
            $this->repo  = rtrim($matches[2], '.git');
        } else {
            throw new InvalidArgumentException('Invalid GitHub URL');
        }
    }

    /**
     * Fetch releases from GitHub API.
     *
     * @since 2.0.0
     *
     * @throws UpdateException
     *
     * @return array List of releases
     */
    protected function fetchReleases(): array
    {
        $apiUrl = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases";

        $headers = ['Accept' => 'application/vnd.github.v3+json'];
        if ($this->accessToken) {
            $headers['Authorization'] = "token {$this->accessToken}";
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('cms.updates.http_timeout', 15))
            ->get($apiUrl);

        if (! $response->successful()) {
            throw UpdateException::versionCheckFailed("GitHub API error: {$response->status()}");
        }

        return $response->json();
    }

    /**
     * Get a specific release by version/tag name.
     *
     * @since 2.0.0
     *
     * @param  string  $version  Version to fetch (without 'v' prefix)
     *
     * @throws UpdateException
     *
     * @return array Release data
     */
    protected function getReleaseByVersion(string $version): array
    {
        // Try with 'v' prefix first (common convention)
        $tag = str_starts_with($version, 'v') ? $version : "v{$version}";

        $apiUrl = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases/tags/{$tag}";

        $headers = ['Accept' => 'application/vnd.github.v3+json'];
        if ($this->accessToken) {
            $headers['Authorization'] = "token {$this->accessToken}";
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('cms.updates.http_timeout', 15))
            ->get($apiUrl);

        if (! $response->successful()) {
            // Try without 'v' prefix
            $tag    = ltrim($version, 'v');
            $apiUrl = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases/tags/{$tag}";

            $response = Http::withHeaders($headers)
                ->timeout(config('cms.updates.http_timeout', 15))
                ->get($apiUrl);

            if (! $response->successful()) {
                throw UpdateException::downloadFailed("Release not found for version: {$version}");
            }
        }

        return $response->json();
    }

    /**
     * Extract download URL from release data.
     *
     * @since 2.0.0
     *
     * @param  array  $release  Release data from GitHub API
     *
     * @return string Download URL
     */
    protected function extractDownloadUrl(array $release): string
    {
        // Find ZIP asset
        $zipAsset = collect($release['assets'] ?? [])
            ->first(fn ($asset) => Str::endsWith($asset['name'], '.zip'));

        if ($zipAsset) {
            return $zipAsset['browser_download_url'];
        }

        // Fallback to source code ZIP
        return $release['zipball_url'] ?? throw UpdateException::downloadFailed('No download URL found in release');
    }
}
