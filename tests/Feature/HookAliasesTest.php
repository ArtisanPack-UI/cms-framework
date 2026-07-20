<?php

declare( strict_types=1 );

/**
 * Feature coverage for the 2.5.0 hook rename aliases.
 *
 * Proves that every legacy hook name registered by
 * {@see HookAliases} still delivers to
 * subscribers registered against the new canonical name (and vice versa),
 * so existing host apps do not lose behavior when we cut over.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.5.0
 */

use ArtisanPackUI\CMSFramework\Support\HookAliases;

it( 'registers every rename against the hooks deprecation manager', function (): void {
	$map = HookAliases::map();

	expect( $map )->not->toBeEmpty();
	expect( $map[ 'ap.admin.menu' ] )->toBe( 'ap.cmsFramework.admin.menu' );
	expect( $map[ 'plugin.activated' ] )->toBe( 'ap.cmsFramework.plugin.activated' );
	expect( $map[ 'comments.moderate' ] )->toBe( 'ap.cmsFramework.abilities.comments.moderate' );
	expect( $map[ 'ap.roleRegistered' ] )->toBe( 'ap.rbac.roleRegistered' );
} );

it( 'forwards actions fired on the old name to subscribers on the new name', function (): void {
	$called = 0;

	addAction( 'ap.cmsFramework.plugin.activated', function () use ( & $called ): void {
		$called++;
	} );

	doAction( 'plugin.activated', 'some-plugin' );

	expect( $called )->toBe( 1 );

	removeAllActions( 'ap.cmsFramework.plugin.activated' );
} );

it( 'forwards actions fired on the new name to subscribers on the old name', function (): void {
	$called = 0;

	addAction( 'plugin.activated', function () use ( & $called ): void {
		$called++;
	} );

	doAction( 'ap.cmsFramework.plugin.activated', 'some-plugin' );

	expect( $called )->toBe( 1 );

	removeAllActions( 'ap.cmsFramework.plugin.activated' );
} );

it( 'forwards filters fired on the old name to subscribers on the new name', function (): void {
	addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
		$menu[] = 'from-new-name';

		return $menu;
	} );

	$result = applyFilters( 'ap.admin.menu', [] );

	expect( $result )->toBe( [ 'from-new-name' ] );

	removeAllFilters( 'ap.cmsFramework.admin.menu' );
} );

it( 'aliases each renamed hook so subscribers fire in both directions', function ( string $old, string $new ): void {
	$oldSubFired = 0;
	$newSubFired = 0;

	addAction( $old, function () use ( & $oldSubFired ): void {
		$oldSubFired++;
	} );
	doAction( $new );
	expect( $oldSubFired )->toBe( 1, "Subscriber on old name {$old} did not fire when {$new} was dispatched." );

	removeAllActions( $old );
	removeAllActions( $new );

	addAction( $new, function () use ( & $newSubFired ): void {
		$newSubFired++;
	} );
	doAction( $old );
	expect( $newSubFired )->toBe( 1, "Subscriber on new name {$new} did not fire when {$old} was dispatched." );

	removeAllActions( $old );
	removeAllActions( $new );
} )->with( array_map(
	static fn ( string $old, string $new ): array => [ $old, $new ],
	array_keys( HookAliases::map() ),
	array_values( HookAliases::map() ),
) );
