<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\GitHubUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

/**
 * GitHub Update Source Tests
 *
 * @since 2.0.0
 */
class GitHubUpdateSourceTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cms.updates.http_timeout', 15);
        $app['config']->set('cms.updates.download_timeout', 300);
    }

    /**
     * Test GitHub source supports GitHub URLs.
     *
     * @since 2.0.0
     */
    public function test_supports_github_urls(): void
    {
        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');

        $this->assertTrue($source->supports('https://github.com/user/repo'));
        $this->assertTrue($source->supports('https://github.com/another-user/another-repo'));
        $this->assertFalse($source->supports('https://gitlab.com/user/repo'));
        $this->assertFalse($source->supports('https://example.com/updates.json'));
    }

    /**
     * Test GitHub source returns correct name.
     *
     * @since 2.0.0
     */
    public function test_returns_correct_name(): void
    {
        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');

        $this->assertEquals('GitHub', $source->getName());
    }

    /**
     * Test GitHub source can check for updates.
     *
     * @since 2.0.0
     */
    public function test_can_check_for_updates(): void
    {
        Http::fake([
            'api.github.com/repos/user/repo/releases' => Http::response([
                [
                    'tag_name' => 'v2.0.0',
                    'prerelease' => false,
                    'body' => 'Release notes here',
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url' => 'https://github.com/user/repo/releases/tag/v2.0.0',
                    'id' => 123,
                    'assets' => [
                        [
                            'name' => 'repo.zip',
                            'browser_download_url' => 'https://github.com/user/repo/releases/download/v2.0.0/repo.zip',
                        ],
                    ],
                    'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                ],
            ], 200),
        ]);

        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
        $this->assertEquals('1.0.0', $updateInfo->currentVersion);
        $this->assertEquals('2.0.0', $updateInfo->latestVersion);
        $this->assertTrue($updateInfo->hasUpdate);
        $this->assertStringContainsString('github.com', $updateInfo->downloadUrl);
        $this->assertEquals('Release notes here', $updateInfo->changelog);
    }

    /**
     * Test GitHub source handles no releases.
     *
     * @since 2.0.0
     */
    public function test_throws_exception_when_no_releases(): void
    {
        Http::fake([
            'api.github.com/repos/user/repo/releases' => Http::response([], 200),
        ]);

        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('No releases found');

        $source->checkForUpdate();
    }

    /**
     * Test GitHub source skips prerelease versions.
     *
     * @since 2.0.0
     */
    public function test_skips_prerelease_versions(): void
    {
        Http::fake([
            'api.github.com/repos/user/repo/releases' => Http::response([
                [
                    'tag_name' => 'v3.0.0-beta',
                    'prerelease' => true,
                    'body' => 'Beta release',
                    'published_at' => '2024-12-20T10:00:00Z',
                    'html_url' => 'https://github.com/user/repo/releases/tag/v3.0.0-beta',
                    'id' => 124,
                    'assets' => [],
                    'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v3.0.0-beta',
                ],
                [
                    'tag_name' => 'v2.0.0',
                    'prerelease' => false,
                    'body' => 'Stable release',
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url' => 'https://github.com/user/repo/releases/tag/v2.0.0',
                    'id' => 123,
                    'assets' => [],
                    'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                ],
            ], 200),
        ]);

        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals('2.0.0', $updateInfo->latestVersion);
    }

    /**
     * Test GitHub source falls back to zipball_url when no assets.
     *
     * @since 2.0.0
     */
    public function test_falls_back_to_zipball_when_no_assets(): void
    {
        Http::fake([
            'api.github.com/repos/user/repo/releases' => Http::response([
                [
                    'tag_name' => 'v2.0.0',
                    'prerelease' => false,
                    'body' => 'Release',
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url' => 'https://github.com/user/repo/releases/tag/v2.0.0',
                    'id' => 123,
                    'assets' => [],
                    'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                ],
            ], 200),
        ]);

        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertStringContainsString('zipball', $updateInfo->downloadUrl);
    }

    /**
     * Test GitHub source handles API errors.
     *
     * @since 2.0.0
     */
    public function test_handles_api_errors(): void
    {
        Http::fake([
            'api.github.com/repos/user/repo/releases' => Http::response([], 500),
        ]);

        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('GitHub API error');

        $source->checkForUpdate();
    }

    /**
     * Test GitHub source can set authentication.
     *
     * @since 2.0.0
     */
    public function test_can_set_authentication(): void
    {
        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');
        $source->setAuthentication('ghp_test_token');

        // We can't directly test the token is used, but we can verify the method doesn't throw
        $this->assertTrue(true);
    }

    /**
     * Test GitHub source parses repository owner and name correctly.
     *
     * @since 2.0.0
     */
    public function test_parses_repository_url_correctly(): void
    {
        Http::fake([
            'api.github.com/repos/test-owner/test-repo/releases' => Http::response([
                [
                    'tag_name' => 'v1.0.0',
                    'prerelease' => false,
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url' => 'https://github.com/test-owner/test-repo/releases/tag/v1.0.0',
                    'id' => 123,
                    'assets' => [],
                    'zipball_url' => 'https://api.github.com/repos/test-owner/test-repo/zipball/v1.0.0',
                ],
            ], 200),
        ]);

        $source = new GitHubUpdateSource('https://github.com/test-owner/test-repo', '0.9.0');
        $updateInfo = $source->checkForUpdate();

        // If we get here without exception, the URL was parsed correctly
        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
    }

    /**
     * Test GitHub source throws exception for invalid URLs.
     *
     * @since 2.0.0
     */
    public function test_throws_exception_for_invalid_urls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid GitHub URL');

        new GitHubUpdateSource('https://invalid-url.com', '1.0.0');
    }

    /**
     * Test GitHub source strips 'v' prefix from version tags.
     *
     * @since 2.0.0
     */
    public function test_strips_v_prefix_from_version_tags(): void
    {
        Http::fake([
            'api.github.com/repos/user/repo/releases' => Http::response([
                [
                    'tag_name' => 'v2.5.1',
                    'prerelease' => false,
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url' => 'https://github.com/user/repo/releases/tag/v2.5.1',
                    'id' => 123,
                    'assets' => [],
                    'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v2.5.1',
                ],
            ], 200),
        ]);

        $source = new GitHubUpdateSource('https://github.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals('2.5.1', $updateInfo->latestVersion);
    }
}
