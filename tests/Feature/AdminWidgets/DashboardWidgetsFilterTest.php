<?php

declare( strict_types=1 );

/**
 * Feature coverage for the `ap.cmsFramework.admin.dashboardWidgets` filter
 * introduced in 2.5.0 (issue #196 / Wave 5).
 *
 * Verifies both branches of {@see AdminWidgetManager::getAvailableWidgetsForUser()}
 * — with and without a user — pass through the filter, and that subscribers
 * receive the user (or null) alongside the widget array.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.5.0
 */

use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Contracts\AdminWidgetInterface;
use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Services\AdminWidgetManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

/**
 * Minimal test-only widget so the manager has a real, capability-free entry to
 * echo back through the filter.
 *
 * @since 2.5.0
 */
class DashboardTestWidget implements AdminWidgetInterface
{
    public static function getWidgetInfo(): array
    {
        return [
            'title'           => 'Test Widget',
            'description'     => 'For dashboard-widgets filter coverage.',
            'default_options' => [],
        ];
    }
}

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.admin.dashboardWidgets' );
} );

it( 'applies ap.cmsFramework.admin.dashboardWidgets when no user is provided', function (): void {
    $manager = new AdminWidgetManager;
    $manager->register( 'test.widget', DashboardTestWidget::class );

    $received = null;
    addFilter( 'ap.cmsFramework.admin.dashboardWidgets', function ( array $widgets, $user ) use ( & $received ): array {
        $received                      = $user;
        $widgets[ 'injected.no-user' ] = [ 'title' => 'Injected' ];

        return $widgets;
    } );

    $widgets = $manager->getAvailableWidgetsForUser();

    expect( $received )->toBeNull();
    expect( $widgets )->toHaveKey( 'injected.no-user' );
    expect( $widgets )->toHaveKey( 'test.widget' );
} );

it( 'applies the dashboard widgets filter after capability filtering when a user is provided', function (): void {
    $manager = new AdminWidgetManager;
    $manager->register( 'test.widget', DashboardTestWidget::class );

    $user = TestUser::factory()->create();

    $receivedUser = null;

    addFilter(
        'ap.cmsFramework.admin.dashboardWidgets',
        function ( array $widgets, $user ) use ( & $receivedUser ): array {
            $receivedUser                   = $user;
            $widgets[ 'injected.per-user' ] = [ 'title' => 'Per-User Injected' ];

            return $widgets;
        },
    );

    $widgets = $manager->getAvailableWidgetsForUser( $user );

    expect( $receivedUser )->not->toBeNull();
    expect( $receivedUser->id )->toBe( $user->id );
    expect( $widgets )->toHaveKey( 'injected.per-user' );
} );

it( 'lets subscribers remove widgets from the resolved list', function (): void {
    $manager = new AdminWidgetManager;
    $manager->register( 'test.widget', DashboardTestWidget::class );

    addFilter( 'ap.cmsFramework.admin.dashboardWidgets', function ( array $widgets ): array {
        unset( $widgets[ 'test.widget' ] );

        return $widgets;
    } );

    $widgets = $manager->getAvailableWidgetsForUser();

    expect( $widgets )->not->toHaveKey( 'test.widget' );
} );
