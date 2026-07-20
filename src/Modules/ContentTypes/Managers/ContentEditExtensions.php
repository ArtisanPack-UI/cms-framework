<?php

declare( strict_types=1 );

/**
 * ContentEditExtensions Manager.
 *
 * Extensibility seam around the post/page edit render pipeline. Host apps
 * consume the merged data structures returned from these methods to render
 * plugin-supplied panels, tabs, before/after-editor content, and to intercept
 * save payloads before persistence.
 *
 * The framework does not render any of these itself — it only fires the
 * filters and normalizes the shapes so hosts and plugins agree on the contract.
 *
 * @since 2.4.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers;

/**
 * Aggregates plugin-registered edit-screen extensions.
 *
 * @since 2.4.0
 */
class ContentEditExtensions
{
    /**
     * Return sidebar/right-column panel definitions applicable to the given
     * content type. Plugins add panels by returning an array of shapes from the
     * `ap.cmsFramework.admin.contentEdit.panels` filter.
     *
     * Each panel: `slug`, `title`, `component`, optional `position`
     * ( `top`/`bottom`/`default` ), optional `order`, optional
     * `contentTypes` ( array of slugs — `['*']` for all, defaults to `['*']` ),
     * optional `capability` ( permission gate applied by the host ).
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $context  Context passed through to filters.
     *
     * @return array<int,array<string,mixed>>
     */
    public function panels( array $context ): array
    {
        return $this->collect( 'ap.cmsFramework.admin.contentEdit.panels', $context );
    }

    /**
     * Return tab definitions rendered above the main editor column.
     *
     * Each tab: `slug`, `title`, `component`, optional `order`, optional
     * `contentTypes`, optional `capability`.
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $context
     *
     * @return array<int,array<string,mixed>>
     */
    public function tabs( array $context ): array
    {
        return $this->collect( 'ap.cmsFramework.admin.contentEdit.tabs', $context );
    }

    /**
     * Return content-block definitions rendered above the main editor column.
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $context
     *
     * @return array<int,array<string,mixed>>
     */
    public function beforeEditor( array $context ): array
    {
        return $this->collect( 'ap.cmsFramework.admin.contentEdit.beforeEditor', $context );
    }

    /**
     * Return content-block definitions rendered below the main editor column.
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $context
     *
     * @return array<int,array<string,mixed>>
     */
    public function afterEditor( array $context ): array
    {
        return $this->collect( 'ap.cmsFramework.admin.contentEdit.afterEditor', $context );
    }

    /**
     * Filter incoming save data before the host persists it. Plugins can wash,
     * validate, or annotate the payload. The return value replaces `$data`.
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $context
     *
     * @return array<string,mixed>
     */
    public function saveData( array $data, array $context ): array
    {
        $filtered = applyFilters( 'ap.cmsFramework.admin.contentEdit.saveData', $data, $context );

        return is_array( $filtered ) ? $filtered : $data;
    }

    /**
     * Fetch, normalize, filter by content type, and order a list of items from
     * one of the panel/tab/block filters.
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $context
     *
     * @return array<int,array<string,mixed>>
     */
    protected function collect( string $hook, array $context ): array
    {
        $raw = applyFilters( $hook, [], $context );

        if ( ! is_array( $raw ) ) {
            return [];
        }

        $contentType = isset( $context['contentType'] ) ? ( string ) $context['contentType'] : null;
        $out         = [];

        foreach ( $raw as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            if ( ! isset( $entry['slug'], $entry['component'] ) ) {
                continue;
            }
            if ( ! $this->matchesContentType( $entry, $contentType ) ) {
                continue;
            }

            $entry['order'] = ( int ) ( $entry['order'] ?? 50 );
            $out[]          = $entry;
        }

        usort( $out, static fn ( array $a, array $b ): int => $a['order'] <=> $b['order'] );

        return $out;
    }

    /**
     * Whether an entry's `contentTypes` restriction admits the requested
     * content type. Missing key or a `'*'` wildcard means "always applies".
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $entry
     */
    protected function matchesContentType( array $entry, ?string $contentType ): bool
    {
        if ( ! isset( $entry['contentTypes'] ) ) {
            return true;
        }

        $allowed = $entry['contentTypes'];
        if ( ! is_array( $allowed ) || [] === $allowed ) {
            return true;
        }

        if ( in_array( '*', $allowed, true ) ) {
            return true;
        }

        if ( null === $contentType ) {
            return false;
        }

        return in_array( $contentType, $allowed, true );
    }
}
