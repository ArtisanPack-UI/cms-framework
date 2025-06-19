<?php
/**
 * PWA Feature Test
 *
 * Tests for the PWA feature to ensure the service worker view is properly defined.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Tests
 * @subpackage ArtisanPackUI\CMSFramework\Tests\Feature
 * @since      1.1.0
 */

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test case for PWA feature
 *
 * This class tests that the PWA service worker view is properly defined.
 *
 * @since 1.1.0
 */
class PWATest extends TestCase
{
    /**
     * Tests that the service worker view exists and contains the expected code
     *
     * This test verifies that the PWA service worker view file exists and contains
     * the expected JavaScript code for caching assets and handling fetch events.
     *
     * @since 1.1.0
     * @return void
     */
    #[Test]
    public function it_has_correct_service_worker_view(): void
    {
        // Check that the service worker view exists
        $this->assertFileExists(__DIR__ . '/../../src/Features/PWA/resources/views/service-worker.blade.php');

        // Check that the service worker view contains expected code
        $content = file_get_contents(__DIR__ . '/../../src/Features/PWA/resources/views/service-worker.blade.php');
        $this->assertStringContainsString('self.addEventListener(\'install\'', $content);
        $this->assertStringContainsString('self.addEventListener(\'fetch\'', $content);
        $this->assertStringContainsString('artisanpack-ui-pwa-cache-v1', $content);
    }
}
