<?php
/**
 * Manages the registration and structure of the admin navigation menu.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Admin\Managers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Managers;

use Illuminate\Support\Facades\Gate;

class AdminMenuManager
{
	protected array $sections = [];
	protected array $items = [];

	/**
	 * Registers a new section for the admin menu.
	 *
	 * @since 2.0.0
	 * @param string $title The display title for the section.
	 * @param int    $order The display order for the section.
	 * @param string $slug  The unique identifier for the section.
	 */
	public function addSection( string $slug, string $title, int $order = 99 ): void
	{
		$this->sections[ $slug ] = [ 'title' => $title, 'order' => $order, 'items' => [] ];
	}

	/**
	 * Registers a top-level or sectioned admin page and its menu item.
	 *
	 * @since 2.0.0
	 * @param string      $slug        The unique slug for the page and route.
	 * @param string|null $sectionSlug The slug of the menu section, or null for a top-level item.
	 * @param array       $options     An array of options (view, icon, capability, etc.).
	 * @param string      $title       The page and menu item title.
	 */
	public function addPage( string $title, string $slug, ?string $sectionSlug, array $options = [] ): void
	{
		$defaults = [ 'action' => '', 'icon' => 'fas.users', 'capability' => 'access_admin_dashboard', 'order' => 99 ];
		$options  = array_merge( $defaults, $options );

		$this->items[ $slug ] = [
			'title' => $title, 'slug' => $slug, 'parent' => null, 'section' => $sectionSlug,
			'icon'  => $options['icon'], 'capability' => $options['capability'], 'order' => $options['order'],
			'route' => 'admin.' . $slug,
		];

		app( AdminPageManager::class )->register( $slug, $options['action'], $options['capability'] );
	}

	/**
	 * Registers a sub-level admin page and its menu item.
	 *
	 * @since 2.0.0
	 * @param string $slug       The unique slug for the page and route.
	 * @param string $parentSlug The slug of the parent menu item.
	 * @param array  $options    An array of options (view, capability, showInMenu, etc.).
	 * @param string $title      The page and menu item title.
	 */
	public function addSubPage( string $title, string $slug, string $parentSlug, array $options = [] ): void
	{
		$defaults = [ 'action' => '', 'capability' => 'access_admin_dashboard', 'order' => 99, 'showInMenu' => true ];
		$options  = array_merge( $defaults, $options );

		$this->items[ $slug ] = [
			'title'      => $title, 'slug' => $slug, 'parent' => $parentSlug, 'section' => null,
			'capability' => $options['capability'], 'order' => $options['order'], 'showInMenu' => $options['showInMenu'],
			'route'      => 'admin.' . str_replace( '/', '.', $slug ),
		];

		app( AdminPageManager::class )->register( $slug, $options['action'], $options['capability'] );
	}

	/**
	 * Builds and returns a filtered, sorted, and structured menu array ready for rendering.
	 *
	 * @since 2.0.0
	 * @return array The final menu structure.
	 */
	public function getAdminMenu(): array
	{
		$menu          = $this->sections;
		$items         = $this->items;
		$topLevelItems = [];

		// 1. Filter all items based on the current user's capabilities.
		$authorizedItems = array_filter( $items, function ( $item ) {
			// Only check the gate if a capability is set and not empty.
			return ! empty( $item['capability'] ) ? Gate::allows( $item['capability'] ) : true;
		} );

		// 2. Structure the menu (top-level, sections, and sub-items).
		foreach ( $authorizedItems as $slug => $item ) {
			if ( $item['parent'] && isset( $authorizedItems[ $item['parent'] ] ) ) {
				$authorizedItems[ $item['parent'] ]['subItems'][ $slug ] = $item;
			}
		}

		// Now add items to their final destinations, using the updated items with subItems
		foreach ( $authorizedItems as $slug => $item ) {
			if ( $item['parent'] && isset( $authorizedItems[ $item['parent'] ] ) ) {
				// This item is a child, it was already added to its parent's subItems above
				continue;
			} else if ( $item['section'] && isset( $menu[ $item['section'] ] ) ) {
				$menu[ $item['section'] ]['items'][ $slug ] = $authorizedItems[ $slug ];
			} else if ( is_null( $item['section'] ) ) {
				$topLevelItems[ $slug ] = $authorizedItems[ $slug ];
			}
		}

		// 3. Remove any sections that are now empty after filtering.
		$menu = array_filter( $menu, fn( $section ) => ! empty( $section['items'] ) );

		// 4. Sort everything by the 'order' property.
		uasort( $topLevelItems, fn( $a, $b ) => $a['order'] <=> $b['order'] );
		uasort( $menu, fn( $a, $b ) => $a['order'] <=> $b['order'] );
		foreach ( $menu as &$section ) {
			if ( ! empty( $section['items'] ) ) {
				uasort( $section['items'], fn( $a, $b ) => $a['order'] <=> $b['order'] );
				// Also sort subItems for each menu item in the section
				foreach ( $section['items'] as &$item ) {
					if ( ! empty( $item['subItems'] ) ) {
						uasort( $item['subItems'], fn( $a, $b ) => $a['order'] <=> $b['order'] );
					}
				}
			}
		}
		// Sort subItems for top-level items as well
		foreach ( $topLevelItems as &$item ) {
			if ( ! empty( $item['subItems'] ) ) {
				uasort( $item['subItems'], fn( $a, $b ) => $a['order'] <=> $b['order'] );
			}
		}

		return array_merge( $topLevelItems, $menu );
	}
}