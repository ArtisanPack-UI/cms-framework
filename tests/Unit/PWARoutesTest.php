<?php
/**
 * PWA Routes Test
 *
 * Tests for the PWA routes to ensure they are properly defined.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Tests
 * @subpackage ArtisanPackUI\CMSFramework\Tests\Unit
 * @since      1.1.0
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test case for PWA routes
 *
 * This class tests that the PWA routes are properly defined in the routes.php file.
 *
 * @since 1.1.0
 */
class PWARoutesTest extends TestCase
{
    /**
     * Tests that the routes file exists and contains the expected routes
     *
     * This test verifies that the PWA routes.php file exists and contains
     * the expected route definitions for the manifest and service worker.
     *
     * @since 1.1.0
     * @return void
     */
    #[Test]
    public function it_has_correct_routes_defined(): void
    {
        // Check that the routes file exists
        $this->assertFileExists(__DIR__ . '/../../src/Features/PWA/routes.php');

        // Check that the routes file contains the expected routes
        $content = file_get_contents(__DIR__ . '/../../src/Features/PWA/routes.php');

        // Check for manifest route
        $this->assertStringContainsString("Route::get( '/manifest.json'", $content);
        $this->assertStringContainsString("->name( 'pwa.manifest' )", $content);

        // Check for service worker route
        $this->assertStringContainsString("Route::get( '/service-worker.js'", $content);
        $this->assertStringContainsString("->name( 'pwa.service-worker' )", $content);

        // Check for settings check in both routes
        $this->assertStringContainsString('$settings->get( \'pwa.enabled\' )', $content);
        $this->assertStringContainsString('abort( 404 )', $content);
    }
}
