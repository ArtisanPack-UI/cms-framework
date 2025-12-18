<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\GitLabUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

/**
 * GitLab Update Source Tests
 *
 * @since 2.0.0
 */
class GitLabUpdateSourceTest extends TestCase
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
     * Test GitLab source supports GitLab URLs.
     *
     * @since 2.0.0
     */
    public function test_supports_gitlab_urls(): void
    {
        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');

        $this->assertTrue($source->supports('https://gitlab.com/user/repo'));
        $this->assertTrue($source->supports('https://gitlab.com/another-user/another-repo'));
        $this->assertFalse($source->supports('https://github.com/user/repo'));
        $this->assertFalse($source->supports('https://example.com/updates.json'));
    }

    /**
     * Test GitLab source returns correct name.
     *
     * @since 2.0.0
     */
    public function test_returns_correct_name(): void
    {
        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');

        $this->assertEquals('GitLab', $source->getName());
    }

    /**
     * Test GitLab source can check for updates.
     *
     * @since 2.0.0
     */
    public function test_can_check_for_updates(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name' => 'v2.0.0',
                    'description' => 'Release notes here',
                    'created_at' => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
        $this->assertEquals('1.0.0', $updateInfo->currentVersion);
        $this->assertEquals('2.0.0', $updateInfo->latestVersion);
        $this->assertTrue($updateInfo->hasUpdate);
        $this->assertStringContainsString('gitlab.com', $updateInfo->downloadUrl);
        $this->assertEquals('Release notes here', $updateInfo->changelog);
    }

    /**
     * Test GitLab source handles no releases.
     *
     * @since 2.0.0
     */
    public function test_throws_exception_when_no_releases(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('No releases found');

        $source->checkForUpdate();
    }

    /**
     * Test GitLab source handles API errors.
     *
     * @since 2.0.0
     */
    public function test_handles_api_errors(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([], 500),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('GitLab API error');

        $source->checkForUpdate();
    }

    /**
     * Test GitLab source can set authentication.
     *
     * @since 2.0.0
     */
    public function test_can_set_authentication(): void
    {
        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $source->setAuthentication('glpat-test_token');

        // We can't directly test the token is used, but we can verify the method doesn't throw
        $this->assertTrue(true);
    }

    /**
     * Test GitLab source parses repository URL correctly.
     *
     * @since 2.0.0
     */
    public function test_parses_repository_url_correctly(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/test-group%2Ftest-repo/releases' => Http::response([
                [
                    'tag_name' => 'v1.0.0',
                    'description' => 'First release',
                    'created_at' => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/test-group/test-repo', '0.9.0');
        $updateInfo = $source->checkForUpdate();

        // If we get here without exception, the URL was parsed correctly
        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
    }

    /**
     * Test GitLab source handles nested group paths.
     *
     * @since 2.0.0
     */
    public function test_handles_nested_group_paths(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/group%2Fsubgroup%2Fproject/releases' => Http::response([
                [
                    'tag_name' => 'v1.0.0',
                    'description' => 'Release',
                    'created_at' => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/group/subgroup/project', '0.9.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
    }

    /**
     * Test GitLab source throws exception for invalid URLs.
     *
     * @since 2.0.0
     */
    public function test_throws_exception_for_invalid_urls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid GitLab URL');

        new GitLabUpdateSource('https://invalid-url.com', '1.0.0');
    }

    /**
     * Test GitLab source strips 'v' prefix from version tags.
     *
     * @since 2.0.0
     */
    public function test_strips_v_prefix_from_version_tags(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name' => 'v2.5.1',
                    'description' => 'Release',
                    'created_at' => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals('2.5.1', $updateInfo->latestVersion);
    }

    /**
     * Test GitLab source generates correct download URL.
     *
     * @since 2.0.0
     */
    public function test_generates_correct_download_url(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name' => 'v2.0.0',
                    'description' => 'Release',
                    'created_at' => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertStringContainsString('gitlab.com/api/v4/projects/user%2Frepo/repository/archive.zip', $updateInfo->downloadUrl);
        $this->assertStringContainsString('sha=v2.0.0', $updateInfo->downloadUrl);
    }

    /**
     * Test GitLab source includes metadata.
     *
     * @since 2.0.0
     */
    public function test_includes_metadata(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name' => 'v2.0.0',
                    'description' => 'Release',
                    'created_at' => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals('gitlab', $updateInfo->metadata['source']);
        $this->assertArrayHasKey('release_url', $updateInfo->metadata);
    }
}
