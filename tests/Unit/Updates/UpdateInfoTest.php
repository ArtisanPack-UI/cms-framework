<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use PHPUnit\Framework\TestCase;

/**
 * Update Info Tests
 *
 * @since 2.0.0
 */
class UpdateInfoTest extends TestCase
{
    /**
     * Test UpdateInfo can be instantiated.
     *
     * @since 2.0.0
     */
    public function test_can_create_update_info(): void
    {
        $info = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            hasUpdate: true,
            downloadUrl: 'https://example.com/update.zip'
        );

        $this->assertEquals('1.0.0', $info->currentVersion);
        $this->assertEquals('2.0.0', $info->latestVersion);
        $this->assertTrue($info->hasUpdate);
        $this->assertEquals('https://example.com/update.zip', $info->downloadUrl);
    }

    /**
     * Test UpdateInfo can be created from array.
     *
     * @since 2.0.0
     */
    public function test_can_create_from_array(): void
    {
        $data = [
            'version' => '2.0.0',
            'download_url' => 'https://example.com/update.zip',
            'changelog' => 'New features',
            'sha256' => 'abc123',
        ];

        $info = UpdateInfo::fromArray($data, '1.0.0');

        $this->assertEquals('1.0.0', $info->currentVersion);
        $this->assertEquals('2.0.0', $info->latestVersion);
        $this->assertTrue($info->hasUpdate);
        $this->assertEquals('https://example.com/update.zip', $info->downloadUrl);
        $this->assertEquals('New features', $info->changelog);
        $this->assertEquals('abc123', $info->sha256);
    }

    /**
     * Test UpdateInfo detects no update when versions match.
     *
     * @since 2.0.0
     */
    public function test_detects_no_update_when_versions_match(): void
    {
        $data = [
            'version' => '1.0.0',
            'download_url' => 'https://example.com/update.zip',
        ];

        $info = UpdateInfo::fromArray($data, '1.0.0');

        $this->assertFalse($info->hasUpdate);
    }

    /**
     * Test UpdateInfo detects no update when current is newer.
     *
     * @since 2.0.0
     */
    public function test_detects_no_update_when_current_is_newer(): void
    {
        $data = [
            'version' => '1.0.0',
            'download_url' => 'https://example.com/update.zip',
        ];

        $info = UpdateInfo::fromArray($data, '2.0.0');

        $this->assertFalse($info->hasUpdate);
    }

    /**
     * Test UpdateInfo can be converted to array.
     *
     * @since 2.0.0
     */
    public function test_can_convert_to_array(): void
    {
        $info = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            hasUpdate: true,
            downloadUrl: 'https://example.com/update.zip',
            changelog: 'New features'
        );

        $array = $info->toArray();

        $this->assertArrayHasKey('current', $array);
        $this->assertArrayHasKey('latest', $array);
        $this->assertArrayHasKey('hasUpdate', $array);
        $this->assertArrayHasKey('download_url', $array);
        $this->assertArrayHasKey('changelog', $array);

        $this->assertEquals('1.0.0', $array['current']);
        $this->assertEquals('2.0.0', $array['latest']);
        $this->assertTrue($array['hasUpdate']);
        $this->assertEquals('https://example.com/update.zip', $array['download_url']);
        $this->assertEquals('New features', $array['changelog']);
    }
}
