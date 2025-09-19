<?php
/**
 * Admin helper functions.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Admin
 */

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;

if ( ! function_exists( 'apAddAdminSection' ) ) {
	/**
	 * Registers a new section for the admin menu.
	 *
	 * @since 2.0.0
	 * @param string $title The display title for the section.
	 * @param int    $order The display order for the section.
	 * @param string $slug  The unique identifier for the section.
	 */
	function apAddAdminSection( string $slug, string $title, int $order = 99 ): void
	{
		app( AdminMenuManager::class )->addSection( $slug, $title, $order );
	}
}

if ( ! function_exists( 'apAddAdminPage' ) ) {
	/**
	 * Registers a top-level or sectioned admin page and its menu item.
	 *
	 * @since 2.0.0
	 * @param string      $slug        The unique slug for the page and route.
	 * @param string|null $sectionSlug The slug of the menu section, or null for a top-level item.
	 * @param array       $options     An array of options (view, icon, capability, etc.).
	 * @param string      $title       The page and menu item title.
	 */
	function apAddAdminPage( string $title, string $slug, ?string $sectionSlug, array $options = [] ): void
	{
		app( AdminMenuManager::class )->addPage( $title, $slug, $sectionSlug, $options );
	}
}

if ( ! function_exists( 'apAddSubAdminPage' ) ) {
	/**
	 * Registers a sub-level admin page and its menu item.
	 *
	 * @since 2.0.0
	 * @param string $slug       The unique slug for the page and route.
	 * @param string $parentSlug The slug of the parent menu item.
	 * @param array  $options    An array of options (view, capability, showInMenu, etc.).
	 * @param string $title      The page and menu item title.
	 */
	function apAddSubAdminPage( string $title, string $slug, string $parentSlug, array $options = [] ): void
	{
		app( AdminMenuManager::class )->addSubPage( $title, $slug, $parentSlug, $options );
	}
}

if ( ! function_exists( 'apGetAdminMenu' ) ) {
	function apGetAdminMenu(): array
	{
		return app( AdminMenuManager::class )->getAdminMenu();
	}
}