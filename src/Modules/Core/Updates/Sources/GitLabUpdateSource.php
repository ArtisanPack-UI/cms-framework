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
 * GitLab Update Source
 *
 * Fetches updates from GitLab releases.
 *
 * @since 2.0.0
 */
class GitLabUpdateSource implements UpdateSourceInterface
{
    /**
     * GitLab access token.
     *
     * @since 2.0.0
     */
    protected ?string $accessToken = null;

    /**
     * GitLab project ID (URL-encoded path).
     *
     * @since 2.0.0
     */
    protected string $projectId;

    /**
     * Create a new GitLabUpdateSource instance.
     *
     * @since 2.0.0
     *
     * @param  string  $url  GitLab repository URL
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
     * @return bool True if URL is a GitLab repository
     */
    public function supports(string $url): bool
    {
        return Str::contains($url, 'gitlab.com');
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
            throw UpdateException::versionCheckFailed('No releases found on GitLab');
        }

        // Filter out prereleases and get latest stable
        $latest = collect($releases)
            ->filter(fn ($release) => empty($release['upcoming_release']))
            ->first();

        if (! $latest) {
            throw UpdateException::versionCheckFailed('No stable releases found');
        }

        // Get download link for source code
        $downloadUrl = "https://gitlab.com/api/v4/projects/{$this->projectId}/repository/archive.zip?sha={$latest['tag_name']}";

        return new UpdateInfo(
            currentVersion: $this->currentVersion,
            latestVersion: ltrim($latest['tag_name'], 'v'),
            hasUpdate: version_compare(ltrim($latest['tag_name'], 'v'), $this->currentVersion, '>'),
            downloadUrl: $downloadUrl,
            changelog: $latest['description'] ?? null,
            releaseDate: $latest['created_at'] ?? null,
            metadata: [
                'source'      => 'gitlab',
                'release_url' => "https://gitlab.com/{$this->projectId}/-/releases/{$latest['tag_name']}",
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

        if (! File::exists(dirname($tempPath))) {
            File::makeDirectory(dirname($tempPath), 0755, true);
        }

        $headers = [];
        if ($this->accessToken) {
            $headers['PRIVATE-TOKEN'] = $this->accessToken;
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
     * @param  array|string  $credentials  GitLab token or credentials array
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
        return 'GitLab';
    }

    /**
     * Parse GitLab URL to extract project ID.
     *
     * @since 2.0.0
     *
     * @param  string  $url  GitLab repository URL
     *
     * @throws InvalidArgumentException If URL is invalid
     */
    protected function parseUrl(string $url): void
    {
        // Extract project path and convert to project ID
        // Supports: https://gitlab.com/group/subgroup/project
        if (preg_match('#gitlab\.com/(.+)$#', $url, $matches)) {
            // URL-encode the project path for API
            $this->projectId = urlencode(trim($matches[1], '/'));
        } else {
            throw new InvalidArgumentException('Invalid GitLab URL');
        }
    }

    /**
     * Fetch releases from GitLab API.
     *
     * @since 2.0.0
     *
     * @throws UpdateException If API request fails
     *
     * @return array<array<string, mixed>> List of releases
     */
    protected function fetchReleases(): array
    {
        $apiUrl = "https://gitlab.com/api/v4/projects/{$this->projectId}/releases";

        $headers = [];
        if ($this->accessToken) {
            $headers['PRIVATE-TOKEN'] = $this->accessToken;
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('cms.updates.http_timeout', 15))
            ->get($apiUrl);

        if (! $response->successful()) {
            throw UpdateException::versionCheckFailed("GitLab API error: {$response->status()}");
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

        $apiUrl = "https://gitlab.com/api/v4/projects/{$this->projectId}/releases/{$tag}";

        $headers = [];
        if ($this->accessToken) {
            $headers['PRIVATE-TOKEN'] = $this->accessToken;
        }

        $response = Http::withHeaders($headers)
            ->timeout(config('cms.updates.http_timeout', 15))
            ->get($apiUrl);

        if (! $response->successful()) {
            // Try without 'v' prefix
            $tag    = ltrim($version, 'v');
            $apiUrl = "https://gitlab.com/api/v4/projects/{$this->projectId}/releases/{$tag}";

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
     * @param  array  $release  Release data from GitLab API
     *
     * @return string Download URL
     */
    protected function extractDownloadUrl(array $release): string
    {
        // Get download link for source code
        if (! isset($release['tag_name'])) {
            throw UpdateException::downloadFailed('No tag_name found in release');
        }

        return "https://gitlab.com/api/v4/projects/{$this->projectId}/repository/archive.zip?sha={$release['tag_name']}";
    }
}
