<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\GitLabUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;

/**
 * GitLab Update Source Tests
 *
 * @since 1.0.0
 */
class GitLabUpdateSourceTest extends TestCase
{
    /**
     * Test GitLab source supports GitLab URLs.
     *
     * @since 1.0.0
     */
    public function test_supports_gitlab_urls(): void
    {
        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');

        $this->assertTrue($source->supports('https://gitlab.com/user/repo'));
        $this->assertTrue($source->supports('https://gitlab.com/another-user/another-repo'));
        $this->assertFalse($source->supports('https://github.com/user/repo'));
        $this->assertFalse($source->supports('https://example.com/updates.json'));
        $this->assertFalse($source->supports('https://example.com/gitlab.com/user/repo'));
    }

    /**
     * Test GitLab source excludes query strings from project ID.
     *
     * @since 1.1.0
     */
    public function test_excludes_query_strings_from_project_id(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo?ref=main', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
        $this->assertEquals('2.0.0', $updateInfo->latestVersion);
    }

    /**
     * Test GitLab source returns correct name.
     *
     * @since 1.0.0
     */
    public function test_returns_correct_name(): void
    {
        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');

        $this->assertEquals('GitLab', $source->getName());
    }

    /**
     * Test GitLab source can check for updates.
     *
     * @since 1.0.0
     */
    public function test_can_check_for_updates(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release notes here',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
        $this->assertEquals('1.0.0', $updateInfo->currentVersion);
        $this->assertEquals('2.0.0', $updateInfo->latestVersion);
        $this->assertTrue($updateInfo->hasUpdate());
        $this->assertStringContainsString('gitlab.com', $updateInfo->downloadUrl);
        $this->assertEquals('Release notes here', $updateInfo->changelog);
    }

    /**
     * Test GitLab source handles no releases.
     *
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
     */
    public function test_can_set_authentication(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $source->setAuthentication('glpat-test_token');

        $source->checkForUpdate();

        Http::assertSent(function ($request) {
            return $request->hasHeader('PRIVATE-TOKEN', 'glpat-test_token');
        });
    }

    /**
     * Test GitLab source parses repository URL correctly.
     *
     * @since 1.0.0
     */
    public function test_parses_repository_url_correctly(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/test-group%2Ftest-repo/releases' => Http::response([
                [
                    'tag_name'    => 'v1.0.0',
                    'description' => 'First release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/test-group/test-repo', '0.9.0');
        $updateInfo = $source->checkForUpdate();

        // If we get here without exception, the URL was parsed correctly
        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
    }

    /**
     * Test GitLab source handles nested group paths.
     *
     * @since 1.0.0
     */
    public function test_handles_nested_group_paths(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/group%2Fsubgroup%2Fproject/releases' => Http::response([
                [
                    'tag_name'    => 'v1.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/group/subgroup/project', '0.9.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf(UpdateInfo::class, $updateInfo);
    }

    /**
     * Test GitLab source throws exception for invalid URLs.
     *
     * @since 1.0.0
     */
    public function test_throws_exception_for_invalid_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid GitLab URL');

        new GitLabUpdateSource('https://invalid-url.com', '1.0.0');
    }

    /**
     * Test GitLab source strips 'v' prefix from version tags.
     *
     * @since 1.0.0
     */
    public function test_strips_v_prefix_from_version_tags(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.5.1',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals('2.5.1', $updateInfo->latestVersion);
    }

    /**
     * Test GitLab source generates correct download URL.
     *
     * @since 1.0.0
     */
    public function test_generates_correct_download_url(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertStringContainsString('gitlab.com/api/v4/projects/user%2Frepo/repository/archive.zip', $updateInfo->downloadUrl);
        $this->assertStringContainsString('sha=v2.0.0', $updateInfo->downloadUrl);
    }

    /**
     * Test GitLab source includes metadata.
     *
     * @since 1.0.0
     */
    public function test_includes_metadata(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals('gitlab', $updateInfo->metadata['source']);
        $this->assertArrayHasKey('release_url', $updateInfo->metadata);
    }

    /**
     * Test GitLab source populates sha256 from a `.sha256` sidecar link.
     *
     * @since 2.0.0
     */
    public function test_populates_sha256_from_sidecar_link(): void
    {
        $expectedHash = str_repeat('a', 64);

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release notes',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'source.zip',
                                'url'  => 'https://gitlab.com/user/repo/-/releases/v2.0.0/source.zip',
                            ],
                            [
                                'name' => 'source.zip.sha256',
                                'url'  => 'https://example.com/releases/v2.0.0/source.zip.sha256',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'example.com/releases/v2.0.0/source.zip.sha256' => Http::response(
                $expectedHash."  source.zip\n",
                200,
            ),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertSame($expectedHash, $updateInfo->sha256);
    }

    /**
     * Test GitLab source populates sha256 from the release description.
     *
     * @since 2.0.0
     */
    public function test_populates_sha256_from_description_marker(): void
    {
        $expectedHash = str_repeat('b', 64);

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => "Release notes\n\nSHA-256: {$expectedHash}\n",
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertSame($expectedHash, $updateInfo->sha256);
    }

    /**
     * Test GitLab source logs a warning when no checksum is published.
     *
     * @since 2.0.0
     */
    public function test_logs_warning_and_leaves_sha256_null_when_absent(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'No checksum here.',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'SHA-256')
                    && 'v2.0.0' === ($context['tag_name'] ?? null);
            });

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertNull($updateInfo->sha256);
    }

    /**
     * Test GitLab source falls back when sidecar fetch fails and uses description.
     *
     * @since 2.0.0
     */
    public function test_falls_back_to_description_when_sidecar_fetch_fails(): void
    {
        $expectedHash = str_repeat('c', 64);

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => "Notes\nSHA256: {$expectedHash}",
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'source.zip.sha256',
                                'url'  => 'https://example.com/missing.sha256',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'example.com/missing.sha256' => Http::response('not found', 404),
        ]);

        // The sidecar fetch failure should be logged, but the source should still
        // surface a checksum extracted from the description.
        Log::shouldReceive('warning')->atLeast()->once();

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertSame($expectedHash, $updateInfo->sha256);
    }

    /**
     * Test GitLab source returns null when sidecar body is not a hex digest.
     *
     * @since 2.0.0
     */
    public function test_returns_null_when_sidecar_body_is_not_hex(): void
    {
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'No description checksum.',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'source.zip.sha256',
                                'url'  => 'https://example.com/bad.sha256',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'example.com/bad.sha256' => Http::response('garbage payload without a hash', 200),
        ]);

        Log::shouldReceive('warning')->atLeast()->once();

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertNull($updateInfo->sha256);
    }

    /**
     * Test GitLab source defaults to auto-archive URL when strategy is unset.
     *
     * @since 2.0.0
     */
    public function test_uses_auto_archive_url_by_default(): void
    {
        // No `gitlab_update_strategy` set — should default to auto_archive.
        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'app-2.0.0.tar.gz',
                                'url'  => 'https://example.com/app-2.0.0.tar.gz',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertStringContainsString('/repository/archive.zip', $updateInfo->downloadUrl);
        $this->assertStringContainsString('sha=v2.0.0', $updateInfo->downloadUrl);
    }

    /**
     * Test GitLab source resolves download URL from release assets when strategy is release_asset.
     *
     * @since 2.0.0
     */
    public function test_uses_release_asset_url_when_strategy_is_release_asset(): void
    {
        config()->set('cms.updates.gitlab_update_strategy', 'release_asset');

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'app-2.0.0.tar.gz',
                                'url'  => 'https://example.com/releases/app-2.0.0.tar.gz',
                            ],
                            [
                                'name' => 'app-2.0.0.tar.gz.sha256',
                                'url'  => 'https://example.com/releases/app-2.0.0.tar.gz.sha256',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertSame('https://example.com/releases/app-2.0.0.tar.gz', $updateInfo->downloadUrl);
    }

    /**
     * Test GitLab source honors a configurable release asset glob pattern.
     *
     * @since 2.0.0
     */
    public function test_release_asset_pattern_is_configurable(): void
    {
        config()->set('cms.updates.gitlab_update_strategy', 'release_asset');
        config()->set('cms.updates.gitlab_release_asset_pattern', '*-release.zip');

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'app-2.0.0.tar.gz',
                                'url'  => 'https://example.com/app-2.0.0.tar.gz',
                            ],
                            [
                                'name' => 'app-2.0.0-release.zip',
                                'url'  => 'https://example.com/app-2.0.0-release.zip',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertSame('https://example.com/app-2.0.0-release.zip', $updateInfo->downloadUrl);
    }

    /**
     * Test GitLab source matches the asset pattern case-insensitively.
     *
     * @since 2.0.0
     */
    public function test_release_asset_pattern_matches_case_insensitively(): void
    {
        config()->set('cms.updates.gitlab_update_strategy', 'release_asset');

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'App-2.0.0.TAR.GZ',
                                'url'  => 'https://example.com/App-2.0.0.TAR.GZ',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertSame('https://example.com/App-2.0.0.TAR.GZ', $updateInfo->downloadUrl);
    }

    /**
     * Test GitLab source falls back to URL basename when asset link has no name.
     *
     * @since 2.0.0
     */
    public function test_release_asset_matches_against_url_basename_when_name_missing(): void
    {
        config()->set('cms.updates.gitlab_update_strategy', 'release_asset');

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'url' => 'https://example.com/builds/app-2.0.0.tar.gz?token=abc',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertSame('https://example.com/builds/app-2.0.0.tar.gz?token=abc', $updateInfo->downloadUrl);
    }

    /**
     * Test GitLab source never selects a SHA-256 sidecar as the download target.
     *
     * @since 2.0.0
     */
    public function test_release_asset_strategy_skips_sha256_sidecars(): void
    {
        config()->set('cms.updates.gitlab_update_strategy', 'release_asset');
        // Set a permissive pattern that would otherwise match the sidecar.
        config()->set('cms.updates.gitlab_release_asset_pattern', '*');

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'app-2.0.0.tar.gz.sha256',
                                'url'  => 'https://example.com/app-2.0.0.tar.gz.sha256',
                            ],
                            [
                                'name' => 'app-2.0.0.tar.gz',
                                'url'  => 'https://example.com/app-2.0.0.tar.gz',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $source     = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $updateInfo = $source->checkForUpdate();

        $this->assertSame('https://example.com/app-2.0.0.tar.gz', $updateInfo->downloadUrl);
    }

    /**
     * Test GitLab source throws when release_asset strategy is set but no asset matches.
     *
     * @since 2.0.0
     */
    public function test_throws_when_release_asset_strategy_and_no_matching_asset(): void
    {
        config()->set('cms.updates.gitlab_update_strategy', 'release_asset');

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                    'assets'      => [
                        'links' => [
                            [
                                'name' => 'docs.pdf',
                                'url'  => 'https://example.com/docs.pdf',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage("No release asset link matched pattern '*.tar.gz'");

        $source->checkForUpdate();
    }

    /**
     * Test GitLab source throws when release_asset strategy is set but no asset links exist.
     *
     * @since 2.0.0
     */
    public function test_throws_when_release_asset_strategy_and_no_asset_links(): void
    {
        config()->set('cms.updates.gitlab_update_strategy', 'release_asset');

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases' => Http::response([
                [
                    'tag_name'    => 'v2.0.0',
                    'description' => 'Release without assets',
                    'created_at'  => '2024-12-15T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');

        $this->expectException(UpdateException::class);

        $source->checkForUpdate();
    }

    /**
     * Test GitLab source uses release_asset URL when downloading an explicit version.
     *
     * @since 2.0.0
     */
    public function test_release_asset_strategy_is_applied_for_explicit_version_downloads(): void
    {
        config()->set('cms.updates.gitlab_update_strategy', 'release_asset');

        Http::fake([
            'gitlab.com/api/v4/projects/user%2Frepo/releases/v2.0.0' => Http::response([
                'tag_name'    => 'v2.0.0',
                'description' => 'Release',
                'created_at'  => '2024-12-15T10:00:00.000Z',
                'assets'      => [
                    'links' => [
                        [
                            'name' => 'app-2.0.0.tar.gz',
                            'url'  => 'https://example.com/app-2.0.0.tar.gz',
                        ],
                    ],
                ],
            ], 200),
            'example.com/app-2.0.0.tar.gz' => Http::response('tarball-bytes', 200),
        ]);

        $source = new GitLabUpdateSource('https://gitlab.com/user/repo', '1.0.0');
        $source->downloadUpdate('v2.0.0');

        Http::assertSent(function ($request) {
            return 'https://example.com/app-2.0.0.tar.gz' === $request->url();
        });
    }

    /**
     * Define environment setup.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cms.updates.http_timeout', 15);
        $app['config']->set('cms.updates.download_timeout', 300);
    }
}
