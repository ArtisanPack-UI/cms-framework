<?php

declare( strict_types=1 );

/**
 * Manages the registration and structure of the admin navigation menu.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Managers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Manages the registration and structure of the admin navigation menu.
 *
 * Stores sections and items, and builds a user-capability-filtered menu structure.
 *
 * @since 1.0.0
 */
class AdminMenuManager
{
    /**
     * Registered menu sections keyed by slug.
     *
     * @since 1.0.0
     *
     * @var array<string,array{title:string,order:int,items:array<string,mixed>}>
     */
    protected array $sections = [];

    /**
     * Registered menu items keyed by slug.
     *
     * @since 1.0.0
     *
     * @var array<string,array{title:string,slug:string,parent:?string,section:?string,icon?:string,capability?:string,order:int,route:string,showInMenu?:bool,subItems?:array<string,mixed>}>
     *                                                                                                                                                                                         $items
     */
    protected array $items = [];

    /**
     * Registers a new section for the admin menu.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The unique identifier for the section.
     * @param  string  $title  The display title for the section.
     * @param  int  $order  The display order for the section.
     */
    public function addSection( string $slug, string $title, int $order = 99 ): void
    {
        $this->sections[ $slug ] = ['title' => $title, 'order' => $order, 'items' => []];
    }

    /**
     * Registers a top-level or sectioned admin page and its menu item.
     *
     * @since 1.0.0
     *
     * @param  string  $title  The page and menu item title.
     * @param  string  $slug  The unique slug for the page and route.
     * @param  string|null  $sectionSlug  The slug of the menu section, or null for a top-level item.
     * @param  array  $options  An array of options (view, icon, capability, etc.).
     */
    public function addPage( string $title, string $slug, ?string $sectionSlug, array $options = [] ): void
    {
        $defaults = [
            'action'     => '',
            'icon'       => 'fas.users',
            'capability' => 'access_admin_dashboard',
            'order'      => 99,
            'menuTitle'  => $title,
        ];
        $options = array_merge( $defaults, $options );

        $this->items[ $slug ] = [
            'title'      => $title,
            'slug'       => $slug,
            'parent'     => null,
            'section'    => $sectionSlug,
            'icon'       => $options['icon'],
            'capability' => $options['capability'],
            'order'      => $options['order'],
            // Route names replace slashes with dots to match what
            // AdminPageManager::registerRoutes actually names the Laravel
            // route, so Route::has() lookups (and the decorated `url` field)
            // resolve correctly for multi-segment slugs like
            // `packages/visual-editor`.
            'route'      => 'admin.' . str_replace( '/', '.', $slug ),
            'menuTitle'  => $options['menuTitle'],
        ];

        app( AdminPageManager::class )->register( $slug, $options['action'], $options['capability'] );
    }

    /**
     * Registers a sub-level admin page and its menu item.
     *
     * @since 1.0.0
     *
     * @param  string  $title  The page and menu item title.
     * @param  string  $slug  The unique slug for the page and route.
     * @param  string  $parentSlug  The slug of the parent menu item.
     * @param  array  $options  An array of options (view, capability, showInMenu, etc.).
     */
    public function addSubPage( string $title, string $slug, string $parentSlug, array $options = [] ): void
    {
        $defaults = [
            'action'     => '',
            'capability' => 'access_admin_dashboard',
            'order'      => 99,
            'showInMenu' => true,
            'menuTitle'  => $title,
            'icon'       => '',
        ];
        $options = array_merge( $defaults, $options );

        $this->items[ $slug ] = [
            'title'      => $title,
            'slug'       => $slug,
            'parent'     => $parentSlug,
            'section'    => null,
            'capability' => $options['capability'],
            'order'      => $options['order'],
            'showInMenu' => $options['showInMenu'],
            'route'      => 'admin.' . str_replace( '/', '.', $slug ),
            'menuTitle'  => $options['menuTitle'],
            'icon'       => $options['icon'],
        ];

        app( AdminPageManager::class )->register( $slug, $options['action'], $options['capability'] );
    }

    /**
     * Builds and returns a filtered, sorted, and structured menu array ready for rendering.
     *
     * @since 1.0.0
     *
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
            } elseif ( $item['section'] && isset( $menu[ $item['section'] ] ) ) {
                $menu[ $item['section'] ]['items'][ $slug ] = $authorizedItems[ $slug ];
            } elseif ( is_null( $item['section'] ) ) {
                $topLevelItems[ $slug ] = $authorizedItems[ $slug ];
            }
        }

        // 3. Remove any sections that are now empty after filtering.
        $menu = array_filter( $menu, fn ( $section ) => ! empty( $section['items'] ) );

        // 4. Sort everything by the 'order' property.
        uasort( $topLevelItems, fn ( $a, $b ) => $a['order'] <=> $b['order'] );
        uasort( $menu, fn ( $a, $b ) => $a['order'] <=> $b['order'] );
        foreach ( $menu as &$section ) {
            if ( ! empty( $section['items'] ) ) {
                uasort( $section['items'], fn ( $a, $b ) => $a['order'] <=> $b['order'] );
                // Also sort subItems for each menu item in the section
                foreach ( $section['items'] as &$item ) {
                    if ( ! empty( $item['subItems'] ) ) {
                        uasort( $item['subItems'], fn ( $a, $b ) => $a['order'] <=> $b['order'] );
                    }
                }
            }
        }
        // Sort subItems for top-level items as well
        foreach ( $topLevelItems as &$item ) {
            if ( ! empty( $item['subItems'] ) ) {
                uasort( $item['subItems'], fn ( $a, $b ) => $a['order'] <=> $b['order'] );
            }
        }
        unset( $item );

        $menu = $this->decorateMenuForConsumers( array_merge( $topLevelItems, $menu ) );

        $menu = applyFilters( 'ap.admin.menu', $menu );

        // Defense-in-depth: re-apply capability filtering AFTER the filter
        // runs so plugin-injected entries can't bypass the Gate check that
        // native items go through above. Filter subscribers can push in new
        // rows freely, but any row declaring a `permission`/`capability` gets
        // hidden from users who lack it.
        return $this->enforceCapabilities( $menu );
    }

    /**
     * Retrieves all registered page data for a given slug.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The unique slug of the page.
     *
     * @return array|null The page's data array, or null if not found.
     */
    public function getPageBySlug( string $slug ): ?array
    {
        return $this->items[ $slug ] ?? null;
    }

    /**
     * Recursively drop any menu node whose `permission` (or legacy
     * `capability`) the current user does not hold. Applied after the
     * `ap.admin.menu` filter so filter-injected entries are gated too.
     *
     * @param  array  $menu  The (already-decorated) menu.
     *
     * @return array The gated menu.
     */
    protected function enforceCapabilities( array $menu ): array
    {
        $allowed = [];
        foreach ( $menu as $key => $node ) {
            $ability = $node['permission'] ?? ( $node['capability'] ?? null );
            if ( ! empty( $ability ) && ! Gate::allows( $ability ) ) {
                continue;
            }

            if ( isset( $node['items'] ) && is_array( $node['items'] ) ) {
                $node['items'] = $this->enforceCapabilities( $node['items'] );
                // Drop sections that end up empty after filtering.
                if ( empty( $node['items'] ) ) {
                    continue;
                }
            }
            if ( isset( $node['subItems'] ) && is_array( $node['subItems'] ) ) {
                $node['subItems'] = $this->enforceCapabilities( $node['subItems'] );
            }

            $allowed[ $key ] = $node;
        }

        return $allowed;
    }

    /**
     * Add React/Inertia-friendly fields (url, label, iconId, permission, external)
     * to each menu row while keeping the existing keys for Blade hosts.
     *
     * @param  array  $menu  The structured menu.
     *
     * @return array The decorated menu.
     */
    protected function decorateMenuForConsumers( array $menu ): array
    {
        foreach ( $menu as $key => &$node ) {
            if ( isset( $node['items'] ) && is_array( $node['items'] ) ) {
                $node['items'] = $this->decorateMenuForConsumers( $node['items'] );
                continue;
            }

            $node = $this->decorateItem( $node );

            if ( ! empty( $node['subItems'] ) ) {
                $node['subItems'] = $this->decorateMenuForConsumers( $node['subItems'] );
            }
        }

        return $menu;
    }

    /**
     * Resolve consumer-friendly fields for a single menu item.
     *
     * @param  array  $item  The menu item as stored in $this->items.
     *
     * @return array The item enriched with url/label/iconId/permission/external.
     */
    protected function decorateItem( array $item ): array
    {
        if ( ! isset( $item['route'] ) ) {
            return $item;
        }

        if ( ! isset( $item['url'] ) ) {
            $item['url'] = $this->resolveRouteUrl( $item['route'], $item['external'] ?? null );
        } elseif ( is_string( $item['url'] ) ) {
            // A filter subscriber (e.g. registerNavEntry) may pre-set `url`.
            // Sanitize regardless so `javascript:` URIs never reach the nav
            // renderer.
            $item['url'] = $this->sanitizeExternalUrl( $item['url'] );
        }

        if ( ! isset( $item['label'] ) ) {
            $item['label'] = $item['menuTitle'] ?? $item['title'] ?? '';
        }

        if ( ! isset( $item['iconId'] ) && isset( $item['icon'] ) ) {
            $item['iconId'] = $item['icon'];
        }

        if ( ! isset( $item['permission'] ) && ! empty( $item['capability'] ) ) {
            $item['permission'] = $item['capability'];
        }

        if ( ! array_key_exists( 'external', $item ) ) {
            $item['external'] = false;
        }

        return $item;
    }

    /**
     * Resolve a route name to a URL. Falls back to '#' when the route is not
     * registered (common in test contexts or before admin routes are booted).
     *
     * External URLs are only trusted when their scheme is safe — any plugin
     * or filter subscriber setting a `javascript:` / `data:` / `vbscript:`
     * URL would otherwise reach the admin nav's `<a href>` and execute in
     * the authenticated admin session.
     */
    protected function resolveRouteUrl( string $routeName, ?bool $external = null ): string
    {
        if ( true === $external ) {
            return $this->sanitizeExternalUrl( $routeName );
        }

        try {
            if ( Route::has( $routeName ) ) {
                return route( $routeName );
            }
        } catch ( Throwable $e ) {
            // Fall through to the safe default below.
        }

        return '#';
    }

    /**
     * Allow only navigation-safe URL forms:
     *   - Absolute http(s) URLs
     *   - Same-origin relative URLs (starting with '/')
     *   - Hash fragments (starting with '#')
     *
     * Anything else (javascript:, data:, vbscript:, file:, etc.) collapses to
     * '#'. Prevents plugin authors from injecting XSS vectors into the admin
     * sidebar via `registerNavEntry(['external' => true, 'url' => ...])`.
     */
    protected function sanitizeExternalUrl( string $url ): string
    {
        $trimmed = trim( $url );
        if ( '' === $trimmed ) {
            return '#';
        }

        if ( str_starts_with( $trimmed, '/' ) || str_starts_with( $trimmed, '#' ) ) {
            return $trimmed;
        }

        // Match absolute URLs with an explicit scheme.
        if ( 1 === preg_match( '#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $trimmed, $matches ) ) {
            $scheme = strtolower( $matches[1] );
            if ( in_array( $scheme, ['http', 'https', 'mailto', 'tel'], true ) ) {
                return $trimmed;
            }

            logger()->warning( "AdminMenuManager: refused unsafe URL scheme '{$scheme}' in nav entry." );

            return '#';
        }

        // Scheme-less values that aren't obviously a path — treat as safe
        // relative reference (e.g., 'docs/index.html').
        return $trimmed;
    }
}
